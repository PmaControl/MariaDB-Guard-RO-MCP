#!/usr/bin/env bash
set -euo pipefail

ENGINE="${1:-mariadb}"
VERSION="${2:-11.8}"
PORT="${3:-33306}"
safe_version="$(echo "$VERSION" | sed 's/[^a-zA-Z0-9]/-/g')"
NAME="guardian-db-${ENGINE}-${safe_version}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
DB_NAME="${DB_NAME:-sakila}"

resolve_percona_latest_tag() {
  local major_minor="$1"
  local page=1
  local matches=""

  while :; do
    local json
    json="$(curl -fsSL "https://registry.hub.docker.com/v2/repositories/percona/percona-server/tags?page_size=100&page=${page}")" || return 1
    local names
    names="$(echo "$json" | jq -r '.results[].name')"

    while IFS= read -r tag; do
      [ -n "$tag" ] || continue
      if [[ "$tag" =~ (arm64|aarch64|amd64)$ ]]; then
        continue
      fi
      if [[ "$tag" =~ ^${major_minor//./\\.}\.[0-9]+([.-][0-9A-Za-z]+)*$ ]]; then
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

  echo "$matches" | sed '/^$/d' | sort -V | tail -n 1
}

resolve_mariadb_latest_tag() {
  local major_minor="$1"
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
      if [[ "$tag" =~ ^${major_minor//./\\.}\.[0-9]+([.-][0-9A-Za-z]+)*$ ]]; then
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

  echo "$matches" | sed '/^$/d' | sort -V | tail -n 1
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
    IMAGE="mysql:${resolved_version}"
    ;;
  percona)
    if [[ "$VERSION" == *":latest" ]]; then
      base="${VERSION%:latest}"
      resolved_version="$(resolve_percona_latest_tag "$base")" || {
        echo "Impossible de resoudre un tag Percona pour ${VERSION}" >&2
        exit 2
      }
    fi
    IMAGE="percona/percona-server:${resolved_version}"
    ;;
  *)
    echo "ENGINE invalide: $ENGINE (attendu: mariadb|mysql|percona)" >&2
    exit 1
    ;;
esac

docker rm -f "$NAME" >/dev/null 2>&1 || true
docker pull "$IMAGE" >/dev/null

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

echo "$NAME|$IMAGE|$resolved_version"
