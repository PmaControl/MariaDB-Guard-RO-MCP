#!/usr/bin/env bash
set -euo pipefail
"/var/www/mcp-mysql/tests/e2e_guardian/bin/run.sh" --unit --id "EXAMPLE-999__mariadb_11.8__ssl_files" --log-level TRACE --explain
