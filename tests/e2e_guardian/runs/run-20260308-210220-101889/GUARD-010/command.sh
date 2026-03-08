#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 8 -X POST "$MCP_ENDPOINT" -H "content-type: application/json" --data "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{}}" | jq -e ".error.code == -32001" >/dev/null
