# Matrice de traçabilité fonctionnelle

Légende des sources : `D` documenté dans le README/manuels, `O` observé dans le code ou le schéma, `T` exécuté par un test. `PASS` ne couvre que la propriété citée; les interactions Web authentifiées restent `BLOCKED`.

| ID | Exigence | Source | Page/API | Rôles autorisés | Contrôle UI | Test | Preuve | Résultat |
|---|---|---|---|---|---|---|---|---|
| F01 | Gérer agences et périmètres utilisateurs | D/O/T | `agence/list.php`, `agence/card.php` | admin fonctionnel, responsable autorisé, scope admin | formulaire générique inspecté; labels/required ajoutés; UI authentifiée non jouée | quick + security | registre, portée directe, IDOR et réaffectation POST | PASS serveur / UI BLOCKED |
| F02 | Gérer les DAS | D/O/T | `das/list.php`, `das/card.php` | agence.read/write | CRUD générique inspecté | quick + schema | objet `das`, table et unicité | PASS structure / UI BLOCKED |
| F03 | Associer les produits aux DAS | D/O/T | `das/list.php?object=productdas` | agence.read/write | CRUD générique inspecté | quick + schema | objet `productdas`, FK agence valide | PASS structure / UI BLOCKED |
| F04 | Ouvrir, exploiter, compter et clôturer une session | D/O/T | `session/my.php`, `session/card.php` | caissier, chef de caisse | formulaires métier inspectés | operational + schema | ouverture, double ouverture refusée, mouvement, comptage, clôture, rollback | PASS serveur / UI BLOCKED |
| F05 | Valider une clôture à plusieurs niveaux | D/O/T | `workflow/my.php`, `session/card.php` | valideurs configurés | actions/token inspectés | operational + security | workflow 2 étapes, décision hors agence refusée | PASS serveur / UI BLOCKED |
| F06 | Fonds initial et comptage par coupures | D/O/T | `session/my.php`, helpers opérations | caissier, chef de caisse | champs numériques min=0 et libellés inspectés | operational + security | solde théorique, comptage et clôture équilibrée | PASS serveur / UI BLOCKED |
| F07 | Encaisser une facture Dolibarr | D/O/T | `mouvement/encaisser.php` | mouvement.cashin dans le périmètre | facture/session/montants/référence/échéance libellés et requis | operational + security | paiement natif, lien, journal unique, session et facture cloisonnées | PASS serveur / UI BLOCKED |
| F08 | Paiements simples et mixtes | D/O/T | `mouvement/encaisser.php` | cashin + mixedpayment selon mode | montants par mode, valeurs >= 0 | operational | un paiement natif par mode et encaissement ultérieur cohérent | PASS serveur / UI BLOCKED |
| F09 | Paramétrer les comptes financiers par mode | D/O/T | `caisse/card.php`, `admin/setup.php` | caisse.write, parametre.write | sélecteurs génériques inspectés | quick + schema | cinq champs de compte installés | PASS structure / UI BLOCKED |
| F10 | Gérer les acomptes clients | D/O/T | `mouvement/acompte.php` | mouvement.cashin dans le périmètre | session contrôlée; formulaire/token inspecté | operational + security | facture d’acompte native créée et encaissée | PASS serveur / UI BLOCKED |
| F11 | Gérer paiements différés et créances | D/O/T | `differe/*`, `creance/list.php` | create/validate/report.read | CRUD et filtres inspectés | quick + schema + operational partiel | objets/schéma; part différée créée dans flux mixte | PARTIAL, cycle UI non joué |
| F12 | Traiter les bons de commande client | D/O/T | `differe/card.php?object=boncommande` | boncommande.validate | CRUD générique inspecté | quick + schema | objet, table, unicité, FK agence | PASS structure / UI BLOCKED |
| F13 | Traiter les BST | D/O/T | `differe/card.php?object=bst` | bst.validate | CRUD générique inspecté | quick + schema | objet, table, unicité, FK agence | PASS structure / UI BLOCKED |
| F14 | Traiter les instructions managériales | D/O/T | `differe/card.php?object=instruction` | instruction.validate | CRUD générique inspecté | quick + schema | champ canonique `instruction_date`, migration vérifiée | PASS structure / UI BLOCKED |
| F15 | Demander, valider et exécuter un remboursement | D/O/T | `remboursement/request.php`, `remboursement/card.php` | request/validate/execute séparés | formulaire/token et portée facture inspectés | operational + security | origine payée, approbation, sortie de caisse, avoir, verrous | PASS serveur / UI BLOCKED |
| F16 | Gérer et consommer des avoirs | D/O/T | `avoir/card.php`, remboursement | avoir.create/validate/use | CRUD générique inspecté | operational + schema | avoir natif lors du remboursement et tracking conforme | PARTIAL, consommation UI non jouée |
| F17 | Détecter et traiter les écarts de caisse | D/O/T | `controle/card.php?object=ecart`, clôture | ecart.manage, session.validate | formulaire générique inspecté | operational + schema | absence d’écart sur clôture équilibrée, intégrité table | PARTIAL, écart critique UI non joué |
| F18 | Contrôle inopiné et gel temporaire | D/O/T | `controle/card.php` | controle.create/freeze | actions et garde serveur inspectées | security + schema | service vérifie périmètre et statut; objet conforme | PARTIAL, scénario nominal UI non joué |
| F19 | Transférer des fonds vers le coffre | D/O/T | `banque/card.php?object=transfert` | transfert.create | transitions/token inspectés | operational + security | brouillon, débit source, réception, débit physique unique | PASS serveur / UI BLOCKED |
| F20 | Préparer, recevoir et suivre un dépôt bancaire | D/O/T | `banque/card.php?object=depotbanque` | depotbanque.create | transitions/token inspectés | operational + security | brouillon, débit, ligne bancaire source, statut | PASS serveur / UI BLOCKED |
| F21 | Rapprocher un dépôt avec une ligne bancaire | D/O/T | `banque/card.php` | depotbanque.reconcile | sélection/action inspectée | operational | rapprochement avec ligne destination correspondante | PASS serveur / UI BLOCKED |
| F22 | Configurer les workflows de validation | D/O/T | `workflow/list.php`, `workflow/card.php` | workflow.write | CRUD générique inspecté | operational + schema | workflow 2 étapes créé et appliqué | PASS serveur / UI BLOCKED |
| F23 | Centraliser « Mes validations » | D/O/T | `workflow/my.php` | valideurs métier | liste/actions et token inspectés | operational + security | portée dérivée et décision inter-agence refusée | PASS serveur / UI BLOCKED |
| F24 | Superviser sessions et opérations en temps réel | D/O/T | `session/supervision.php` | direction, validate, audit.read | filtre agence et tableaux inspectés | security statique + requêtes | requêtes datées PostgreSQL corrigées et filtrées par portée | PARTIAL, rendu/rafraîchissement non joués |
| F25 | Rattacher automatiquement factures/paiements aux contextes | D/O/T | trigger Dolibarr, services | selon opération et périmètre | sans UI propre | operational + schema | contexte unique facture/ticket/paiement, liens sans doublon | PASS serveur |
| F26 | Affecter les terminaux TakePOS | D/O/T | `admin/terminal_mapping.php`, AJAX | caisse.write/parametre.write | formulaire et liste filtrés inspectés | operational + security | mapping créé, endpoint cloisonné | PASS serveur / UI BLOCKED |
| F27 | Bloquer TakePOS sans session si règle active | D/O/T | trigger + `ajax/check_takepos_session.php` | utilisateur TakePOS autorisé | message côté JS, garde serveur | operational + security | terminal non mappé bloqué, probe inter-agence refusée | PASS serveur / UI BLOCKED |
| F28 | Déverser en comptabilité | D/O/T | `admin/accounting.php` | compta.post | liste/action/token inspectés | security statique + schema | session vérifiée par entité et agence, statut protégé | PARTIAL, écriture comptable nominale non jouée |
| F29 | Tableaux de bord par profil | D/O | `index.php`, supervision, rapports, audit | agence/finance/direction/audit selon droit | code et menus inspectés | justification: données réelles et rôles manquants | aucun test UI authentifié disponible | BLOCKED |
| F30 | Rapports limités au périmètre utilisateur | D/O/T | `report/index.php`, `report/transversal.php` | report.read/direction/scope.write | filtres inspectés | security + inspection SQL | helper de portée appliqué aux agrégats et exports | PASS serveur / UI BLOCKED |
| F31 | Exporter CSV et générer PDF | D/O/T | `report/index.php`, `document.php` | report.export et lecture ressource | boutons/liens inspectés | quick + security | modèle PDF chargé, portée PDF, neutralisation formules CSV | PASS sécurité/structure; rendu final BLOCKED |
| F32 | Alertes automatiques périodiques | D/O/T | job `SofAlerte::runScheduledAlerts` | tâche planifiée | sans UI critique | quick + inspection SQL | job horaire déclaré; prédicats date PostgreSQL corrigés | PARTIAL, déclenchement planifié non observé |
| F33 | Piste d’audit détaillée | D/O/T | `audit/list.php`, service journal | audit.read | liste/filtres inspectés | operational + schema | opérations critiques journalisées et table cohérente | PARTIAL, exhaustivité UI non mesurée |
| F34 | Exposer 35 droits distincts | D/O/T | descripteur et permissions Dolibarr | administrateurs | écran natif Dolibarr non joué | quick + inspection | 35 déclarations distinctes, gardes de pages et services | PASS déclaration / attribution UI BLOCKED |

## Inventaire des pages et contrôles

| Surface | Formulaires/contrôles observés | Vérifications effectuées | Limite |
|---|---|---|---|
| CRUD générique `*/list.php` | recherche globale/statut, tri, pagination, création, lecture, édition, suppression, export selon page | échappement HTML, filtre SQL, portée directe/dérivée, liens, valeurs vides, colonnes | interactions, clavier et dimensions BLOCKED |
| CRUD générique `*/card.php` | champs texte, nombres, dates, listes, objets internes, textarea, statut, token CSRF | labels/id/required, types serveur, whitelist table, entité, ID et portée après POST | messages et double-clic BLOCKED |
| Session | ouverture, fonds initial, mouvements, comptage par coupures, clôture, validations | états, doublons, atomicité, soldes, séparation des étapes | navigateur/rechargement/expiration BLOCKED |
| Encaissement/acompte | session, facture/tiers, montants, modes, référence, différé, échéance | token, min numériques, périmètre, persistance native, rollback test | erreurs réseau et rendu mobile BLOCKED |
| Remboursement/avoir | origine, montant, motif, demande, validation, exécution | plafonds, origine payée, portée, transitions, idempotence | confirmations navigateur BLOCKED |
| Banque/contrôle/comptabilité | transfert, dépôt, rapprochement, gel, contrôle, comptabilisation | droits, portée, transitions attendues, verrouillage | parcours UI et erreurs d’infrastructure BLOCKED |
| Rapports/documents | filtres agence/date/type, CSV, PDF | portée SQL, neutralisation CSV, contrôle d’accès PDF | validation visuelle PDF et téléchargement BLOCKED |
| Administration | paramètres booléens, mappings terminal/caisse/DAS, comptes | POST+CSRF, portée, références existantes | écran authentifié BLOCKED |
| Authentification | formulaire Dolibarr local | URL module renvoie HTTP 200 avec formulaire d’identification; aucun contenu module visible | comptes, session expirée/désactivée BLOCKED |

## Cas négatifs et limites couverts côté serveur

- identifiant direct d’une autre agence, réaffectation d’agence dans un POST et appel direct du service ;
- validation de workflow hors périmètre ;
- terminal TakePOS non mappé et probe d’une autre agence ;
- session double, session gelée/inactive et transitions obsolètes ;
- double paiement, double remboursement et concurrence sur références/statuts ;
- tables étrangères au module, entité incorrecte, doublons et références orphelines ;
- rollback complet des fixtures financières.

Les absences de champ, formats Web invalides, double-clic navigateur, retour arrière, session expirée, utilisateur désactivé, affichages responsifs et accessibilité avec technologie d’assistance sont `BLOCKED`, car ils requièrent des comptes de test authentifiés.
