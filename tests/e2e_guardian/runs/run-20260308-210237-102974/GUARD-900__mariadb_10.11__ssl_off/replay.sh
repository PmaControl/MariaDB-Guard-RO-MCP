#!/usr/bin/env bash
set -euo pipefail
"/var/www/mcp-mysql/tests/e2e_guardian/bin/run.sh" --unit --id "GUARD-900__mariadb_10.11__ssl_off" --log-level TRACE --explain
