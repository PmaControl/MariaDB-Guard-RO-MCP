# 7013 - Upgrade Framework de test E2E/intégration gardien (Bash only)

## 1) Arborescence cible
```text
tests/e2e_guardian/
  README.md
  bin/
    run.sh                        # orchestrateur principal
  lib/
    common.sh                     # logs, helpers, diagnostics
    test_parser.sh                # parsing/validation des .test
  scripts/
    setup_env.sh                  # check prérequis CLI
    generate_certs.sh             # génération certs de test
    provision_db.sh               # provisioning DB par moteur/version
    cleanup.sh                    # nettoyage infra test
    collect_artifacts.sh          # archive artefacts d'un run
  cases/
    unit/
      GUARD-001-health.test
    blocks/
      GUARD-010-auth-unauthorized.test
      GUARD-020-guard-or-blocked.test
    full/
      GUARD-100-timeout-sleep.test
      GUARD-110-mcp-test-safe.test
    hardcore/
      GUARD-900-ssl-matrix.test
    examples/
      EXAMPLE-999-complete.test
  runs/
    <run-id>/
      timeline.log
      summary.json
      junit.xml
      <test-id>/
        stdout.log
        stderr.log
        env.snapshot
        resolved.config
        timeline.log
        exit.code
        command.sh
        replay.sh
        diagnosis.txt
```

## 2) Spécification `.test`
Format: fichier shell simple (`KEY=VALUE`) sourceable par Bash.

### Champs requis
- `TEST_ID`, `TEST_NAME`, `BLOCK`
- `DB_ENGINE`, `DB_VERSION`, `SSL_MODE`
- `GUARDIAN`, `STACK`
- `PR_ID`, `MCP_ID` (mapping `id -> PR -> MCP`)
- `MCP_ENDPOINT`, `COMMAND`

### Champs optionnels
- `TEST_DESCRIPTION`, `TAGS`, `PRIORITY`
- `TIMEOUT_S`, `RETRIES`
- `MCP_TOKEN`
- `PRE_HOOK`, `POST_HOOK`
- `EXPECT_JSON_PATH`, `EXPECT_EQUALS`
- `ENABLED`
- `MATRIX_EXPAND`, `DB_VERSION_LIST`, `SSL_MODE_LIST`

### Exemple complet
Référence: `tests/e2e_guardian/cases/examples/EXAMPLE-999-complete.test`

Paramètres SSL supportés dans les scénarios:
- `off`
- `files` (CA/cert/key fournis)
- `server` (certs fournis côté serveur, ex MariaDB >= 11.4)

## 3) Matrice de tests
Dimensions:
- moteur/version DB
- mode SSL (`off`, `files`, `server`)
- type gardien
- stack de déploiement
- mode run (`unit`, `block`, `full`, `hardcore`)

### Standard (`--full`)
- Réduction intelligente:
  - un sous-ensemble représentatif par test matrix (`DB_VERSION_LIST[0]`, `SSL_MODE_LIST[0]`)
  - exclusion des tests taggés `hardcore`

### Hardcore (`--hardcore`)
- Expansion totale:
  - produit cartésien `DB_VERSION_LIST x SSL_MODE_LIST`
  - exécution de tous les tests marqués `MATRIX_EXPAND=true`
  - inclut cas extrêmes et de charge

## 4) Catalogue de test cases à couvrir

### Positifs
- Health OK
- Initialize auth OK
- `mcp_test.safe=true`
- `db_select` standard
- `db_explain_table` format humain

### Négatifs sécurité gardien
- unauthorized token
- OR dans WHERE (bloqué)
- `FOR UPDATE` (bloqué)
- `WITH RECURSIVE` (bloqué)
- full scan interdit sur grandes tables
- timeout (`guard [execution time reached]`)

### SSL/TLS
- SSL off
- SSL files (CA + client cert + key)
- SSL server-provided (>=11.4)
- cert expiré
- cert invalide
- hostname mismatch
- CA absente
- clé manquante
- permissions fichier cert invalides

### Réseau / robustesse
- DB indisponible
- MCP indisponible
- latence réseau / timeout
- retry behavior

### Compatibilité / non-régression
- MariaDB multi-versions
- MySQL multi-versions
- fallback `DB_PASS` / `DB_PASSWORD`
- sans SSL vars (backward compatible)

## 5) Orchestrateur Bash principal
Fichier: `tests/e2e_guardian/bin/run.sh`

Fonctions clés:
- modes:
  - `--unit`
  - `--block`
  - `--full`
  - `--hardcore`
- filtres:
  - `--db`, `--version`, `--ssl-mode`, `--tag`, `--guardian`, `--stack`
- debug:
  - `--explain`
  - `--dry-run`
  - `--replay <test-id-instance>`
- logs:
  - horodatage + niveaux `INFO/DEBUG/TRACE`
- reporting:
  - `summary.json`
  - `junit.xml`
- codes retour:
  - `0` si tout passe
  - `1` si fail/error

## 6) Scripts utilitaires Bash
- `scripts/setup_env.sh`
  - valide la présence des outils CLI requis
- `scripts/generate_certs.sh`
  - génère CA + certs serveur/client de test
- `scripts/provision_db.sh`
  - démarre DB Docker par moteur/version
- `scripts/cleanup.sh`
  - supprime les ressources de test
- `scripts/collect_artifacts.sh`
  - archive un run complet pour partage/debug

Prérequis CLI obligatoires:
- `bash`, `curl`, `jq`, `awk`, `sed`, `grep`, `find`, `timeout`
- `docker`, `skopeo`
- `mysql`, `mysqladmin`
- `php`

Installation rapide Debian/Ubuntu:
```bash
apt-get update
apt-get install -y skopeo docker.io mariadb-client jq php-cli
```

## 7) Exploitation et debug

### Lancer chaque mode
```bash
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-001
./tests/e2e_guardian/bin/run.sh --block --block sql-guards
./tests/e2e_guardian/bin/run.sh --full
./tests/e2e_guardian/bin/run.sh --hardcore
```

### Lancer un test unique
```bash
./tests/e2e_guardian/bin/run.sh --unit --id GUARD-020
```

### Lancer un bloc
```bash
./tests/e2e_guardian/bin/run.sh --block --block auth
```

### Activer hardcore
```bash
./tests/e2e_guardian/bin/run.sh --hardcore --log-level DEBUG
```

### Reproduire un échec localement
1. Ouvrir le dossier artefacts du test échoué.
2. Lire `resolved.config`, `stderr.log`, `diagnosis.txt`.
3. Rejouer `command.sh` (copier-coller direct).
4. Utiliser `replay.sh` pour relancer via orchestrateur.

### Ajouter une version MySQL/MariaDB
1. Ajouter la version dans `DB_VERSION_LIST` des `.test` matrix.
2. Ajouter/provisionner l’image correspondante via `provision_db.sh`.
3. Exécuter `--hardcore --db <engine> --version <x.y.z>`.

### Ajouter un nouveau type de gardien
1. Ajouter `GUARDIAN=<type>` dans les nouveaux `.test`.
2. Ajouter le bloc fonctionnel sous `cases/blocks/`.
3. Lancer `--block` puis `--full`.

### Brancher un nouveau MCP
1. Surcharger `MCP_ENDPOINT`/`MCP_TOKEN` via env ou `.test`.
2. Valider avec `--unit GUARD-001`.
3. Monter la campagne complète.

### Conventions de nommage
- IDs: `GUARD-XXX`
- blocs: `auth`, `sql-guards`, `performance-guards`, `security`, `ssl-matrix`
- tags: `smoke`, `negative`, `hardcore`, `ssl`, `matrix`

### Troubleshooting orienté humain
- `Connection refused`:
  - vérifier endpoint MCP/DB, firewall, bind address
- `Access denied`:
  - vérifier credentials + grants + host/user matching
- `SSL error`:
  - vérifier CA/cert/key, permissions, hostname/cn/san
- timeout:
  - vérifier charge DB, limites MCP, réseau

## 8) Critères d’acceptation couverts
- 4 modes fonctionnels
- modes SSL obligatoires modélisés (`off/files/server`)
- matrice multi-version exécutable via point d’entrée unique
- traçabilité `id -> PR -> MCP`
- artefacts complets par test
- 100% Bash, sans dépendance agent IA
