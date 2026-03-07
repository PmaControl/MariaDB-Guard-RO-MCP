# MCP MariaDB/MySQL Server (PHP)

Serveur MCP (Model Context Protocol) en PHP pour MariaDB/MySQL, orienté lecture seule.

## Auteur
- **Aurélien LEQUOY**

## Licence
Ce projet est distribué sous licence **GNU GPL v3**.

- Licence: GPL-3.0-or-later
- Texte officiel: https://www.gnu.org/licenses/gpl-3.0.html

## Fonctionnalités
- Endpoint santé: `GET /health`
- Endpoint MCP JSON-RPC: `POST /mcp`
- Transport compatible **Streamable HTTP**
- Authentification Bearer optionnelle via `MCP_TOKEN`
- Outils SQL read-only:
  - `db_select`
  - `db_tables`
  - `db_schema`
  - `db_indexes`
  - `db_explain`
  - `db_processlist`
  - `db_variables`

## Architecture
Structure “1 fichier = 1 classe”:

- `public/index.php` (point d’entrée web)
- `src/Env.php`
- `src/Http.php`
- `src/Db.php`
- `src/SqlGuard.php`
- `src/JsonRpc.php`
- `src/Tools.php`
- `src/App.php`

## Prérequis
- Debian/Ubuntu (recommandé)
- Apache 2.4+
- PHP 8.2+
- Extensions PHP:
  - `pdo`
  - `pdo_mysql`
  - `mbstring` (recommandé)
- Accès réseau à la base MariaDB/MySQL

## Installation complète (Apache)

### 1. Déployer le code
```bash
cd /var/www
git clone https://github.com/PmaControl/AsterDB-MCP.git mcp-mariadb
cd /var/www/mcp-mariadb
```

### 2. Configurer l’environnement
Créer/éditer `.env`:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pmacontrol
DB_USER=mcp_ro
DB_PASS=change_me
MCP_TOKEN=change_me_if_needed
MAX_ROWS_DEFAULT=200
MAX_ROWS_HARD=5000
```

Notes:
- `MCP_TOKEN` vide (`MCP_TOKEN=`) => pas d’auth
- `MCP_TOKEN` non vide => header `Authorization: Bearer <token>` obligatoire

Créer l'utilisateur MySQL/MariaDB (exemple):
```sql
GRANT SELECT ON *.* TO `cline`@`%` IDENTIFIED BY 'change_me';
FLUSH PRIVILEGES;
```

### 3. Permissions
```bash
chown -R www-data:www-data /var/www/mcp-mariadb
find /var/www/mcp-mariadb -type d -exec chmod 755 {} \;
find /var/www/mcp-mariadb -type f -exec chmod 644 {} \;
```

### 4. Activer les modules Apache nécessaires
```bash
a2enmod rewrite headers setenvif
```

### 5. Créer le VirtualHost Apache
Créer `/etc/apache2/sites-available/mcp-mariadb.conf`:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/mcp-mariadb/public

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

    <Directory /var/www/mcp-mariadb/public>
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
```

Adapter:
- `ServerName`
- la règle réseau `Require ip ...`
- `Require ip 10.68.68.0/24` signifie: seules les IP de `10.68.68.1` à `10.68.68.254` (CIDR `/24`) peuvent accéder à `/mcp` et `/health`, en plus de `Require local` (localhost).

### 6. Activer le site et redémarrer Apache
```bash
a2ensite mcp-mariadb.conf
a2dissite 000-default.conf
systemctl reload apache2
# ou
service apache2 restart
```

### 7. Vérification Apache
```bash
apache2ctl configtest
systemctl status apache2
```

## Tests de fonctionnement

### Healthcheck
```bash
curl -sS http://<HOST>/health
```

### Initialize MCP (avec token)
```bash
curl -sS -X POST http://<HOST>/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

### Initialize MCP (sans token)
```bash
curl -sS -X POST http://<HOST>/mcp \
  -H 'content-type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

## Configuration MCP Inspector (Streamable HTTP)
- Transport: **Streamable HTTP**
- URL: `http://<HOST>/mcp`
- Authentication: `None`
- Si `MCP_TOKEN` est défini, ajouter le header:
  - `Authorization: Bearer <MCP_TOKEN>`

## Sécurité
- Utiliser un compte DB à privilèges minimum (lecture seule recommandé)
- Restreindre l’accès réseau Apache (`Require ip`)
- Utiliser un token fort pour `MCP_TOKEN`
- Mettre le service derrière HTTPS (reverse proxy/Nginx/Apache TLS)

## Dépannage
- `404` sur `/mcp` avec `curl`: vérifier que vous faites un **POST** (pas GET)
- `Unauthorized`: token manquant ou invalide
- Erreurs CORS Inspector: vérifier `OPTIONS /mcp` (204) et headers CORS
- Vérifier logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_error.log`

## Commandes utiles
```bash
# Redémarrer Apache
service apache2 restart

# Voir les logs en direct
tail -f /var/log/apache2/mcp_mariadb_access.log /var/log/apache2/mcp_mariadb_error.log
```
