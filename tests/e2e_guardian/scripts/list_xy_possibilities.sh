#!/usr/bin/env bash
set -euo pipefail

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing required command: $1" >&2
    exit 1
  }
}

require_cmd skopeo
require_cmd jq
require_cmd awk
require_cmd sort

declare -a SOURCES=(
  "mariadb|docker://docker.io/library/mariadb"
  "mysql|docker://docker.io/library/mysql"
  "percona|docker://docker.io/percona"
  "percona/percona-server|docker://docker.io/percona/percona-server"
)

for src in "${SOURCES[@]}"; do
  IFS='|' read -r name image <<<"$src"
  echo "=== ${name} (${image}) ==="

  tags="$(skopeo inspect "$image" | jq -r '.RepoTags[]' | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' || true)"
  if [ -z "$tags" ]; then
    echo "No strict semver tags found."
    echo
    continue
  fi

  echo "X.Y branches:"
  echo "$tags" | awk -F. '{print $1"."$2}' | sort -V -u | sed 's/^/- /'

  echo "Latest patch per X.Y:"
  echo "$tags" \
    | awk -F. '{print $1"."$2, $0}' \
    | sort -k1,1 -k2,2V \
    | awk '{latest[$1]=$2} END{for (k in latest) print k, latest[k]}' \
    | sort -V \
    | awk '{print "- "$1" => "$2}'

  echo
done
