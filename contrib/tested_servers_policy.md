# Tested Servers Maintenance Policy

This repository tracks tested database versions with explicit minor versions.

## Rules
- Always keep explicit minor versions in `README.md` (`Serveurs Testés`) and `README_en.md` (`Tested Servers`).
- Never keep only `:latest` in the tested-servers list.
- After each successful E2E matrix run, add newly validated versions to both README files.
- Keep the list sorted by engine and version.
- Keep matrix sections aligned with actual scripts:
  - `tests/e2e_guardian/scripts/run_version_matrix.sh`
  - `tests/e2e_guardian/scripts/run_hardcore_matrix.sh`
- If a version is only validated through legacy local images, keep it under the legacy subsection.

## Required Update Workflow
1. Run the matrix campaign and collect resolved versions from artifacts (`matrix-summary.tsv` / `hardcore-summary.tsv`).
2. Update both README files with the new explicit minor versions.
3. Commit documentation update in the same branch as the matrix/test update.
4. Ensure FR and EN sections stay consistent.

## Scope
- Engines covered: MySQL, MariaDB, Percona Server.
- This policy is documentation governance only; it does not change runtime behavior.

## Reference list
- For the current explicit tested-version catalog (dev/AI readable), see:
  - `contrib/tested_servers_policy_dev_ai.md`
