#!/usr/bin/env bash
set -euo pipefail
curl -sS -m 10 -X POST "$MCP_ENDPOINT" -H "content-type: application/json" -H "authorization: Bearer $MCP_TOKEN" --data "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"db_select\",\"arguments\":{\"sql\":\"SELECT film_id FROM film WHERE film_id = 1 OR film_id = 2\"}}}" | jq -e ".error.message == \"db_select forbids OR in WHERE. Rewrite the query using UNION or UNION ALL.\"" >/dev/null
