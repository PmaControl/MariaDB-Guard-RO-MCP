#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-owned}"
MCP_TOKEN="${MCP_TOKEN:-my_token}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-sakila}"
DB_USER="${DB_USER:-my_user_mcp_ro}"
DB_PASS="${DB_PASS:-my_password}"
ROOT_USER="${ROOT_DB_USER:-root}"
ROOT_PASS="${ROOT_DB_PASS:-root_change_me}"
TIMEOUT_S="${MAX_SELECT_TIME_S:-5}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../../.." && pwd)"
MCP_PORT="${AUTO_KILL_TEST_MCP_PORT:-50140}"
ENV_FILE_PATH="$(mktemp)"
SERVER_LOG="$(mktemp)"
SERVER_PID=""
MCP_ENDPOINT="http://127.0.0.1:${MCP_PORT}/mcp"

cleanup() {
  if [ -n "$SERVER_PID" ]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" >/dev/null 2>&1 || true
  fi
  rm -f "$ENV_FILE_PATH" "$SERVER_LOG"
}

trap cleanup EXIT

cat >"$ENV_FILE_PATH" <<ENV
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
MCP_TOKEN=$MCP_TOKEN
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_S=$TIMEOUT_S
WHERE_FULLSCAN_MAX_ROWS=30000
MAX_CONCURRENT_DB_SELECT=3
AUTO_KILL_DB_SELECT=1
MCP_QUERY_LOG=/tmp/mcp_auto_kill_query.log
ENV

ENV_FILE="$ENV_FILE_PATH" php -S "127.0.0.1:${MCP_PORT}" -t "$ROOT_DIR/public" "$ROOT_DIR/public/index.php" >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 20); do
  if curl -sS "http://127.0.0.1:${MCP_PORT}/health" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

mcp_call() {
  local sql="$1"
  curl -sS -m 30 -X POST "$MCP_ENDPOINT" \
    -H "content-type: application/json" \
    -H "authorization: Bearer $MCP_TOKEN" \
    --data "{\"jsonrpc\":\"2.0\",\"id\":140,\"method\":\"tools/call\",\"params\":{\"name\":\"db_select\",\"arguments\":{\"sql\":\"$sql\"}}}"
}

mysql_root() {
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$ROOT_USER" "-p$ROOT_PASS" "$@"
}

case "$MODE" in
  owned)
    start_ts="$(date +%s)"
    response="$(mcp_call 'SELECT SLEEP(7)')"
    end_ts="$(date +%s)"
    elapsed=$((end_ts - start_ts))
    echo "$response" | jq -e '.error.message == "guard [execution time reached]"' >/dev/null
    if [ "$elapsed" -ge 7 ]; then
      echo "auto-kill owned query took too long: ${elapsed}s" >&2
      exit 1
    fi
    ;;
  external)
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" "-p$DB_PASS" "$DB_NAME" \
      -e "SELECT SLEEP(20)" >/tmp/external_auto_kill_guard_"$$".out 2>/tmp/external_auto_kill_guard_"$$".err &
    external_pid=$!
    trap 'kill "$external_pid" >/dev/null 2>&1 || true; rm -f /tmp/external_auto_kill_guard_"$$".out /tmp/external_auto_kill_guard_"$$".err; cleanup' EXIT

    external_conn_id=""
    for _ in $(seq 1 20); do
      external_conn_id="$(mysql_root -Nse "SELECT ID FROM information_schema.PROCESSLIST WHERE USER = '${DB_USER}' AND COMMAND IN ('Query','Execute') AND INFO LIKE 'SELECT SLEEP(20)%' ORDER BY ID ASC LIMIT 1" || true)"
      if [ -n "$external_conn_id" ]; then
        break
      fi
      sleep 1
    done

    [ -n "$external_conn_id" ] || { echo "external query not visible in processlist" >&2; exit 1; }

    response="$(mcp_call 'SELECT SLEEP(7)')"
    echo "$response" | jq -e '.error.message == "guard [execution time reached]"' >/dev/null

    still_running="$(mysql_root -Nse "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = ${external_conn_id} AND COMMAND IN ('Query','Execute')" || true)"
    if [ "${still_running:-0}" -lt 1 ]; then
      echo "external query was unexpectedly killed" >&2
      exit 1
    fi

    wait "$external_pid"
    rm -f /tmp/external_auto_kill_guard_"$$".out /tmp/external_auto_kill_guard_"$$".err
    trap cleanup EXIT
    ;;
  *)
    echo "Unknown mode: $MODE" >&2
    exit 2
    ;;
esac
