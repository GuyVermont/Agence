# Module Dolibarr `agence`

Module PowerERP édité par **iPowerWorld** pour piloter les agences, DAS, caisses et flux financiers dans Dolibarr. La version 2.2 réunit les fonctions auparavant portées par `sofops`, les parcours financiers qualifiés et les fonctions d’exploitation industrielle dans le module unique `agence`.

## Positionnement

`agence` est desormais le module metier unique a conserver. Il orchestre les objets standards Dolibarr sans modifier le coeur : tiers, produits, factures, avoirs, paiements, comptes bancaires, lignes bancaires, utilisateurs, documents et TakePOS restent les referentiels natifs. Les tables `llx_sof_*` ajoutent l'agence, le DAS, la caisse, la session, le workflow, les controles, les liens et l'audit.

Le module `sofops` doit rester desactive afin d'eviter le doublonnage des menus, hooks, triggers et traitements de paiement.

## Fonctions operationnelles

- referentiels agences, DAS, caisses, produits/DAS et perimetres utilisateurs ;
- ouverture, exploitation, comptage par coupures, cloture et validation multi-niveaux des sessions ;
- encaissement de factures Dolibarr, paiements mixtes et comptes financiers par mode ;
- acomptes clients sous forme de factures d'acompte Dolibarr ;
- paiements differes, bons de commande client, BST et instructions manageriales ;
- creances clients, avoirs, demandes et execution controlee des remboursements ;
- controles inopines, gel, ecarts, alertes automatiques et piste d'audit ;
- versements coffre, reception, depots banque et rapprochement avec les lignes bancaires ;
- file « Mes validations », supervision temps reel et comptabilisation par lot ;
- rattachement automatique des factures/paiements et integration TakePOS par terminal ;
- tableaux de bord, rapports filtres par perimetre, exports CSV et documents PDF.
- notifications configurables par e-mail, SMS HTTPS et canal interne, avec file, déduplication, reprises et escalades ;
- import de relevés bancaires, Orange Money et Mobile Money avec rapprochement semi-automatique ;
- relances clients et workflow documenté de recouvrement des créances ;
- imports de masse tracés des agences, DAS, caisses et affectations en création, mise à jour ou `upsert` ;
- journal technique avec reprise contrôlée et charges utiles expurgées des secrets ;
- contrepassations financières à deux niveaux, sans suppression de l’écriture d’origine ;
- archivage, conservation, purge à double confirmation et diagnostic administrateur.

## Installation locale

Copier le module dans `htdocs/custom/agence`, puis activer **Agences SOFITOUL** depuis la page des modules Dolibarr. Une reactivation applique aussi les evolutions additives du schema. Affecter ensuite les droits aux groupes concernes et parametrer agences, DAS, caisses, comptes par mode et workflows.

## Verification

Depuis `htdocs/custom/agence` sous PowerShell :

```powershell
.\test\run_quality_gate.ps1
```

Le quality gate contrôle la syntaxe PHP/JavaScript, l'installation du module, les cycles métier complets, une concurrence avec deux processus simultanés, l'isolation entre deux entités, les régressions de sécurité et la cohérence du schéma. Les tests transactionnels annulent leurs données ; les tests multiprocessus détruisent leurs fixtures après exécution.

## Documentation

- `doc/Manuel_utilisation.md`
- `doc/Documentation_technique.md`
- `doc/Plan_tests_recette.md`
- `doc/QUALIFICATION_INDUSTRIELLE.md`
- `doc/FONCTIONS_INDISPENSABLES_2.2.md`
- `CHANGELOG.md`

Éditeur : iPowerWorld — support : csa@ipowerworld.net.
