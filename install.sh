#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: ./install.sh [options]

Options:
  --http-port <port>      HTTP port for Apache virtualhost (default: 13306)
  --db-host <host>        Database host (default: 127.0.0.1)
  --db-port <port>        Database port (default: 3306)
  --db-name <name>        Database name (default: my_database)
  --db-user <user>        Database user (default: my_user_mcp_ro)
  --db-pass <pass>        Database password (default: my_password)
  --mcp-token <token>     MCP bearer token (default: my_token)
  --allow-cidr <cidr>     Apache Require ip CIDR (default: derived from hostname -I, /24)
  -h, --help              Show this help
USAGE
}

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

# Defaults (can be overridden via CLI)
HTTP_PORT=13306
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_NAME="my_database"
DB_USER="my_user_mcp_ro"
DB_PASS="my_password"
MCP_TOKEN="my_token"
ALLOW_CIDR=""

while [ $# -gt 0 ]; do
  case "$1" in
    --http-port)
      HTTP_PORT="${2:-}"; shift 2 ;;
    --db-host)
      DB_HOST="${2:-}"; shift 2 ;;
    --db-port)
      DB_PORT="${2:-}"; shift 2 ;;
    --db-name)
      DB_NAME="${2:-}"; shift 2 ;;
    --db-user)
      DB_USER="${2:-}"; shift 2 ;;
    --db-pass)
      DB_PASS="${2:-}"; shift 2 ;;
    --mcp-token)
      MCP_TOKEN="${2:-}"; shift 2 ;;
    --allow-cidr)
      ALLOW_CIDR="${2:-}"; shift 2 ;;
    -h|--help)
      usage; exit 0 ;;
    *)
      echo "Option inconnue: $1" >&2
      usage
      exit 1 ;;
  esac
done

# Derive Apache allowed network from first IPv4 returned by hostname -I.
if [ -z "${ALLOW_CIDR}" ]; then
  FIRST_IPV4="$(hostname -I 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i ~ /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/){print $i; exit}}')"
  if [ -n "${FIRST_IPV4}" ]; then
    BASE_24="$(echo "${FIRST_IPV4}" | awk -F. '{print $1"."$2"."$3}')"
    ALLOW_CIDR="${BASE_24}.0/24"
  else
    ALLOW_CIDR="10.68.68.0/24"
  fi
fi

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
cat > "${TARGET_DIR}/.env" <<ENV
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
MCP_TOKEN=${MCP_TOKEN}
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_S=5
WHERE_FULLSCAN_MAX_ROWS=30000
MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_query.log
ENV

echo "[4/8] Permissions"
chown -R www-data:www-data "${TARGET_DIR}"
find "${TARGET_DIR}" -type d -exec chmod 755 {} \;
find "${TARGET_DIR}" -type f -exec chmod 644 {} \;
chmod +x "${TARGET_DIR}/install.sh"

echo "[5/8] Configuration Apache"
a2enmod rewrite headers setenvif

if ! grep -Eq "^Listen[[:space:]]+${HTTP_PORT}$" /etc/apache2/ports.conf; then
  echo "Listen ${HTTP_PORT}" >> /etc/apache2/ports.conf
fi

cat > "${APACHE_VHOST}" <<VHOST
<VirtualHost *:${HTTP_PORT}>
    ServerName localhost
    DocumentRoot /srv/www/mcp-mariadb/public

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=\$1

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
        Require ip ${ALLOW_CIDR}
    </Location>

    ErrorLog \${APACHE_LOG_DIR}/mcp_mariadb_error.log
    CustomLog \${APACHE_LOG_DIR}/mcp_mariadb_access.log combined
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
sleep 1

echo "[8/8] Verification API"
if curl -fsS "http://127.0.0.1:${HTTP_PORT}/health" >/dev/null; then
  echo "Installation terminee avec succes."
else
  echo "Installation terminee, mais /health ne repond pas encore." >&2
fi

echo ""
echo "Chemin application : ${TARGET_DIR}"
echo "Endpoint MCP      : http://<host>:${HTTP_PORT}/mcp"
echo "Healthcheck       : http://<host>:${HTTP_PORT}/health"
echo "Require ip CIDR   : ${ALLOW_CIDR}"
echo ""
echo "Configuration appliquee dans ${TARGET_DIR}/.env."
