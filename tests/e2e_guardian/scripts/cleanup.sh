#!/usr/bin/env bash
set -euo pipefail

PREFIX="${1:-guardian-db-}"

docker ps -a --format '{{.Names}}' | grep "^${PREFIX}" | while read -r n; do
  docker rm -f "$n" >/dev/null 2>&1 || true
  echo "Removed $n"
done
