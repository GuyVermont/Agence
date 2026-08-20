# Journal des versions

Toutes les évolutions significatives du module Agence sont consignées dans ce fichier.

## [2.3.0] - 2026-08-20

Version d’intégration à l’écosystème PowerERP éditée par iPowerWorld.

### API, événements et BI

- API REST native Dolibarr authentifiée par clé API, avec relecture du compte et des droits à chaque requête ;
- filtrage systématique par entité et périmètre d’agence, limites de pagination et jeux de données sur liste blanche ;
- webhooks CloudEvents-like asynchrones et idempotents, signature HMAC-SHA256, tentatives exponentielles et rejeu contrôlé ;
- événements de clôture, décision de validation, remboursement, dépôt bancaire et alerte ;
- enregistrement de ces cinq événements dans le module Notification standard de Dolibarr ;
- exports BI incrémentaux à curseur précis, sans doublon entre lots PostgreSQL.

### Connecteurs et déploiement

- connecteurs pull JSON pour banques, Orange Money et Mobile Money, authentification `Bearer`, `X-API-Key`, Basic ou aucune ;
- import dans le rapprochement existant, curseur distant, journal de synchronisation et planification par cron ;
- chiffrement réversible Dolibarr des secrets de webhook et de connecteur ;
- paquet de configuration versionné et contrôlé par SHA-256 pour développement, recette et production ;
- exclusion stricte des secrets et remplacement des identifiants techniques par les références métier ;
- simulation d’import, listes blanches, validation croisée et journal des transferts ;
- écran administrateur unique pour webhooks, connecteurs, BI et transport de configuration ;
- scénario transactionnel dédié intégré à la porte qualité locale.

## [2.2.0] - 2026-08-17

Version d’exploitation industrielle éditée par iPowerWorld.

### Exploitation et intégrations financières

- notifications configurables par e-mail Dolibarr, passerelle SMS HTTPS ou canal interne persistant ;
- file de messages dédupliquée, délais exponentiels, erreurs définitives tracées et reprise contrôlée ;
- escalades à trois niveaux des alertes critiques et validations en retard ;
- import CSV bancaire avec suggestions par compte, montant, date et référence, puis rapprochement confirmé ;
- rapprochement des relevés Orange Money et Mobile Money avec les mouvements non encore consommés ;
- imports initiaux et mises à jour de masse des agences, DAS, caisses et affectations ;
- workflow de recouvrement gradué, relances, actions, litiges et promesses de paiement documentées.

### Contrôle, conservation et administration

- demandes de contrepassation motivées, séparation des responsabilités et écriture opposée immuable ;
- politique de conservation des audits, documents et erreurs, prévisualisation et purge à double confirmation ;
- journal des archives avec empreintes SHA-256 et séparation physique par entité ;
- tableau de diagnostic pour le schéma, les tâches planifiées, les mappings, les comptes et les intégrations ;
- dix droits dédiés, menus opérationnels, filtre par périmètre d’agence et migrations additives ;
- test transactionnel complet des dix fonctions indispensables et contrôles de schéma/index étendus.

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
[2.2.0]: https://github.com/GuyVermont/Agence/releases/tag/v2.2.0
[2.3.0]: https://github.com/GuyVermont/Agence/releases/tag/v2.3.0
