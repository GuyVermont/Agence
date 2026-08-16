# Guide d’exécution locale et CI

## Préconditions

- Dolibarr/PowerERP 22.0 ou supérieur avec le module `agence` activé.
- Module obsolète `sofops` désactivé.
- PHP CLI 8.3, Node.js et PostgreSQL de test accessibles.
- Base non productive contenant le schéma Dolibarr et les tables `llx_sof_*`.
- Pour l’E2E : comptes de test non productifs couvrant au minimum administrateur, caissier, chef de caisse, responsable d’agence, contrôleur, comptable, valideur, direction, auditeur et utilisateur multi-agences.

## Quality gate local

Depuis la racine du module :

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\test\run_quality_gate.ps1
```

Le script échoue au premier contrôle bloquant et exécute :

1. lint des fichiers PHP ;
2. vérification syntaxique JavaScript ;
3. smoke/configuration (`quick_check.php`) ;
4. intégration métier transactionnelle (`operational_check.php`) ;
5. non-régression de sécurité (`security_regression_check.php`) ;
6. conformité et intégrité du schéma (`schema_check.php`).

Les scénarios qui créent des données le font dans une transaction de test et effectuent un rollback. `schema_check.php` est en lecture seule. Ne pas exécuter cette suite sur une base de production.

## Scanners reproduits

```powershell
trivy fs --skip-db-update --scanners vuln,misconfig,secret --format json --output "$env:TEMP\agence-qualification\trivy-fs.json" .
trivy fs --skip-db-update --format cyclonedx --output "$env:TEMP\agence-qualification\sbom.cdx.json" .
semgrep scan --config p/php --config p/owasp-top-ten --metrics=off --json --json-output "$env:TEMP\agence-qualification\semgrep.json" .
```

La qualification du 16 août 2026 a utilisé Trivy 0.73.0 avec une base locale datée du 11 août 2026 et Semgrep 1.173.0. La règle Semgrep `tainted-object-instantiation` a atteint son délai de fixpoint sur `lib/agence_operations.lib.php`; ce fichier ne peut donc pas être déclaré couvert par cette règle précise.

## Chaîne CI/CD cible

Le module n’est rattaché à aucun dépôt Git détectable et aucun workflow n’existe. La chaîne ne peut donc pas être activée à un emplacement fiable. Lors de l’intégration dans le dépôt propriétaire, utiliser les étapes suivantes avec permissions minimales et sans secret sur les PR non fiables :

| Déclencheur | Contrôles obligatoires | Artifacts |
|---|---|---|
| Pull request | quality gate, analyse du diff, Semgrep, Trivy secrets/config, contrôle de migration | JUnit/logs, SARIF/JSON |
| Branche principale | suite serveur complète, E2E rôles, accessibilité, SAST/SCA complets | résultats tests, captures |
| Release | reconstruction du commit, suite complète, deep scan, ZAP staging, SBOM, rapport consolidé | SBOM, rapports signés, décision |
| Périodique | mise à jour des bases CVE puis rescans | tendances et tickets |

Les jobs optionnels doivent retourner explicitement `BLOCKED` si leur service est absent; ils ne doivent pas être masqués par `continue-on-error` sur une release.

## Paramètres externes requis, sans valeur

- URL de staging autorisée.
- Identifiants des comptes de test non productifs et matrice de rôles attendue.
- Chaîne de connexion à une base de qualification isolée.
- Préfixe de tables Dolibarr si différent de `llx_`.
- Comptes financiers de test par mode de paiement.
- Terminaux TakePOS de test et leurs affectations agence/caisse/DAS.
- Comptes bancaires et lignes bancaires de rapprochement de test.
- Dépôt Git cible, plateforme CI, branche principale et politique de protection.
- Stockage d’artifacts, durée de rétention et destinataires des alertes.
- Configuration TLS et politique d’en-têtes HTTP de staging/production.

## Actions humaines exactes pour lever les blocages

1. Ouvrir l’URL locale dans le navigateur intégré et se connecter avec les comptes de test ci-dessus, sans transmettre leurs mots de passe dans un rapport ou un prompt.
2. Rejouer chaque parcours critique et négatif de `MATRICE_TRACABILITE.md`, avec captures et vérification de persistance.
3. Fournir une URL de staging explicitement autorisée puis exécuter OWASP ZAP, sans viser la production.
4. Placer le module dans son dépôt Git propriétaire; activer la CI cible et un runner disposant de PHP, Node, PostgreSQL, Semgrep et Trivy.
5. Relancer Codex Security Deep Scan dans un contexte où le worker reçoit un profil de permissions filesystem géré.
6. Activer Xdebug ou PCOV dans le runner pour mesurer et borner la couverture des branches critiques.
