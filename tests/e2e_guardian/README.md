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
- `scripts/provision_db.sh` : démarre DB docker par moteur/version (`mariadb|mysql|percona`), support `:latest` pour branches mineures (ex: `10.3:latest`, `8.0:latest`)
- `scripts/cleanup.sh` : supprime containers de test
- `scripts/collect_artifacts.sh` : archive artefacts de run
- `scripts/test_concurrency_guard.sh` : stress test concurrence (`db_select`) avec plafond d'acceptation
- `scripts/run_version_matrix.sh` : exécute la matrice versions demandée (MySQL/MariaDB/Percona) avec validation MCP de `SELECT VERSION()`

## Matrice versions spécifiques
Lancer la campagne multi-moteurs/multi-versions:
```bash
./tests/e2e_guardian/scripts/run_version_matrix.sh
```
Versions incluses:
- MySQL: `5.5.62`, `5.6.49`, `5.7.4`, `8.0.44`, `8.4.7`, `9.6.0`
- MariaDB: `10.5.29`, `10.6.23`, `10.11.14`, `11.4.8`, `11.8.6`, `12.0.2`, `10.0:latest`, `10.1:latest`, `10.2:latest`, `10.3:latest`
- Percona Server: `5.7.44`, `8.0:latest`, `8.4:latest`, `9.6:latest`

Pour les tags `:latest`, le script résout automatiquement le patch le plus récent disponible sur Docker Hub.
Sortie:
- résumé TSV: `tests/e2e_guardian/runs/<run-id>/matrix-summary.tsv`
- détails JSON/JUnit par version dans le même dossier

## Test de concurrence (gardien)
Cas prêt à l'emploi: `GUARD-120`.
```bash
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-120
```
Validation attendue:
- 10 requêtes simultanées `SELECT SLEEP(10)`
- maximum 3 requêtes acceptées
- les autres refusées avec `database busy retry in 1 second`

## Ajouter un test
1. Créer un fichier `.test` dans `cases/`.
2. Définir les champs requis (`TEST_ID`, `DB_ENGINE`, `SSL_MODE`, etc.).
3. Mettre une commande Bash rejouable dans `COMMAND`.
4. Exécuter avec `--explain --dry-run` puis en réel.
