#!/usr/bin/env bash
set -euo pipefail
resp=$(curl -sS -m 12 -X POST "$MCP_ENDPOINT" -H "content-type: application/json" -H "authorization: Bearer $MCP_TOKEN" --data "{\"jsonrpc\":\"2.0\",\"id\":5,\"method\":\"tools/call\",\"params\":{\"name\":\"db_select\",\"arguments\":{\"sql\":\"SHOW STATUS LIKE \\\"Ssl_cipher\\\"\"}}}"); echo "$resp" | jq -e ".result.structuredContent.rows | length >= 1" >/dev/null
