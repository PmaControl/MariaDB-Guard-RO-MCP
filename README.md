# MCP MariaDB/MySQL Server (PHP)
<a id="title-one-liner"></a>

Le serveur MCP (Model Context Protocol) en PHP pour MariaDB/MySQL qui transforme tes accès SQL en mode production: lecture sécurisée, garde-fous anti-requêtes toxiques, limites d'exécution et diagnostics EXPLAIN exploitables pour analyser plus vite sans casser ta base.

English version: [README_en.md](README_en.md)

## Objectif
Se connecter à la production sans stress, même avec des utilisateurs non experts: le serveur MCP agit comme un agent gardien qui bloque les requêtes risquées et ne laisse passer que celles capables de s'exécuter dans de bonnes conditions réelles (taille de tables, plan EXPLAIN, indexation et charge serveur), pour protéger les données, la performance et la tranquillité de l'équipe.

Conçu pour les environnements critiques avec des tables massives (centaines de millions à milliards de lignes), ce serveur MCP réduit fortement le risque de requête destructive en prod tout en gardant une capacité d'analyse opérationnelle rapide.

## Index
1. [Features](#features)
2. [Production Disclaimer](#production-disclaimer)
3. [Quick Start (5 min)](#quick-start-5-min)
4. [Tested Servers](#tested-servers)
5. [Configuration](#configuration)
6. [Security Model](#security-model)
7. [Installation](#installation)
8. [MCP Inspector Setup](#mcp-inspector-setup)
9. [Developer Guide](#developer-guide)
10. [Troubleshooting](#troubleshooting)
11. [Logs](#logs)
12. [Project Structure](#project-structure)
13. [Author / License](#author-license)

<a id="features"></a>
## Features
En pratique, le serveur MCP ajoute des protections clés:
- `read-only` sur les outils SQL exposés
- rejet des patterns dangereux (`FOR UPDATE`, `OR` non maîtrisé, `WITH RECURSIVE`)
- timeout SQL (MariaDB/MySQL selon version)
- policy sur full scan avec `WHERE` selon taille de table
- plafonnement de résultat (`MAX_ROWS_DEFAULT` / `MAX_ROWS_HARD`)
- garde de charge: refus temporaire si trop de requêtes déjà en cours (`database busy retry in 1 second`)
- logs requête + plan + temps + volume retourné pour audit et tuning

<a id="production-disclaimer"></a>
## Production Disclaimer
Ce MCP est conçu pour les très grosses bases, mais en production il doit être branché sur une réplique (`slave`/read replica) et non sur le serveur maître (`master`/primary).

<a id="quick-start-5-min"></a>
## Quick Start (5 min)
```bash
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
cd mcp-mariadb
./install.sh \
  --install-dir /srv/www/mcp-mariadb \
  --db-host 127.0.0.1 \
  --db-port 3306 \
  --db-name my_database \
  --db-user my_user_mcp_ro \
  --db-pass my_password \
  --mcp-token my_token
curl -sS http://127.0.0.1:13306/health
```

<a id="tested-servers"></a>
## Serveurs Testés
- `MariaDB`
  - `5.5.45`
  - `10.1.1+` (dont `10.5.29`, `10.6.23`, `10.11.16`, `12.3.2`)
- `MySQL`
  - `4.1.22`
  - `5.7.1`, `5.7.4+` (dont `5.7.44`)
  - `8.0.45`
  - `8.4.5`
  - `9.6.0`
- `Percona Server`
  - `5.7.1`

Notes:
- Compatibilité de principe: le serveur est conçu pour fonctionner avec les moteurs compatibles MySQL à partir de la génération `MySQL 4.1+` (dont MariaDB et Percona Server), sous réserve des différences spécifiques de version/fonctionnalités.
- Le mécanisme de timeout SQL dépend de la version serveur:
  - MariaDB: actif à partir de `10.1.1`
  - MySQL: actif à partir de `5.7.4`
  - Percona Server: même règle que MySQL (`5.7.4+`)

<a id="configuration"></a>
## Configuration
- Copier le template: `cp -a .env.sample .env`
- Variables clés: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MCP_TOKEN`
- Limites de sécurité/perf: `MAX_ROWS_DEFAULT`, `MAX_ROWS_HARD`, `MAX_SELECT_TIME_S`, `WHERE_FULLSCAN_MAX_ROWS`
- Log applicatif: `MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_13306_query.log` (suffixé avec le port HTTP)
- Cache de validation du compte DB: `.account_tested` (racine projet)
- Si `.env` est plus récent que `.account_tested`, le cache est invalidé automatiquement et un nouveau test des droits est forcé.

<a id="security-model"></a>
## Security Model
- Surface SQL en lecture contrôlée
- Blocage des patterns dangereux
- Limites de résultat et timeout SQL
- Garde de charge (`database busy retry in 1 second`)
- Exécution recommandée sur read replica en prod
- Compte MySQL/MariaDB en read-only strict: `SELECT` obligatoire (`USAGE` implicite), `SHOW VIEW` et `PROCESS` optionnels.
- Si un droit d'écriture/modification est détecté, le serveur MCP est bloqué.
- Le tool `mcp_test` reste disponible pour exécuter la checklist sécurité et guider la remédiation.

<a id="installation"></a>
## Installation

Installation rapide (root) sur Ubuntu 24.04 / Debian 12 / Debian 13:
```bash
chmod +x install.sh
./install.sh
```

### With install.sh
Exemple avec paramètres en une seule commande:
```bash
./install.sh \
  --install-dir /srv/www/mcp-mariadb \
  --http-port 13306 \
  --db-host 127.0.0.1 \
  --db-port 3306 \
  --db-name my_database \
  --db-user my_user_mcp_ro \
  --db-pass my_password \
  --mcp-token my_token
```

Le port HTTP reste `13306` par défaut. Utiliser `--http-port` pour le changer, et `--install-dir` pour isoler plusieurs instances sur le même serveur.

Par défaut, `install.sh` déduit `Require ip` via `hostname -I` (première IPv4, réseau `/24`). Vous pouvez forcer un CIDR avec `--allow-cidr`.

### Options (`install.sh`)
- `--install-dir <path>`: dossier d'installation (défaut: `/srv/www/mcp-mariadb`)
- `--http-port <port>`: port HTTP Apache (défaut: `13306`)
- `--db-host <host>`: hôte MariaDB/MySQL (défaut: `127.0.0.1`)
- `--db-port <port>`: port MariaDB/MySQL (défaut: `3306`)
- `--db-name <name>`: base de données (défaut: `my_database`)
- `--db-user <user>`: utilisateur DB (défaut: `my_user_mcp_ro`)
- `--db-pass <pass>`: mot de passe DB (défaut: `my_password`)
- `--mcp-token <token>`: token Bearer MCP (défaut: `my_token`)
- `--allow-cidr <cidr>`: réseau autorisé pour `/mcp` et `/health` (défaut: auto via `hostname -I` en `/24`)
- `-h`, `--help`: affiche l'aide

### Manual
#### 1. Déployer le code
```bash
cd /srv/www
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
cd /srv/www/mcp-mariadb
```

#### 2. Configurer l’environnement
Copier le template puis adapter:
```bash
cp -a .env.sample .env
```

Exemple `.env`:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=my_database
DB_USER=my_user_mcp_ro
DB_PASS=my_password
MCP_TOKEN=my_token
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_S=5
WHERE_FULLSCAN_MAX_ROWS=30000
MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_13306_query.log
```

Notes:
- `MCP_TOKEN` vide (`MCP_TOKEN=`) => pas d’auth
- `MCP_TOKEN` non vide => header `Authorization: Bearer <token>` obligatoire
- `MAX_ROWS_DEFAULT=1000` applique une limite par défaut de 1000 lignes
- `MAX_ROWS_HARD=5000` impose une limite maximum absolue de 5000 lignes
- `MAX_SELECT_TIME_S` limite la durée max des requêtes `SELECT`
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Valeur recommandée: `5` (5s). Ce seuil protège le serveur contre les requêtes longues en production.
- `WHERE_FULLSCAN_MAX_ROWS=30000` fixe le seuil de refus pour les full scans avec `WHERE`.
- `MCP_QUERY_LOG` définit le fichier de log JSONL des requêtes MCP SQL (SQL formatée, `rowCount`, `durationMs`, `plan`).
- Recommandé en multi-instance: suffixer le log par port (ex: `mcp_mariadb_13306_query.log`, `mcp_mariadb_13307_query.log`).

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
CREATE USER IF NOT EXISTS `my_user_mcp_ro`@`%` IDENTIFIED BY 'my_password';
GRANT SELECT ON *.* TO `my_user_mcp_ro`@`%`;
-- Optionnel (lecture/diagnostic):
-- GRANT SHOW VIEW, PROCESS ON *.* TO `my_user_mcp_ro`@`%`;
FLUSH PRIVILEGES;
```

#### 3. Permissions
```bash
chown -R www-data:www-data /srv/www/mcp-mariadb
find /srv/www/mcp-mariadb -type d -exec chmod 755 {} \;
find /srv/www/mcp-mariadb -type f -exec chmod 644 {} \;
```

#### 4. Activer les modules Apache nécessaires
```bash
a2enmod rewrite headers setenvif
```

#### 5. Créer le VirtualHost Apache
Créer `/etc/apache2/sites-available/mcp-mariadb-13306.conf`:

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
        Require ip <YOUR_ALLOWED_CIDR>
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/mcp_mariadb_13306_error.log
    CustomLog ${APACHE_LOG_DIR}/mcp_mariadb_13306_access.log combined
</VirtualHost>
```

Adapter:
- `ServerName`
- la règle réseau `Require ip ...`
- `Require ip <YOUR_ALLOWED_CIDR>` signifie: seules les IP du réseau autorisé peuvent accéder à `/mcp` et `/health`, en plus de `Require local` (localhost).

#### 6. Activer le site et redémarrer Apache
```bash
a2ensite mcp-mariadb-13306.conf
a2dissite 000-default.conf
systemctl reload apache2
# ou
service apache2 restart
```

#### 7. Vérification Apache
```bash
apache2ctl configtest
systemctl status apache2
```

### Run with Docker
Voir la section [Docker](#docker).

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

### Tool `db_explain_table` (EXPLAIN lisible)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"db_explain_table","arguments":{"sql":"SELECT id,id_mysql_server,port FROM alias_dns WHERE id_mysql_server = 113 ORDER BY id DESC LIMIT 50"}}}'
```

<a id="mcp-inspector-setup"></a>
## MCP Inspector Setup
- Transport: **Streamable HTTP**
- URL: `http://<HOST>:13306/mcp`
- Authentication: `None`
- Si `MCP_TOKEN` est défini, ajouter le header:
  - `Authorization: Bearer <MCP_TOKEN>`

<a id="security-model-details"></a>
## Security Model (Details)
- Utiliser un compte DB à privilèges minimum (lecture seule recommandé)
- Donner uniquement les droits nécessaires (`SELECT` obligatoire; `SHOW VIEW` et `PROCESS` optionnels)
- Restreindre l’accès réseau Apache (`Require ip`)
- Utiliser un token fort pour `MCP_TOKEN`
- Mettre le service derrière HTTPS (reverse proxy/Nginx/Apache TLS)
- Les requêtes `SELECT ... FOR UPDATE` sont bloquées explicitement
- `db_select` applique une politique de requête:
  - `SELECT *` sans `WHERE` est autorisé uniquement sur une seule table sans `JOIN`
  - `SELECT *` avec `WHERE` bloqué uniquement si la table ciblée a plus de 30 colonnes
  - CTE non récursifs (`WITH ...`) autorisés
  - CTE récursifs (`WITH RECURSIVE ...`) bloqués
  - `OR` dans `WHERE` bloqué (réécrire avec `UNION`/`UNION ALL`)
  - avec `WHERE`, un full scan est autorisé si la table a au plus `30000` lignes
  - avec `WHERE`, un full scan est refusé si la table dépasse `30000` lignes
  - garde charge DB: si plus de `3` requêtes SQL sont déjà en cours, `db_select` renvoie `database busy retry in 1 second`
  - si la requête dépasse le timeout SQL, l'erreur renvoyée est normalisée en: `guard [execution time reached]`

<a id="troubleshooting"></a>
## Troubleshooting
- `.env` manquant: copier le template puis adapter les valeurs:
```bash
cp -a .env.sample .env
```
- Serveur MCP bloqué (compte non read-only): exécuter `mcp_test`, retirer tous les droits d'écriture/DDL/admin, puis mettre à jour `.env` (ou supprimer `.account_tested`) pour forcer un re-test.
- `404` sur `/mcp` avec `curl`: vérifier que vous faites un **POST** (pas GET)
- `Unauthorized`: token manquant ou invalide
- Erreurs CORS Inspector: vérifier `OPTIONS /mcp` (204) et headers CORS
- Vérifier logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_13306_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_13306_error.log`
  - SQL MCP (JSONL): `/srv/www/mcp-mariadb/mcp_mariadb_13306_query.log`

## Developer Guide
Pour l'installation de la plateforme de dev, PHPUnit, CI/CD, hooks Git et checklist sécurité:
- `docs/developer_setup.md`

## Docker
Build local:
```bash
docker build -t mariadb-guard-ro-mcp:local .
```

Run local:
```bash
docker run --rm -p 13306:13306 \
  -e DB_HOST=127.0.0.1 \
  -e DB_PORT=3306 \
  -e DB_NAME=my_database \
  -e DB_USER=my_user_mcp_ro \
  -e DB_PASS=my_password \
  -e MCP_TOKEN=my_token \
  mariadb-guard-ro-mcp:local
```

<a id="logs"></a>
## Logs
```bash
# Redémarrer Apache
service apache2 restart

# Voir les logs en direct
tail -f /var/log/apache2/mcp_mariadb_13306_access.log /var/log/apache2/mcp_mariadb_13306_error.log /srv/www/mcp-mariadb/mcp_mariadb_13306_query.log
```

<a id="project-structure"></a>
## Project Structure

- `public/index.php` (point d’entrée web)
- `src/Env.php`
- `src/Http.php`
- `src/Db.php`
- `src/SqlGuard.php`
- `src/JsonRpc.php`
- `src/Tools.php`
- `src/App.php`

<a id="author-license"></a>
## Author / License
- **Aurélien LEQUOY** https://www.linkedin.com/in/aur%C3%A9lien-lequoy-30255473/
- Licence: **GNU GPL v3** (`GPL-3.0-or-later`) https://www.gnu.org/licenses/gpl-3.0.html
