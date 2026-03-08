#!/usr/bin/env bash
set -euo pipefail
"/var/www/mcp-mysql/tests/e2e_guardian/bin/run.sh" --unit --id "GUARD-110" --log-level TRACE --explain
