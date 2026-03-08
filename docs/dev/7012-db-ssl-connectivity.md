# 7012 - DB SSL Connectivity

## Description
This feature adds optional database connectivity hardening parameters for MariaDB/MySQL:
- configurable connection charset
- optional TLS/SSL transport for DB connections
- optional certificate and identity verification

Defaults keep backward compatibility:
- `DB_CHARSET` defaults to `utf8mb4`
- SSL is disabled unless `DB_SSL=true`

## Environment Variables
| Variable | Required | Default | Description |
|---|---|---|---|
| `DB_CHARSET` | no | `utf8mb4` | Charset applied in DSN (`charset=...`). |
| `DB_SSL` | no | `false` | Enable TLS/SSL for DB connection when `true`. |
| `DB_SSL_CA` | no | empty | CA certificate path used to validate DB server cert. |
| `DB_SSL_CERT` | no | empty | Client certificate path (mTLS to DB, optional). |
| `DB_SSL_KEY` | no | empty | Client private key path (mTLS to DB, optional). |
| `DB_SSL_VERIFY_CERT` | no | `false` | Enforce certificate-chain validation (`VERIFY_CA`). |
| `DB_SSL_VERIFY_IDENTITY` | no | `false` | Enforce certificate + hostname verification (`VERIFY_IDENTITY`). |

Compatibility note:
- password uses `DB_PASS` (existing behavior) and falls back to `DB_PASSWORD` if `DB_PASS` is empty.

## Example .env Configuration
```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pmacontrol
DB_USER=cline
DB_PASSWORD=secret

DB_CHARSET=utf8mb4

DB_SSL=true
DB_SSL_CA=/etc/mysql/ssl/ca.pem
DB_SSL_CERT=/etc/mysql/ssl/client-cert.pem
DB_SSL_KEY=/etc/mysql/ssl/client-key.pem
DB_SSL_VERIFY_CERT=true
DB_SSL_VERIFY_IDENTITY=true
```

## Example Secure Production Configuration
```dotenv
DB_HOST=db-replica.internal
DB_PORT=3306
DB_NAME=pmacontrol
DB_USER=mcp_ro
DB_PASS=strong_ro_password
DB_CHARSET=utf8mb4

DB_SSL=true
DB_SSL_CA=/etc/mysql/ssl/ca.pem
DB_SSL_CERT=/etc/mysql/ssl/mcp_ro-client.pem
DB_SSL_KEY=/etc/mysql/ssl/mcp_ro-client.key
DB_SSL_VERIFY_CERT=true
DB_SSL_VERIFY_IDENTITY=true
```

## SSL Verification Modes
- `DB_SSL=true` only:
  - TLS required (`ssl-mode=REQUIRED`)
  - no explicit certificate/hostname verification
- `DB_SSL=true` + `DB_SSL_VERIFY_CERT=true`:
  - certificate chain verification (`ssl-mode=VERIFY_CA`)
- `DB_SSL=true` + `DB_SSL_VERIFY_IDENTITY=true`:
  - certificate + hostname verification (`ssl-mode=VERIFY_IDENTITY`)
  - strongest mode

## Misconfiguration Validation
- If `DB_SSL=true` and `DB_SSL_CA` is missing:
  - MCP logs a warning (`error_log`) but does not hard-fail by itself.
- If any provided SSL file path (`DB_SSL_CA`, `DB_SSL_CERT`, `DB_SSL_KEY`) does not exist:
  - MCP fails early at connection bootstrap with explicit runtime error.

## Runtime Validation Command
Use this SQL command through MCP to verify SSL cipher is active:
```sql
SHOW STATUS LIKE 'Ssl_cipher';
```

Expected:
- non-empty `Value` when SSL/TLS is active
- empty `Value` if SSL is not negotiated
