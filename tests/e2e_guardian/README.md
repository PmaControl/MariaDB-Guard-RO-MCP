# Guardian E2E Framework (Bash only)

Framework de tests E2E + intégration pour le gardien MCP, 100% Bash.

## Modes
- `--unit` : un seul test via `--id`
- `--block` : suite fonctionnelle via `--block`
- `--full` : campagne standard (matrice réduite)
- `--hardcore` : matrice exhaustive + cas extrêmes

## Exécution rapide
```bash
# setup outil local
./tests/e2e_guardian/scripts/setup_env.sh

# un test
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-001

# un bloc
./tests/e2e_guardian/bin/run.sh --block --block sql-guards

# campagne standard
./tests/e2e_guardian/bin/run.sh --full

# hardcore + filtres
./tests/e2e_guardian/bin/run.sh --hardcore --db mariadb --ssl-mode files --tag ssl
```

## Filtres
- `--db mysql|mariadb`
- `--version x.y.z`
- `--ssl-mode off|files|server`
- `--tag <tag>`
- `--guardian <type>`
- `--stack <name>`

## Debug humain
Chaque test produit un dossier artefacts:
- `stdout.log`
- `stderr.log`
- `env.snapshot`
- `resolved.config`
- `timeline.log`
- `exit.code`
- `command.sh`
- `replay.sh`
- `diagnosis.txt` (si échec)

Artefacts de run:
- `tests/e2e_guardian/runs/<run-id>/summary.json`
- `tests/e2e_guardian/runs/<run-id>/junit.xml`

## Replay
Relancer un test isolé:
```bash
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-020
# ou par ID instance matrix en hardcore:
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-900__mariadb_11.8__ssl_files
```

## Outils utilitaires
- `scripts/generate_certs.sh` : génère CA/server/client certs de test
- `scripts/provision_db.sh` : démarre DB docker par moteur/version
- `scripts/cleanup.sh` : supprime containers de test
- `scripts/collect_artifacts.sh` : archive artefacts de run

## Ajouter un test
1. Créer un fichier `.test` dans `cases/`.
2. Définir les champs requis (`TEST_ID`, `DB_ENGINE`, `SSL_MODE`, etc.).
3. Mettre une commande Bash rejouable dans `COMMAND`.
4. Exécuter avec `--explain --dry-run` puis en réel.
