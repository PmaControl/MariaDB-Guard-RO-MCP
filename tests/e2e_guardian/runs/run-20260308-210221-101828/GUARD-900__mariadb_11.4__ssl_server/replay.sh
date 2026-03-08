#!/usr/bin/env bash
set -euo pipefail
"/var/www/mcp-mysql/tests/e2e_guardian/bin/run.sh" --unit --id "GUARD-900__mariadb_11.4__ssl_server" --log-level TRACE --explain
