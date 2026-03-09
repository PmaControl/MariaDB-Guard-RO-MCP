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
