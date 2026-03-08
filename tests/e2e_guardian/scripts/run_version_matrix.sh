#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_DIR="$(cd "$ROOT_DIR/../.." && pwd)"

MCP_PORT="${MCP_PORT:-49999}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_START_PORT="${DB_START_PORT:-33306}"
DB_NAME="${DB_NAME:-sakila}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
RO_USER="${RO_USER:-my_user_mcp_ro}"
RO_PASS="${RO_PASS:-my_password}"
MCP_TOKEN="${MCP_TOKEN:-my_token}"
MAX_CONCURRENT_DB_SELECT="${MAX_CONCURRENT_DB_SELECT:-3}"

RUN_ID="matrix-$(date +%Y%m%d-%H%M%S)-$$"
RUN_DIR="${ROOT_DIR}/runs/${RUN_ID}"
mkdir -p "$RUN_DIR"

declare -a TARGETS=(
  "mysql|5.5.62"
  "mysql|5.6.49"
  "mysql|5.7.4"
  "mysql|8.0.44"
  "mysql|8.4.7"
  "mysql|9.6.0"
  "mariadb|10.5.29"
  "mariadb|10.6.23"
  "mariadb|10.11.14"
  "mariadb|11.4.8"
  "mariadb|11.8.6"
  "mariadb|12.0.2"
  "mariadb|10.0:latest"
  "mariadb|10.1:latest"
  "mariadb|10.2:latest"
  "mariadb|10.3:latest"
  "percona|5.7.44"
  "percona|8.0:latest"
  "percona|8.4:latest"
  "percona|9.6:latest"
)

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

require_cmd docker
require_cmd jq
require_cmd curl
require_cmd php
require_cmd mysqladmin
require_cmd mysql

cleanup() {
  if [ -n "${MCP_PID:-}" ] && kill -0 "$MCP_PID" >/dev/null 2>&1; then
    kill "$MCP_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

wait_db_ready() {
  local port="$1"
  for _ in $(seq 1 90); do
    if mysqladmin ping -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

seed_db_and_user() {
  local port="$1"
  mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
USE \`${DB_NAME}\`;
CREATE TABLE IF NOT EXISTS film (
  film_id INT PRIMARY KEY
) ENGINE=InnoDB;
INSERT IGNORE INTO film (film_id) VALUES (1), (2), (3);
SQL

  mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" <<SQL || true
DROP USER IF EXISTS '${RO_USER}'@'%';
SQL

  mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" <<SQL || true
CREATE USER '${RO_USER}'@'%' IDENTIFIED BY '${RO_PASS}';
GRANT SELECT ON \`${DB_NAME}\`.* TO '${RO_USER}'@'%';
FLUSH PRIVILEGES;
SQL

  mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" <<SQL || true
GRANT SELECT ON \`${DB_NAME}\`.* TO '${RO_USER}'@'%' IDENTIFIED BY '${RO_PASS}';
FLUSH PRIVILEGES;
SQL
}

write_env() {
  local port="$1"
  cat > "${PROJECT_DIR}/.env" <<ENV
DB_HOST=${DB_HOST}
DB_PORT=${port}
DB_NAME=${DB_NAME}
DB_USER=${RO_USER}
DB_PASS=${RO_PASS}
MCP_TOKEN=${MCP_TOKEN}
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_S=5
WHERE_FULLSCAN_MAX_ROWS=30000
MAX_CONCURRENT_DB_SELECT=${MAX_CONCURRENT_DB_SELECT}
MCP_QUERY_LOG=/tmp/mcp_mariadb_query_${RUN_ID}.log
ENV
}

start_mcp() {
  php -S "127.0.0.1:${MCP_PORT}" -t "${PROJECT_DIR}/public" "${PROJECT_DIR}/public/index.php" >"${RUN_DIR}/mcp_server.log" 2>&1 &
  MCP_PID=$!
  sleep 2
  curl -fsS "http://127.0.0.1:${MCP_PORT}/health" | jq -e '.ok == true' >/dev/null
}

stop_mcp() {
  if [ -n "${MCP_PID:-}" ] && kill -0 "$MCP_PID" >/dev/null 2>&1; then
    kill "$MCP_PID" >/dev/null 2>&1 || true
    wait "$MCP_PID" 2>/dev/null || true
  fi
  MCP_PID=""
}

summary_file="${RUN_DIR}/matrix-summary.tsv"
echo -e "engine\trequested_version\tresolved_version\timage\tstatus\tnote" > "$summary_file"

port="$DB_START_PORT"
for target in "${TARGETS[@]}"; do
  IFS='|' read -r engine version <<<"$target"
  log_prefix="[${engine}:${version}]"
  echo "${log_prefix} provisioning on port ${port}"

  set +e
  provision_out="$("${SCRIPT_DIR}/provision_db.sh" "$engine" "$version" "$port" 2>"${RUN_DIR}/${engine}-${version//[^a-zA-Z0-9]/-}.provision.err")"
  rc=$?
  set -e
  if [ "$rc" -ne 0 ]; then
    echo -e "${engine}\t${version}\t-\t-\tskipped\timage/provision unavailable" >> "$summary_file"
    port=$((port + 1))
    continue
  fi

  IFS='|' read -r container_name image resolved_version <<<"$provision_out"
  if ! wait_db_ready "$port"; then
    echo -e "${engine}\t${version}\t${resolved_version}\t${image}\tfailed\tDB did not become ready" >> "$summary_file"
    docker rm -f "$container_name" >/dev/null 2>&1 || true
    port=$((port + 1))
    continue
  fi

  seed_db_and_user "$port"
  write_env "$port"
  start_mcp

  expected_regex="${resolved_version%%-*}"
  expected_regex="${expected_regex%%+*}"
  expected_regex="${expected_regex//./\\.}"

  set +e
  MCP_ENDPOINT="http://127.0.0.1:${MCP_PORT}/mcp" \
  MCP_TOKEN="${MCP_TOKEN}" \
  EXPECTED_DB_VERSION_REGEX="${expected_regex}" \
  "${ROOT_DIR}/bin/run.sh" --unit --id GUARD-130 \
    --output-json "${RUN_DIR}/${engine}-${resolved_version}.json" \
    --output-junit "${RUN_DIR}/${engine}-${resolved_version}.xml" \
    >/dev/null 2>&1
  run_rc=$?
  set -e

  if [ "$run_rc" -eq 0 ]; then
    echo -e "${engine}\t${version}\t${resolved_version}\t${image}\tsuccess\tversion detected via MCP" >> "$summary_file"
  else
    echo -e "${engine}\t${version}\t${resolved_version}\t${image}\tfailed\tGUARD-130 failed" >> "$summary_file"
  fi

  stop_mcp
  docker rm -f "$container_name" >/dev/null 2>&1 || true
  port=$((port + 1))
done

echo "Matrix run completed: ${summary_file}"
column -t -s $'\t' "$summary_file" || cat "$summary_file"
