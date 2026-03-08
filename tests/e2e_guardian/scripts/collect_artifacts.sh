#!/usr/bin/env bash
set -euo pipefail

RUN_DIR="${1:?Usage: collect_artifacts.sh <run_dir>}"
OUT="${2:-${RUN_DIR}.tar.gz}"

tar -czf "$OUT" -C "$(dirname "$RUN_DIR")" "$(basename "$RUN_DIR")"
echo "$OUT"
