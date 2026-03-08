#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_DIR="$(cd "$ROOT_DIR/../.." && pwd)"

MCP_PORT="${MCP_PORT:-49999}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_START_PORT="${DB_START_PORT:-33600}"
DB_NAME="${DB_NAME:-sakila}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
RO_USER="${RO_USER:-my_user_mcp_ro}"
RO_PASS="${RO_PASS:-my_password}"
MCP_TOKEN="${MCP_TOKEN:-my_token}"
MAX_CONCURRENT_DB_SELECT="${MAX_CONCURRENT_DB_SELECT:-3}"

declare -a TARGETS=(
  "mysql|5.6:latest"
  "mariadb|10.11:latest"
  "mariadb|11.4:latest"
  "mysql|5.7:latest"
  "mysql|8.4:latest"
)

if [ -n "${HARDCORE_TARGETS:-}" ]; then
  IFS=',' read -r -a TARGETS <<<"${HARDCORE_TARGETS}"
fi

declare -a TEST_IDS=(
  "GUARD-001"
  "GUARD-010"
  "GUARD-020"
  "GUARD-100"
  "GUARD-110"
  "GUARD-120"
  "GUARD-130"
  "GUARD-900"
)

if [ -n "${HARDCORE_TEST_IDS:-}" ]; then
  IFS=',' read -r -a TEST_IDS <<<"${HARDCORE_TEST_IDS}"
fi

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

require_cmd docker
require_cmd jq
require_cmd curl
require_cmd php
require_cmd mysqladmin
require_cmd mysql

RUN_ID="hardcore-matrix-$(date +%Y%m%d-%H%M%S)-$$"
RUN_DIR="${ROOT_DIR}/runs/${RUN_ID}"
mkdir -p "$RUN_DIR"

MCP_PID=""

cleanup() {
  if [ -n "$MCP_PID" ] && kill -0 "$MCP_PID" >/dev/null 2>&1; then
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

  local user_exists
  user_exists="$(mysql -N -s -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" -e "SELECT COUNT(*) FROM mysql.user WHERE user='${RO_USER}' AND host='%';" 2>/dev/null || echo "0")"
  if [ "${user_exists:-0}" -gt 0 ]; then
    mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" -e "DROP USER '${RO_USER}'@'%';" >/dev/null 2>&1 || true
  fi

  mysql -h"$DB_HOST" -P"$port" -uroot -p"$ROOT_PASS" <<SQL || true
CREATE USER '${RO_USER}'@'%' IDENTIFIED BY '${RO_PASS}';
GRANT SELECT ON \`${DB_NAME}\`.* TO '${RO_USER}'@'%';
FLUSH PRIVILEGES;
SQL
}

write_env() {
  local port="$1"
  local ssl_mode="${2:-off}"
  local db_ssl="false"
  local db_ssl_verify_cert="false"
  local db_ssl_verify_identity="false"
  if [ "$ssl_mode" = "required" ]; then
    db_ssl="true"
  fi

  cat > "${PROJECT_DIR}/.env" <<ENV
DB_HOST=${DB_HOST}
DB_PORT=${port}
DB_NAME=${DB_NAME}
DB_USER=${RO_USER}
DB_PASS=${RO_PASS}
DB_CHARSET=utf8mb4
DB_SSL=${db_ssl}
DB_SSL_CA=
DB_SSL_CERT=
DB_SSL_KEY=
DB_SSL_VERIFY_CERT=${db_ssl_verify_cert}
DB_SSL_VERIFY_IDENTITY=${db_ssl_verify_identity}
MCP_TOKEN=${MCP_TOKEN}
MAX_ROWS_DEFAULT=1000
MAX_ROWS_HARD=5000
MAX_SELECT_TIME_S=5
WHERE_FULLSCAN_MAX_ROWS=30000
MAX_CONCURRENT_DB_SELECT=${MAX_CONCURRENT_DB_SELECT}
MCP_QUERY_LOG=/tmp/mcp_mariadb_hardcore_${RUN_ID}.log
ENV
}

start_mcp() {
  php -S "127.0.0.1:${MCP_PORT}" -t "${PROJECT_DIR}/public" "${PROJECT_DIR}/public/index.php" >"${RUN_DIR}/mcp_server.log" 2>&1 &
  MCP_PID=$!
  sleep 2
  curl -fsS "http://127.0.0.1:${MCP_PORT}/health" | jq -e '.ok == true' >/dev/null
}

stop_mcp() {
  if [ -n "$MCP_PID" ] && kill -0 "$MCP_PID" >/dev/null 2>&1; then
    kill "$MCP_PID" >/dev/null 2>&1 || true
    wait "$MCP_PID" 2>/dev/null || true
  fi
  MCP_PID=""
}

SUMMARY_FILE="${RUN_DIR}/hardcore-summary.tsv"
echo -e "engine\trequested_version\tresolved_version\timage\tpull_status\ttest_id\tstatus\tnote" > "$SUMMARY_FILE"

emit_result() {
  local engine="$1"
  local requested="$2"
  local resolved="$3"
  local image="$4"
  local pull_status="$5"
  local test_id="$6"
  local status="$7"
  local note="$8"
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$engine" "$requested" "$resolved" "$image" "$pull_status" "$test_id" "$status" "$note" >> "$SUMMARY_FILE"
  echo "[result] engine=${engine} requested=${requested} resolved=${resolved} test=${test_id} status=${status} note=${note} pull=${pull_status}"
}

port="$DB_START_PORT"
for target in "${TARGETS[@]}"; do
  IFS='|' read -r engine version <<<"$target"
  echo "[${engine}:${version}] provisioning on port ${port}"

  set +e
  provision_out="$("${SCRIPT_DIR}/provision_db.sh" "$engine" "$version" "$port" 2>"${RUN_DIR}/${engine}-${version//[^a-zA-Z0-9]/-}.provision.err")"
  rc=$?
  set -e

  if [ "$rc" -ne 0 ]; then
    for test_id in "${TEST_IDS[@]}"; do
      emit_result "$engine" "$version" "-" "-" "-" "$test_id" "skipped" "image/provision unavailable"
    done
    port=$((port + 1))
    continue
  fi

  IFS='|' read -r container_name image resolved_version pull_status <<<"$provision_out"
  echo "[provision] engine=${engine} requested=${version} resolved=${resolved_version} image=${image} pull=${pull_status}"

  if ! wait_db_ready "$port"; then
    for test_id in "${TEST_IDS[@]}"; do
      emit_result "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$test_id" "failed" "DB did not become ready"
    done
    docker rm -f "$container_name" >/dev/null 2>&1 || true
    port=$((port + 1))
    continue
  fi

  seed_db_and_user "$port"
  write_env "$port" "off"
  start_mcp

  export MCP_ENDPOINT="http://127.0.0.1:${MCP_PORT}/mcp"
  export MCP_TOKEN

  expected_regex="${resolved_version%%-*}"
  expected_regex="${expected_regex//./\\.}"
  export EXPECTED_DB_VERSION_REGEX="${expected_regex}"

  for test_id in "${TEST_IDS[@]}"; do
    if [ "$test_id" = "GUARD-900" ]; then
      write_env "$port" "required"
    else
      write_env "$port" "off"
    fi

    out_prefix="${RUN_DIR}/${engine}-${resolved_version}-${test_id}"
    set +e
    "${ROOT_DIR}/bin/run.sh" --unit --id "$test_id" \
      --output-json "${out_prefix}.json" \
      --output-junit "${out_prefix}.xml" \
      >/dev/null 2>&1
    test_rc=$?
    set -e

    if [ "$test_rc" -eq 0 ]; then
      emit_result "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$test_id" "success" "-"
    else
      emit_result "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$test_id" "failed" "see run artifacts"
    fi
  done

  stop_mcp
  docker rm -f "$container_name" >/dev/null 2>&1 || true
  port=$((port + 1))
done

echo "Hardcore matrix completed: ${SUMMARY_FILE}"
column -t -s $'\t' "$SUMMARY_FILE" || cat "$SUMMARY_FILE"
