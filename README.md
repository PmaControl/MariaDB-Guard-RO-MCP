# MCP MariaDB/MySQL Server (PHP)

Serveur MCP (Model Context Protocol) en PHP pour MariaDB/MySQL, orienté lecture avec création de table contrôlée.

English version: [README_en.md](README_en.md)

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
- Outils SQL:
  - `db_select`
  - `db_tables`
  - `db_schema`
  - `db_indexes`
  - `db_explain`
  - `db_processlist`
  - `db_variables`
- `db_create_table`
  - `db_ping`

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
cd /srv/www
git clone https://github.com/PmaControl/AsterDB-MCP.git mcp-mariadb
cd /srv/www/mcp-mariadb
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
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_MS=5000
```

Notes:
- `MCP_TOKEN` vide (`MCP_TOKEN=`) => pas d’auth
- `MCP_TOKEN` non vide => header `Authorization: Bearer <token>` obligatoire
- `MAX_ROWS_DEFAULT=1000` applique une limite par défaut de 1000 lignes
- `MAX_ROWS_HARD=5000` impose une limite maximum absolue de 5000 lignes
- `MAX_SELECT_TIME_MS` limite la durée max des requêtes `SELECT`
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Valeur recommandée: `5000` (5s). Ce seuil coupe les requêtes lourdes qui peuvent bloquer le serveur, tout en laissant passer les requêtes normales de diagnostic.

Exemple MySQL:
```sql
SELECT /*+ MAX_EXECUTION_TIME(5000) */ *
FROM huge_table;
```

Exemple MariaDB:
```sql
SET STATEMENT max_statement_time=5 FOR
SELECT *
FROM huge_table;
```

Créer l'utilisateur MySQL/MariaDB (exemple compatible):
```sql
CREATE USER IF NOT EXISTS `cline`@`%` IDENTIFIED BY 'change_me';
GRANT SELECT, CREATE ON *.* TO `cline`@`%`;
FLUSH PRIVILEGES;
```

### 3. Permissions
```bash
chown -R www-data:www-data /srv/www/mcp-mariadb
find /srv/www/mcp-mariadb -type d -exec chmod 755 {} \;
find /srv/www/mcp-mariadb -type f -exec chmod 644 {} \;
```

### 4. Activer les modules Apache nécessaires
```bash
a2enmod rewrite headers setenvif
```

### 5. Créer le VirtualHost Apache
Créer `/etc/apache2/sites-available/mcp-mariadb.conf`:

```apache
<VirtualHost *:13306>
    ServerName localhost
    DocumentRoot /srv/www/mcp-mariadb/public

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

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
curl -sS http://<HOST>:13306/health
```

### Initialize MCP (avec token)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

### Initialize MCP (sans token)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

### Ping MCP
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":2,"method":"ping","params":{}}'
```

### Tool `db_ping` (ping DB host)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"db_ping","arguments":{"host":"10.68.68.111","port":3306,"timeoutMs":1500}}}'
```

## Configuration MCP Inspector (Streamable HTTP)
- Transport: **Streamable HTTP**
- URL: `http://<HOST>:13306/mcp`
- Authentication: `None`
- Si `MCP_TOKEN` est défini, ajouter le header:
  - `Authorization: Bearer <MCP_TOKEN>`

## Sécurité
- Utiliser un compte DB à privilèges minimum (lecture seule recommandé)
- Donner uniquement les droits nécessaires (`SELECT` et `CREATE` si `db_create_table` est utilisé)
- Restreindre l’accès réseau Apache (`Require ip`)
- Utiliser un token fort pour `MCP_TOKEN`
- Mettre le service derrière HTTPS (reverse proxy/Nginx/Apache TLS)
- Les requêtes `SELECT ... FOR UPDATE` sont bloquées explicitement
- `db_create_table` n'accepte que `CREATE TABLE` simple (multi-statements et `CREATE TABLE ... AS SELECT` bloqués)
- `db_select` applique une politique de requête:
  - `SELECT *` bloqué uniquement si la table ciblée a plus de 30 colonnes
  - `OR` dans `WHERE` bloqué (réécrire avec `UNION`/`UNION ALL`)
  - vérification `EXPLAIN` obligatoire: accès indexé requis (full scan rejeté)
  - pour toute table avec plus de 100000 lignes, tous les champs utilisés dans le `WHERE` pour cette table doivent être couverts par un même index, sinon la requête est refusée

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
