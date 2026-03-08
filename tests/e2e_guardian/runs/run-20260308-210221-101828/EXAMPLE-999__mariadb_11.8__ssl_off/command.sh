#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 10 -X POST "$MCP_ENDPOINT" \
  -H "content-type: application/json" \
  -H "authorization: Bearer $MCP_TOKEN" \
  --data "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{}}"
