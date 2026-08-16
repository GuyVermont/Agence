# Journal des versions

Toutes les évolutions significatives du module Agence sont consignées dans ce fichier.

## [2.1.0] - 2026-08-16

Version de qualification industrielle des parcours financiers et de sécurité.

### Parcours métier complétés

- cycle transactionnel du paiement différé : validation, litige, régularisation, solde et clôture tracée ;
- validation et consommation atomique des avoirs Dolibarr, avec contrôle du tiers, du montant, du statut et de l’expiration ;
- décision et clôture auditée des écarts majeurs ou critiques avec séparation des responsabilités ;
- contrôle inopiné sous verrou, gel effectif des écritures et restauration exacte de l’état de caisse ;
- déversement comptable réel, rejet persistant, compteur de tentatives et reprise après correction du mapping ;
- exécution idempotente des alertes par le planificateur Dolibarr et déduplication concurrente ;
- six tableaux de bord filtrés selon les rôles : caissier, agence, recouvrement, audit, comptabilité et direction.

### Sécurité, isolation et documents

- relecture autoritative des droits et du statut utilisateur à chaque opération sensible ;
- refus immédiat après révocation, désactivation ou invalidation de session ;
- verrouillage pessimiste des écritures critiques et test avec deux processus réellement simultanés ;
- isolation stricte par entité pour les objets, services, rapports, écritures et chemins PDF ;
- téléchargement PDF protégé, stockage par entité et modèle iPowerWorld vérifié visuellement ;
- export CSV protégé contre l’injection de formule, avec en-têtes même sans résultat ;
- migrations additives, nouvelles traces métier et porte qualité étendue.

## [2.0.1] - 2026-08-16

Version de référence initiale du dépôt public.

### Fonctionnalités

- gestion unifiée des agences, DAS, caisses, sessions et périmètres utilisateurs ;
- encaissements Dolibarr, paiements mixtes, acomptes, différés, avoirs et remboursements ;
- comptage, clôture, workflows de validation, contrôles, alertes et audit ;
- transferts coffre, dépôts bancaires, rapprochement et comptabilisation ;
- intégration TakePOS, rapports, exports CSV et documents PDF ;
- documentation iPowerWorld et procédures de qualification incluses.

### Sécurité et qualité

- contrôle d’accès par entité et périmètre agence ;
- liste blanche typée des paramètres administrables ;
- validation croisée des relations agence/caisse/DAS ;
- protections CSRF, IDOR, concurrence et mises à jour SQL typées ;
- quality gate local couvrant syntaxe, installation, scénarios opérationnels, sécurité et schéma.

[2.0.1]: https://github.com/GuyVermont/Agence/releases/tag/v2.0.1
[2.1.0]: https://github.com/GuyVermont/Agence/releases/tag/v2.1.0
