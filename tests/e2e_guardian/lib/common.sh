#!/usr/bin/env bash
set -euo pipefail

LOG_LEVEL="${LOG_LEVEL:-INFO}"

now_iso() {
  date -u +"%Y-%m-%dT%H:%M:%SZ"
}

log_level_num() {
  case "$1" in
    TRACE) echo 10 ;;
    DEBUG) echo 20 ;;
    INFO)  echo 30 ;;
    WARN)  echo 40 ;;
    ERROR) echo 50 ;;
    *)     echo 30 ;;
  esac
}

log() {
  local level="$1"; shift
  local msg="$*"
  local current wanted
  current="$(log_level_num "$LOG_LEVEL")"
  wanted="$(log_level_num "$level")"
  if [ "$wanted" -lt "$current" ]; then
    return 0
  fi
  printf '[%s] [%s] %s\n' "$(now_iso)" "$level" "$msg"
}

die() {
  log ERROR "$*"
  exit 1
}

require_cmd() {
  local cmd="$1"
  command -v "$cmd" >/dev/null 2>&1 || die "Commande requise introuvable: $cmd"
}

join_by() {
  local sep="$1"; shift
  local out="${1:-}"
  shift || true
  for item in "$@"; do
    out+="$sep$item"
  done
  printf '%s' "$out"
}

contains_csv_value() {
  local csv="$1"
  local value="$2"
  IFS=',' read -r -a arr <<<"$csv"
  for v in "${arr[@]}"; do
    if [ "${v// /}" = "$value" ]; then
      return 0
    fi
  done
  return 1
}

mk_artifact_dir() {
  local base="$1"
  local run_id="$2"
  local test_id="$3"
  local dir="$base/$run_id/$test_id"
  mkdir -p "$dir"
  printf '%s' "$dir"
}

write_timeline() {
  local timeline_file="$1"
  shift
  printf '%s %s\n' "$(now_iso)" "$*" >>"$timeline_file"
}

render_diagnostic() {
  local stderr_file="$1"
  local msg=""

  if grep -qi 'Connection refused' "$stderr_file" 2>/dev/null; then
    msg='Cause probable: service DB/MCP non joignable (connection refused). Vérifier host/port/firewall.'
  elif grep -qi 'Access denied' "$stderr_file" 2>/dev/null; then
    msg='Cause probable: credentials DB invalides ou grants insuffisants.'
  elif grep -qi 'SSL' "$stderr_file" 2>/dev/null; then
    msg='Cause probable: configuration SSL invalide (CA/cert/key/hostname). Vérifier chemins et mode de vérification.'
  elif grep -qi 'timeout' "$stderr_file" 2>/dev/null; then
    msg='Cause probable: timeout atteint. Vérifier performance, réseau ou limites de garde.'
  else
    msg='Cause probable: échec non classifié. Consulter stderr.log et timeline.log.'
  fi

  printf '%s\n' "$msg"
}
