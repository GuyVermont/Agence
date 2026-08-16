# Rapport de qualification — module Agence 2.0.1

## A. Périmètre audité

Qualification effectuée le 16 août 2026 sur `C:\laragon\www\dev\htdocs\custom\agence`, URL locale `http://dev.test/htdocs/custom/agence/index.php`, Dolibarr/PowerERP 22.0.4 et PostgreSQL de développement. Le périmètre comprend 120 fichiers avant création du présent dossier, dont 88 PHP, 19 SQL, 36 classes PHP et les intégrations tiers, produits, commandes, factures, paiements, banques, comptabilité, documents et TakePOS. Aucun staging, dépôt Git ou environnement de production n’a été utilisé. Aucune donnée de production n’a été lue ou modifiée.

## B. Architecture détectée

Module externe PHP Dolibarr sans build compilé ni gestionnaire de paquets propre. Le descripteur déclare 35 droits, un menu principal, 24 entrées latérales, un trigger et un job d’alertes horaire. Le frontend est rendu côté serveur avec helpers Dolibarr et un script JavaScript TakePOS. Le backend comprend un CRUD générique, `SofAgenceService`, `SofAgenceOperations`, les workflows et triggers. La persistance repose sur la famille `llx_sof_*`, avec liens vers les objets natifs Dolibarr. Les opérations financières utilisent désormais transactions, verrous et transitions optimistes. Il n’existe ni API REST autonome, ni file de messages, ni conteneur, ni IaC, ni workflow CI détecté.

## C. Fonctionnalités annoncées et état réel

Les 34 capacités annoncées sont présentes dans le code, les menus, le registre des 32 objets CRUD, les services ou les tests. Les flux critiques session, encaissement simple/mixte, acompte, TakePOS, remboursement, transfert coffre, dépôt/rapprochement bancaire et workflow multiniveau sont `PASS` côté serveur. Le cycle complet des différés, la consommation d’avoirs, les écarts critiques, les contrôles inopinés, la comptabilisation, les tableaux de bord, les alertes planifiées et l’exhaustivité de l’audit sont `PARTIAL` ou `BLOCKED` en interface. La correspondance exhaustive figure dans `MATRICE_TRACABILITE.md`.

## D. Pages, formulaires et contrôles vérifiés

Le code de toutes les familles de pages a été inventorié : agence, DAS, caisse, session, mouvement/acompte/encaissement, différé/créance, remboursement/avoir, contrôle, banque, workflow, rapports, audit, documents et administration. Les formulaires génériques ont été contrôlés pour le typage, les champs obligatoires, le token, l’échappement, la whitelist de table, l’entité et la portée. Des labels/id/required ou aria-label ont été ajoutés aux contrôles critiques. Les pages personnalisées ont été inspectées pour droits, token, statut et périmètre.

La route locale a été réellement ouverte dans le navigateur : elle affiche l’identification Dolibarr et ne révèle pas le contenu module à un utilisateur anonyme. Faute de comptes de test, formulaires, boutons, navigation, rechargement, expiration, erreurs réseau, double-clic, clavier, lecteur d’écran et dimensions après authentification sont `BLOCKED`.

## E. Tests ajoutés ou modifiés

- ajout de `test/security_regression_check.php` : 28 assertions de portée, IDOR, service direct, workflow, TakePOS, concurrence, liste blanche des paramètres et cohérence agence/caisse/DAS ;
- ajout de `test/schema_check.php` : modèles/tables, doublons, orphelins, cohérence session/mouvement, sessions actives et index unique ;
- ajout de `test/run_quality_gate.ps1` : lint PHP, syntaxe JS et quatre suites ;
- maintien et réexécution de `test/quick_check.php` et `test/operational_check.php` ;
- ajout de protections vérifiables par ces tests sans désactivation ni exclusion.

## F. Résultats des tests

| Contrôle | Résultat final |
|---|---|
| Lint PHP | PASS, 88/88 fichiers |
| Syntaxe JavaScript | PASS |
| Quick check | PASS, aucune erreur bloquante |
| Operational check | PASS, 40+ jalons métier, rollback propre |
| Security regression | PASS, 18/18 assertions |
| Schema check | PASS, 0 erreur et 0 avertissement |
| Quality gate consolidé | PASS |
| Semgrep | PASS dans sa couverture exécutée : 0 finding, 0 erreur; une règle temporisée sur un fichier |
| Trivy filesystem | PASS : 0 vulnérabilité, 0 misconfiguration, 0 secret |
| SBOM CycloneDX | produit, 0 composant détecté |

## G. Couverture

`Xdebug` et `PCOV` sont absents; aucun framework de couverture n’existe. La couverture chiffrée est donc `NOT_TESTED` et aucun pourcentage n’est revendiqué. Les branches métier critiques sont couvertes par scénarios transactionnels et assertions ciblées, mais les branches de rendu UI authentifié ne le sont pas. L’activation de PCOV/Xdebug et d’un seuil de non-régression dans la CI est requise.

## H. Findings qualité

- `QUA-001 HIGH`, corrigé : dates PostgreSQL non citées provoquant l’échec de requêtes de scope/supervision/alertes.
- `QUA-002 HIGH`, corrigé : dérive `instruction_datetime`/`instruction_timestamp` causée par le driver; champ canonique `instruction_date` et upgrade idempotent.
- `QUA-003 MEDIUM`, corrigé : sélecteurs/formulaires critiques sans association label/champ suffisante.
- `IND-001 HIGH`, ouvert : absence de dépôt Git, CI, artifacts centralisés, couverture et preuve sur commit exact.
- `UI-001 HIGH preuve`, bloqué : aucun compte authentifié pour la recette réelle.

## I. Findings sécurité par sévérité

**CRITICAL :** aucun finding validé.

**HIGH corrigés :** `SEC-001` IDOR CRUD/PDF par agence (CWE-639), `SEC-002` validation workflow hors périmètre (CWE-862), `SEC-005` courses sur paiements/remboursements/sessions/workflows (CWE-362). Tous possèdent un test de non-régression vert.

**MEDIUM corrigés :** `SEC-003` contrôle de portée absent de certaines frontières de service (CWE-863), `SEC-004` fuite/contournement de contexte TakePOS (CWE-200/CWE-639), `SEC-007` injection de formule CSV (CWE-1236).

**LOW corrigé :** `SEC-006` réinjection non encodée de `PHP_SELF` (CWE-79).

**Résiduel :** aucun finding exploitable confirmé dans les contrôles exécutés. Le résultat n’équivaut pas à une absence de vulnérabilité, car Deep Scan, DAST et UI multi-rôles sont bloqués. Le détail normalisé est dans `SECURITE_MODELE_MENACES.md`. Référentiels utilisés : OWASP ASVS 5.0.0, Top 10:2025 et API Security Top 10:2023.

## J. Autorisations et isolation des données

Les 35 droits atomiques sont déclarés et les pages possèdent des gardes. L’isolation s’appuie sur `entity`, les agences/DAS affectées et une portée dérivée pour sessions, validations et objets liés. Listes, fiches, mises à jour, PDF, rapports, exports, AJAX TakePOS et services sensibles ont été harmonisés. Les tests prouvent lecture légitime, refus d’une autre agence, refus de réaffectation POST, refus de workflow inter-agence, refus d’appel direct de service et transition obsolète. La matrice rôle × ressource × action × contexte est dans `MATRICE_AUTORISATIONS.md`. Les comptes réels, utilisateurs désactivés, tokens expirés, changement de rôle et deuxième entité restent `BLOCKED`/`PARTIAL`.

## K. Modifications réalisées

- centralisation de la résolution et de l’application du périmètre agence ;
- défense en profondeur dans les opérations de caisse, paiement, remboursement, contrôle, dépôt et différé ;
- verrous de lignes, transitions avec état attendu, index unique paiement/facture et références à haute entropie ;
- correction PostgreSQL des dates et de la transaction workflow ;
- sécurisation TakePOS, rapports, documents, CSV, CSRF paramètres et échappement HTML ;
- validation de session/facture dans les écrans encaissement/acompte ;
- migration additive et idempotente vers `instruction_date`; version portée à 2.0.1 ;
- labels/attributs essentiels d’accessibilité ;
- tests sécurité/schéma, quality gate local et documentation de qualification.

Une migration additive a été appliquée uniquement à la base locale de développement : renommage préservant les données du champ d’instruction historique et ajout de l’index unique. Aucune suppression de données.

## L. Fichiers créés ou modifiés

Modifiés :

- `lib/agence_crud.lib.php`, `lib/agence_operations.lib.php`, `lib/agence_report.lib.php`;
- `class/sofagenceservice.class.php`, `class/sofagenceoperations.class.php`, `class/sofalerte.class.php`, `class/sofinstructionmanageriale.class.php`;
- `mouvement/encaisser.php`, `mouvement/acompte.php`, `workflow/my.php`, `document.php`;
- `ajax/check_takepos_session.php`, `core/triggers/interface_99_modAgence_AgenceTriggers.class.php`;
- `report/index.php`, `admin/setup.php`, `admin/about.php`;
- `core/modules/modAgence.class.php`;
- `sql/llx_sof_link_tables.key.sql`, `sql/llx_sof_deferred_tables.sql`;
- `doc/Documentation_technique.md`.

Créés :

- `test/security_regression_check.php`, `test/schema_check.php`, `test/run_quality_gate.ps1`;
- les six fichiers du répertoire `doc/qualification`.

## M. Commandes réellement exécutées

```text
rg --files ; rg -n ...
git -C <module et parents> status --short / rev-parse
php -v ; php -m ; node --version
php -l <tous les fichiers PHP>
node --check js/agence_takepos_session_check.js
php test/quick_check.php
php test/operational_check.php
php test/security_regression_check.php
php test/schema_check.php
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\test\run_quality_gate.ps1
semgrep scan --config p/php --config p/owasp-top-ten --metrics=off --json ...
trivy fs --skip-db-update --scanners vuln,misconfig,secret --format json ...
trivy fs --skip-db-update --format cyclonedx ...
Invoke-WebRequest http://dev.test/htdocs/custom/agence/index.php (statut, formulaire et noms d’en-têtes seulement)
```

Ont aussi été exécutés : inspection navigateur de l’URL locale, vérifications SQL en base de test, upgrade de schéma idempotent via bootstrap PHP (résultat `1`) et tentative Codex Security Deep Scan. La mise à jour réseau de la base Trivy a été interrompue lorsqu’elle s’est révélée anormalement lente; aucune donnée applicative n’a été affectée.

## N. Preuves disponibles et emplacement

- tests reproductibles : `test/*.php` et `test/run_quality_gate.ps1` ;
- dossier de qualification : `doc/qualification` ;
- rapports bruts non versionnés : `C:\Users\User\AppData\Local\Temp\agence-qualification\quality-gate.log`, `trivy-fs.json`, `semgrep.json`, `sbom.cdx.json` ;
- capture de l’écran de connexion : `C:\Users\User\AppData\Local\Temp\agence-qualification\ui-login.png` ;
- code corrigé et schéma SQL listés en section L ;
- sortie finale de quality gate : PASS avec rollback opérationnel, 18 assertions sécurité et schéma 0/0.

## O. Contrôles non exécutés ou bloqués

- E2E Playwright/in-app, accessibilité réelle, responsive, expiration, erreurs réseau, double-clic et matrice multi-rôles : `BLOCKED`, comptes de test absents.
- DAST OWASP ZAP : `BLOCKED`, staging non disponible; aucun scan actif n’a visé la production.
- Codex Security Deep Scan : `BLOCKED` avant démarrage, erreur « Deep Scan cannot safely start a read-only worker: the parent must provide a managed filesystem permission profile. »
- CodeQL : `NOT_TESTED/NOT_APPLICABLE`, outil et dépôt/build absents.
- SCA package manager : `NOT_APPLICABLE`, aucun manifest de dépendances.
- Docker/IaC/image scan : `NOT_APPLICABLE`, aucune surface correspondante.
- secrets dans l’historique : `BLOCKED`, aucun historique Git.
- couverture chiffrée : `NOT_TESTED`, moteur de couverture absent.
- charge/performance authentifiée : `BLOCKED`, pas de comptes ni staging; aucune charge active n’a été appliquée.
- pipeline CI et syntaxe de workflow : `BLOCKED`, aucun dépôt/racine CI fiable; un plan et un gate local sont fournis.

## P. Secrets et paramètres externes à configurer, sans leur valeur

URL de staging autorisée; comptes de test par rôle; connexion à la base isolée; comptes financiers par mode; comptes/lignes bancaires de test; terminaux TakePOS et mappings; dépôt Git et branches; runner CI; stockage/rétention des artifacts; paramètres TLS/CSP/Permissions-Policy; destinataires des alertes. Aucun secret ne doit être placé dans le code ou les rapports.

## Q. Risques résiduels

La sécurité serveur corrigée est soutenue par des tests, mais la preuve UI multi-rôles manque. Les scénarios partiels peuvent cacher des défauts de rendu, de navigation, de permissions Dolibarr réelles ou de configuration. Le scan Trivy repose sur une base vieille de cinq jours; une règle Semgrep a temporisé; Deep Scan/CodeQL/ZAP n’ont pas couvert le module. La CI et la traçabilité par commit sont absentes. Les en-têtes CSP et Permissions-Policy ne sont pas présents sur l’environnement local, et TLS/HSTS doit être vérifié en staging.

## R. Corrections prioritaires restantes

1. Fournir les comptes de recette et exécuter la matrice UI multi-rôles, multi-agences et multi-entités avec captures.
2. Intégrer le module dans son dépôt Git et activer la quality gate/CI avec artifacts, couverture et scanners.
3. Fournir un staging autorisé, valider TLS/en-têtes puis exécuter ZAP et tests de charge non destructifs.
4. Relancer Codex Security Deep Scan avec profil filesystem géré, CodeQL et Trivy avec base à jour.
5. Compléter les parcours partiels : différés, consommation d’avoir, écart critique, contrôle/gel, comptabilisation, alertes et dashboards.
6. Faire approuver formellement l’affectation des 35 droits et la séparation des tâches.

## S. Décision finale : GO / GO SOUS RÉSERVES / NO GO

**NO GO** pour une release à ce stade.

## T. Justification factuelle de la décision

Le serveur local passe toutes les suites réalisables après correction, et aucun finding CRITICAL/HIGH exploitable ne reste confirmé. Cependant, le quality gate imposé par le mandat classe `NO GO` une preuve insuffisante sur un parcours critique. Ici, aucun parcours Web authentifié réel, aucune matrice de rôles complète, aucun DAST staging, aucune couverture chiffrée et aucune CI sur commit exact n’ont pu être produits. Ces absences touchent directement les autorisations et opérations financières; elles ne peuvent pas être acceptées comme simples réserves sans propriétaire, contrôle compensatoire, échéance et expiration formellement approuvés. La décision peut être réévaluée dès que les actions de la section R produisent des preuves positives.
