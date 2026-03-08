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
DISCOVER_ALL_LATEST="${DISCOVER_ALL_LATEST:-1}"
DISCOVERY_CACHE_FILE="${DISCOVERY_CACHE_FILE:-/tmp/mcp_e2e_minor_versions_cache.tsv}"
DISCOVERY_CACHE_TTL_S="${DISCOVERY_CACHE_TTL_S:-86400}"
DOCKER_PULL_LOCK_FILE="${DOCKER_PULL_LOCK_FILE:-/tmp/mcp_e2e_docker_pull.lock}"

RUN_ID="matrix-$(date +%Y%m%d-%H%M%S)-$$"
RUN_DIR="${ROOT_DIR}/runs/${RUN_ID}"
mkdir -p "$RUN_DIR"

declare -a TARGETS=()
declare -a TEST_IDS=()

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

require_cmd docker
require_cmd jq
require_cmd curl
require_cmd php
require_cmd mysqladmin
require_cmd mysql
require_cmd nproc
require_cmd flock

SERVER_THREADS="$(nproc --all)"
if [ -z "${SERVER_THREADS}" ] || [ "${SERVER_THREADS}" -lt 1 ]; then
  SERVER_THREADS=1
fi
TEST_PARALLELISM="${TEST_PARALLELISM:-$((SERVER_THREADS * 2))}"
if [ "${TEST_PARALLELISM}" -lt 1 ]; then
  TEST_PARALLELISM=1
fi

discover_guard_test_ids() {
  find "${ROOT_DIR}/cases" -type f -name 'GUARD-*.test' \
    | sed -E 's#^.*/(GUARD-[0-9]+)-.*#\1#' \
    | sort -V -u
}

cache_get_minors() {
  local engine="$1"
  [ -f "$DISCOVERY_CACHE_FILE" ] || return 1
  awk -F'\t' -v e="$engine" -v now="$(date +%s)" -v ttl="$DISCOVERY_CACHE_TTL_S" '
    $1==e {
      if ((now - $3) <= ttl) {
        print $2
        found=1
      }
    }
    END { if (!found) exit 1 }
  ' "$DISCOVERY_CACHE_FILE" | sort -V -u
}

cache_set_minors() {
  local engine="$1"
  shift
  local tmp="${DISCOVERY_CACHE_FILE}.tmp"
  mkdir -p "$(dirname "$DISCOVERY_CACHE_FILE")"
  touch "$DISCOVERY_CACHE_FILE"
  awk -F'\t' -v e="$engine" '$1!=e' "$DISCOVERY_CACHE_FILE" > "$tmp" || true
  local ts
  ts="$(date +%s)"
  for minor in "$@"; do
    [ -n "$minor" ] || continue
    printf '%s\t%s\t%s\n' "$engine" "$minor" "$ts" >> "$tmp"
  done
  mv "$tmp" "$DISCOVERY_CACHE_FILE"
}

fetch_minors_from_hub() {
  local repo="$1"
  local page=1
  local tags=()

  while :; do
    local json
    json="$(curl -fsSL "https://registry.hub.docker.com/v2/repositories/${repo}/tags?page_size=100&page=${page}")" || return 1
    while IFS= read -r tag; do
      [ -n "$tag" ] || continue
      if [[ "$tag" =~ ^([0-9]+)\.([0-9]+)\..* ]]; then
        tags+=("${BASH_REMATCH[1]}.${BASH_REMATCH[2]}")
      fi
    done < <(echo "$json" | jq -r '.results[].name')

    local next
    next="$(echo "$json" | jq -r '.next // empty')"
    [ -n "$next" ] || break
    page=$((page + 1))
  done

  if [ "${#tags[@]}" -eq 0 ]; then
    return 1
  fi

  printf '%s\n' "${tags[@]}" | sort -V -u
}

discover_engine_minors() {
  local engine="$1"
  local repo="$2"
  local minors=""

  if minors="$(cache_get_minors "$engine" 2>/dev/null)"; then
    printf '%s\n' "$minors"
    return 0
  fi

  minors="$(fetch_minors_from_hub "$repo")" || return 1
  mapfile -t minor_arr <<<"$minors"
  cache_set_minors "$engine" "${minor_arr[@]}"
  printf '%s\n' "$minors"
}

build_targets() {
  local engine="$1"
  local repo="$2"
  local minors
  minors="$(discover_engine_minors "$engine" "$repo")" || return 1
  while IFS= read -r minor; do
    [ -n "$minor" ] || continue
    TARGETS+=("${engine}|${minor}:latest")
  done <<<"$minors"
}

if [ "$DISCOVER_ALL_LATEST" = "1" ]; then
  build_targets "mysql" "library/mysql" || true
  build_targets "mariadb" "library/mariadb" || true
  build_targets "percona" "percona" || true
  build_targets "percona/percona-server" "percona/percona-server" || true
fi

if [ "${#TARGETS[@]}" -eq 0 ]; then
  TARGETS=(
    "mysql|5.6:latest"
    "mysql|5.7:latest"
    "mysql|8.0:latest"
    "mysql|8.4:latest"
    "mariadb|10.5:latest"
    "mariadb|10.6:latest"
    "mariadb|10.11:latest"
    "mariadb|11.4:latest"
    "mariadb|11.8:latest"
    "mariadb|12.0:latest"
    "percona|5.7:latest"
    "percona/percona-server|5.7:latest"
    "percona|8.0:latest"
    "percona/percona-server|8.0:latest"
    "percona|8.4:latest"
    "percona/percona-server|8.4:latest"
  )
fi

if [ -n "${VERSION_MATRIX_TARGETS:-}" ]; then
  IFS=',' read -r -a TARGETS <<<"${VERSION_MATRIX_TARGETS}"
fi

if [ -n "${VERSION_MATRIX_TEST_IDS:-}" ]; then
  IFS=',' read -r -a TEST_IDS <<<"${VERSION_MATRIX_TEST_IDS}"
else
  mapfile -t TEST_IDS < <(discover_guard_test_ids)
fi

if [ "${#TEST_IDS[@]}" -eq 0 ]; then
  TEST_IDS=(
    "GUARD-001"
    "GUARD-010"
    "GUARD-020"
    "GUARD-100"
    "GUARD-110"
    "GUARD-120"
    "GUARD-130"
    "GUARD-900"
  )
fi

echo "[matrix] tests=${TEST_IDS[*]}"
echo "[matrix] test_parallelism=${TEST_PARALLELISM} (threads=${SERVER_THREADS})"

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
summary_lock_file="${RUN_DIR}/matrix-summary.lock"
echo -e "engine\trequested_version\tresolved_version\timage\tssl_mode\ttest_id\tstatus\tnote" > "$summary_file"
touch "$summary_lock_file"

emit_result() {
  local engine="$1"
  local requested="$2"
  local resolved="$3"
  local image="$4"
  local ssl_mode="$5"
  local test_id="$6"
  local status="$7"
  local note="$8"
  (
    flock -x 9
    printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$engine" "$requested" "$resolved" "$image" "$ssl_mode" "$test_id" "$status" "$note" >> "$summary_file"
    echo "[result] engine=${engine} requested=${requested} resolved=${resolved} ssl=${ssl_mode} test=${test_id} status=${status} note=${note}"
  ) 9>"$summary_lock_file"
}

wait_for_parallel_slot() {
  while [ "$(jobs -rp | wc -l | tr -d ' ')" -ge "$TEST_PARALLELISM" ]; do
    wait -n || true
  done
}

run_single_guard_test() {
  local engine="$1"
  local version="$2"
  local resolved_version="$3"
  local image="$4"
  local pull_status="$5"
  local expected_regex="$6"
  local ssl_mode="$7"
  local test_id="$8"

  set +e
  MCP_ENDPOINT="http://127.0.0.1:${MCP_PORT}/mcp" \
  MCP_TOKEN="${MCP_TOKEN}" \
  EXPECTED_DB_VERSION_REGEX="${expected_regex}" \
  "${ROOT_DIR}/bin/run.sh" --unit --id "$test_id" \
    --output-json "${RUN_DIR}/${engine}-${resolved_version}-${test_id}.json" \
    --output-junit "${RUN_DIR}/${engine}-${resolved_version}-${test_id}.xml" \
    >/dev/null 2>&1
  test_rc=$?
  set -e

  if [ "$test_rc" -eq 0 ]; then
    emit_result "$engine" "$version" "$resolved_version" "$image" "$ssl_mode" "$test_id" "success" "ok"
  else
    emit_result "$engine" "$version" "$resolved_version" "$image" "$ssl_mode" "$test_id" "failed" "test failed"
  fi
}

run_tests_for_ssl_mode() {
  local engine="$1"
  local version="$2"
  local resolved_version="$3"
  local image="$4"
  local pull_status="$5"
  local expected_regex="$6"
  local ssl_mode="$7"
  shift 7
  local tests=("$@")

  if [ "${#tests[@]}" -eq 0 ]; then
    return 0
  fi

  if [ "$ssl_mode" = "required" ]; then
    write_env "$port" "required"
  else
    write_env "$port" "off"
  fi

  start_mcp
  for test_id in "${tests[@]}"; do
    wait_for_parallel_slot
    run_single_guard_test "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$expected_regex" "$ssl_mode" "$test_id" &
  done
  wait
  stop_mcp
}

port="$DB_START_PORT"
for target in "${TARGETS[@]}"; do
  IFS='|' read -r engine version <<<"$target"
  log_prefix="[${engine}:${version}]"
  echo "${log_prefix} provisioning on port ${port}"

  set +e
  provision_out="$(DOCKER_PULL_LOCK_FILE="${DOCKER_PULL_LOCK_FILE}" "${SCRIPT_DIR}/provision_db.sh" "$engine" "$version" "$port" 2>"${RUN_DIR}/${engine}-${version//[^a-zA-Z0-9]/-}.provision.err")"
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
      emit_result "$engine" "$version" "$resolved_version" "$image" "-" "$test_id" "failed" "DB did not become ready (pull=${pull_status})"
    done
    docker rm -f "$container_name" >/dev/null 2>&1 || true
    port=$((port + 1))
    continue
  fi

  seed_db_and_user "$port"

  expected_regex="${resolved_version%%-*}"
  expected_regex="${expected_regex%%+*}"
  expected_regex="${expected_regex//./\\.}"

  declare -a off_tests=()
  declare -a required_tests=()
  for test_id in "${TEST_IDS[@]}"; do
    if [ "$test_id" = "GUARD-900" ]; then
      required_tests+=("$test_id")
    else
      off_tests+=("$test_id")
    fi
  done

  run_tests_for_ssl_mode "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$expected_regex" "off" "${off_tests[@]}"
  run_tests_for_ssl_mode "$engine" "$version" "$resolved_version" "$image" "$pull_status" "$expected_regex" "required" "${required_tests[@]}"

  docker rm -f "$container_name" >/dev/null 2>&1 || true
  port=$((port + 1))
done

echo "Matrix run completed: ${summary_file}"
column -t -s $'\t' "$summary_file" || cat "$summary_file"
