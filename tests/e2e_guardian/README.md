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

Prérequis CLI vérifiés par `setup_env.sh`:
- `bash`, `curl`, `jq`, `awk`, `sed`, `grep`, `find`, `timeout`
- `docker`, `skopeo`
- `mysql`, `mysqladmin`
- `php`

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
- `scripts/run_version_matrix.sh` : exécute la matrice versions demandée (MySQL/MariaDB/Percona) avec 100% des tests `GUARD-*` détectés automatiquement dans `cases/**`
- `scripts/list_xy_possibilities.sh` : affiche toutes les branches `X.Y` et le dernier patch `X.Y.Z` par dépôt Docker

## Matrice versions spécifiques
Lancer la campagne multi-moteurs/multi-versions:
```bash
./tests/e2e_guardian/scripts/run_version_matrix.sh
```
Versions incluses:
- Découverte automatique de toutes les branches `X.Y` disponibles sur Docker Hub pour:
  - MySQL (`library/mysql`)
  - MariaDB (`library/mariadb`)
  - Percona (`percona`)
  - Percona Server (`percona/percona-server`)
- Exécution de chaque cible en mode `X.Y:latest`.

Pour les tags `:latest`, le script résout automatiquement le patch le plus récent disponible sur Docker Hub.
Optimisation:
- les images Docker ne sont plus re-téléchargées par défaut (`DOCKER_PULL_POLICY=if-missing`)
- cache local des résolutions `:latest` dans `/tmp/mcp_e2e_tag_cache.tsv` (TTL configurable via `DB_TAG_CACHE_TTL_S`)
- cache local des branches `X.Y` découvertes dans `/tmp/mcp_e2e_minor_versions_cache.tsv` (TTL configurable via `DISCOVERY_CACHE_TTL_S`)
- exécution des tests GUARD en parallèle (par cible DB) avec `TEST_PARALLELISM` (défaut: `nproc * 2`)
- téléchargement des images Docker sérialisé globalement via lock (`DOCKER_PULL_LOCK_FILE`, défaut `/tmp/mcp_e2e_docker_pull.lock`)
- support de dépôts Docker externes dédiés pour MariaDB legacy:
  - `MARIADB55_REPO` (ex: `docker.io/pmacontrol/mariadb-5-5`)
  - `MARIADB100_REPO` (ex: `docker.io/pmacontrol/mariadb-10-0`)
Sortie:
- résumé TSV: `tests/e2e_guardian/runs/<run-id>/matrix-summary.tsv`
- détails JSON/JUnit par version dans le même dossier
- SSL stack testée pour tous les serveurs via `GUARD-900` en mode `DB_SSL=true` (cipher SSL requis non vide)
- affichage progressif en console, ligne par ligne (`[provision]` puis `[result]`)
- détail image Docker utilisée et statut de récupération (`pull_status`: cache local ou téléchargement)

Exemples:
```bash
# toutes les branches X.Y disponibles (défaut)
./tests/e2e_guardian/scripts/run_version_matrix.sh

# sans découverte auto, fallback statique
DISCOVER_ALL_LATEST=0 ./tests/e2e_guardian/scripts/run_version_matrix.sh

# forcer une liste de tests précise
VERSION_MATRIX_TEST_IDS='GUARD-001,GUARD-020,GUARD-900' ./tests/e2e_guardian/scripts/run_version_matrix.sh

# forcer une liste de cibles précise
VERSION_MATRIX_TARGETS='mysql|5.7:latest,mariadb|10.11:latest' ./tests/e2e_guardian/scripts/run_version_matrix.sh

# forcer le parallélisme des tests GUARD
TEST_PARALLELISM=16 ./tests/e2e_guardian/scripts/run_version_matrix.sh

# utiliser des repos externes dédiés pour MariaDB 5.5 et 10.0
MARIADB55_REPO='docker.io/pmacontrol/mariadb-5-5' \
MARIADB100_REPO='docker.io/pmacontrol/mariadb-10-0' \
VERSION_MATRIX_TARGETS='mariadb|5.5:latest,mariadb|10.0:latest' \
./tests/e2e_guardian/scripts/run_version_matrix.sh
```

## Full Matrix Hardcore (versions ciblées)
Déclinaison de tous les tests gardien sur:
- `mysql 5.6:latest`
- `mariadb 10.11:latest`
- `mariadb 11.4:latest`
- `mysql 5.7:latest`
- `mysql 8.4:latest`

Commande:
```bash
./tests/e2e_guardian/scripts/run_hardcore_matrix.sh
```

Options utiles:
```bash
# ne jamais pull (utilise uniquement les images locales)
DOCKER_PULL_POLICY=never ./tests/e2e_guardian/scripts/run_hardcore_matrix.sh

# forcer un refresh complet des images
DOCKER_PULL_POLICY=always ./tests/e2e_guardian/scripts/run_hardcore_matrix.sh
```

Sortie:
- résumé TSV: `tests/e2e_guardian/runs/<run-id>/hardcore-summary.tsv`
- détails JSON/JUnit par couple `version + test`
- affichage progressif en console avec statut de chaque test

## Détection auto de nouvelles versions (skopeo)
Script:
```bash
./tests/e2e_guardian/scripts/discover_and_test_new_versions.sh
```

Comportement:
- lit `RepoTags` via `skopeo inspect docker://... | jq '.RepoTags'`
- filtre les tags semver stricts `X.Y.Z`
- conserve l’inventaire local dans `tests/e2e_guardian/state/known_repo_tags.tsv`
- pour chaque nouvelle version détectée, lance toute la suite gardien via `run_hardcore_matrix.sh` ciblé sur ce serveur

Afficher toutes les possibilités `X.Y`:
```bash
./tests/e2e_guardian/scripts/list_xy_possibilities.sh
```

Installation `skopeo` (Debian/Ubuntu):
```bash
apt-get update && apt-get install -y skopeo
```

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
