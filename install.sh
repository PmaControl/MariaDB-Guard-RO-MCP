#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID}" -ne 0 ]; then
  echo "Ce script doit etre lance en root." >&2
  echo "Exemple: su - puis ./install.sh" >&2
  exit 1
fi

if [ ! -f /etc/os-release ]; then
  echo "OS non supporte: /etc/os-release introuvable" >&2
  exit 1
fi

# shellcheck disable=SC1091
. /etc/os-release

is_supported=0
case "${ID:-}" in
  ubuntu)
    if [ "${VERSION_ID:-}" = "24.04" ]; then
      is_supported=1
    fi
    ;;
  debian)
    if [ "${VERSION_ID:-}" = "12" ] || [ "${VERSION_ID:-}" = "13" ]; then
      is_supported=1
    fi
    ;;
esac

if [ "${is_supported}" -ne 1 ]; then
  echo "Distribution non supportee: ${PRETTY_NAME:-unknown}" >&2
  echo "Supporte: Ubuntu 24.04, Debian 12, Debian 13" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="/srv/www/mcp-mariadb"
APACHE_VHOST="/etc/apache2/sites-available/mcp-mariadb.conf"
REAL_SCRIPT_DIR="$(realpath "${SCRIPT_DIR}")"
REAL_TARGET_DIR="$(realpath -m "${TARGET_DIR}")"

export DEBIAN_FRONTEND=noninteractive

echo "[1/8] Installation des paquets systeme"
apt-get update
apt-get install -y \
  apache2 \
  ca-certificates \
  curl \
  git \
  jq \
  libapache2-mod-php \
  mariadb-client \
  openssl \
  php \
  php-cli \
  php-curl \
  php-mbstring \
  php-mysql \
  php-xml \
  rsync \
  unzip

echo "[2/8] Preparation du dossier applicatif"
mkdir -p /srv/www
if [ "${REAL_SCRIPT_DIR}" != "${REAL_TARGET_DIR}" ]; then
  mkdir -p "${TARGET_DIR}"
  rsync -a \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='.phpunit.result.cache' \
    --exclude='.env' \
    --exclude='.env.*' \
    "${SCRIPT_DIR}/" "${TARGET_DIR}/"
fi

echo "[3/8] Configuration de l'environnement (.env)"
if [ ! -f "${TARGET_DIR}/.env" ]; then
  MCP_TOKEN="$(openssl rand -hex 24)"
  cat > "${TARGET_DIR}/.env" <<ENV
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=sakila
DB_USER=cline
DB_PASS=change_me
MCP_TOKEN=${MCP_TOKEN}
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_MS=30000
WHERE_FULLSCAN_MAX_ROWS=30000
MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_query.log
ENV
fi

echo "[4/8] Permissions"
chown -R www-data:www-data "${TARGET_DIR}"
find "${TARGET_DIR}" -type d -exec chmod 755 {} \;
find "${TARGET_DIR}" -type f -exec chmod 644 {} \;
chmod +x "${TARGET_DIR}/install.sh"

echo "[5/8] Configuration Apache"
a2enmod rewrite headers setenvif

if ! grep -Eq '^Listen[[:space:]]+13306$' /etc/apache2/ports.conf; then
  echo 'Listen 13306' >> /etc/apache2/ports.conf
fi

cat > "${APACHE_VHOST}" <<'VHOST'
<VirtualHost *:13306>
    ServerName localhost
    DocumentRoot /srv/www/mcp-mariadb/public

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]

    <Directory /srv/www/mcp-mariadb/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    <Location "^/(mcp|health)$">
        Require local
        Require ip 10.68.68.0/24
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/mcp_mariadb_error.log
    CustomLog ${APACHE_LOG_DIR}/mcp_mariadb_access.log combined
</VirtualHost>
VHOST

a2ensite mcp-mariadb.conf
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
  a2dissite 000-default.conf
fi

echo "[6/8] Verification de la config Apache"
apache2ctl configtest

echo "[7/8] Redemarrage Apache"
if command -v systemctl >/dev/null 2>&1; then
  systemctl enable apache2
  systemctl restart apache2
else
  service apache2 restart
fi

echo "[8/8] Verification API"
if curl -fsS "http://127.0.0.1:13306/health" >/dev/null; then
  echo "Installation terminee avec succes."
else
  echo "Installation terminee, mais /health ne repond pas encore." >&2
fi

echo ""
echo "Chemin application : ${TARGET_DIR}"
echo "Endpoint MCP      : http://<host>:13306/mcp"
echo "Healthcheck       : http://<host>:13306/health"
echo ""
echo "Pensez a adapter ${TARGET_DIR}/.env avec les vrais identifiants BDD."
