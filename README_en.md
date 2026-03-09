# MCP MariaDB/MySQL Server (PHP)

A production-ready PHP MCP (Model Context Protocol) server for MariaDB/MySQL that turns SQL access into a safer workflow: read-only by design, guarded against risky queries, execution-limited, and backed by actionable EXPLAIN diagnostics. It is especially useful for AI Data Scientist agents that need to explore large production datasets under strict safety controls.

## Goal
Connect to production without stress, even with non-expert users: the MCP server acts as a guardian agent that blocks risky SQL and only lets through queries that can run in safe real-world conditions (table size, EXPLAIN plan, indexing quality, and server load), protecting data, performance, and team confidence.

Built for critical environments with massive datasets (hundreds of millions to billions of rows), this MCP server is designed to reduce production risk while keeping fast, practical investigation capabilities.

**Production Disclaimer:** this MCP is built for very large databases, but in production it should run against a replica (`slave`/read replica), not your primary (`master`) server.

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
- LinkedIn: https://www.linkedin.com/in/aur%C3%A9lien-lequoy-30255473/

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
  - `mcp_test`
  - `db_select`
  - `db_tables`
  - `db_schema`
  - `db_indexes`
  - `db_explain`
  - `db_explain_table`
  - `db_processlist`
  - `db_variables`

## Tested Servers
### MySQL
| Vendor | Tested minor versions |
|---|---|
| MySQL | 5.5.62, 5.6.51, 5.7.44, 8.0.45, 8.1.0, 8.2.0, 8.3.0, 8.4.8, 9.1.0, 9.2.0, 9.3.0, 9.4.0, 9.5.0, 9.6.0 |

### MariaDB
| Vendor | Tested minor versions |
|---|---|
| MariaDB | 5.5.64, 10.0.38, 10.2.44, 10.3.39, 10.4.34, 10.5.29, 10.6.25, 10.7.8, 10.8.8, 10.9.8, 10.10.7, 10.11.16, 11.0.6, 11.1.6, 11.3.2, 11.4.10, 11.5.2, 11.6.2, 11.8.6, 12.0.2, 12.1.2, 12.2.2, 12.3.1 |

### Percona Server
| Vendor | Tested minor versions |
|---|---|
| Percona Server | 5.7.44, 8.0.43, 8.4.7 |

Notes:
- The versions above are explicit minor versions resolved during test runs (image variants may include distribution suffixes such as `-ubi9` or `-oraclelinux9`).
- This list is continuously maintained: whenever a new version is validated by the E2E matrix, the `Tested Servers` section is updated in the documentation.
- Maintenance directive (dev & AI): `contrib/tested_servers_policy_dev_ai.md`
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

### Usage Modes
The project supports 2 modes:
- `Standalone` (without Composer): clone + `.env` + Apache/PHP, using built-in `require_once` fallback loading.
- `Composer library`: integrate from another PHP project with `composer require pmacontrol/mariadb-guard-ro-mcp`.

Composer integration example (from another project):
```bash
composer require pmacontrol/mariadb-guard-ro-mcp
```

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

App::run();
```

Quick install (root) on Ubuntu 24.04 / Debian 12 / Debian 13:
```bash
chmod +x install.sh
./install.sh
```

One-shot install with explicit parameters:
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

HTTP port stays `13306` by default. Use `--http-port` to change it and `--install-dir` to isolate multiple instances on the same host.

By default, `install.sh` derives `Require ip` from `hostname -I` (first IPv4, `/24` network). You can override it with `--allow-cidr`.

## Options (`install.sh`)
- `--install-dir <path>`: installation directory (default: `/srv/www/mcp-mariadb`)
- `--http-port <port>`: Apache HTTP port (default: `13306`)
- `--db-host <host>`: MariaDB/MySQL host (default: `127.0.0.1`)
- `--db-port <port>`: MariaDB/MySQL port (default: `3306`)
- `--db-name <name>`: database name (default: `my_database`)
- `--db-user <user>`: DB user (default: `my_user_mcp_ro`)
- `--db-pass <pass>`: DB password (default: `my_password`)
- `--mcp-token <token>`: MCP Bearer token (default: `my_token`)
- `--allow-cidr <cidr>`: allowed network for `/mcp` and `/health` (default: auto-derived from `hostname -I` as `/24`)
- `-h`, `--help`: show help

### 1. Deploy the code
```bash
cd /srv/www
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
cd /srv/www/mcp-mariadb
```

### 2. Configure environment
Copy template then adjust values:
```bash
cp -a .env.sample .env
```

Sample `.env`:
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
MAX_CONCURRENT_DB_SELECT=3
MCP_QUERY_LOG=/srv/www/mcp-mariadb/mcp_mariadb_13306_query.log
```

Notes:
- Empty `MCP_TOKEN` (`MCP_TOKEN=`) => no auth
- Non-empty `MCP_TOKEN` => `Authorization: Bearer <token>` header required
- `MAX_ROWS_DEFAULT=1000` applies a default limit of 1000 rows
- `MAX_ROWS_HARD=5000` enforces an absolute maximum of 5000 rows
- `MAX_SELECT_TIME_S` limits `SELECT` runtime
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Recommended value: `5` (5s). This protects the server against long-running production queries.
- `WHERE_FULLSCAN_MAX_ROWS=30000` sets the rejection threshold for `WHERE` full scans.
- `MAX_CONCURRENT_DB_SELECT=3` sets the maximum number of concurrent `db_select` queries allowed.
- `MCP_QUERY_LOG` defines the MCP SQL JSONL log file (formatted SQL, `rowCount`, `durationMs`, `plan`).
- For multi-instance setups, use a port-suffixed log file (for example `mcp_mariadb_13306_query.log`, `mcp_mariadb_13307_query.log`).

MySQL example:
```sql
SELECT /*+ MAX_EXECUTION_TIME(5000) */ *
FROM huge_table;
```

MariaDB example:
```sql
SET STATEMENT max_statement_time=5 FOR
SELECT *
FROM huge_table;
```

Create MySQL/MariaDB user (compatible example):
```sql
CREATE USER IF NOT EXISTS `my_user_mcp_ro`@`%` IDENTIFIED BY 'my_password';
GRANT SELECT ON *.* TO `my_user_mcp_ro`@`%`;
-- Optional (read/diagnostics):
-- GRANT SHOW VIEW, PROCESS ON *.* TO `my_user_mcp_ro`@`%`;
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
Create `/etc/apache2/sites-available/mcp-mariadb-13306.conf`:

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

Adjust:
- `ServerName`
- the network rule `Require ip ...`
- `Require ip <YOUR_ALLOWED_CIDR>` means only IPs from your allowed network can access `/mcp` and `/health`, in addition to `Require local` (localhost).

### 6. Enable site and restart Apache
```bash
a2ensite mcp-mariadb-13306.conf
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
- Grant only required privileges (`SELECT` mandatory; `SHOW VIEW` and `PROCESS` are optional)
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
  - DB load guard: if running SQL queries reach `MAX_CONCURRENT_DB_SELECT` (default `3`), `db_select` returns `database busy retry in 1 second`
  - when SQL timeout is reached, returned error is normalized to: `guard [execution time reached]`

## Troubleshooting
- Missing `.env`: copy template then adjust values:
```bash
cp -a .env.sample .env
```
- MCP blocked (account not read-only): run `mcp_test`, revoke all write/DDL/admin privileges, then update `.env` (or remove `.account_tested`) to force a new validation.
- `404` on `/mcp` with `curl`: ensure you use **POST** (not GET)
- `Unauthorized`: missing or invalid token
- Inspector CORS errors: verify `OPTIONS /mcp` (204) and CORS headers
- Check logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_13306_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_13306_error.log`
  - MCP SQL (JSONL): `/srv/www/mcp-mariadb/mcp_mariadb_13306_query.log`

## Developer Guide
For developer platform setup, PHPUnit, CI/CD, Git hooks, and security checklist:
- `docs/developer_setup.md`

Composer/Packagist:
- dependencies: `composer install`
- tests: `./vendor/bin/phpunit --configuration phpunit.xml`
- package: `pmacontrol/mariadb-guard-ro-mcp`

PHPUnit CI:
- standard: PHP `8.2`
- compatibility matrix: `8.2`, `8.3`, `8.4`, `8.5` (latest minor per major)

## Docker
Local build:
```bash
docker build -t mariadb-guard-ro-mcp:local .
```

Local run:
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

## Useful Commands
```bash
# Restart Apache
service apache2 restart

# Watch logs
 tail -f /var/log/apache2/mcp_mariadb_13306_access.log /var/log/apache2/mcp_mariadb_13306_error.log /srv/www/mcp-mariadb/mcp_mariadb_13306_query.log
```
