# Journal des versions

Toutes les évolutions significatives du module Agence sont consignées dans ce fichier.

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
