# Sécurité, modèle de menaces et findings

## Référentiels

La qualification s’appuie sur [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/releases/tag/v5.0.0_release), [OWASP Top 10:2025](https://owasp.org/www-project-top-ten/) et [OWASP API Security Top 10:2023](https://owasp.org/API-Security/editions/2023/en/0x10-api-security-risks/), versions stables officielles disponibles le 16 août 2026. Le niveau visé est une sélection ASVS L2 adaptée à une application financière authentifiée; il ne s’agit pas d’une certification ASVS complète.

## Actifs et frontières de confiance

| Élément | Actifs | Frontière / contrôle attendu |
|---|---|---|
| Navigateur utilisateur | identité, token CSRF, données affichées | authentification et session Dolibarr, encodage HTML, droits serveur |
| Pages PHP du module | commandes métier et identifiants | `GETPOST`, droits atomiques, entité et périmètre agence |
| Endpoint AJAX TakePOS | terminal, session, agence/caisse/DAS | session Dolibarr, scope utilisateur, réponse minimale |
| Services métier | paiements, remboursements, soldes, transitions | défense en profondeur, transactions, verrous, états attendus |
| Triggers Dolibarr/TakePOS | événements facture/paiement/ticket | contexte canonique, idempotence, refus explicite en erreur |
| PostgreSQL | tables `llx_sof_*` et objets Dolibarr | entité, FK logiques, contraintes uniques, transactions |
| Documents/exports | données financières et audit | contrôle d’accès identique à l’objet, neutralisation CSV, échappement |
| Jobs d’alertes | retards, écarts, dépôts | entité, périmètre du job, idempotence et journalisation |
| Banque/comptabilité Dolibarr | écritures et rapprochements | séparation des tâches, rapprochement exact, audit |
| CI/CD future | code, artifacts, éventuels secrets | permissions minimales, provenance, aucun secret sur PR non fiable |

## Menaces prioritaires et mesures

| Menace | OWASP/CWE | Impact | Mesures vérifiées | État |
|---|---|---|---|---|
| IDOR/BOLA entre agences | API1:2023, CWE-639 | exposition ou mutation financière inter-agence | garde canonique page/liste/PDF/service/AJAX, tests négatifs | Corrigé et PASS serveur |
| Escalade par réaffectation POST | A01:2025, CWE-863 | déplacer un objet dans un périmètre autorisé | réévaluation de portée après hydratation POST | Corrigé et PASS |
| Double paiement/remboursement | A06:2025, CWE-362 | perte financière, journal incohérent | verrous `FOR UPDATE`, index unique, état optimiste, transactions | Corrigé et PASS |
| Contournement workflow | A01:2025, CWE-862 | validation non autorisée | portée dérivée, étape verrouillée et état attendu | Corrigé et PASS |
| Injection SQL | A05:2025, CWE-89 | lecture/altération de données | typage entier, échappement, whitelist de tables, Semgrep | Aucun finding validé; DAST BLOCKED |
| XSS/CSV injection | A05:2025, CWE-79/CWE-1236 | exécution navigateur/poste analyste | échappement `PHP_SELF`, encodage HTML, apostrophe CSV | Corrigé et PASS ciblé |
| CSRF | ASVS V3/V4 | action forcée | tokens Dolibarr, paramètre booléen déplacé de GET vers POST | Inspecté; E2E expiré BLOCKED |
| Mass assignment | API3:2023, CWE-915 | champs/états non prévus | registre de champs et transitions métier contrôlées | Aucun contournement confirmé |
| Fuite d’erreurs/secrets | A02/A04:2025 | renseignement ou compromission | scan secrets, messages contrôlés | Trivy PASS; historique Git BLOCKED |
| Supply chain | A03:2025 | dépendance compromise | aucun manifest/module embarqué; SBOM vide | SCA NOT_APPLICABLE, CI absente |
| Épuisement/rate limiting | API4:2023 | disponibilité | limite listes à 500; protections Dolibarr héritées | DAST/performance authentifiée BLOCKED |

## Findings validés et traitement

| ID | Source | Sévérité | CWE | Emplacement | Atteignabilité / preuve | Impact | Correctif | Statut |
|---|---|---:|---|---|---|---|---|---|
| SEC-001 | revue + test | HIGH | CWE-639 | CRUD générique, `document.php` | identifiant direct ou champ agence POST; test inter-agence | lecture/mutation inter-agence | portée canonique directe/dérivée et revalidation POST | FIXED, test PASS |
| SEC-002 | revue + test | HIGH | CWE-862 | `workflow/my.php` et service | validation cible sans `fk_agence`; test négatif | décision workflow hors mandat | résolution de l’agence cible et filtre liste/action | FIXED, test PASS |
| SEC-003 | revue + test | MEDIUM | CWE-863 | opérations métier | appel direct de service contournait certaines pages | mouvement financier hors périmètre | `ensureAgencyScope` sur opérations sensibles | FIXED, test PASS |
| SEC-004 | revue + test | MEDIUM | CWE-200/CWE-639 | TakePOS AJAX/trigger | terminal d’une autre agence et contexte ticket | fuite de contexte ou vente incohérente | filtre agences, session/caissier/superviseur, erreurs propagées | FIXED, tests PASS |
| SEC-005 | revue + test | HIGH | CWE-362 | paiements, remboursements, sessions, workflows | lectures puis écritures non verrouillées; rafale de références | doublons et incohérence comptable | verrous, index unique, statut attendu, aléa cryptographique | FIXED, tests PASS |
| SEC-006 | revue | LOW | CWE-79 | formulaires CRUD | `PHP_SELF` réinjecté sans encodage | XSS réfléchi selon serveur/URL | `dol_escape_htmltag` | FIXED, lint + revue |
| SEC-007 | revue + test statique | MEDIUM | CWE-1236 | export CSV | cellule débutant par `=`, `+`, `-`, `@` | formule exécutée à l’ouverture | préfixe apostrophe | FIXED, revue |
| QUA-001 | exécution | HIGH qualité | CWE-20 | requêtes dates | PostgreSQL rejetait des prédicats non cités | dashboards/alertes indisponibles | dates échappées et citées | FIXED, suites PASS |
| QUA-002 | schéma | HIGH qualité | CWE-1284 | instruction managériale | driver transformait `instruction_datetime` | champ modèle absent, migration incohérente | `instruction_date`, upgrade idempotent | FIXED, schema PASS |
| IND-001 | découverte | HIGH industrialisation | — | dépôt/module | aucun `.git`, manifest ou workflow détecté | pas de quality gate central ni preuve par commit | script local et plan CI fournis | OPEN/BLOCKED propriétaire |
| UI-001 | navigateur | HIGH preuve | — | interface authentifiée | écran de connexion seulement, aucun compte de test | parcours critiques non qualifiés en conditions réelles | fournir comptes et exécuter E2E/accessibilité | BLOCKED |

Les findings SEC-001 à SEC-007 ne sont plus ouverts dans l’état local testé. Aucun finding CRITICAL validé et aucun secret réel détecté.

## Résultats des scanners

| Contrôle | Version/périmètre | Résultat | Limite |
|---|---|---|---|
| Semgrep | 1.173.0, règles PHP + OWASP Top 10, 116 cibles au scan final | 0 finding, 0 erreur JSON | timeout de fixpoint `tainted-object-instantiation` sur `lib/agence_operations.lib.php` |
| Trivy filesystem | 0.73.0, vuln/config/secret | 0 vulnérabilité, 0 mauvaise configuration, 0 secret | base locale du 11 août 2026 utilisée après abandon d’une mise à jour réseau lente |
| SBOM CycloneDX | Trivy, spec 1.7 | artifact valide, 0 composant | aucun manifest de dépendances dans le module |
| CodeQL | outil absent, aucun dépôt/build | NOT_TESTED / NOT_APPLICABLE au module isolé | à activer dans le dépôt propriétaire |
| SCA gestionnaire | aucun Composer/npm manifest | NOT_APPLICABLE | dépendances fournies par Dolibarr hors périmètre module |
| Images/IaC | aucun Dockerfile/IaC | NOT_APPLICABLE | aucune surface détectée |
| Historique secrets | aucun dépôt Git | BLOCKED | historique non accessible |
| Codex Security Deep Scan | périmètre module | BLOCKED, aucun worker démarré | profil filesystem géré non fourni au worker lecture seule |
| OWASP ZAP | staging | BLOCKED | aucune URL de staging autorisée; scan actif local/prod non substitué |

## En-têtes HTTP observés sur l’environnement local

La route du module renvoie HTTP 200 avec le formulaire d’identification Dolibarr. `X-Frame-Options`, `X-Content-Type-Options` et `Referrer-Policy` sont présents. `Content-Security-Policy` et `Permissions-Policy` sont absents. `Strict-Transport-Security` est absent sur l’URL locale HTTP, ce qui est attendu hors TLS mais doit être vérifié sur staging/production. Ces en-têtes relèvent de la configuration PowerERP/serveur, pas du module isolé.

## Risques résiduels

- absence de preuve dynamique multi-rôles et multi-entités ;
- absence de DAST de staging et de validation TLS/headers de production ;
- absence de couverture instrumentée et d’E2E accessibilité ;
- absence de CI, de dépôt Git et de preuve reproductible sur un commit exact ;
- base Trivy vieille de cinq jours lors du scan ;
- règle Semgrep temporisée sur un fichier ;
- affectation métier des 35 droits et séparation des tâches à approuver.
