#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 8 "${MCP_ENDPOINT%/mcp}/health" | jq -e ".ok == true" >/dev/null
