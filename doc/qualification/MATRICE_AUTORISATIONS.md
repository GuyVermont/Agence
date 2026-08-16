# Matrice des autorisations et de l’isolation

Le module déclare des droits atomiques; il ne code pas de profils nommés. Les associations de rôles ci-dessous constituent la politique cible à valider avec le propriétaire métier avant recette. Un droit n’autorise l’accès qu’à l’entité Dolibarr courante et aux agences retournées par `SofAgenceService::allowedAgencyIds`, sauf administrateur ou périmètre global explicitement accordé.

## Droits déclarés

| N° | Ressource.action | Capacité | Rôles cibles usuels |
|---:|---|---|---|
| 1 | agence.read | lire les agences | responsable, direction, audit, administrateurs |
| 2 | agence.write | créer/modifier/désactiver | administrateur fonctionnel |
| 3 | caisse.read | lire les caisses | caissier, chef, responsable, contrôle |
| 4 | caisse.write | administrer caisses/terminaux | chef, administrateur fonctionnel |
| 5 | session.open | ouvrir une session | caissier |
| 6 | session.close | clôturer sa session | caissier, chef |
| 7 | session.validate | valider une clôture | chef, responsable, valideur |
| 8 | mouvement.cashin | encaisser | caissier |
| 9 | mouvement.mixedpayment | paiement mixte | caissier autorisé |
| 10 | paiementdiffere.create | créer un différé | caissier, responsable |
| 11 | paiementdiffere.validate | valider un différé | gestionnaire financier, valideur |
| 12 | boncommande.validate | créer/valider un BC client | valideur métier |
| 13 | bst.validate | créer/valider un BST | valideur métier |
| 14 | instruction.validate | créer/valider une instruction | direction/valideur |
| 15 | remboursement.request | demander un remboursement | caissier, responsable |
| 16 | remboursement.validate | valider un remboursement | responsable, gestionnaire financier |
| 17 | remboursement.execute | exécuter le remboursement | gestionnaire financier/chef |
| 18 | avoir.create | créer le suivi d’un avoir | responsable/finance |
| 19 | avoir.validate | valider un avoir | finance/valideur |
| 20 | avoir.use | consommer un avoir | caissier autorisé |
| 21 | ecart.manage | traiter les écarts | chef, contrôleur |
| 22 | controle.create | contrôle inopiné | contrôleur interne |
| 23 | controle.freeze | geler une caisse | contrôleur interne habilité |
| 24 | transfert.create | transfert coffre | chef/finance |
| 25 | depotbanque.create | préparer un dépôt | chef/finance |
| 26 | depotbanque.reconcile | rapprocher un dépôt | gestionnaire financier/comptable |
| 27 | compta.post | déverser en comptabilité | comptable |
| 28 | report.read | lire les rapports | responsable, finance, direction, audit |
| 29 | report.export | exporter les rapports | rôles explicitement habilités |
| 30 | dashboard.direction | tableaux direction | DCF/DAF, direction |
| 31 | dashboard.audit | tableaux contrôle/audit | contrôleur, auditeur |
| 32 | scope.write | gérer les périmètres transversaux | administrateur fonctionnel |
| 33 | parametre.write | administrer les paramètres | administrateur fonctionnel |
| 34 | audit.read | lire la piste d’audit | auditeur, contrôle |
| 35 | workflow.write | administrer les workflows | administrateur fonctionnel |

## Rôle × ressource × action × contexte

| Rôle | Ressource | Actions attendues | Contexte autorisé | Interdictions structurantes |
|---|---|---|---|---|
| Anonyme | toutes | aucune | aucune entité/agence | contenu module, AJAX, document et action métier |
| Administrateur technique | configuration et diagnostic | toutes par administration Dolibarr | entité administrée | usage quotidien des opérations financières à éviter |
| Administrateur fonctionnel | agences, caisses, scopes, paramètres, workflows | CRUD et configuration | entité courante, périmètre global explicite | valider/exécuter ses propres opérations si séparation des tâches requise |
| Responsable d’agence | agence, session, différés, remboursements, rapports | lire, superviser, demander/valider selon délégation | agence(s) affectée(s) | autre agence et administration globale |
| Chef de caisse | caisse, sessions, écarts, transferts, dépôts | exploiter, fermer, valider, transférer | caisses de ses agences | comptabilisation et changement de périmètre |
| Caissier | sa session, mouvements, acomptes | ouvrir/fermer, encaisser, demander | session affectée, agence affectée, lui-même sauf délégation | validation de sa propre demande, autre session/agence |
| Contrôleur interne | contrôles, gels, écarts, audit | créer, geler, traiter, lire | agences mandatées | encaissement et exécution financière |
| Auditeur | audit, rapports, supervision | lecture seule | agences mandatées ou transversal explicite | toute mutation |
| Gestionnaire financier | différés, remboursements, avoirs, dépôts | valider/exécuter/rapprocher selon délégation | agences affectées ou multi-agences explicite | paramètres techniques |
| Comptable | sessions validées, écritures | comptabiliser | agences affectées | modifier l’opération source |
| DCF/DAF | tableaux, rapports, validations | lire/valider selon politique | périmètre transversal explicite | opérations de caisse quotidiennes |
| Valideur workflow | validations assignées | approuver/refuser | cible, étape et agence assignées | étape obsolète, cible hors portée, auto-validation interdite si configurée |
| Direction | tableaux et rapports | lecture/validation exceptionnelle | périmètre direction | administration technique |
| Multi-agences | ressources de son rôle | actions du rôle seulement | union explicite des agences/DAS | accès global implicite |

## Contrôles d’isolation implémentés

- `entity` est imposée dans les requêtes et mises à jour génériques.
- la portée directe utilise `fk_agence`; les validations, sessions et objets liés utilisent une portée dérivée canonique ;
- les listes, fiches, PDF, rapports, exports, endpoints AJAX et services sensibles utilisent la même résolution de portée ;
- une mutation réévalue la portée après application des valeurs POST pour empêcher la réaffectation IDOR ;
- les opérations financières verrouillent l’objet source et contrôlent l’état attendu avant transition ;
- une whitelist limite le helper d’update aux tables `sof_*`.

## Preuves automatiques positives et négatives

`test/security_regression_check.php` vérifie notamment : utilisateur synthétique limité à une agence, lecture autorisée de cette agence, refus d’une autre agence, refus de réaffectation POST, refus de validation workflow inter-agence, refus d’appel direct au service, refus de probe TakePOS inter-agence et refus de transition obsolète.

## Contrôles non prouvés

| Cas demandé | Statut | Action requise |
|---|---|---|
| Rôle légitime/insuffisant dans l’UI | BLOCKED | se connecter avec chaque compte de test et rejouer menus, URL directes et actions |
| Escalade verticale réelle | BLOCKED | comptes sans droits et avec droits partiels |
| Utilisateur désactivé | BLOCKED | désactiver un compte de test puis vérifier toutes ses sessions |
| Session/token expiré | BLOCKED | réduire la durée en qualification et rejouer POST/AJAX |
| Modification de rôle en session | BLOCKED | retirer un droit puis vérifier la prise d’effet immédiate |
| Isolation entre entités Dolibarr | PARTIAL | `entity` inspectée/testée au schéma; recette avec une seconde entité nécessaire |
| Cache, fichiers, jobs et notifications multi-entités | PARTIAL | code inspecté; scénarios dynamiques multi-entités nécessaires |
| Séparation des tâches complète | PARTIAL | droits distincts présents; politique métier et comptes réels à fournir |

Aucun accès inter-agence n’est encore confirmé après correction, mais l’absence d’une campagne UI multi-rôles interdit de conclure à une preuve complète d’autorisation.
