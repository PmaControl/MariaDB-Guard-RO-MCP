#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

STATE_DIR="${ROOT_DIR}/state"
STATE_FILE="${STATE_DIR}/known_repo_tags.tsv"
RUN_ID="new-versions-$(date +%Y%m%d-%H%M%S)-$$"
RUN_DIR="${ROOT_DIR}/runs/${RUN_ID}"
SUMMARY_FILE="${RUN_DIR}/new-versions-summary.tsv"

mkdir -p "$STATE_DIR" "$RUN_DIR"
touch "$STATE_FILE"

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing required command: $1" >&2
    exit 1
  }
}

require_cmd skopeo
require_cmd jq
require_cmd awk
require_cmd grep

declare -a SOURCES=(
  "mysql|docker://docker.io/library/mysql"
  "mariadb|docker://docker.io/library/mariadb"
  "percona|docker://docker.io/percona"
  "percona/percona-server|docker://docker.io/percona/percona-server"
)

declare -a NEW_TARGETS=()

echo -e "engine\ttag\tstatus\tnote" > "$SUMMARY_FILE"

for src in "${SOURCES[@]}"; do
  IFS='|' read -r engine image <<<"$src"
  echo "[discover] ${engine} -> ${image}"

  tags_json="$(skopeo inspect "$image" | jq -r '.RepoTags[]')"
  while IFS= read -r tag; do
    [ -n "$tag" ] || continue
    if [[ ! "$tag" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      continue
    fi

    key="${engine}|${tag}"
    if grep -Fxq "$key" "$STATE_FILE"; then
      continue
    fi

    echo "$key" >> "$STATE_FILE"
    NEW_TARGETS+=("$key")
  done <<<"$tags_json"
done

if [ "${#NEW_TARGETS[@]}" -eq 0 ]; then
  echo "No new versions detected."
  echo "Summary: $SUMMARY_FILE"
  exit 0
fi

echo "New versions detected: ${#NEW_TARGETS[@]}"

for target in "${NEW_TARGETS[@]}"; do
  IFS='|' read -r engine tag <<<"$target"
  echo "[test] ${engine}|${tag}"

  set +e
  HARDCORE_TARGETS="${engine}|${tag}" "${SCRIPT_DIR}/run_hardcore_matrix.sh" >/dev/null 2>&1
  rc=$?
  set -e

  if [ "$rc" -eq 0 ]; then
    echo -e "${engine}\t${tag}\tsuccess\tfull guardian suite executed" >> "$SUMMARY_FILE"
  else
    echo -e "${engine}\t${tag}\tfailed\tsee hardcore run artifacts" >> "$SUMMARY_FILE"
  fi
done

echo "Done. Summary: $SUMMARY_FILE"
column -t -s $'\t' "$SUMMARY_FILE" || cat "$SUMMARY_FILE"
