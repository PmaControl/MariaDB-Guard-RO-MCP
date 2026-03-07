# MCP MariaDB/MySQL Server (PHP)

Le serveur MCP (Model Context Protocol) en PHP pour MariaDB/MySQL qui transforme tes accès SQL en mode production: lecture sécurisée, garde-fous anti-requêtes toxiques, limites d'exécution et diagnostics EXPLAIN exploitables pour analyser plus vite sans casser ta base.

English version: [README_en.md](README_en.md)

## Objectif
Faire des requêtes sur une base de production avec un filet de sécurité actif avant exécution SQL réelle: contrôle du volume de lignes, validation du plan d'exécution (EXPLAIN), limite de temps, garde sur les table scans et limitation de la pression parallèle.

Conçu pour les environnements critiques avec des tables massives (centaines de millions à milliards de lignes), ce serveur MCP réduit fortement le risque de requête destructive en prod tout en gardant une capacité d'analyse opérationnelle rapide.

En pratique, le serveur ajoute des protections clés:
- `read-only` sur les outils SQL exposés
- rejet des patterns dangereux (`FOR UPDATE`, `OR` non maîtrisé, `WITH RECURSIVE`)
- timeout SQL (MariaDB/MySQL selon version)
- policy sur full scan avec `WHERE` selon taille de table
- plafonnement de résultat (`MAX_ROWS_DEFAULT` / `MAX_ROWS_HARD`)
- garde de charge: refus temporaire si trop de requêtes déjà en cours (`database busy retry in 1 second`)
- logs requête + plan + temps + volume retourné pour audit et tuning

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
  - `db_explain_table`
  - `db_processlist`
  - `db_variables`

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

Installation rapide (root) sur Ubuntu 24.04 / Debian 12 / Debian 13:
```bash
chmod +x install.sh
./install.sh
```

### 1. Déployer le code
```bash
cd /srv/www
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
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
MAX_SELECT_TIME_MS=30000
WHERE_FULLSCAN_MAX_ROWS=30000
MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_query.log
```

Notes:
- `MCP_TOKEN` vide (`MCP_TOKEN=`) => pas d’auth
- `MCP_TOKEN` non vide => header `Authorization: Bearer <token>` obligatoire
- `MAX_ROWS_DEFAULT=1000` applique une limite par défaut de 1000 lignes
- `MAX_ROWS_HARD=5000` impose une limite maximum absolue de 5000 lignes
- `MAX_SELECT_TIME_MS` limite la durée max des requêtes `SELECT`
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Valeur recommandée: `30000` (30s). Ce seuil laisse passer des requêtes analytiques plus lourdes tout en gardant une limite de protection.
- `WHERE_FULLSCAN_MAX_ROWS=30000` fixe le seuil de refus pour les full scans avec `WHERE`.
- `MCP_QUERY_LOG` définit le fichier de log JSONL des requêtes MCP SQL (SQL formatée, `rowCount`, `durationMs`, `plan`).

Exemple MySQL:
```sql
SELECT /*+ MAX_EXECUTION_TIME(30000) */ *
FROM huge_table;
```

Exemple MariaDB:
```sql
SET STATEMENT max_statement_time=30 FOR
SELECT *
FROM huge_table;
```

Créer l'utilisateur MySQL/MariaDB (exemple compatible):
```sql
CREATE USER IF NOT EXISTS `cline`@`%` IDENTIFIED BY 'change_me';
GRANT SELECT ON *.* TO `cline`@`%`;
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

### Tool `db_explain_table` (EXPLAIN lisible)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"db_explain_table","arguments":{"sql":"SELECT id,id_mysql_server,port FROM alias_dns WHERE id_mysql_server = 113 ORDER BY id DESC LIMIT 50"}}}'
```

## Configuration MCP Inspector (Streamable HTTP)
- Transport: **Streamable HTTP**
- URL: `http://<HOST>:13306/mcp`
- Authentication: `None`
- Si `MCP_TOKEN` est défini, ajouter le header:
  - `Authorization: Bearer <MCP_TOKEN>`

## Sécurité
- Utiliser un compte DB à privilèges minimum (lecture seule recommandé)
- Donner uniquement les droits nécessaires (`SELECT` recommandé)
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

## Dépannage
- `404` sur `/mcp` avec `curl`: vérifier que vous faites un **POST** (pas GET)
- `Unauthorized`: token manquant ou invalide
- Erreurs CORS Inspector: vérifier `OPTIONS /mcp` (204) et headers CORS
- Vérifier logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_error.log`
  - SQL MCP (JSONL): `/srv/www/mcp-mariadb/mcp_mariadb_query.log`

## Tests PHPUnit
- Installation système recommandée: `apt-get install -y phpunit`
- Lancer les tests:
```bash
phpunit --configuration phpunit.xml
```
- Suite actuelle: `tests/ToolsDbSelectPolicyTest.php`
  - cas mockés sur `EXPLAIN`, estimation de lignes (`TABLE_ROWS`) et validation des règles `db_select`
- Rejeu des requêtes complexes: `tests/ToolsComplexQueriesReplayTest.php`
- Génération d'un rapport Markdown des requêtes replay (source, SQL formatée, explain, temps, succès/erreur, rows):
```bash
php scripts/generate_mcp_test_report.php
```
  - Fichier généré: `docs/mcp_test_queries_report.md`
- Génération du catalogue MYXPLAIN (`explain_*.json`) avec ~100 requêtes `db_explain` (règle `id_xxx -> xxx.id`):
```bash
php scripts/generate_myxplain_query_catalog.php
```
  - Fichiers générés:
    - `docs/myxplain_query_catalog.md`
    - `docs/myxplain_query_catalog.json`
  - Le catalogue exécute chaque cas via `db_explain_table` et stocke:
    - exécution réelle `db_select` (vrai `Returned rows` + vrai `Execution time (ms)`)
    - `db_explain_table` (tableau EXPLAIN lisible)
    - statut pass/fail et raison d'échec pour les 2 exécutions
    - vérification `Expected signature` vs `Signature match`
    - filtre des relations: tables avec `TABLE_ROWS >= 100`
  - Source des cas: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`

## Docker
Build local:
```bash
docker build -t mariadb-guard-ro-mcp:local .
```

Run local:
```bash
docker run --rm -p 13307:13306 \
  -e DB_HOST=10.68.68.111 \
  -e DB_PORT=3306 \
  -e DB_NAME=pmacontrol \
  -e DB_USER=cline \
  -e DB_PASS=cline \
  -e MCP_TOKEN=change_me_if_needed \
  mariadb-guard-ro-mcp:local
```

## CI/CD GitHub + GHCR + Docker Hub
- CI: `.github/workflows/ci.yml`
  - déclenchement: `push` sur `main` + `pull_request`
  - exécute `phpunit --configuration phpunit.xml`
- CD: `.github/workflows/cd-ghcr.yml`
  - déclenchement: `push` sur `main` et tags `v*`
  - build multi-arch (`linux/amd64`, `linux/arm64`)
  - push vers `ghcr.io/pmacontrol/mariadb-guard-ro-mcp`
  - push vers `docker.io/pmacontrol/mariadb-guard-ro-mcp`
  - utilise `GITHUB_TOKEN` (permissions `packages: write`)
  - utilise aussi les secrets GitHub:
    - `DOCKERHUB_USERNAME`
    - `DOCKERHUB_TOKEN`

Authentification GHCR en local (PAT classic):
```bash
export CR_PAT=YOUR_TOKEN
echo \"$CR_PAT\" | docker login ghcr.io -u <github_username> --password-stdin
```

Pull image:
```bash
docker pull ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Run image GHCR:
```bash
docker run --rm -p 13307:13306 ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Run image GHCR avec configuration BDD:
```bash
docker run --rm -p 13307:13306 \
  -e DB_HOST=IP_OU_HOST_DB \
  -e DB_PORT=3306 \
  -e DB_NAME=sakila \
  -e DB_USER=cline \
  -e DB_PASS=change_me \
  -e MCP_TOKEN=change_me_if_needed \
  ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Pull image Docker Hub:
```bash
docker pull pmacontrol/mariadb-guard-ro-mcp:latest
```

Run image Docker Hub:
```bash
docker run --rm -p 13307:13306 pmacontrol/mariadb-guard-ro-mcp:latest
```

## Commandes utiles
```bash
# Redémarrer Apache
service apache2 restart

# Voir les logs en direct
tail -f /var/log/apache2/mcp_mariadb_access.log /var/log/apache2/mcp_mariadb_error.log /srv/www/mcp-mariadb/mcp_mariadb_query.log
```
