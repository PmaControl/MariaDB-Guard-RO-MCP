#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 12 -X POST "$MCP_ENDPOINT" -H "content-type: application/json" -H "authorization: Bearer $MCP_TOKEN" --data "{\"jsonrpc\":\"2.0\",\"id\":4,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp_test\",\"arguments\":{\"forceRefresh\":true}}}" | jq -e ".result.structuredContent.safe == true" >/dev/null
