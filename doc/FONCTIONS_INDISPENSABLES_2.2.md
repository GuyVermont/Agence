# Fonctions indispensables — Agence 2.2

Éditeur : **iPowerWorld**
Support : **csa@ipowerworld.net**

Ce document décrit les fonctions d’exploitation ajoutées à la version 2.2 du module PowerERP Agence. Elles sont accessibles dans le menu Agence, sous **Opérations industrielles**, et restent isolées par entité Dolibarr.

## 1. Notifications et escalades

Une règle associe un événement, une sévérité minimale, un niveau d’escalade et une destination. Les canaux disponibles sont :

- `internal` : message persistant dans la boîte d’envoi interne du module ;
- `email` : envoi par le transport de messagerie configuré dans Dolibarr ;
- `sms` : appel JSON vers une passerelle HTTPS configurée par l’administrateur.

Le destinataire peut être une adresse ou un numéro, un identifiant utilisateur, ou un rôle Agence. Le code événement `*` couvre tous les événements. Les messages sont dédupliqués, mis en file, puis repris avec un délai exponentiel en cas d’échec. Un échec définitif alimente le journal technique.

Événements automatiques principaux : `critical_alert`, `validation_overdue`, `collection_reminder1`, `collection_reminder2`, `collection_formal_notice`, `collection_dispute`, `financial_reversal_requested`, `financial_reversal_approved` et `financial_reversal_rejected`.

Les alertes critiques et validations en retard progressent sur trois niveaux. Les délais sont contrôlés par `AGENCE_CRITICAL_ESCALATION_MINUTES` et `AGENCE_VALIDATION_ESCALATION_HOURS`.

## 2. Imports et rapprochements financiers

### Relevés bancaires et opérateurs

Formats acceptés : CSV ou TXT, 20 Mo maximum et 100 000 lignes maximum. Les séparateurs point-virgule, virgule et tabulation sont détectés automatiquement. Les en-têtes français usuels sont normalisés.

Colonnes reconnues :

| Colonne canonique | Alias admis | Obligatoire |
|---|---|---|
| `operation_date` | `date`, `date_operation` | oui |
| `value_date` | `date_valeur` | non |
| `amount` | `montant` | oui |
| `external_ref` | `reference`, `ref_externe` | recommandé |
| `description` | `libelle` | non |
| `counterparty` | `tiers` | non |
| `currency_code` | `devise` | non |
| `payment_mode` | `mode_paiement` | non |

Pour une source `bank`, le compte bancaire Dolibarr est obligatoire. Le moteur recherche une ligne bancaire du même compte, avec montant exact et date proche, puis complète le score avec la référence. Il recherche aussi le dépôt Agence compatible. La confirmation utilise le rapprochement bancaire existant du module.

Pour `orange_money` et `mobile_money`, le moteur recherche un mouvement non encore rapproché, sur le mode `OM`, `MM`, `MOMO` ou `MOBILE`, avec montant et fenêtre de date compatibles. Une ligne opérateur ne peut confirmer qu’un seul mouvement.

Chaque fichier est identifié par son empreinte SHA-256 afin d’empêcher un double import. La confirmation est explicite, verrouillée et limitée au périmètre d’agence de l’utilisateur.

### Référentiels en masse

Trois modes sont disponibles : `create`, `update` et `upsert`. Chaque ligne conserve sa charge utile filtrée, l’objet cible, l’action effectuée et l’erreur éventuelle.

| Référentiel | Colonnes minimales | Colonnes utiles |
|---|---|---|
| Agence | `ref`, `label` | `town`, `country_code`, `address`, `phone`, `email`, plafonds, `status` |
| DAS | `ref`, `label` | `description`, `status` |
| Caisse | `ref`, `label`, `agency_ref` | `allowed_das_refs`, `caisse_type`, `currency_code`, plafonds, `status` |
| Affectation | `user_login`, `agency_ref`, `role_code` | `scope_type`, `scope_value`, `validation_limit`, dates et `status` |

Les relations agence/caisse/DAS sont résolues par référence et les validations croisées du module sont réutilisées.

## 3. Recouvrement des créances

Le traitement planifié ouvre ou met à jour un dossier pour chaque paiement différé échu présentant un solde. Le niveau évolue automatiquement selon l’ancienneté :

- 1 à 6 jours : `reminder1` ;
- 7 à 14 jours : `reminder2` ;
- 15 à 29 jours : `formal_notice` ;
- à partir de 30 jours : `dispute`, avec priorité critique.

Une relance client est mise en file si une adresse valide existe ; sinon une notification interne est créée pour le responsable d’agence. Les appels, e-mails, SMS, visites, mises en demeure, litiges, promesses et clôtures sont documentés. Une promesse exige une date et un montant. Le règlement ou la clôture du paiement différé ferme automatiquement le dossier.

## 4. Erreurs techniques et reprises

Le journal enregistre une référence, l’opération, l’objet, le message, une charge utile expurgée des secrets, le nombre maximal de tentatives et la prochaine date de reprise. Seuls des gestionnaires prédéfinis sont exécutables : notification, rapprochement, recouvrement et comptabilisation de session. Aucun nom de fonction arbitraire issu des données n’est exécuté.

Les reprises automatiques concernent uniquement les opérations sûres et idempotentes. Une reprise manuelle exige le droit dédié. Les accès concurrents sont verrouillés et toutes les requêtes sont limitées à l’entité courante.

## 5. Annulations et contrepassations

Une écriture financière validée n’est jamais supprimée ni modifiée pour simuler une annulation. Le demandeur fournit un motif d’au moins dix caractères et, si disponible, une référence de preuve. Un approbateur distinct accepte ou refuse la demande, sauf si l’auto-approbation a été explicitement activée.

Une acceptation crée un nouveau mouvement immuable : même montant, sens opposé, liens vers l’agence, la caisse, la session, le DAS et les objets Dolibarr, référence de l’écriture d’origine et preuve. La décision et le nouveau mouvement sont inscrits dans l’audit.

## 6. Archivage, conservation et purge

Valeurs par défaut : audits et documents 3 650 jours, erreurs techniques 730 jours. Le traitement planifié peut archiver, mais ne purge jamais de lui-même.

Le parcours administrateur propose :

1. une prévisualisation sans écriture ;
2. l’archivage des éléments échus ;
3. une purge explicite, possible uniquement si `AGENCE_ENABLE_PURGE=1` et si l’opérateur saisit `PURGER`.

Les documents sont déplacés sous `agence/archive/entity_<id>` en conservant leur chemin relatif. Chaque déplacement ou suppression est tracé avec une empreinte SHA-256. Un délai de sécurité supplémentaire d’un an est appliqué avant purge des archives.

## 7. Diagnostic administrateur

La page **Diagnostic Agence** est en lecture seule. Elle contrôle :

- la présence des tables industrielles ;
- les deux tâches planifiées Agence, leur état et leur dernier résultat ;
- les mappings comptables actifs ;
- les caisses actives sans compte espèces ;
- les règles de notification ;
- la configuration de la passerelle SMS ;
- les modes Orange Money et Mobile Money ;
- les erreurs ouvertes et notifications définitivement échouées.

Les états sont affichés en `OK`, `WARNING` ou `ERROR` sans exposer les jetons ni les charges utiles sensibles.

## 8. Paramètres et droits

Paramètres nouveaux : `AGENCE_ENABLE_NOTIFICATIONS`, `AGENCE_SMS_GATEWAY_URL`, `AGENCE_SMS_GATEWAY_TOKEN`, `AGENCE_CRITICAL_ESCALATION_MINUTES`, `AGENCE_VALIDATION_ESCALATION_HOURS`, `AGENCE_AUDIT_RETENTION_DAYS`, `AGENCE_DOCUMENT_RETENTION_DAYS`, `AGENCE_TECH_ERROR_RETENTION_DAYS` et `AGENCE_ENABLE_PURGE`. Les noms sont sur liste blanche et les URL SMS doivent utiliser HTTPS.

Droits nouveaux : gestion des notifications, import et rapprochement, recouvrement, import de masse, erreurs et reprises, demande et approbation de contrepassation, conservation/purge et diagnostic. Les listes et actions métier sont filtrées par entité et périmètre agence.

## 9. Mise en service

1. Réactiver le module Agence pour appliquer les tables, colonnes, droits, menus et deux tâches planifiées.
2. Affecter les nouveaux droits aux groupes, avec séparation entre demandeur et approbateur de contrepassation.
3. Configurer l’expéditeur e-mail Dolibarr et, si nécessaire, la passerelle SMS HTTPS.
4. Créer les règles de notification et vérifier la file interne.
5. Importer un petit relevé de recette, contrôler les propositions puis confirmer manuellement.
6. Prévisualiser la conservation ; laisser la purge désactivée tant que la politique interne n’est pas approuvée.
7. Ouvrir **Diagnostic Agence** et traiter tout état `ERROR` avant production.
8. Exécuter `test/run_quality_gate.ps1` depuis la racine du module.

La qualification automatisée ne déclenche aucun e-mail ni SMS réel. Les canaux externes doivent faire l’objet d’un test contrôlé avec des destinataires de recette avant ouverture en production.
