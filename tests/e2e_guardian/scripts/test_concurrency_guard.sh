#!/usr/bin/env bash
set -euo pipefail

MCP_ENDPOINT="${MCP_ENDPOINT:?MCP_ENDPOINT requis}"
MCP_TOKEN="${MCP_TOKEN:?MCP_TOKEN requis}"
TOTAL_REQUESTS="${TOTAL_REQUESTS:-10}"
SLEEP_SECONDS="${SLEEP_SECONDS:-10}"
MAX_ALLOWED="${MAX_ALLOWED:-3}"
WORKDIR="${WORKDIR:-$(mktemp -d)}"

mkdir -p "$WORKDIR"

payload=$(cat <<JSON
{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"db_select","arguments":{"sql":"SELECT SLEEP(${SLEEP_SECONDS})"}}}
JSON
)

for i in $(seq 1 "$TOTAL_REQUESTS"); do
  (
    curl -sS -m $((SLEEP_SECONDS + 20)) -X POST "$MCP_ENDPOINT" \
      -H 'content-type: application/json' \
      -H "authorization: Bearer ${MCP_TOKEN}" \
      --data "$payload" >"$WORKDIR/resp_${i}.json" 2>"$WORKDIR/err_${i}.log" || true
  ) &
done
wait

accepted=0
busy=0
other=0

for i in $(seq 1 "$TOTAL_REQUESTS"); do
  f="$WORKDIR/resp_${i}.json"
  if [ ! -s "$f" ]; then
    other=$((other + 1))
    continue
  fi
  msg=$(jq -r '.error.message // empty' "$f" 2>/dev/null || true)
  if [ "$msg" = "database busy retry in 1 second" ]; then
    busy=$((busy + 1))
  else
    accepted=$((accepted + 1))
  fi
done

cat <<OUT
{
  "total": $TOTAL_REQUESTS,
  "sleepSeconds": $SLEEP_SECONDS,
  "accepted": $accepted,
  "busy": $busy,
  "other": $other,
  "maxAllowed": $MAX_ALLOWED,
  "workdir": "${WORKDIR}"
}
OUT

if [ "$accepted" -gt "$MAX_ALLOWED" ]; then
  echo "Concurrency guard failed: accepted=$accepted > maxAllowed=$MAX_ALLOWED" >&2
  exit 1
fi

if [ "$busy" -lt $((TOTAL_REQUESTS - MAX_ALLOWED)) ]; then
  echo "Concurrency guard failed: busy responses too low (busy=$busy)" >&2
  exit 1
fi
