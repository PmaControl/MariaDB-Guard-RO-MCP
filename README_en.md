# MCP MariaDB/MySQL Server (PHP)

MCP (Model Context Protocol) server in PHP for MariaDB/MySQL, focused on read operations with controlled table creation.

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
  - `db_processlist`
  - `db_variables`
  - `db_create_table`
  - `db_ping`

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

### 1. Deploy the code
```bash
cd /srv/www
git clone https://github.com/PmaControl/AsterDB-MCP.git mcp-mariadb
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
MAX_SELECT_TIME_MS=5000
```

Notes:
- Empty `MCP_TOKEN` (`MCP_TOKEN=`) => no auth
- Non-empty `MCP_TOKEN` => `Authorization: Bearer <token>` header required
- `MAX_ROWS_DEFAULT=1000` applies a default limit of 1000 rows
- `MAX_ROWS_HARD=5000` enforces an absolute maximum of 5000 rows
- `MAX_SELECT_TIME_MS` limits `SELECT` runtime
  - MariaDB (>= 10.1.1): via `SET STATEMENT max_statement_time=... FOR SELECT ...`
  - MySQL (>= 5.7.4): via hint `/*+ MAX_EXECUTION_TIME(...) */`
- Recommended value: `5000` (5s). This cuts heavy queries that can impact the server while allowing normal diagnostic queries.

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

### `db_ping` tool (DB host ping)
```bash
curl -sS -X POST http://<HOST>:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"db_ping","arguments":{"host":"10.68.68.111","port":3306,"timeoutMs":1500}}}'
```

## MCP Inspector Configuration (Streamable HTTP)
- Transport: **Streamable HTTP**
- URL: `http://<HOST>:13306/mcp`
- Authentication: `None`
- If `MCP_TOKEN` is set, add header:
  - `Authorization: Bearer <MCP_TOKEN>`

## Security
- Use a DB account with minimum privileges (read-only recommended)
- Grant only required privileges (`SELECT` and `CREATE` if `db_create_table` is used)
- Restrict Apache network access (`Require ip`)
- Use a strong token for `MCP_TOKEN`
- Put the service behind HTTPS (reverse proxy/Nginx/Apache TLS)
- `SELECT ... FOR UPDATE` is explicitly blocked
- `db_create_table` only accepts simple `CREATE TABLE` (multi-statements and `CREATE TABLE ... AS SELECT` are blocked)
- `db_select` now enforces query policy:
  - `SELECT *` is blocked only when the target table has more than 30 columns
  - `OR` in `WHERE` is blocked (rewrite with `UNION`/`UNION ALL`)
  - mandatory `EXPLAIN` check: indexed access required (full scans are rejected)
  - for any table with more than 100000 rows, all `WHERE` fields for that table must be covered by the same index, otherwise the query is rejected

## Troubleshooting
- `404` on `/mcp` with `curl`: ensure you use **POST** (not GET)
- `Unauthorized`: missing or invalid token
- Inspector CORS errors: verify `OPTIONS /mcp` (204) and CORS headers
- Check logs:
  - Apache access: `/var/log/apache2/mcp_mariadb_access.log`
  - Apache error: `/var/log/apache2/mcp_mariadb_error.log`

## Useful Commands
```bash
# Restart Apache
service apache2 restart

# Watch logs
 tail -f /var/log/apache2/mcp_mariadb_access.log /var/log/apache2/mcp_mariadb_error.log
```
