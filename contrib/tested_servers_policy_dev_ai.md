# Tested Servers Policy (Dev & AI Guide)

This document explains how developers and AI agents must maintain the `Tested Servers` sections.

## Why this exists
- The project is production-safety oriented.
- Tested version claims must be traceable to real E2E runs.
- Explicit minor versions reduce ambiguity (`8.4.8` is auditable, `8.4:latest` is not).

## Who uses this guide
- Human developers preparing PRs.
- AI coding/testing agents generating updates.

## Sources of truth
- `tests/e2e_guardian/scripts/run_version_matrix.sh`
- `tests/e2e_guardian/scripts/run_hardcore_matrix.sh`
- Run artifacts:
  - `tests/e2e_guardian/runs/<run-id>/matrix-summary.tsv`
  - `tests/e2e_guardian/runs/<run-id>/hardcore-summary.tsv`

## Mandatory rules
- Update both files together:
  - `README.md` → `Serveurs Testés`
  - `README_en.md` → `Tested Servers`
- Use explicit minor versions only.
- Keep engine grouping:
  - MySQL
  - MariaDB
  - Percona Server
- Keep legacy-only validations in a dedicated legacy subsection.
- Do not add a version unless it is validated by a successful matrix run.

## Dev workflow
1. Run matrix test campaign.
2. Extract resolved versions from summary TSV artifacts.
3. Update FR/EN tested-server sections.
4. Add/update note explaining when the list was refreshed.
5. Commit doc changes in the same branch.

## AI agent workflow
1. Detect latest matrix artifacts.
2. Parse resolved versions (`resolved_version` column).
3. Filter out failed/unavailable rows.
4. Generate normalized sorted version list per engine.
5. Patch both README files consistently.
6. Keep wording unchanged outside the tested-server block unless requested.

## Validation checklist (before merge)
- [ ] FR and EN contain same versions.
- [ ] Versions are explicit minors (no `:latest` in tested list).
- [ ] All versions in docs are present in successful matrix artifacts.
- [ ] Legacy section is used only for legacy image paths.
- [ ] PR description includes artifact run-id references.

## Suggested PR note template
```
Docs update: Tested Servers refreshed from E2E matrix artifacts.
Run sources:
- matrix: tests/e2e_guardian/runs/<run-id>/matrix-summary.tsv
- hardcore: tests/e2e_guardian/runs/<run-id>/hardcore-summary.tsv
No runtime code change.
```

## Current explicit tested versions (from matrix artifacts)

MySQL:
- `5.5.62`
- `5.6.51`
- `5.7.44`
- `8.0.45`
- `8.1.0`
- `8.2.0`
- `8.3.0`
- `8.4.8`
- `9.1.0`
- `9.2.0`
- `9.3.0`
- `9.4.0`
- `9.5.0`
- `9.6.0`

MariaDB:
- `5.5.64`
- `10.0.38`
- `10.2.44`
- `10.3.39`
- `10.4.34`
- `10.5.29`
- `10.6.25`
- `10.7.8`
- `10.8.8`
- `10.9.8`
- `10.10.7`
- `10.11.16`
- `11.0.6`
- `11.1.6`
- `11.3.2`
- `11.4.10`
- `11.5.2`
- `11.6.2`
- `11.8.6`
- `12.0.2`
- `12.1.2`
- `12.2.2`
- `12.3.1`

Percona Server:
- `5.7.44`
- `8.0.43`
- `8.4.7`

Note:
- Suffix variants (`-ubi9`, `-oraclelinux9`, etc.) can appear in artifact `resolved_version`; the list above is normalized to numeric version for readability.
