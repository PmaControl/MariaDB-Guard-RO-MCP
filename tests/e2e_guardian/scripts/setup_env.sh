#!/usr/bin/env bash
set -euo pipefail

REQUIRED_CMDS=(bash curl jq awk sed grep find timeout docker skopeo mysql mysqladmin php)
MISSING_CMDS=()
for c in "${REQUIRED_CMDS[@]}"; do
  command -v "$c" >/dev/null 2>&1 || MISSING_CMDS+=("$c")
done

if [ "${#MISSING_CMDS[@]}" -gt 0 ]; then
  echo "Commandes manquantes: ${MISSING_CMDS[*]}"
  echo "Installez-les avant exécution."
  exit 1
fi

echo "[OK] Outils de base présents"
