# Branch
- Name: `feature/7010-auto-kill-fixed`

## Scope
- Short description of the feature: automatically kill MCP-owned queries that exceed allowed execution time.
- Main goal: enforce hard runtime cutoffs even if the database keeps executing.
- Why it matters for this MCP server: time guards are weak if runaway queries continue server-side after client timeout.

## Current State
- Historical progress marked the original feature as `partial`.
- The fixed implementation now uses one dedicated DB connection per guarded `db_select` request when `AUTO_KILL_DB_SELECT=1`.
- Ownership is proven by the exact `CONNECTION_ID()` of that dedicated connection while it is still executing a live `Query/Execute`.
- Current test status: dedicated PHPUnit coverage added, plus two new E2E guards (`GUARD-140`, `GUARD-141`) for positive and negative paths.

## Expected Result
- Functional outcome expected: long MCP query is terminated at the DB level near the configured threshold.
- Security outcome expected: service cannot be abused to leave expensive orphaned queries behind.
- Operational outcome expected: kill action is auditable and targets only MCP-owned sessions.

## Remaining Work
- [x] Implement reliable mapping between MCP request and DB processlist entry.
- [x] Add tests proving non-MCP queries are never killed.
- [ ] Validate engine/version differences for `SHOW PROCESSLIST` and `KILL QUERY` on every supported matrix target.

## Tests To Provide
- [x] Normal path: MCP query over threshold is killed (`GUARD-140`).
- [x] Failure path: external user query over threshold is not killed (`GUARD-141`).
- [x] Regression path: short MCP query is never killed (existing timeout regression path stays unchanged because auto-kill is opt-in).
- [ ] Edge cases: concurrent long queries, connection reuse, race between timeout and completion.
- [ ] Performance / load behavior if relevant: processlist polling does not overload DB.
- [ ] Security abuse cases if relevant: attacker tries to get the system to kill unrelated sessions.
- [x] launch long `SLEEP` via direct mysql (sans MCP) and prove MCP does not kill this external session.

## Extreme Cases To Cover
- Session ID reused after disconnect.
- Multiple MCP queries from one connection or pooled connections.
- Query already finished when kill fires.
- Replica with delayed processlist visibility.

## Risks
- Security risks: wrong-session kill is operationally dangerous.
- Production risks: excessive polling or kill storms can destabilize a replica.
- Compatibility risks: processlist columns and privileges differ across engines.
- Observability / supportability risks: operators need proof of why a query was killed.

## Validation Status
- Status: `ready for validation`
- Confidence level: `medium`
- Reason: ownership model is now strict and testable, but full DB matrix validation is still pending before claiming production validation.

## References
- Related files or classes: `src/AutoKill.php`, `src/Tools.php`, `src/Db.php`, `bin/mcp-auto-kill-monitor.php`
- Related PRs / commits if identifiable: security hardening track `7010`

## Implementation Notes
- Auto-kill is opt-in through `AUTO_KILL_DB_SELECT=1`.
- When enabled, `db_select` runs on a fresh dedicated PDO connection instead of the shared cached handle.
- The monitor process gets the exact `CONNECTION_ID()` from that dedicated connection.
- The monitor waits until the configured timeout, then re-checks `information_schema.PROCESSLIST`.
- It will only run `KILL QUERY <id>` if:
  - the processlist row still exists,
  - the row id is the exact tracked connection id,
  - the row is still in `Query` or `Execute`,
  - the DB user and schema still match the tracked MCP session.
- If the query already finished, became `Sleep`, disappeared from processlist, or ownership is not proven, no kill happens.
- This design avoids broad heuristics on SQL text, user only, host only, or duration only.

Prompte detaillé :


Projet: MariaDB-Guard-RO-MCP

Tu travailles sur le dépôt du projet `MariaDB-Guard-RO-MCP`.

Ta mission est d’implémenter, fiabiliser, tester et documenter la feature suivante dans une nouvelle branche Git :

# Branch
feature/7010-auto-kill-fixed

# Contexte
Cette feature doit permettre de tuer automatiquement les requêtes SQL lancées par le serveur MCP quand elles dépassent le temps d’exécution autorisé.

Le but n’est pas seulement de couper côté client ou applicatif, mais bien d’imposer une coupure réelle côté base de données, même si la requête continue à tourner côté serveur SQL après timeout applicatif.

Aujourd’hui, l’état de cette feature est `partial` :
- des changements existent côté app / tool handling ;
- mais il n’existe pas encore de preuve robuste que le mapping entre requête MCP et entrée `SHOW PROCESSLIST` est fiable ;
- il n’existe pas encore de validation bout en bout prouvant que seules les requêtes appartenant au MCP sont tuées ;
- il n’existe pas encore de preuve que les sessions externes (hors MCP) sont préservées.

Les logs de rebuild génériques ne suffisent pas à valider cette feature en sécurité.

# Objectif principal
Implémenter une version robuste, sûre et observable de l’auto-kill des requêtes MCP trop longues.

# Résultat attendu
## Fonctionnel
- Une requête MCP qui dépasse le seuil configuré est tuée réellement au niveau SGBD, dans une fenêtre proche du seuil prévu.

## Sécurité
- Le service ne doit pas pouvoir laisser derrière lui des requêtes orphelines coûteuses.
- Le mécanisme d’auto-kill ne doit jamais tuer une requête non-MCP.

## Opérationnel
- Chaque kill doit être traçable / auditable.
- Le ciblage doit être strictement limité aux sessions appartenant au MCP.
- Le comportement doit être compréhensible en production.

# Périmètre technique
Travaille en priorité sur ces fichiers, si pertinent :
- `src/App.php`
- `src/Tools.php`
- `src/Db.php`

Tu peux créer de nouveaux fichiers / classes / helpers / tests / docs si nécessaire.

# Contraintes fortes
1. Ne casse pas le comportement read-only du serveur MCP.
2. Ne fais aucun kill “large” ou heuristique dangereuse basée uniquement sur :
   - le texte SQL,
   - l’utilisateur SQL,
   - l’host,
   - la durée seule,
   - ou une simple recherche approximative dans `SHOW PROCESSLIST`.
3. Le kill ne doit viser qu’une requête dont la propriété “MCP-owned” est démontrable.
4. En cas de doute sur l’identité de la session SQL, il faut préférer :
   - ne pas tuer,
   - logguer clairement l’échec de corrélation,
   - retourner un état explicite.
5. Préserve la compatibilité autant que possible avec MariaDB / MySQL, et documente précisément les écarts de comportement.
6. Toute logique de polling processlist doit être bornée, mesurée et justifiée pour éviter la surcharge.
7. Ne fais pas d’implémentation “magique” non testable. Toute hypothèse doit être vérifiable par tests.

# Travail demandé

## 1. Analyse de l’existant
Avant de coder, lis l’implémentation actuelle et identifie :
- comment une requête MCP est envoyée à la base ;
- où le timeout applicatif est géré ;
- comment l’identifiant de session SQL pourrait être récupéré de manière fiable ;
- si la connexion DB est unique, réutilisée, poolée ou recréée ;
- quels sont les risques actuels de mauvais kill.

Dans ton rendu, explique brièvement :
- l’architecture actuelle du flow SQL ;
- ce qui rend la version actuelle `partial` ;
- le point exact où il faut attacher la logique de tracking d’ownership.

## 2. Concevoir un mécanisme fiable de corrélation MCP -> session SQL
Implémente ou valide une méthode robuste pour associer une requête MCP à l’entrée processlist correcte.

Tu dois privilégier une stratégie fiable et explicable, par exemple :
- récupération stricte du `CONNECTION_ID()` / thread id SQL de la connexion réellement utilisée pour exécuter la requête ;
- stockage local de métadonnées de requête côté app ;
- journalisation d’un identifiant interne de requête MCP ;
- éventuel marquage contextuel si disponible et sûr ;
- tout mécanisme additionnel de corrélation si nécessaire.

Mais attention :
- si la connexion est réutilisée pour plusieurs requêtes, traite correctement les collisions et courses ;
- si plusieurs requêtes MCP concurrentes existent, le système doit rester correct ;
- si l’ID de session est réutilisé après déconnexion, évite les faux positifs ;
- si la requête a déjà fini quand le kill part, le système doit le gérer proprement.

Tu dois documenter clairement le modèle de propriété retenu :
- qu’est-ce qui prouve qu’une requête appartient au MCP ;
- quelles preuves minimales sont exigées avant un kill ;
- dans quels cas on refuse volontairement de tuer.

## 3. Implémenter l’auto-kill sûr
Ajoute le mécanisme qui :
- détecte qu’une requête MCP a dépassé le seuil autorisé ;
- tente le kill côté DB ;
- vérifie / gère le résultat ;
- loggue l’événement proprement.

Attendus :
- kill borné dans le temps ;
- pas de boucle infinie ;
- pas de polling agressif ;
- logs explicites ;
- comportement propre si la requête a déjà fini ;
- comportement propre si le kill échoue faute de privilèges ou de visibilité processlist.

Documente aussi :
- privilèges requis pour observer et tuer ;
- différences possibles entre MariaDB et MySQL ;
- impacts éventuels sur réplica / primaire.

## 4. Tests obligatoires
Ajoute des tests automatisés et/ou scripts de validation reproductibles couvrant au minimum :

### Normal path
- une requête MCP longue dépasse le seuil ;
- elle est tuée au niveau DB ;
- le résultat côté MCP reflète bien un timeout / kill réel.

### Failure path
- une requête lancée hors MCP (par exemple via client mysql direct) dépasse le seuil ;
- le MCP ne doit jamais la tuer.

### Regression path
- une requête MCP courte ne doit jamais être tuée.

### Edge cases
- plusieurs requêtes longues concurrentes ;
- réutilisation de connexion ;
- course entre fin normale et kill ;
- tentative de kill quand la requête n’existe déjà plus ;
- session ID réutilisé après disconnect ;
- plusieurs requêtes MCP sur une même connexion ou via connexions poolées ;
- réplica avec visibilité retardée de processlist, si applicable.

### Performance / charge
- démontre que le polling processlist ne surcharge pas significativement la DB ;
- borne la fréquence et la durée du polling ;
- explique le coût attendu.

### Security abuse cases
- un attaquant essaie de faire tuer une session externe ;
- une requête externe ressemblant à une requête MCP ne doit pas être tuée ;
- une collision d’identifiants ou de timing ne doit pas provoquer un mauvais kill.

### Cas spécifique imposé
Ajouter un scénario reproductible :
- lancer `SELECT MAX_TIME+5` via client mysql direct, sans passer par MCP ;
- vérifier via MCP ;
- s’assurer que cette requête externe n’est pas tuée par le mécanisme auto-kill MCP.

Si la syntaxe exacte `MAX_TIME+5` n’est pas valide telle quelle dans le contexte réel, adapte le test pour produire une requête longue équivalente via mysql direct, tout en conservant l’intention du test : prouver qu’une requête externe longue n’est jamais tuée par le MCP.

## 5. Documentation dédiée à la branche
Créer ou mettre à jour une documentation spécifique à cette feature.

Je veux une section dédiée, claire et exploitable, contenant :
1. objectif de la feature ;
2. architecture de corrélation ownership ;
3. algorithme de kill ;
4. garanties de sécurité ;
5. limites connues ;
6. différences MariaDB / MySQL / versions ;
7. privilèges nécessaires ;
8. stratégie de logs / audit ;
9. scénarios testés ;
10. scénarios explicitement refusés pour sécurité ;
11. raisons pour lesquelles la version précédente était `partial` ;
12. ce qu’il reste éventuellement hors scope.

Le document doit expliquer noir sur blanc :
- pourquoi tuer une mauvaise session est dangereux ;
- comment l’implémentation l’évite ;
- pourquoi les tests négatifs sont indispensables.

## 6. Validation finale
Je veux un rendu final structuré avec ces sections exactes :

### 1. Summary
- ce que tu as modifié ;
- si la feature passe de `partial` à `success` ou reste `partial` ;
- niveau de confiance final.

### 2. Ownership model
- comment tu identifies une requête MCP ;
- quelles preuves tu utilises ;
- pourquoi ce n’est pas ambigu.

### 3. Files changed
- liste des fichiers modifiés / créés ;
- rôle de chacun.

### 4. Tests
- liste des tests ajoutés ;
- ce qu’ils prouvent ;
- résultat attendu / observé.

### 5. Security analysis
- comment tu évites le wrong-session kill ;
- ce qui se passe en cas de doute ;
- surfaces d’abus restantes.

### 6. Compatibility notes
- différences MariaDB / MySQL ;
- différences de `SHOW PROCESSLIST`, `information_schema.processlist`, privilèges, sémantique de `KILL`, etc.

### 7. Operational notes
- logs ;
- auditabilité ;
- coût du polling ;
- signaux utiles pour exploitation.

### 8. Documentation updated
- chemin du document ;
- résumé des sections ajoutées.

### 9. Remaining gaps
- ce qui reste éventuellement non couvert ;
- pourquoi ;
- impact.

# Exigences de qualité
- Code propre, lisible, commenté seulement quand utile.
- Pas de complexité cachée.
- Pas de hack fragile.
- Pas de validation uniquement “à l’œil”.
- Pas de conclusion “success” sans test négatif solide.
- Si une partie ne peut pas être rendue sûre, dis-le explicitement et laisse le statut `partial`.

# Critère décisif
La feature ne peut être considérée `success` que si tu apportes une preuve crédible et reproductible que :
1. les requêtes MCP longues sont bien tuées côté DB ;
2. les requêtes externes ne sont jamais tuées ;
3. le lien entre requête MCP et session SQL est suffisamment robuste pour éviter les faux positifs ;
4. le tout est documenté et auditable.

Sinon, laisse la feature en `partial` avec justification précise.

# Important
Ne te contente pas d’un patch minimal.
Je veux une implémentation défendable en revue sécurité / production.

Commence par analyser le code existant, puis implémente la solution, ajoute les tests, mets à jour la documentation, et termine par un rapport structuré suivant exactement le format demandé.
