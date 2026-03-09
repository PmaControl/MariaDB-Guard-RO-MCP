#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"
# shellcheck source=../lib/test_parser.sh
source "$ROOT_DIR/lib/test_parser.sh"

MODE=""
SELECT_ID=""
SELECT_BLOCK=""
FILTER_DB=""
FILTER_VERSION=""
FILTER_SSL_MODE=""
FILTER_TAG=""
FILTER_GUARDIAN=""
FILTER_STACK=""
DRY_RUN=false
EXPLAIN=false
REPLAY_ID=""
LOG_LEVEL="INFO"
RUNS_DIR="$ROOT_DIR/runs"
RUN_ID="run-$(date +%Y%m%d-%H%M%S)-$$"
LATEST_SYMLINK="$RUNS_DIR/latest"
OUTPUT_JSON=""
OUTPUT_JUNIT=""

usage() {
  cat <<USAGE
Usage: $0 [mode] [options]

Modes (obligatoire):
  --unit            Exécuter un seul test (nécessite --id)
  --block           Exécuter une suite fonctionnelle (nécessite --block)
  --full            Campagne standard (matrice réduite)
  --hardcore        Campagne exhaustive (matrice complète + cas extrêmes)

Options:
  --id <test-id>                Sélectionner un test unique
  --block <block>               Sélectionner un bloc
  --db <mysql|mariadb>          Filtre moteur DB
  --version <x.y.z>             Filtre version DB
  --ssl-mode <off|files|server> Filtre mode SSL
  --tag <tag>                   Filtre tag
  --guardian <type>             Filtre type gardien
  --stack <name>                Filtre stack
  --dry-run                     N'exécute pas, affiche la résolution
  --explain                     Affiche la config résolue de chaque test
  --replay <test-instance-id>   Relance un test isolé via son ID instance
  --log-level <INFO|DEBUG|TRACE>
  --runs-dir <path>             Répertoire des artefacts (défaut: tests/e2e_guardian/runs)
  --output-json <file>          Export JSON du résumé
  --output-junit <file>         Export JUnit XML
  -h, --help                    Aide
USAGE
}

while [ $# -gt 0 ]; do
  case "$1" in
    --unit|--full|--hardcore)
      MODE="${1#--}"; shift ;;
    --block)
      if [ -z "$MODE" ]; then
        MODE="block"
        shift
      else
        SELECT_BLOCK="${2:-}"; shift 2
      fi
      ;;
    --id)
      SELECT_ID="${2:-}"; shift 2 ;;
    --db)
      FILTER_DB="${2:-}"; shift 2 ;;
    --version)
      FILTER_VERSION="${2:-}"; shift 2 ;;
    --ssl-mode)
      FILTER_SSL_MODE="${2:-}"; shift 2 ;;
    --tag)
      FILTER_TAG="${2:-}"; shift 2 ;;
    --guardian)
      FILTER_GUARDIAN="${2:-}"; shift 2 ;;
    --stack)
      FILTER_STACK="${2:-}"; shift 2 ;;
    --dry-run)
      DRY_RUN=true; shift ;;
    --explain)
      EXPLAIN=true; shift ;;
    --replay)
      REPLAY_ID="${2:-}"; shift 2 ;;
    --log-level)
      LOG_LEVEL="${2:-}"; shift 2 ;;
    --runs-dir)
      RUNS_DIR="${2:-}"; shift 2 ;;
    --output-json)
      OUTPUT_JSON="${2:-}"; shift 2 ;;
    --output-junit)
      OUTPUT_JUNIT="${2:-}"; shift 2 ;;
    -h|--help)
      usage; exit 0 ;;
    *)
      die "Option inconnue: $1" ;;
  esac
done

[ -n "$MODE" ] || die "Mode requis (--unit|--block|--full|--hardcore)"
if [ "$MODE" = "unit" ] && [ -z "$SELECT_ID" ]; then
  die "--unit nécessite --id"
fi
if [ "$MODE" = "block" ] && [ -z "$SELECT_BLOCK" ]; then
  die "--block nécessite --block <name>"
fi

mkdir -p "$RUNS_DIR/$RUN_ID"
ln -sfn "$RUNS_DIR/$RUN_ID" "$LATEST_SYMLINK"

SUMMARY_JSON="$RUNS_DIR/$RUN_ID/summary.json"
SUMMARY_JUNIT="$RUNS_DIR/$RUN_ID/junit.xml"
: > "$RUNS_DIR/$RUN_ID/timeline.log"

write_timeline "$RUNS_DIR/$RUN_ID/timeline.log" "run.start mode=$MODE run_id=$RUN_ID"
log INFO "Run ID: $RUN_ID"
log INFO "Mode: $MODE"

TEST_FILES=()
while IFS= read -r f; do
  TEST_FILES+=("$f")
done < <(find "$ROOT_DIR/cases" -type f -name '*.test' | sort)

if [ "${#TEST_FILES[@]}" -eq 0 ]; then
  die "Aucun fichier .test trouvé dans $ROOT_DIR/cases"
fi

SELECTED=()

select_test_instance() {
  local test_file="$1"
  local instance_id="$2"
  local db_version="$3"
  local ssl_mode="$4"

  parse_test_file "$test_file"

  [ "$ENABLED" = "true" ] || return 0

  local effective_tags="$TAGS"

  if [ -n "$SELECT_ID" ] && [ "$TEST_ID" != "$SELECT_ID" ] && [ "$instance_id" != "$SELECT_ID" ]; then
    return 0
  fi

  if [ "$MODE" = "block" ] && [ "$BLOCK" != "$SELECT_BLOCK" ]; then
    return 0
  fi

  if [ -n "$REPLAY_ID" ] && [ "$instance_id" != "$REPLAY_ID" ] && [ "$TEST_ID" != "$REPLAY_ID" ]; then
    return 0
  fi

  if [ -n "$FILTER_DB" ] && [ "$DB_ENGINE" != "$FILTER_DB" ]; then
    return 0
  fi

  if [ -n "$FILTER_VERSION" ] && [ "$db_version" != "$FILTER_VERSION" ]; then
    return 0
  fi

  if [ -n "$FILTER_SSL_MODE" ] && [ "$ssl_mode" != "$FILTER_SSL_MODE" ]; then
    return 0
  fi

  if [ -n "$FILTER_TAG" ] && ! contains_csv_value "$effective_tags" "$FILTER_TAG"; then
    return 0
  fi

  if [ -n "$FILTER_GUARDIAN" ] && [ "$GUARDIAN" != "$FILTER_GUARDIAN" ]; then
    return 0
  fi

  if [ -n "$FILTER_STACK" ] && [ "$STACK" != "$FILTER_STACK" ]; then
    return 0
  fi

  if [ "$MODE" = "full" ] && contains_csv_value "$effective_tags" "hardcore"; then
    return 0
  fi

  SELECTED+=("$test_file|$instance_id|$db_version|$ssl_mode")
}

for test_file in "${TEST_FILES[@]}"; do
  parse_test_file "$test_file"

  if [ "$MODE" = "hardcore" ] && [ "$MATRIX_EXPAND" = "true" ]; then
    IFS=',' read -r -a versions <<<"$DB_VERSION_LIST"
    IFS=',' read -r -a ssl_modes <<<"$SSL_MODE_LIST"
    for v in "${versions[@]}"; do
      v="${v// /}"
      for s in "${ssl_modes[@]}"; do
        s="${s// /}"
        inst="${TEST_ID}__${DB_ENGINE}_${v}__ssl_${s}"
        select_test_instance "$test_file" "$inst" "$v" "$s"
      done
    done
  elif [ "$MODE" = "full" ] && [ "$MATRIX_EXPAND" = "true" ]; then
    IFS=',' read -r -a versions <<<"$DB_VERSION_LIST"
    IFS=',' read -r -a ssl_modes <<<"$SSL_MODE_LIST"
    v="${versions[0]// /}"
    s="${ssl_modes[0]// /}"
    inst="${TEST_ID}__${DB_ENGINE}_${v}__ssl_${s}"
    select_test_instance "$test_file" "$inst" "$v" "$s"
  else
    inst="$TEST_ID"
    select_test_instance "$test_file" "$inst" "$DB_VERSION" "$SSL_MODE"
  fi
done

if [ "${#SELECTED[@]}" -eq 0 ]; then
  die "Aucun test sélectionné après filtres"
fi

log INFO "Tests sélectionnés: ${#SELECTED[@]}"

PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
ERROR_COUNT=0

RESULT_LINES=()

run_one() {
  local test_file="$1"
  local test_instance_id="$2"
  local db_version_override="$3"
  local ssl_mode_override="$4"

  parse_test_file "$test_file"

  DB_VERSION="$db_version_override"
  SSL_MODE="$ssl_mode_override"

  local artifact_dir
  artifact_dir="$(mk_artifact_dir "$RUNS_DIR" "$RUN_ID" "$test_instance_id")"
  local stdout_file="$artifact_dir/stdout.log"
  local stderr_file="$artifact_dir/stderr.log"
  local timeline_file="$artifact_dir/timeline.log"
  local resolved_file="$artifact_dir/resolved.config"
  local env_file="$artifact_dir/env.snapshot"
  local exit_file="$artifact_dir/exit.code"
  local command_file="$artifact_dir/command.sh"
  local replay_file="$artifact_dir/replay.sh"
  local diagnosis_file="$artifact_dir/diagnosis.txt"

  env | sort > "$env_file"
  render_resolved_config > "$resolved_file"

  write_timeline "$timeline_file" "test.start id=$test_instance_id"

  local command="$COMMAND"
  command="${command//\{\{MCP_ENDPOINT\}\}/$MCP_ENDPOINT}"
  command="${command//\{\{MCP_TOKEN\}\}/$MCP_TOKEN}"
  command="${command//\{\{DB_ENGINE\}\}/$DB_ENGINE}"
  command="${command//\{\{DB_VERSION\}\}/$DB_VERSION}"
  command="${command//\{\{SSL_MODE\}\}/$SSL_MODE}"
  command="${command//\{\{TEST_ID\}\}/$TEST_ID}"
  command="${command//\{\{PR_ID\}\}/$PR_ID}"
  command="${command//\{\{MCP_ID\}\}/$MCP_ID}"

  printf '#!/usr/bin/env bash\nset -euo pipefail\n%s\n' "$command" > "$command_file"
  chmod +x "$command_file"

  cat > "$replay_file" <<REPLAY
#!/usr/bin/env bash
set -euo pipefail
"$SCRIPT_DIR/run.sh" --unit --id "$test_instance_id" --log-level TRACE --explain
REPLAY
  chmod +x "$replay_file"

  if [ "$EXPLAIN" = true ]; then
    log INFO "[$test_instance_id] configuration résolue"
    cat "$resolved_file"
    log INFO "[$test_instance_id] commande: $command"
  fi

  if [ "$DRY_RUN" = true ]; then
    echo 0 > "$exit_file"
    write_timeline "$timeline_file" "test.skip dry-run"
    RESULT_LINES+=("$test_instance_id|$TEST_NAME|SKIP|0|$artifact_dir")
    SKIP_COUNT=$((SKIP_COUNT + 1))
    return 0
  fi

  local attempts=0
  local max_attempts=$((RETRIES + 1))
  local status="FAIL"
  local start_ts end_ts duration_ms

  while [ "$attempts" -lt "$max_attempts" ]; do
    attempts=$((attempts + 1))
    write_timeline "$timeline_file" "attempt.start n=$attempts"
    start_ts="$(date +%s%3N)"

    set +e
    if [ -n "$PRE_HOOK" ]; then
      MCP_ENDPOINT="$MCP_ENDPOINT" \
      MCP_TOKEN="$MCP_TOKEN" \
      DB_ENGINE="$DB_ENGINE" \
      DB_VERSION="$DB_VERSION" \
      SSL_MODE="$SSL_MODE" \
      TEST_ID="$TEST_ID" \
      PR_ID="$PR_ID" \
      MCP_ID="$MCP_ID" \
      bash -lc "$PRE_HOOK" >>"$stdout_file" 2>>"$stderr_file"
    fi

    MCP_ENDPOINT="$MCP_ENDPOINT" \
    MCP_TOKEN="$MCP_TOKEN" \
    DB_ENGINE="$DB_ENGINE" \
    DB_VERSION="$DB_VERSION" \
    SSL_MODE="$SSL_MODE" \
    TEST_ID="$TEST_ID" \
    PR_ID="$PR_ID" \
    MCP_ID="$MCP_ID" \
    timeout "$TIMEOUT_S" bash -lc "$command" >>"$stdout_file" 2>>"$stderr_file"
    rc=$?

    if [ -n "$POST_HOOK" ]; then
      MCP_ENDPOINT="$MCP_ENDPOINT" \
      MCP_TOKEN="$MCP_TOKEN" \
      DB_ENGINE="$DB_ENGINE" \
      DB_VERSION="$DB_VERSION" \
      SSL_MODE="$SSL_MODE" \
      TEST_ID="$TEST_ID" \
      PR_ID="$PR_ID" \
      MCP_ID="$MCP_ID" \
      bash -lc "$POST_HOOK" >>"$stdout_file" 2>>"$stderr_file"
    fi
    set -e

    end_ts="$(date +%s%3N)"
    duration_ms=$((end_ts - start_ts))

    if [ "$rc" -eq 0 ]; then
      if [ -n "$EXPECT_JSON_PATH" ]; then
        local got expected
        got="$(tail -n 1 "$stdout_file" | jq -r "$EXPECT_JSON_PATH" 2>/dev/null || true)"
        expected="$EXPECT_EQUALS"
        if [ "$got" != "$expected" ]; then
          rc=90
          printf 'Expectation mismatch: path=%s expected=%s got=%s\n' "$EXPECT_JSON_PATH" "$expected" "$got" >>"$stderr_file"
        fi
      fi
    fi

    if [ "$rc" -eq 0 ]; then
      status="PASS"
      echo 0 > "$exit_file"
      write_timeline "$timeline_file" "attempt.pass n=$attempts duration_ms=$duration_ms"
      break
    fi

    echo "$rc" > "$exit_file"
    write_timeline "$timeline_file" "attempt.fail n=$attempts rc=$rc duration_ms=$duration_ms"

    if [ "$attempts" -ge "$max_attempts" ]; then
      if [ "$rc" -eq 124 ]; then
        status="ERROR"
      else
        status="FAIL"
      fi
      break
    fi
  done

  if [ "$status" = "PASS" ]; then
    PASS_COUNT=$((PASS_COUNT + 1))
  elif [ "$status" = "FAIL" ]; then
    FAIL_COUNT=$((FAIL_COUNT + 1))
  else
    ERROR_COUNT=$((ERROR_COUNT + 1))
  fi

  if [ "$status" != "PASS" ]; then
    render_diagnostic "$stderr_file" > "$diagnosis_file"
    log ERROR "[$test_instance_id] $status"
    cat "$diagnosis_file"
  else
    log INFO "[$test_instance_id] PASS"
  fi

  RESULT_LINES+=("$test_instance_id|$TEST_NAME|$status|$duration_ms|$artifact_dir")
}

for row in "${SELECTED[@]}"; do
  IFS='|' read -r tf tid dbv sslm <<<"$row"
  run_one "$tf" "$tid" "$dbv" "$sslm"
done

TOTAL=$((PASS_COUNT + FAIL_COUNT + SKIP_COUNT + ERROR_COUNT))

{
  printf '{\n'
  printf '  "runId": "%s",\n' "$RUN_ID"
  printf '  "mode": "%s",\n' "$MODE"
  printf '  "summary": {"total": %d, "pass": %d, "fail": %d, "skip": %d, "error": %d},\n' "$TOTAL" "$PASS_COUNT" "$FAIL_COUNT" "$SKIP_COUNT" "$ERROR_COUNT"
  printf '  "results": [\n'
  idx=0
  for line in "${RESULT_LINES[@]}"; do
    IFS='|' read -r rid rname rstatus rdur rart <<<"$line"
    idx=$((idx + 1))
    printf '    {"id":"%s","name":%s,"status":"%s","durationMs":%s,"artifacts":%s}' \
      "$rid" "$(printf '%s' "$rname" | jq -R .)" "$rstatus" "$rdur" "$(printf '%s' "$rart" | jq -R .)"
    if [ "$idx" -lt "${#RESULT_LINES[@]}" ]; then
      printf ','
    fi
    printf '\n'
  done
  printf '  ]\n'
  printf '}\n'
} > "$SUMMARY_JSON"

{
  printf '<?xml version="1.0" encoding="UTF-8"?>\n'
  printf '<testsuite name="guardian-e2e" tests="%d" failures="%d" errors="%d" skipped="%d">\n' "$TOTAL" "$FAIL_COUNT" "$ERROR_COUNT" "$SKIP_COUNT"
  for line in "${RESULT_LINES[@]}"; do
    IFS='|' read -r rid rname rstatus rdur rart <<<"$line"
    local_time="$(awk "BEGIN{printf \"%.3f\", $rdur/1000}")"
    printf '  <testcase classname="guardian" name="%s" time="%s">\n' "$rid" "$local_time"
    if [ "$rstatus" = "FAIL" ]; then
      printf '    <failure message="failed">Artifacts: %s</failure>\n' "$rart"
    elif [ "$rstatus" = "ERROR" ]; then
      printf '    <error message="error">Artifacts: %s</error>\n' "$rart"
    elif [ "$rstatus" = "SKIP" ]; then
      printf '    <skipped/>\n'
    fi
    printf '  </testcase>\n'
  done
  printf '</testsuite>\n'
} > "$SUMMARY_JUNIT"

if [ -n "$OUTPUT_JSON" ]; then
  cp "$SUMMARY_JSON" "$OUTPUT_JSON"
fi
if [ -n "$OUTPUT_JUNIT" ]; then
  cp "$SUMMARY_JUNIT" "$OUTPUT_JUNIT"
fi

log INFO "Résumé: total=$TOTAL pass=$PASS_COUNT fail=$FAIL_COUNT skip=$SKIP_COUNT error=$ERROR_COUNT"
log INFO "JSON: $SUMMARY_JSON"
log INFO "JUnit: $SUMMARY_JUNIT"

write_timeline "$RUNS_DIR/$RUN_ID/timeline.log" "run.end total=$TOTAL pass=$PASS_COUNT fail=$FAIL_COUNT skip=$SKIP_COUNT error=$ERROR_COUNT"

if [ "$FAIL_COUNT" -gt 0 ] || [ "$ERROR_COUNT" -gt 0 ]; then
  exit 1
fi
