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

Manual startup check:
```bash
curl -sS http://127.0.0.1:13306/health
```

## 4. PHPUnit
Run the full suite:
```bash
phpunit --configuration phpunit.xml
```

Run a single test file:
```bash
phpunit --configuration phpunit.xml tests/AccountSecurityTest.php
```

## 5. Security Account Checklist (`mcp_test`)
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

## 6. CI/CD & Releases
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

## 7. Pre-commit Hook (Local)
Install local pre-commit hook to enforce tests before commit:
```bash
cat > .git/hooks/pre-commit <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail
phpunit --configuration phpunit.xml
HOOK
chmod +x .git/hooks/pre-commit
```

