#!/usr/bin/env bash
set -euo pipefail

ENGINE="${1:-mariadb}"
VERSION="${2:-11.8}"
PORT="${3:-33306}"
safe_engine="$(echo "$ENGINE" | sed 's/[^a-zA-Z0-9]/-/g')"
safe_version="$(echo "$VERSION" | sed 's/[^a-zA-Z0-9]/-/g')"
NAME="guardian-db-${safe_engine}-${safe_version}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
DB_NAME="${DB_NAME:-sakila}"
DOCKER_PULL_POLICY="${DOCKER_PULL_POLICY:-if-missing}" # always|if-missing|never
DB_TAG_CACHE_FILE="${DB_TAG_CACHE_FILE:-/tmp/mcp_e2e_tag_cache.tsv}"
DB_TAG_CACHE_TTL_S="${DB_TAG_CACHE_TTL_S:-86400}"
DOCKER_PULL_LOCK_FILE="${DOCKER_PULL_LOCK_FILE:-/tmp/mcp_e2e_docker_pull.lock}"
MARIADB55_REPO="${MARIADB55_REPO:-}"
MARIADB100_REPO="${MARIADB100_REPO:-}"
PERCONA_8_0_FALLBACK_TAG="${PERCONA_8_0_FALLBACK_TAG:-8.0.43}"

docker_pull_locked() {
  local image="$1"
  if command -v flock >/dev/null 2>&1; then
    mkdir -p "$(dirname "$DOCKER_PULL_LOCK_FILE")"
    # Download lock is global to avoid concurrent Docker Hub pulls.
    (
      flock -x 9
      docker pull "$image" >/dev/null
    ) 9>"$DOCKER_PULL_LOCK_FILE"
  else
    docker pull "$image" >/dev/null
  fi
}

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
  resolved="$(echo "$matches" | sed '/^$/d' | grep -E "^${major_minor//./\\.}\\.[0-9]+$" | sort -V | tail -n 1 || true)"
  if [ -z "$resolved" ]; then
    resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  fi
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
  resolved="$(echo "$matches" | sed '/^$/d' | grep -E "^${major_minor//./\\.}\\.[0-9]+$" | sort -V | tail -n 1 || true)"
  if [ -z "$resolved" ]; then
    resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  fi
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
  resolved="$(echo "$matches" | sed '/^$/d' | grep -E "^${major_minor//./\\.}\\.[0-9]+$" | sort -V | tail -n 1 || true)"
  if [ -z "$resolved" ]; then
    resolved="$(echo "$matches" | sed '/^$/d' | sort -V | tail -n 1)"
  fi
  cache_set mysql "$major_minor" "$resolved"
  echo "$resolved"
}

resolved_version="$VERSION"
IMAGE=""
case "$ENGINE" in
  mariadb)
    requested_base="$VERSION"
    if [[ "$VERSION" == *":latest" ]]; then
      requested_base="${VERSION%:latest}"
    fi

    legacy_repo=""
    if [[ "$requested_base" =~ ^5\.5($|[^0-9]) ]]; then
      legacy_repo="$MARIADB55_REPO"
    elif [[ "$requested_base" =~ ^10\.0($|[^0-9]) ]]; then
      legacy_repo="$MARIADB100_REPO"
    fi

    if [[ -n "$legacy_repo" ]]; then
      # Support explicit image reference with tag/digest for external legacy repos.
      if [[ "$VERSION" == *":latest" ]]; then
        resolved_version="$requested_base"
      fi
      if [[ "$legacy_repo" == *@* ]]; then
        IMAGE="$legacy_repo"
      elif [[ "$legacy_repo" =~ :[^/]+$ ]]; then
        IMAGE="$legacy_repo"
      else
        IMAGE="${legacy_repo}:${resolved_version}"
      fi
    else
      if [[ "$VERSION" == *":latest" ]]; then
        base="${VERSION%:latest}"
        resolved_version="$(resolve_mariadb_latest_tag "$base")" || {
          echo "Impossible de resoudre un tag MariaDB pour ${VERSION}" >&2
          exit 2
        }
      fi
      IMAGE="mariadb:${resolved_version}"
    fi
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
    docker_pull_locked "$IMAGE"
    PULL_STATUS="pulled(always)"
    ;;
  if-missing)
    if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
      docker_pull_locked "$IMAGE"
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
    -e MYSQL_ROOT_PASSWORD="$ROOT_PASS" \
    -e MARIADB_DATABASE="$DB_NAME" \
    -e MYSQL_DATABASE="$DB_NAME" \
    "$IMAGE" >/dev/null
else
  set +e
  docker run -d --name "$NAME" -p "$PORT:3306" \
    -e MYSQL_ROOT_PASSWORD="$ROOT_PASS" \
    -e MYSQL_DATABASE="$DB_NAME" \
    "$IMAGE" >/dev/null
  rc=$?
  set -e
  if [ "$rc" -ne 0 ] && [ "$ENGINE" = "percona/percona-server" ] && [[ "$VERSION" == "8.0:latest" ]] && [ -n "$PERCONA_8_0_FALLBACK_TAG" ] && [ "$PERCONA_8_0_FALLBACK_TAG" != "$resolved_version" ]; then
    fallback_image="percona/percona-server:${PERCONA_8_0_FALLBACK_TAG}"
    case "$DOCKER_PULL_POLICY" in
      always) docker_pull_locked "$fallback_image" >/dev/null 2>&1 || true ;;
      if-missing)
        docker image inspect "$fallback_image" >/dev/null 2>&1 || docker_pull_locked "$fallback_image" >/dev/null 2>&1 || true
        ;;
    esac
    docker rm -f "$NAME" >/dev/null 2>&1 || true
    docker run -d --name "$NAME" -p "$PORT:3306" \
      -e MYSQL_ROOT_PASSWORD="$ROOT_PASS" \
      -e MYSQL_DATABASE="$DB_NAME" \
      "$fallback_image" >/dev/null
    IMAGE="$fallback_image"
    resolved_version="$PERCONA_8_0_FALLBACK_TAG"
    PULL_STATUS="${PULL_STATUS}|fallback(${PERCONA_8_0_FALLBACK_TAG})"
  elif [ "$rc" -ne 0 ]; then
    exit "$rc"
  fi
fi

echo "$NAME|$IMAGE|$resolved_version|$PULL_STATUS"
