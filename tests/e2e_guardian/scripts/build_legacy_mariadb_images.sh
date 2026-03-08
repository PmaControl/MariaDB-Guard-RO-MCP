#!/usr/bin/env bash
set -euo pipefail

MARIADB55_TARGET="${MARIADB55_TARGET:-local/mariadb-5-5:latest}"
MARIADB100_TARGET="${MARIADB100_TARGET:-local/mariadb-10-0:latest}"

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

require_cmd docker
require_cmd jq

pull_and_tag() {
  local src="$1"
  local target="$2"
  echo "[legacy-build] pull $src"
  docker pull "$src" >/dev/null
  echo "[legacy-build] tag  $src -> $target"
  docker tag "$src" "$target"
  local img_id
  img_id="$(docker image inspect "$target" --format '{{.Id}}')"
  echo "[legacy-build] ok   $target ($img_id)"
}

manifest_check() {
  local ref="$1"
  echo "[legacy-build] manifest inspect $ref"
  docker manifest inspect "$ref" | jq -r '.schemaVersion, (.mediaType // "-")'
}

pull_and_tag "mariadb:5.5" "$MARIADB55_TARGET"
pull_and_tag "mariadb:10.0" "$MARIADB100_TARGET"

manifest_check "mariadb:5.5"
manifest_check "mariadb:10.0"

echo
echo "Use these in tests:"
echo "  export MARIADB55_REPO='${MARIADB55_TARGET}'"
echo "  export MARIADB100_REPO='${MARIADB100_TARGET}'"
echo
echo "Then run:"
echo "  VERSION_MATRIX_TARGETS='mariadb|5.5:latest,mariadb|10.0:latest' ./tests/e2e_guardian/scripts/run_version_matrix.sh"
