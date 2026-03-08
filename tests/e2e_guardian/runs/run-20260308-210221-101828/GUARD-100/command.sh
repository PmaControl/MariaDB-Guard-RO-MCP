#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 20 -X POST "$MCP_ENDPOINT" -H "content-type: application/json" -H "authorization: Bearer $MCP_TOKEN" --data "{\"jsonrpc\":\"2.0\",\"id\":3,\"method\":\"tools/call\",\"params\":{\"name\":\"db_select\",\"arguments\":{\"sql\":\"SELECT SLEEP(7)\"}}}" | jq -e ".error.message == \"guard [execution time reached]\"" >/dev/null
