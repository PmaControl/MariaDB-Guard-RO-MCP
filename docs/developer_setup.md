# Developer Setup Guide

This guide is for contributors and maintainers.

## 1. Local Platform Prerequisites
- Debian 12/13 or Ubuntu 24.04
- PHP 8.2+
- Apache 2.4+
- MariaDB/MySQL access for integration checks

Recommended packages:
```bash
apt-get update
apt-get install -y \
  git curl jq \
  php php-cli php-mysql php-xml php-mbstring php-curl \
  apache2 libapache2-mod-php \
  mariadb-client \
  phpunit
```

## 2. Clone and Initial Setup
```bash
git clone https://github.com/PmaControl/MariaDB-Guard-RO-MCP.git mcp-mariadb
cd mcp-mariadb
cp -a .env.sample .env
```

Edit `.env` with your local values.

## 3. Install/Run
One-shot installer:
```bash
chmod +x install.sh
./install.sh
```

Example with explicit install directory and port:
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

Manual startup check:
```bash
curl -sS http://127.0.0.1:13306/health
```

## 4. Pull Request Workflow
For each feature, create a dedicated branch with a unique numeric identifier.

Rules:
- branch pattern: `feature/<id>-short-name`
- unique id constraint: `7000 < id < 65000`
- one feature per branch (no mixing)
- include documentation update in the same branch

Recommended flow:
```bash
git checkout main
git pull
git checkout -b feature/7001-example-name

# code changes + docs changes
phpunit --configuration phpunit.xml

git add -A
git commit -m "Implement feature 7001 example"
git push -u origin feature/7001-example-name
```

Then open a Pull Request including:
- implementation summary
- security/behavior impact
- test commands + results
- updated documentation files

## 5. PHPUnit
Run the full suite:
```bash
phpunit --configuration phpunit.xml
```

Run a single test file:
```bash
phpunit --configuration phpunit.xml tests/AccountSecurityTest.php
```

## 6. Security Account Checklist (`mcp_test`)
Validate DB account safety:
```bash
curl -sS -X POST http://127.0.0.1:13306/mcp \
  -H 'content-type: application/json' \
  -H 'authorization: Bearer <MCP_TOKEN>' \
  --data '{"jsonrpc":"2.0","id":99,"method":"tools/call","params":{"name":"mcp_test","arguments":{"forceRefresh":true}}}'
```

If blocked:
- remove write/DDL/admin grants from DB account
- keep `SELECT` mandatory
- `SHOW VIEW` and `PROCESS` are optional read/diagnostic grants
- update `.env` (or remove `.account_tested`) to force a retest

## 7. CI/CD & Releases
- CI workflow: `.github/workflows/ci.yml`
  - trigger: push on `main`, pull requests
  - action: runs PHPUnit

- CD workflow: `.github/workflows/cd-ghcr.yml`
  - trigger: push on `main` and tags `v*`
  - GHCR publish on `main` and `v*`
  - Docker Hub publish only on `v*` tags

Release example:
```bash
git tag v1.0.2
git push origin v1.0.2
```

## 8. Pre-commit Hook (Local)
Install local pre-commit hook to enforce tests before commit:
```bash
cat > .git/hooks/pre-commit <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail
phpunit --configuration phpunit.xml
HOOK
chmod +x .git/hooks/pre-commit
```
