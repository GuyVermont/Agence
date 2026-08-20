# Intégrations PowerERP — module Agence 2.3

Éditeur : **iPowerWorld**
Support : **csa@ipowerworld.net**

## 1. Principes de sécurité

L’intégration réutilise l’authentification REST de Dolibarr. Chaque appel recharge l’état actif du compte, ses droits Agence, l’entité courante et ses affectations d’agence. Une clé API ne contourne donc ni une désactivation, ni un retrait de droit, ni un changement de périmètre.

Les lectures utilisent des jeux de données et des champs définis dans le code. Aucun nom de table, champ, tri ou filtre SQL libre n’est accepté. Les listes sont limitées à 500 lignes et les exports BI à 1 000 lignes par lot.

Les URL sortantes doivent être HTTPS, sans identifiants dans l’URL ni fragment. Le client HTTP Dolibarr applique en plus sa protection anti-SSRF. Les secrets sont chiffrés par `dolEncrypt()` et ne sont affichés, journalisés ou exportés sous aucune forme.

## 2. API REST sécurisée

Point d’entrée :

```text
/api/index.php/agence
```

En-tête d’authentification standard :

```text
DOLAPIKEY: <clé API Dolibarr>
```

Principales routes :

| Méthode | Route | Droit Agence | Usage |
|---|---|---|---|
| GET | `/health` | `api.read` ou `diagnostic.read` | état du module et des files |
| GET | `/agencies` | `api.read` | agences du périmètre courant |
| GET | `/cashdesks` | `api.read` | caisses du périmètre courant |
| GET | `/bi/{dataset}` | `bi.export` | lot BI incrémental |
| POST | `/webhooks` | `webhook.manage` | créer ou modifier un endpoint |
| POST | `/webhooks/{id}/replay` | `webhook.replay` | rejouer une livraison terminée |
| POST | `/connectors` | `connector.manage` | créer ou modifier un connecteur |
| POST | `/connectors/{id}/sync` | `connector.sync` | lancer une synchronisation |
| GET | `/configuration/export` | `configtransfer.export` | exporter un paquet sans secrets |
| POST | `/configuration/import` | `configtransfer.import` | simuler ou importer un paquet |

Une réponse `401` signifie que l’authentification ou l’état du compte n’est plus valide. Une réponse `403` signifie que le droit ou le périmètre est insuffisant. Les erreurs de validation sont renvoyées en `400`, les conflits de rejeu en `409` et les erreurs d’un système distant en `502`.

## 3. Webhooks

### Événements

| Code | Déclenchement |
|---|---|
| `cash_closure.completed` | clôture de caisse effectivement enregistrée |
| `validation.decided` | approbation ou rejet d’une validation financière |
| `refund.completed` | remboursement effectivement exécuté |
| `bank_deposit.completed` | dépôt exécuté ou rapproché ; le champ `stage` précise l’étape |
| `alert.created` | création d’une nouvelle alerte opérationnelle |

La charge utile reprend le modèle CloudEvents 1.0 : `specversion`, `id`, `source`, `type`, `time`, `subject`, `datacontenttype` et `data`. `data.entity` et `data.fk_agence` permettent au consommateur de vérifier son routage.

### Signature

Chaque livraison contient :

```text
X-PowerERP-Delivery: <référence de livraison>
X-PowerERP-Event: <code événement>
X-PowerERP-Timestamp: <timestamp Unix>
X-PowerERP-Signature: sha256=<hexadécimal>
```

La signature est calculée ainsi :

```text
HMAC-SHA256(secret, timestamp + "." + corps JSON exact)
```

Le consommateur doit refuser un timestamp trop ancien, recalculer le HMAC sur le corps brut et comparer les signatures en temps constant. L’identifiant `X-PowerERP-Delivery` sert à dédupliquer les traitements.

Une réponse HTTP `2xx` clôture la livraison. `408`, `409`, `425`, `429`, les erreurs `5xx` et les erreurs réseau sont reprises avec délai exponentiel. Les autres `4xx` sont définitifs. Un administrateur autorisé peut rejouer une livraison sans effacer la preuve des tentatives précédentes.

## 4. BI et export incrémental

Jeux de données : `movements`, `sessions`, `refunds`, `deposits`, `alerts`.

Le premier appel omet `cursor`. La réponse contient `rows`, `count`, `has_more` et `next_cursor`. Le consommateur persiste `next_cursor` uniquement après avoir chargé durablement le lot. Le curseur combine la date de modification exacte et l’identifiant de ligne, ce qui évite les doublons lorsque plusieurs écritures ont le même timestamp.

Exemple :

```text
GET /api/index.php/agence/bi/movements?limit=500&fk_agence=12
GET /api/index.php/agence/bi/movements?limit=500&fk_agence=12&cursor=<next_cursor>
```

Le même service produit un CSV depuis l’écran **Agence > Intégrations PowerERP**. Pour une alimentation BI automatisée, utiliser la réponse JSON REST et conserver le curseur par entité, agence et jeu de données.

## 5. Notifications Dolibarr

Les cinq événements Agence sont inscrits dans `llx_c_action_trigger` et ajoutés à la liste gérée par le hook `notification/notifsupported`. Ils apparaissent donc dans l’administration du module Notification Dolibarr et peuvent utiliser ses destinataires, modèles et règles habituels.

Cette intégration complète la file multicanale Agence existante. Le module Notification Dolibarr traite les abonnements Dolibarr ; la file Agence gère les règles opérationnelles internes, e-mail et SMS. Une panne de notification est journalisée mais n’annule jamais une transaction financière déjà validée.

## 6. Connecteurs banques et opérateurs

Types pris en charge : `bank`, `orange_money`, `mobile_money`. Authentifications : aucune, Bearer, en-tête `X-API-Key` ou Basic avec une valeur `user:password` chiffrée.

Le connecteur effectue un `GET` HTTPS. Après le premier passage, il ajoute `cursor=<remote_cursor>`. Le système distant doit répondre :

```json
{
  "transactions": [
    {
      "operation_date": "2026-08-20",
      "value_date": "2026-08-20",
      "amount": "125000",
      "external_ref": "TX-123",
      "currency_code": "XAF",
      "counterparty": "Client",
      "description": "Règlement",
      "payment_mode": "OM"
    }
  ],
  "next_cursor": "opaque-provider-cursor"
}
```

La réponse est limitée à 5 000 transactions. Elle est convertie vers le service d’import existant, qui applique l’idempotence par empreinte et le rapprochement semi-automatique. Le curseur n’avance qu’après un import réussi ou la détection sûre d’un lot déjà importé. Chaque passage est tracé dans `llx_sof_integration_sync`.

Le cron Agence exécute les connecteurs arrivés à échéance et la file webhook toutes les quinze minutes. Un lancement manuel reste disponible dans l’écran d’intégration ou par API.

## 7. Transport de configuration

Le paquet JSON contient :

- paramètres Agence sur liste blanche, sauf les paramètres de type `secret` ;
- mappings comptables ;
- workflows ;
- règles de notification ;
- métadonnées de webhooks et connecteurs, sans leurs secrets ;
- métadonnées de format, version, environnement source et empreinte SHA-256.

Les identifiants de base sont remplacés par les références d’agence, DAS et compte bancaire. L’import les résout dans l’entité cible. Les webhooks et connecteurs doivent être précréés sur la cible avec leurs secrets locaux ; l’import met ensuite à jour leur configuration sans toucher à ces secrets.

Procédure recommandée :

1. exporter depuis `development` ;
2. versionner et faire relire le JSON ;
3. importer en simulation vers `staging` ;
4. corriger les références absentes ;
5. importer réellement en recette et exécuter la qualification ;
6. répéter simulation puis import vers `production` ;
7. vérifier le diagnostic, le cron, les comptes et les intégrations.

Chaque export, simulation et import est tracé dans `llx_sof_config_transfer` avec son empreinte. Toute altération du fichier après export invalide le paquet.

## 8. Exploitation

L’écran **Agence > Intégrations PowerERP** centralise les endpoints, livraisons en échec, connecteurs, synchronisations manuelles, export BI et paquets de configuration. Le tableau **Diagnostic Agence** vérifie également les cinq tables d’intégration, l’activation de l’API REST, les webhooks et les connecteurs.

Qualification locale :

```powershell
.\test\run_quality_gate.ps1
```

Le scénario ciblé est `test/integration_ecosystem_check.php`. Il s’exécute dans une transaction et annule ses données de test.
