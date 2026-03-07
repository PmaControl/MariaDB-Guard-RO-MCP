# MCP MariaDB/MySQL Server (PHP)

A production-ready PHP MCP (Model Context Protocol) server for MariaDB/MySQL that turns SQL access into a safer workflow: read-only by design, guarded against risky queries, execution-limited, and backed by actionable EXPLAIN diagnostics.

## Goal
Run queries against production databases with safety controls enforced before SQL hits the server: row-volume limits, EXPLAIN-based validation, execution-time caps, table-scan guards, and parallel load protection.

Built for critical environments with massive datasets (hundreds of millions to billions of rows), this MCP server is designed to reduce production risk while keeping fast, practical investigation capabilities.

In practice, it enforces key protections:
- read-only SQL tooling surface
- dangerous pattern blocking (`FOR UPDATE`, unmanaged `OR`, `WITH RECURSIVE`)
- SQL timeout guards (MariaDB/MySQL version-aware)
- `WHERE` full-scan policy based on table size
- result-size caps (`MAX_ROWS_DEFAULT` / `MAX_ROWS_HARD`)
- load guard: temporary refusal when too many queries are already running (`database busy retry in 1 second`)
- query + plan + duration + row-count logging for audit and tuning

Version francaise: [README.md](README.md)

## Author
- **Aurélien LEQUOY**

## License
This project is distributed under the **GNU GPL v3** license.

- License: GPL-3.0-or-later
- Official text: https://www.gnu.org/licenses/gpl-3.0.html

## Features
- Health endpoint: `GET /health`
- MCP JSON-RPC endpoint: `POST /mcp`
- **Streamable HTTP** transport compatible
- Optional Bearer authentication via `MCP_TOKEN`
- SQL tools:
  - `db_select`
  - `db_tables`
  - `db_schema`
  - `db_indexes`
  - `db_explain`
  - `db_explain_table`
  - `db_processlist`
  - `db_variables`

## Tested Servers
- `MariaDB`
  - `5.5.45`
  - `10.1.1+` (including `10.5.29`, `10.6.23`, `10.11.16`, `12.3.2`)
- `MySQL`
  - `4.1.22`
  - `5.7.1`, `5.7.4+` (including `5.7.44`)
  - `8.0.45`
  - `8.4.5`
  - `9.6.0`
- `Percona Server`
  - `5.7.1`

Notes:
- Expected compatibility: the server is designed to work with MySQL-compatible engines from the `MySQL 4.1+` generation (including MariaDB and Percona Server), subject to version-specific feature differences.
- SQL timeout behavior is version-dependent:
  - MariaDB: enabled from `10.1.1`
  - MySQL: enabled from `5.7.4`
  - Percona Server: same rule as MySQL (`5.7.4+`)

## Architecture
“One file = one class” structure:

- `public/index.php` (web entrypoint)
- `src/Env.php`
- `src/Http.php`
- `src/Db.php`
- `src/SqlGuard.php`
- `src/JsonRpc.php`
- `src/Tools.php`
- `src/App.php`

## Requirements
- Debian/Ubuntu (recommended)
- Apache 2.4+
- PHP 8.2+
- PHP extensions:
  - `pdo`
  - `pdo_mysql`
  - `mbstring` (recommended)
- Network access to MariaDB/MySQL

## Full Installation (Apache)

Quick install (root) on Ubuntu 24.04 / Debian 12 / Debian 13:
```bash
chmod +x install.sh
./install.sh
```

### 1. Deploy the code
```bash
cd /srv/www
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
cd /srv/www/mcp-mariadb
```

### 2. Configure environment
Create/edit `.env`:
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
- Empty `MCP_TOKEN` (`MCP_TOKEN=`) => no auth
- Non-empty `MCP_TOKEN` => `Authorization: Bearer <token>` header required
- `MAX_ROWS_DEFAULT=1000` applies a default limit of 1000 rows
- `MAX_ROWS_HARD=5000` enforces an absolute maximum of 5000 rows
- `MAX_SELECT_TIME_MS` limits `SELECT` runtime
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Recommended value: `30000` (30s). This allows heavier analytical queries while keeping a protective timeout.
- `WHERE_FULLSCAN_MAX_ROWS=30000` sets the rejection threshold for `WHERE` full scans.
- `MCP_QUERY_LOG` defines the MCP SQL JSONL log file (formatted SQL, `rowCount`, `durationMs`, `plan`).

MySQL example:
```sql
SELECT /*+ MAX_EXECUTION_TIME(30000) */ *
FROM huge_table;
```

MariaDB example:
```sql
SET STATEMENT max_statement_time=30 FOR
SELECT *
FROM huge_table;
```

Create MySQL/MariaDB user (compatible example):
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

### 4. Enable required Apache modules
```bash
a2enmod rewrite headers setenvif
```

### 5. Create Apache VirtualHost
Create `/etc/apache2/sites-available/mcp-mariadb.conf`:

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

Adjust:
- `ServerName`
- the network rule `Require ip ...`
- `Require ip 10.68.68.0/24` means only IPs from `10.68.68.1` to `10.68.68.254` (CIDR `/24`) can access `/mcp` and `/health`, in addition to `Require local` (localhost).

### 6. Enable site and restart Apache
```bash
a2ensite mcp-mariadb.conf
a2dissite 000-default.conf
systemctl reload apache2
# or
service apache2 restart
```

### 7. Verify Apache
```bash
apache2ctl configtest
systemctl status apache2
```

## Runtime Tests

### Healthcheck
```bash
curl -sS http://<HOST>:13306/health
```

### Initialize MCP (with token)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

### Initialize MCP (without token)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

### MCP Ping
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":2,"method":"ping","params":{}}'
```

### `db_explain_table` tool (human-readable EXPLAIN)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"db_explain_table","arguments":{"sql":"SELECT id,id_mysql_server,port FROM alias_dns WHERE id_mysql_server = 113 ORDER BY id DESC LIMIT 50"}}}'
```

## MCP Inspector Configuration (Streamable HTTP)
- Transport: **Streamable HTTP**
- URL: `http://<HOST>:13306/mcp`
- Authentication: `None`
- If `MCP_TOKEN` is set, add header:
  - `Authorization: Bearer <MCP_TOKEN>`

## Security
- Use a DB account with minimum privileges (read-only recommended)
- Grant only required privileges (`SELECT` recommended)
- Restrict Apache network access (`Require ip`)
- Use a strong token for `MCP_TOKEN`
- Put the service behind HTTPS (reverse proxy/Nginx/Apache TLS)
- `SELECT ... FOR UPDATE` is explicitly blocked
- `db_select` now enforces query policy:
  - `SELECT *` without `WHERE` is allowed only on a single table without `JOIN`
  - `SELECT *` with `WHERE` is blocked only when the target table has more than 30 columns
  - non-recursive CTE (`WITH ...`) is allowed
  - recursive CTE (`WITH RECURSIVE ...`) is blocked
  - `OR` in `WHERE` is blocked (rewrite with `UNION`/`UNION ALL`)
  - with `WHERE`, full scan is allowed when the table has at most `30000` rows
  - with `WHERE`, full scan is rejected when the table has more than `30000` rows
  - DB load guard: if more than `3` SQL queries are already running, `db_select` returns `database busy retry in 1 second`
  - when SQL timeout is reached, returned error is normalized to: `guard [execution time reached]`

## Troubleshooting
- `404` on `/mcp` with `curl`: ensure you use **POST** (not GET)
- `Unauthorized`: missing or invalid token
- Inspector CORS errors: verify `OPTIONS /mcp` (204) and CORS headers
- Check logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_error.log`
  - MCP SQL (JSONL): `/srv/www/mcp-mariadb/mcp_mariadb_query.log`

## PHPUnit Tests
- Recommended system install: `apt-get install -y phpunit`
- Run tests:
```bash
phpunit --configuration phpunit.xml
```
- Current suite: `tests/ToolsDbSelectPolicyTest.php`
  - mocked cases for `EXPLAIN`, row estimates (`TABLE_ROWS`), and `db_select` policy checks
- Complex query replay suite: `tests/ToolsComplexQueriesReplayTest.php`
- Generate a Markdown replay report (source, formatted SQL, explain, processing time, success/guard error, rows):
```bash
php scripts/generate_mcp_test_report.php
```
  - Generated file: `docs/mcp_test_queries_report.md`
- Generate MYXPLAIN (`explain_*.json`) catalog with ~100 `db_explain` queries (rule `id_xxx -> xxx.id`):
```bash
php scripts/generate_myxplain_query_catalog.php
```
  - Generated files:
    - `docs/myxplain_query_catalog.md`
    - `docs/myxplain_query_catalog.json`
  - The catalog executes each case via `db_explain_table` and stores:
    - real `db_select` execution (true `Returned rows` + true `Execution time (ms)`)
    - `db_explain_table` output (human-readable EXPLAIN table)
    - pass/fail and fail reason for both executions
    - `Expected signature` vs `Signature match` validation
    - relation filter: tables with `TABLE_ROWS >= 100`
  - Case source: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`

## Docker
Local build:
```bash
docker build -t mariadb-guard-ro-mcp:local .
```

Local run:
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

## GitHub + GHCR + Docker Hub CI/CD
- CI: `.github/workflows/ci.yml`
  - triggers: `push` on `main` + `pull_request`
  - runs `phpunit --configuration phpunit.xml`
- CD: `.github/workflows/cd-ghcr.yml`
  - triggers: `push` on `main` and `v*` tags
  - multi-arch build (`linux/amd64`, `linux/arm64`)
  - pushes to `ghcr.io/pmacontrol/mariadb-guard-ro-mcp`
  - pushes to `docker.io/pmacontrol/mariadb-guard-ro-mcp`
  - uses `GITHUB_TOKEN` (permissions `packages: write`)
  - also uses GitHub secrets:
    - `DOCKERHUB_USERNAME`
    - `DOCKERHUB_TOKEN`

Local GHCR authentication (classic PAT):
```bash
export CR_PAT=YOUR_TOKEN
echo \"$CR_PAT\" | docker login ghcr.io -u <github_username> --password-stdin
```

Pull image:
```bash
docker pull ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Run GHCR image:
```bash
docker run --rm -p 13307:13306 ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Run GHCR image with DB configuration:
```bash
docker run --rm -p 13307:13306 \
  -e DB_HOST=DB_HOST_OR_IP \
  -e DB_PORT=3306 \
  -e DB_NAME=sakila \
  -e DB_USER=cline \
  -e DB_PASS=change_me \
  -e MCP_TOKEN=change_me_if_needed \
  ghcr.io/pmacontrol/mariadb-guard-ro-mcp:latest
```

Pull Docker Hub image:
```bash
docker pull pmacontrol/mariadb-guard-ro-mcp:latest
```

Run Docker Hub image:
```bash
docker run --rm -p 13307:13306 pmacontrol/mariadb-guard-ro-mcp:latest
```

## Useful Commands
```bash
# Restart Apache
service apache2 restart

# Watch logs
 tail -f /var/log/apache2/mcp_mariadb_access.log /var/log/apache2/mcp_mariadb_error.log /srv/www/mcp-mariadb/mcp_mariadb_query.log
```
