# Tests rapides du module Agence

Depuis le dossier `htdocs/custom/agence/test`, lancer :

```bash
php quick_check.php
php operational_check.php
php lifecycle_qualification_check.php
php industrial_operations_check.php
php integration_ecosystem_check.php
php translation_check.php
php concurrency_check.php
php entity_isolation_check.php
```

Le test rapide verifie le chargement du descripteur, des permissions, des menus, du registre CRUD, des classes metier, des helpers de reporting, du modele PDF et la presence des fichiers SQL/tables.

Le test operationnel execute dans une transaction les parcours critiques : session, encaissement mixte, paiement ulterieur sans doublon, acompte natif, remboursement et avoir, versement coffre, depot et rapprochement bancaire, comptage, workflow multi-niveaux et cloture. Toutes ses donnees de recette sont annulees par rollback.

Le test de cycle qualifie les transitions différé/litige/régularisation/clôture, l'avoir, le contrôle inopiné, l'écart critique, le rejet/reprise comptable, les alertes, les tableaux par rôle et la révocation à chaud. Le test de concurrence lance deux processus PHP réellement simultanés contre la même ligne. Le test d'isolation injecte une seconde entité logique et prouve l'étanchéité des objets, services, rapports et chemins PDF.

Le test industriel qualifie les notifications internes et la mise en file e-mail/SMS sans émission externe, les escalades, les imports et rapprochements banque/Orange Money/Mobile Money, la création et mise à jour en masse des référentiels, le recouvrement, la reprise d'erreur, la contrepassation, la conservation et le diagnostic. Il s'exécute dans une transaction et annule toutes ses fixtures.

Le test d’écosystème qualifie les tables d’intégration, le chiffrement des secrets, l’idempotence et la signature des webhooks, la pagination BI sans doublon, les connecteurs, l’exclusion des secrets du paquet de configuration, les événements Notification Dolibarr, le contrat de santé REST et le refus HTTP 401 sans authentification.

Le contrôle des traductions vérifie toutes les clés visibles des champs, listes de valeurs, paramètres, menus, permissions et codes métier dans les catalogues français et anglais. Il refuse aussi les doublons et les traductions vides.

La recette navigateur utilise une fixture temporaire :

```bash
php browser_fixture.php setup
php browser_fixture.php disable
php browser_fixture.php restore
php browser_fixture.php revoke
php browser_fixture.php cleanup
```

Après `setup`, ouvrir les URL retournées avec l'utilisateur temporaire, générer le PDF et exporter le CSV. Tester ensuite `disable`, `restore` et `revoke` dans la même session. Toujours terminer par `cleanup`. Les mots de passe temporaires ne doivent jamais être consignés dans un rapport ou un journal versionné.
