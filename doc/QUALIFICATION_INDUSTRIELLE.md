# Qualification industrielle — Agence 2.1.0

Date de référence : 16 août 2026  
Éditeur : iPowerWorld — csa@ipowerworld.net  
Socle qualifié : Dolibarr 22.0.4, PHP 8.3, PostgreSQL

## Résultat

Les parcours critiques identifiés sont implémentés et qualifiés. La porte qualité locale est entièrement verte. Les contrôles navigateur confirment les téléchargements, le filtrage par rôle et les changements d’autorisation pendant une session réelle.

## Matrice de couverture

| Parcours | Garantie apportée | Preuve de qualification |
|---|---|---|
| Paiement différé | Transitions verrouillées, motif obligatoire, litige préservé par la synchronisation, régularisation sur solde Dolibarr et clôture seulement à zéro | `lifecycle_qualification_check.php` |
| Avoir | Avoir natif validé, même entité et même tiers, plafond et expiration contrôlés, consommation sans dépassement sous verrou | `lifecycle_qualification_check.php` |
| Écart majeur/critique | Décision et motif requis, séparation des responsabilités, clôture de l’alerte associée et audit | `lifecycle_qualification_check.php` |
| Contrôle inopiné | Verrou de session, gel avant recalcul, refus de mouvement pendant le gel, restauration exacte de l’état antérieur | `lifecycle_qualification_check.php` |
| Comptabilisation | Mapping obligatoire, rejet et erreur persistants, compteur de tentatives, reprise et création d’écritures `BookKeeping` | `lifecycle_qualification_check.php` |
| Alertes planifiées | Détection idempotente, clé de déduplication unique par entité et exécution par le runner cron officiel Dolibarr | test de cycle + exécution réelle du job Dolibarr |
| Tableaux par rôle | Six vues distinctes, accès selon les droits et filtrage agence/entité | test de cycle + recette navigateur |
| PDF | Autorisation et périmètre contrôlés, CSRF sur génération, stockage isolé par entité, téléchargement `application/pdf` sans cache | recette navigateur + rendu Poppler A4 contrôlé visuellement |
| CSV | Jeu de données autorisé, périmètre utilisateur, téléchargement navigateur, BOM UTF-8, en-têtes à vide et neutralisation des formules | recette navigateur + test de reporting |
| Droits à chaud | Relecture des droits en base sans détruire les droits des autres modules Dolibarr | test de cycle + recette navigateur |
| Compte/session invalides | Compte désactivé refusé immédiatement ; session terminée redirigée vers l’authentification | test de cycle + recette navigateur |
| Concurrence réelle | Deux processus et connexions distinctes partent de la même version ; un seul gagne, l’autre reçoit un conflit optimiste | `concurrency_check.php` |
| Multi-entité | Lectures, écritures, services, rapports et documents bornés à l’entité Dolibarr courante | `entity_isolation_check.php` |

## Exécution de référence

Depuis `htdocs/custom/agence` :

```powershell
powershell -ExecutionPolicy Bypass -File test/run_quality_gate.ps1 `
  -PhpExecutable "C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe" `
  -NodeExecutable "C:\Program Files\nodejs\node.exe"
```

Résultat attendu : `Agence local quality gate: PASS`.

Le job d’alertes doit aussi être actif dans **Accueil > Outils d’administration > Travaux planifiés**. L’activation du module remet automatiquement en service le job `AgenceAlertDetection`. La clé d’exécution du cron relève de la configuration secrète de l’instance et ne doit jamais être commise.

## Recette navigateur reproductible

1. Exécuter `php test/browser_fixture.php setup` et conserver temporairement les identifiants affichés.
2. Se connecter, ouvrir la fiche agence retournée, cliquer sur **Générer PDF**, puis vérifier le téléchargement.
3. Ouvrir le reporting, vérifier que seules les vues du rôle apparaissent et cliquer sur **Exporter CSV**.
4. Exécuter `disable` pendant que la session est active : la requête suivante doit invalider la session.
5. Exécuter `restore`, se reconnecter, puis exécuter `revoke` : la session reste ouverte mais la page protégée doit répondre **Accès refusé**.
6. Fermer la session puis appeler directement le reporting : le formulaire d’authentification doit être présenté.
7. Exécuter `php test/browser_fixture.php cleanup`.

## Critères d’exploitation

- appliquer les migrations par désactivation/réactivation contrôlée du module après sauvegarde de la base ;
- affecter les permissions par groupes et limiter les droits de validation/comptabilisation ;
- renseigner les mappings comptables et comptes financiers avant mise en production ;
- superviser le job d’alertes, les rejets comptables et la croissance de la piste d’audit ;
- rejouer la porte qualité après toute mise à jour Dolibarr, PHP, PostgreSQL ou du module.
