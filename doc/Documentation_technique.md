# Documentation technique - Module `agence`

## Version cible

- Module Agence : 2.2.0
- Dolibarr : 22.0.4
- Base locale detectee : PostgreSQL
- Prefixe : `llx_`
- Module externe : `htdocs/custom/agence`

## Decisions structurantes

- Nom du module : `agence`
- Classe descriptor : `modAgence`
- Droits : `$user->hasRight('agence', '<objet>', '<action>')`
- Tables metier : `llx_sof_*`
- Integration : hooks, triggers, permissions, menus, modeles PDF, exports, API REST si necessaire
- Interdit : modification directe du noyau Dolibarr
- Module remplace : `sofops` (a maintenir desactive)

## Orientation CdC v2

Le cahier des charges v2 impose une approche **Dolibarr-native first** :

- Dolibarr reste le referentiel maitre pour les tiers, contacts, produits/services, devis, commandes, factures, paiements, avoirs, banque/caisse, comptabilite, utilisateurs et documents.
- Le module `agence` ne cree une table propre que lorsque la dimension metier n'existe pas dans Dolibarr.
- Les tables SOFITOUL ajoutent des rattachements agence, caisse, session, DAS, workflow, controle, audit, reporting et perimetres transversaux.
- Les paiements differes, bons de commande, BST et instructions ne sont jamais des paiements encaisses. Ils creent ou suivent une creance, une commande ou une facture Dolibarr.
- Les avoirs comptables restent des avoirs Dolibarr ; `llx_sof_avoir_tracking` ajoute le suivi metier.
- Les paiements reels restent des paiements Dolibarr ; `llx_sof_paiement_link` ajoute le rattachement agence/caisse/session/DAS.

## Architecture operationnelle 2.0

Le service central `class/sofagenceoperations.class.php` porte les transactions metier. Il verifie les droits, le perimetre agence, l'etat de session, le DAS, les modes autorises, la separation des taches et les transitions d'etat avant toute ecriture.

- Un encaissement cree un `Paiement` Dolibarr par composante reelle (especes, carte, cheque, mobile, autre), utilise le compte configure pour ce mode, puis cree les liens et mouvements SOFITOUL dans la meme transaction.
- Un acompte cree et valide une facture Dolibarr de type acompte, puis utilise exactement le meme moteur d'encaissement.
- Un paiement differe ne cree aucun paiement bancaire tant qu'il n'est pas effectivement encaisse.
- Un remboursement approuve cree une sortie de caisse et une facture d'avoir Dolibarr ; le cumul ne peut pas depasser le montant effectivement paye.
- Un versement coffre debite la session source a l'execution et credite la session destinataire a la reception.
- Un depot banque cree la ligne native de sortie sur le compte caisse et n'est rapproche qu'avec une ligne positive compatible sur le compte bancaire cible.
- Les validations sont sequentielles et configurables. L'auto-approbation est interdite par defaut.
- Les hooks et triggers rattachent les operations natives Dolibarr sans doubler celles deja creees par le moteur Agence.

Les pages HTTP ne modifient pas directement les soldes : elles appellent le service central. Les champs calcules ou systeme des objets operationnels sont en lecture seule dans le CRUD generique.

La version 2.2 ajoute trois services bornés à l'entité courante : `SofNotificationService` pour les notifications, escalades, recouvrement et reprises ; `SofImportService` pour les relevés et référentiels CSV ; `SofAgenceIndustrialService` pour le cron consolidé, les contrepassations, la conservation et le diagnostic.

## Tables propres SOFITOUL

Tables de referentiel et perimetre :

- `llx_sof_agence`
- `llx_sof_das`
- `llx_sof_agence_user`
- `llx_sof_role_transversal`
- `llx_sof_parametre`

Tables caisse et controle :

- `llx_sof_caisse`
- `llx_sof_caisse_session`
- `llx_sof_caisse_cloture`
- `llx_sof_caisse_comptage`
- `llx_sof_caisse_ecart`
- `llx_sof_caisse_controle`
- `llx_sof_caisse_validation`
- `llx_sof_caisse_transfert`
- `llx_sof_caisse_depot_banque`
- `llx_sof_caisse_workflow`
- `llx_sof_caisse_alerte`
- `llx_sof_caisse_auditlog`
- `llx_sof_mapping_comptable`

Tables paiements differes :

- `llx_sof_bon_commande_client`
- `llx_sof_bst`
- `llx_sof_instruction_manageriale`
- `llx_sof_paiement_differe`

Tables de liaison/enrichissement Dolibarr :

- `llx_sof_facture_link`
- `llx_sof_paiement_link`
- `llx_sof_commande_link`
- `llx_sof_takepos_link`
- `llx_sof_avoir_tracking`
- `llx_sof_bank_link`
- `llx_sof_product_das`
- `llx_sof_tiers_credit_profile`

Tables d'exploitation industrielle :

- `llx_sof_notification_config`
- `llx_sof_notification_outbox`
- `llx_sof_bank_import`
- `llx_sof_bank_import_line`
- `llx_sof_recouvrement`
- `llx_sof_recouvrement_action`
- `llx_sof_bulk_import`
- `llx_sof_bulk_import_line`
- `llx_sof_technical_error`
- `llx_sof_financial_reversal`
- `llx_sof_archive_log`

## References Dolibarr

Les champs suivants portent les liens vers le referentiel Dolibarr :

- `fk_soc` : tiers/client Dolibarr
- `fk_contact` / `fk_contact_*` : contacts Dolibarr
- `fk_product` : produit/service Dolibarr
- `fk_commande` : commande client Dolibarr
- `fk_facture` / `fk_facture_origin` / `fk_facture_avoir` : factures et avoirs Dolibarr
- `fk_paiement` : paiement Dolibarr
- `fk_bank` : ligne bancaire Dolibarr
- `fk_bank_account` : compte banque/caisse Dolibarr
- `fk_user_*` : utilisateurs Dolibarr

## Couverture fonctionnelle livree

1. Socle et perimetres : agences, DAS, caisses, utilisateurs/agences, roles transversaux, droits et audit.
2. Exploitation : ouverture, encaissement, paiement mixte, acompte, comptage, cloture, ecart et validation.
3. Credit client : paiement differe, BC, BST, instruction, creances, avoirs et remboursements.
4. Controle : workflows multi-niveaux, validations personnelles, controles inopines, gel et alertes.
5. Tresorerie : versement coffre, reception, depot banque, lignes natives et rapprochement.
6. Integration : triggers, hooks, TakePOS, PDF, exports, reporting, supervision et preparation comptable.

Les evolutions ulterieures possibles (API REST publique, scoring risque ou BI externe) sont des extensions et non des pre-requis au fonctionnement quotidien du module.
