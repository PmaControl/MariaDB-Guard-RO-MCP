#!/usr/bin/env bash
set -euo pipefail

ENGINE="${1:-mariadb}"
VERSION="${2:-11.8}"
PORT="${3:-33306}"
NAME="guardian-db-${ENGINE}-${VERSION//./-}"
ROOT_PASS="${ROOT_PASS:-root_guardian}"
DB_NAME="${DB_NAME:-sakila}"

IMAGE=""
case "$ENGINE" in
  mariadb) IMAGE="mariadb:${VERSION}" ;;
  mysql) IMAGE="mysql:${VERSION}" ;;
  *) echo "ENGINE invalide: $ENGINE"; exit 1 ;;
esac

docker rm -f "$NAME" >/dev/null 2>&1 || true
docker pull "$IMAGE"

if [ "$ENGINE" = "mariadb" ]; then
  docker run -d --name "$NAME" -p "$PORT:3306" \
    -e MARIADB_ROOT_PASSWORD="$ROOT_PASS" \
    -e MARIADB_DATABASE="$DB_NAME" \
    "$IMAGE"
else
  docker run -d --name "$NAME" -p "$PORT:3306" \
    -e MYSQL_ROOT_PASSWORD="$ROOT_PASS" \
    -e MYSQL_DATABASE="$DB_NAME" \
    "$IMAGE"
fi

echo "$NAME"
