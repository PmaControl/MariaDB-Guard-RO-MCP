#!/usr/bin/env bash
set -euo pipefail

ENGINE="${1:-mariadb}"
VERSION="${2:-11.8}"
PORT="${3:-33306}"
safe_version="$(echo "$VERSION" | sed 's/[^a-zA-Z0-9]/-/g')"
NAME="guardian-db-${ENGINE}-${safe_version}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
DB_NAME="${DB_NAME:-sakila}"
DOCKER_PULL_POLICY="${DOCKER_PULL_POLICY:-if-missing}" # always|if-missing|never
DB_TAG_CACHE_FILE="${DB_TAG_CACHE_FILE:-/tmp/mcp_e2e_tag_cache.tsv}"
DB_TAG_CACHE_TTL_S="${DB_TAG_CACHE_TTL_S:-86400}"

cache_get() {
  local engine="$1"
  local key="$2"
  [ -f "$DB_TAG_CACHE_FILE" ] || return 1
  awk -F'\t' -v e="$engine" -v k="$key" -v now="$(date +%s)" -v ttl="$DB_TAG_CACHE_TTL_S" '
    $1==e && $2==k {
      if ((now - $4) <= ttl) {
        print $3
        found=1
        exit 0
      }
    }
    END { if (!found) exit 1 }
  ' "$DB_TAG_CACHE_FILE"
}

cache_set() {
  local engine="$1"
  local key="$2"
  local value="$3"
  mkdir -p "$(dirname "$DB_TAG_CACHE_FILE")"
  touch "$DB_TAG_CACHE_FILE"
  awk -F'\t' -v e="$engine" -v k="$key" '!( $1==e && $2==k )' "$DB_TAG_CACHE_FILE" > "${DB_TAG_CACHE_FILE}.tmp" || true
  printf '%s\t%s\t%s\t%s\n' "$engine" "$key" "$value" "$(date +%s)" >> "${DB_TAG_CACHE_FILE}.tmp"
  mv "${DB_TAG_CACHE_FILE}.tmp" "$DB_TAG_CACHE_FILE"
}

resolve_percona_latest_tag() {
  local major_minor="$1"
  local repo="${2:-percona/percona-server}"
  local cache_key="${3:-percona}"
  local cached
  if cached="$(cache_get "$cache_key" "$major_minor" 2>/dev/null)"; then
    echo "$cached"
    return 0
  fi
  local page=1
  local matches=""

  while :; do
    local json
    json="$(curl -fsSL "https://registry.hub.docker.com/v2/repositories/${repo}/tags?page_size=100&page=${page}")" || return 1
    local names
    names="$(echo "$json" | jq -r '.results[].name')"

    while IFS= read -r tag; do
      [ -n "$tag" ] || continue
      if [[ "$tag" =~ (arm64|aarch64|amd64)$ ]]; then
        continue
      fi
      if [[ "$tag" =~ ^${major_minor//./\\.}([.-][0-9A-Za-z]+)*$ ]] || [[ "$tag" == ${major_minor}* ]]; then
        matches+="${tag}"$'\n'
      fi
    done <<<"$names"

    local next
    next="$(echo "$json" | jq -r '.next // empty')"
    [ -n "$next" ] || break
    page=$((page + 1))
  done

  if [ -z "$matches" ]; then
    return 1
  fi

  local resolved
  resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  cache_set "$cache_key" "$major_minor" "$resolved"
  echo "$resolved"
}

resolve_mariadb_latest_tag() {
  local major_minor="$1"
  local cached
  if cached="$(cache_get mariadb "$major_minor" 2>/dev/null)"; then
    echo "$cached"
    return 0
  fi
  local page=1
  local matches=""

  while :; do
    local json
    json="$(curl -fsSL "https://registry.hub.docker.com/v2/repositories/library/mariadb/tags?page_size=100&page=${page}")" || return 1
    local names
    names="$(echo "$json" | jq -r '.results[].name')"

    while IFS= read -r tag; do
      [ -n "$tag" ] || continue
      if [[ "$tag" =~ (arm64|aarch64|amd64)$ ]]; then
        continue
      fi
      if [[ "$tag" =~ ^${major_minor//./\\.}([.-][0-9A-Za-z]+)*$ ]] || [[ "$tag" == ${major_minor}* ]]; then
        matches+="${tag}"$'\n'
      fi
    done <<<"$names"

    local next
    next="$(echo "$json" | jq -r '.next // empty')"
    [ -n "$next" ] || break
    page=$((page + 1))
  done

  if [ -z "$matches" ]; then
    return 1
  fi

  local resolved
  resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  cache_set mariadb "$major_minor" "$resolved"
  echo "$resolved"
}

resolve_mysql_latest_tag() {
  local major_minor="$1"
  local cached
  if cached="$(cache_get mysql "$major_minor" 2>/dev/null)"; then
    echo "$cached"
    return 0
  fi
  local page=1
  local matches=""

  while :; do
    local json
    json="$(curl -fsSL "https://registry.hub.docker.com/v2/repositories/library/mysql/tags?page_size=100&page=${page}")" || return 1
    local names
    names="$(echo "$json" | jq -r '.results[].name')"

    while IFS= read -r tag; do
      [ -n "$tag" ] || continue
      if [[ "$tag" =~ (arm64|aarch64|amd64)$ ]]; then
        continue
      fi
      if [[ "$tag" =~ ^${major_minor//./\\.}([.-][0-9A-Za-z]+)*$ ]] || [[ "$tag" == ${major_minor}* ]]; then
        matches+="${tag}"$'\n'
      fi
    done <<<"$names"

    local next
    next="$(echo "$json" | jq -r '.next // empty')"
    [ -n "$next" ] || break
    page=$((page + 1))
  done

  if [ -z "$matches" ]; then
    return 1
  fi

  local resolved
  resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  cache_set mysql "$major_minor" "$resolved"
  echo "$resolved"
}

resolved_version="$VERSION"
IMAGE=""
case "$ENGINE" in
  mariadb)
    if [[ "$VERSION" == *":latest" ]]; then
      base="${VERSION%:latest}"
      resolved_version="$(resolve_mariadb_latest_tag "$base")" || {
        echo "Impossible de resoudre un tag MariaDB pour ${VERSION}" >&2
        exit 2
      }
    fi
    IMAGE="mariadb:${resolved_version}"
    ;;
  mysql)
    if [[ "$VERSION" == *":latest" ]]; then
      base="${VERSION%:latest}"
      resolved_version="$(resolve_mysql_latest_tag "$base")" || {
        echo "Impossible de resoudre un tag MySQL pour ${VERSION}" >&2
        exit 2
      }
    fi
    IMAGE="mysql:${resolved_version}"
    ;;
  percona|percona/percona-server)
    percona_repo="$ENGINE"
    if [ "$percona_repo" = "percona" ]; then
      percona_repo="percona"
    else
      percona_repo="percona/percona-server"
    fi
    if [[ "$VERSION" == *":latest" ]]; then
      base="${VERSION%:latest}"
      resolved_version="$(resolve_percona_latest_tag "$base" "$percona_repo" "$ENGINE")" || {
        echo "Impossible de resoudre un tag Percona pour ${VERSION}" >&2
        exit 2
      }
    fi
    IMAGE="${percona_repo}:${resolved_version}"
    ;;
  *)
    echo "ENGINE invalide: $ENGINE (attendu: mariadb|mysql|percona|percona/percona-server)" >&2
    exit 1
    ;;
esac

docker rm -f "$NAME" >/dev/null 2>&1 || true
PULL_STATUS="cached"
case "$DOCKER_PULL_POLICY" in
  always)
    docker pull "$IMAGE" >/dev/null
    PULL_STATUS="pulled(always)"
    ;;
  if-missing)
    if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
      docker pull "$IMAGE" >/dev/null
      PULL_STATUS="pulled(missing)"
    else
      PULL_STATUS="cached(local)"
    fi
    ;;
  never)
    if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
      echo "Image absente localement et DOCKER_PULL_POLICY=never: $IMAGE" >&2
      exit 3
    fi
    PULL_STATUS="cached(policy-never)"
    ;;
  *)
    echo "DOCKER_PULL_POLICY invalide: $DOCKER_PULL_POLICY (always|if-missing|never)" >&2
    exit 4
    ;;
esac

if [ "$ENGINE" = "mariadb" ]; then
  docker run -d --name "$NAME" -p "$PORT:3306" \
    -e MARIADB_ROOT_PASSWORD="$ROOT_PASS" \
    -e MARIADB_DATABASE="$DB_NAME" \
    "$IMAGE" >/dev/null
else
  docker run -d --name "$NAME" -p "$PORT:3306" \
    -e MYSQL_ROOT_PASSWORD="$ROOT_PASS" \
    -e MYSQL_DATABASE="$DB_NAME" \
    "$IMAGE" >/dev/null
fi

echo "$NAME|$IMAGE|$resolved_version|$PULL_STATUS"
