# Manuel d'utilisation - Module Agence SOFITOUL

## 1. Objet du manuel

Ce manuel explique comment configurer et utiliser le module Dolibarr externe `agence`.

Le module couvre la gestion operationnelle des agences SOFITOUL, des caisses, des sessions de caisse, des flux financiers rattaches aux agences, des paiements differes, des bons de commande client, des Bons Speciaux de Transport, des instructions manageriales, des avoirs, des remboursements, des controles, des workflows, des alertes, de l'audit trail, des rapports et de la consolidation multi-agences.

Le module respecte une logique **Dolibarr-native first** :

- les tiers restent geres par le module Tiers de Dolibarr ;
- les produits et services restent geres par le module Produits/Services ;
- les commandes restent geres par Dolibarr ;
- les factures, avoirs et paiements restent geres par Dolibarr ;
- les comptes banque/caisse et la comptabilite restent geres par Dolibarr ;
- le module `agence` ajoute les dimensions SOFITOUL absentes du standard : agence, caisse avancee, session, DAS, perimetres, workflow, controle, reporting et audit metier.

Important :

- un bon de commande, un BST ou une instruction manageriale ne sont pas des encaissements ;
- ces objets representent une autorisation, un support de paiement differe, une creance ou une prestation a facturer ;
- un remboursement est une sortie de tresorerie ;
- un avoir est un credit client et ne genere pas automatiquement une sortie de tresorerie.

## 2. Public concerne

Le manuel s'adresse aux profils suivants :

- administrateur Dolibarr ;
- administrateur fonctionnel SOFITOUL ;
- responsable d'agence ;
- responsable adjoint ;
- chef de caisse ;
- caissier ;
- agent commercial ;
- agent operationnel ;
- referent comptable local ;
- controleur interne ;
- auditeur ;
- DCF/DAF ;
- comptable ;
- directeur executif ;
- DG ;
- administrateur systeme.

## 3. Acces au module

Une fois le module active, un menu principal **Agences SOFITOUL** apparait dans Dolibarr.

Les principales entrees de menu sont :

- Tableau de bord agence ;
- Agences ;
- DAS / activites ;
- Caisses ;
- Sessions de caisse ;
- Flux financiers ;
- Paiements differes ;
- Remboursements et avoirs ;
- Controles et audit ;
- Depots banque ;
- Workflows et validations ;
- Statistiques et reporting ;
- Pilotage transversal ;
- Journal d'audit ;
- Configuration.

L'affichage de ces menus depend des droits attribues a l'utilisateur connecte.

## 4. Installation et activation

### 4.1 Emplacement du module

Le module doit etre installe dans :

```text
htdocs/custom/agence
```

Dans l'installation locale Laragon actuelle, le chemin complet est :

```text
C:\laragon\www\dev\htdocs\custom\agence
```

### 4.2 Activation dans Dolibarr

Depuis Dolibarr :

1. Se connecter avec un compte administrateur.
2. Aller dans **Accueil > Configuration > Modules/Applications**.
3. Rechercher le module **Agences SOFITOUL**.
4. Activer le module.
5. Verifier que l'activation cree les tables `llx_sof_*`.
6. Affecter les droits aux groupes utilisateurs.

### 4.3 Verification technique rapide

Depuis la racine Dolibarr :

```bash
php htdocs/custom/agence/test/quick_check.php
```

Le script verifie :

- le chargement du descripteur du module ;
- la declaration des droits ;
- la declaration des menus ;
- les classes metier ;
- le registre CRUD ;
- les helpers de reporting ;
- le modele PDF ;
- la presence des fichiers SQL ;
- la presence des tables principales en base.

Si les tables apparaissent en `WARN`, cela signifie generalement que le module n'a pas encore ete active, ou que l'initialisation SQL doit etre relancee.

## 5. Modules Dolibarr a activer ou verifier

Le module `agence` fonctionne en lien avec plusieurs modules standards Dolibarr.

Modules fortement recommandes :

- Tiers ;
- Produits / Services ;
- Commandes client ;
- Factures client ;
- Avoirs client ;
- Paiements ;
- Banque / Caisse ;
- Utilisateurs et groupes ;
- Documents / GED ;
- Notifications.

Modules utiles selon le contexte :

- Comptabilite avancee ;
- TakePOS ;
- API REST ;
- Projets / analytique si utilise pour le suivi interne ;
- Multicompany si plusieurs entites legales Dolibarr sont gerees.

Le module `agence` ne remplace pas ces modules. Il les enrichit avec les dimensions SOFITOUL.

## 6. Configuration generale

### 6.1 Page de configuration

Acces :

```text
Agences SOFITOUL > Configuration
```

La page de configuration permet d'activer ou de desactiver les briques structurantes :

- perimetres transversaux ;
- audit trail metier ;
- reporting ;
- obligation d'une session ouverte pour les operations de caisse.

### 6.2 Parametres globaux recommandes

Parametres a definir avant exploitation :

- activer l'audit trail ;
- activer le reporting ;
- activer la gestion des perimetres transversaux ;
- rendre obligatoire la session ouverte pour les operations de caisse ;
- definir les groupes utilisateurs ;
- affecter les droits par groupe ;
- creer les agences ;
- creer les DAS ;
- creer les caisses ;
- rattacher les caissiers et responsables ;
- definir les workflows de validation ;
- definir les seuils financiers ;
- definir les mappings comptables ;
- verifier les comptes banque/caisse Dolibarr.

## 7. Droits et habilitations

### 7.1 Principe general

Le module utilise les droits Dolibarr sous la forme :

```php
$user->hasRight('agence', '<objet>', '<action>')
```

Les droits doivent etre attribues aux groupes plutot qu'aux utilisateurs individuellement lorsque c'est possible.

### 7.2 Droits principaux

Droits de referentiel :

- lire les agences ;
- creer, modifier ou desactiver une agence ;
- lire les caisses ;
- creer, modifier ou desactiver une caisse ;
- administrer les parametres ;
- gerer les perimetres transversaux.

Droits caisse :

- ouvrir une session de caisse ;
- cloturer une session ;
- valider une cloture ;
- enregistrer un encaissement ;
- enregistrer un paiement mixte ;
- gerer un ecart de caisse.

Droits paiements differes :

- enregistrer un paiement differe ;
- valider un paiement differe ;
- creer ou valider un bon de commande client ;
- creer ou valider un BST ;
- creer ou valider une instruction manageriale.

Droits avoirs et remboursements :

- demander un remboursement ;
- valider un remboursement ;
- executer un remboursement ;
- creer un suivi d'avoir ;
- valider un suivi d'avoir ;
- utiliser un avoir.

Droits controle :

- realiser un controle inopine ;
- geler une caisse pendant controle ;
- consulter le journal d'audit.

Droits banque et comptabilite :

- creer un versement coffre ;
- creer un depot banque ;
- rapprocher un depot banque ;
- deverser en comptabilite.

Droits reporting :

- voir les rapports ;
- exporter les rapports ;
- voir les tableaux de bord direction ;
- voir les tableaux de bord audit et controle.

### 7.3 Groupes utilisateurs recommandes

Groupe **Administrateur fonctionnel agence** :

- configuration ;
- agences ;
- caisses ;
- workflows ;
- perimetres ;
- reporting ;
- audit.

Groupe **Responsable agence** :

- lecture agence ;
- lecture caisse ;
- supervision sessions ;
- validation cloture ;
- rapports agence ;
- consultation ecarts ;
- consultation paiements differes.

Groupe **Chef de caisse** :

- lecture caisses ;
- ouverture et cloture ;
- validation de certaines operations ;
- comptage ;
- gestion ecarts selon seuil.

Groupe **Caissier** :

- ouverture session ;
- operations de caisse autorisees ;
- saisie comptage ;
- consultation de ses sessions.

Groupe **Controle interne / Audit** :

- controles inopines ;
- gel caisse ;
- audit trail ;
- rapports controle ;
- consultation transversale selon perimetre.

Groupe **DCF/DAF / Comptabilite** :

- paiements differes ;
- depots banque ;
- rapprochement ;
- preparation comptable ;
- tableaux de bord finance.

Groupe **Direction** :

- reporting transversal ;
- tableaux de bord direction ;
- alertes critiques ;
- consultation consolidee.

### 7.4 Regles de separation des roles

Les regles suivantes doivent etre appliquees dans la configuration des droits et workflows :

- un caissier ne doit pas valider sa propre cloture en cas d'ecart ;
- un caissier ne doit pas valider son propre remboursement ;
- un controleur ne doit pas modifier les operations qu'il controle ;
- un administrateur technique ne doit pas valider des operations financieres metier sans habilitation ;
- les utilisateurs transversaux doivent avoir un perimetre explicite ;
- les utilisateurs metier ne doivent pas pouvoir modifier le journal d'audit.

## 8. Perimetres utilisateurs

### 8.1 Objectif

Le perimetre utilisateur definit ou et comment un utilisateur peut intervenir.

Un utilisateur peut etre limite a :

- une agence ;
- plusieurs agences ;
- toutes les agences ;
- un DAS ;
- plusieurs DAS ;
- une region ;
- un groupe de caisses ;
- un type d'operation ;
- un seuil financier.

### 8.2 Perimetres locaux

Les perimetres locaux se configurent avec les rattachements utilisateur/agence.

Exemples :

- un caissier rattache a l'agence de Douala ;
- un chef de caisse rattache a deux caisses ;
- un referent comptable local rattache a une agence et a un DAS ;
- un responsable tourisme rattache a l'agence et au DAS tourisme.

### 8.3 Roles transversaux

Les roles transversaux servent aux fonctions qui interviennent sur plusieurs agences.

Exemples :

- controleur interne sur toutes les agences ;
- auditeur sur une region ;
- DAF sur tous les paiements differes superieurs a un seuil ;
- comptable sur les depots banque ;
- directeur executif sur les validations exceptionnelles.

## 9. Referentiel agences

### 9.1 Acces

```text
Agences SOFITOUL > Agences
```

### 9.2 Informations principales

Une agence contient notamment :

- reference ;
- libelle ;
- ville ;
- pays ;
- adresse ;
- telephone ;
- email ;
- responsable ;
- responsable adjoint ;
- chef de caisse ;
- referent comptable ;
- referent commercial ;
- horaires ;
- DAS autorises ;
- plafonds ;
- seuils d'alerte ;
- regles de validation ;
- regles de cloture ;
- centre analytique ;
- statut.

### 9.3 Statuts

Statuts possibles :

- active ;
- suspendue ;
- fermee ;
- en test ;
- archivee.

Utilisation recommandee :

- **active** : agence exploitable ;
- **suspendue** : agence temporairement bloquee ;
- **fermee** : agence arretee ;
- **en test** : agence utilisee pour parametrage ou formation ;
- **archivee** : agence conservee pour historique.

### 9.4 Bonnes pratiques

- Ne jamais supprimer physiquement une agence exploitee.
- Desactiver ou archiver l'agence si elle ne doit plus etre utilisee.
- Renseigner les responsables avant de lancer les operations.
- Definir les plafonds avant d'ouvrir les sessions de caisse.
- Verifier les comptes financiers associes dans Dolibarr.

## 10. DAS / activites

### 10.1 Acces

```text
Agences SOFITOUL > DAS / activites
```

### 10.2 Objectif

Le DAS permet de suivre les activites :

- billetterie ;
- transport ;
- tourisme ;
- location ;
- evenementiel ;
- mission ;
- autres activites SOFITOUL.

### 10.3 Informations principales

Un DAS contient :

- reference ;
- libelle ;
- description ;
- code comptable ;
- code analytique ;
- regles de validation ;
- regles de remboursement ;
- regles d'avoir ;
- modes de paiement autorises ;
- documents requis ;
- configuration de tableau de bord ;
- statut.

### 10.4 Rattachement produits/DAS

Le module permet de rattacher des produits ou services Dolibarr a un DAS.

Utilisation :

- creer les produits/services dans Dolibarr ;
- creer les DAS dans le module ;
- renseigner les rattachements produit/DAS ;
- utiliser ces rattachements pour le reporting et les controles.

## 11. Referentiel caisses

### 11.1 Acces

```text
Agences SOFITOUL > Caisses
```

### 11.2 Types de caisse

Types possibles selon l'organisation :

- caisse principale ;
- caisse secondaire ;
- caisse billetterie ;
- caisse transport ;
- caisse tourisme ;
- caisse location ;
- caisse evenementiel ;
- caisse temporaire ;
- caisse mobile ;
- caisse coffre ;
- caisse remboursement ;
- caisse TakePOS ;
- caisse urgence ;
- caisse mission ;
- caisse generale.

### 11.3 Informations principales

Une caisse contient :

- agence ;
- reference ;
- libelle ;
- type ;
- devise ;
- compte Dolibarr associe ;
- compte comptable ;
- caissiers autorises ;
- chef de caisse ;
- responsable ;
- plafonds ;
- DAS autorises ;
- modes de paiement autorises ;
- statut.

### 11.4 Regles de gestion

- Une caisse doit etre rattachee a une agence.
- Une caisse inactive ne doit pas recevoir de nouvelle session.
- Une caisse TakePOS doit etre rattachee a un terminal ou a un usage POS.
- Une caisse coffre ne doit pas etre utilisee comme caisse de vente.
- Une caisse remboursement doit etre reservee aux sorties de tresorerie validees.

## 12. Sessions de caisse

### 12.1 Acces

```text
Agences SOFITOUL > Sessions de caisse
```

### 12.2 Objectif

La session de caisse represente la periode controlee pendant laquelle un caissier ou une equipe realise des operations.

Types possibles :

- journaliere ;
- matin ;
- apres-midi ;
- nuit ;
- evenement ;
- mission ;
- exceptionnelle ;
- temporaire ;
- multi-caissiers si autorise.

### 12.3 Informations principales

Une session contient :

- reference ;
- agence ;
- caisse ;
- DAS ;
- caissier ;
- type de session ;
- date/heure ouverture ;
- date/heure cloture ;
- date validation ;
- fonds initial ;
- solde theorique ;
- solde physique ;
- ecart ;
- statut comptable ;
- statut de gel ;
- statut metier ;
- validateur ;
- rapport.

### 12.4 Cycle de vie recommande

1. Creation ou ouverture de session.
2. Exploitation.
3. Comptage.
4. Cloture.
5. Validation.
6. Depot ou transfert si necessaire.
7. Preparation comptable.
8. Verrouillage.

### 12.5 Regles critiques

- Aucune operation de caisse ne doit etre realisee sans session ouverte.
- Une session cloturee ne doit plus etre modifiee.
- Une session comptabilisee doit etre definitivement verrouillee.
- Toute correction doit se faire par operation de regularisation, pas par suppression physique.

## 13. Ouverture de caisse

### 13.1 Donnees a saisir

Lors de l'ouverture :

- agence ;
- caisse ;
- caissier ;
- fonds initial ;
- detail des coupures si disponible ;
- reliquat eventuel ;
- observations ;
- justificatifs si necessaire.

### 13.2 Controles a effectuer

Avant ouverture :

- l'utilisateur est autorise ;
- l'agence est active ;
- la caisse est active ;
- aucune session concurrente non autorisee n'est deja ouverte ;
- la session precedente est cloturee ;
- les ecarts precedents sont traites ou autorises ;
- le fonds initial respecte le plafond ;
- le terminal POS est disponible si applicable.

## 14. Exploitation de caisse

### 14.1 Operations possibles

Le module prevoit le suivi des operations suivantes :

- encaissement facture ;
- encaissement acompte ;
- paiement partiel ;
- paiement mixte ;
- paiement sur reservation ;
- paiement sur billet ;
- paiement sur BST ;
- paiement sur bon de commande ;
- paiement sur instruction manageriale ;
- remboursement ;
- avoir ;
- annulation ;
- transfert coffre ;
- depot banque ;
- regularisation autorisee.

### 14.2 Modes de paiement immediats

Modes prevus :

- especes ;
- carte bancaire ;
- virement ;
- cheque ;
- Mobile Money ;
- Orange Money ;
- paiement electronique ;
- portefeuille interne si applicable ;
- paiement mixte ;
- compensation autorisee ;
- paiement via agence partenaire.

Chaque detail de paiement doit idealement comporter :

- type ;
- montant ;
- reference ;
- date transaction ;
- payeur ;
- operateur ou banque ;
- justificatif ;
- statut.

### 14.3 Lien avec Dolibarr

Le paiement reel reste un paiement Dolibarr.

Le module ajoute :

- agence ;
- caisse ;
- session ;
- DAS ;
- mode detaille ;
- reference transaction ;
- statut de rapprochement ;
- justificatif ;
- audit.

## 15. Paiements differes

### 15.1 Acces

```text
Agences SOFITOUL > Paiements differes
```

### 15.2 Principe

Un paiement differe n'est pas un paiement recu.

Il correspond a une prestation realisee ou autorisee dont le paiement sera obtenu plus tard, via :

- bon de commande client ;
- BST ;
- instruction manageriale ;
- convention client ;
- accord corporate ;
- autorisation exceptionnelle ;
- prise en charge institutionnelle.

### 15.3 Informations suivies

Le paiement differe suit :

- client debiteur ;
- agence ;
- caisse ;
- session ;
- DAS ;
- source ;
- facture liee ;
- commande liee ;
- date operation ;
- description de prestation ;
- montant attendu ;
- montant facture ;
- montant paye ;
- montant restant ;
- date attendue de paiement ;
- relances ;
- litige ;
- cloture.

### 15.4 Statuts recommandes

- brouillon ;
- valide ;
- facture ;
- partiellement paye ;
- paye ;
- en retard ;
- en litige ;
- cloture ;
- annule.

### 15.5 Bonnes pratiques

- Toujours rattacher le paiement differe a un tiers Dolibarr.
- Joindre le justificatif dans la GED Dolibarr lorsque possible.
- Ne jamais enregistrer un paiement differe comme paiement recu.
- Creer ou rattacher la facture Dolibarr des que possible.
- Suivre les retards via reporting.

## 16. Bons de commande client

### 16.1 Objectif

Le bon de commande client autorise une prestation dans une limite de montant, de validite et d'objet.

### 16.2 Informations principales

- numero ;
- date ;
- client ;
- montant autorise ;
- montant consomme ;
- solde ;
- objet ;
- prestation ;
- date de validite ;
- signataire ;
- piece jointe ;
- statut.

### 16.3 Statuts recommandes

- recu ;
- verifie ;
- utilise ;
- partiellement utilise ;
- expire ;
- rejete ;
- facture ;
- paye.

### 16.4 Controles

- verifier le tiers client ;
- verifier le signataire ;
- verifier la validite ;
- verifier le montant disponible ;
- verifier la piece jointe ;
- verifier les plafonds client ;
- bloquer ou alerter si plafond depasse.

## 17. Bons Speciaux de Transport

### 17.1 Objectif

Le BST autorise ou formalise une prestation de transport speciale.

### 17.2 Informations principales

- numero ;
- date ;
- emetteur ;
- beneficiaire ;
- client payeur ;
- trajet ou prestation ;
- montant estime ;
- montant definitif ;
- conditions de facturation ;
- agent ;
- agence ;
- piece jointe ;
- statut.

### 17.3 Statuts recommandes

- emis ;
- valide ;
- consomme ;
- facture ;
- paye ;
- annule ;
- conteste.

### 17.4 Points d'attention

- Un BST consomme doit etre suivi jusqu'a facturation.
- Un BST facture doit etre suivi jusqu'au paiement.
- Un BST conteste doit apparaitre dans les rapports de risque.

## 18. Instructions manageriales

### 18.1 Objectif

L'instruction manageriale documentee permet d'encadrer une prestation autorisee par une personne habilitee, sans paiement immediat.

### 18.2 Informations principales

- reference ;
- emetteur ;
- fonction ;
- date/heure ;
- client ;
- prestation ;
- motif ;
- montant estimatif ;
- montant definitif ;
- niveau d'urgence ;
- piece jointe ;
- validateur final ;
- statut.

### 18.3 Statuts recommandes

- en attente ;
- acceptee ;
- executee ;
- facturee ;
- payee ;
- rejetee ;
- annulee.

### 18.4 Regles

- L'instruction doit etre documentee.
- Elle doit etre validee selon seuil et urgence.
- Elle doit etre rattachee a une facture ou a un paiement differe.
- Elle ne doit jamais etre traitee comme un encaissement.

## 19. Avoirs

### 19.1 Acces

```text
Agences SOFITOUL > Remboursements et avoirs
```

### 19.2 Principe

L'avoir comptable reste un avoir Dolibarr.

Le module ajoute un suivi metier :

- agence ;
- DAS ;
- operation d'origine ;
- montant initial ;
- montant utilise ;
- montant restant ;
- motif ;
- expiration ;
- createur ;
- validateur ;
- statut.

### 19.3 Utilisation d'un avoir

Un avoir peut etre :

- impute sur une facture ;
- utilise comme moyen de reglement ;
- utilise partiellement ;
- suivi jusqu'a epuisement ;
- bloque s'il est expire, annule ou invalide.

### 19.4 Difference avec remboursement

Avoir :

- credit client ;
- pas de sortie immediate de tresorerie ;
- suivi de solde.

Remboursement :

- sortie de tresorerie ;
- validation obligatoire ;
- execution financiere ;
- justificatif et recu.

## 20. Remboursements

### 20.1 Workflow recommande

1. Demande.
2. Motif.
3. Pieces justificatives.
4. Verification de l'operation initiale.
5. Premiere validation.
6. Calcul du montant remboursable.
7. Validation selon seuil.
8. Validation finale si necessaire.
9. Execution.
10. Recu.
11. Comptabilisation.
12. Archivage.

### 20.2 Interdictions

Le module et les procedures doivent empecher :

- double remboursement ;
- remboursement superieur au montant encaisse ;
- remboursement sans motif ;
- remboursement sans validation ;
- remboursement sur caisse cloturee non autorisee ;
- remboursement d'une operation inexistante.

### 20.3 Bonnes pratiques

- Utiliser une caisse remboursement separee si l'organisation le permet.
- Exiger un justificatif.
- Appliquer les seuils de validation.
- Interdire l'auto-validation.
- Conserver l'audit et le recu.

## 21. Cloture de caisse

### 21.1 Assistant de cloture attendu

La cloture doit suivre les etapes suivantes :

1. Arret des operations.
2. Verification des operations en attente.
3. Comptage especes.
4. Detail des coupures.
5. Declaration paiements electroniques.
6. Verification paiements differes.
7. Verification BC/BST/instructions.
8. Verification remboursements.
9. Verification avoirs.
10. Calcul solde theorique.
11. Calcul solde physique.
12. Calcul ecart.
13. Commentaire caissier.
14. Validation responsable.
15. Rapport PDF.
16. Preparation comptable.
17. Verrouillage.

### 21.2 Ecarts

Un ecart doit contenir :

- session ;
- cloture ou controle lie ;
- agence ;
- caisse ;
- type d'ecart ;
- montant theorique ;
- montant physique ;
- montant ecart ;
- criticite ;
- motif ;
- decision de traitement ;
- caissier ;
- validateur ;
- statut.

### 21.3 Regles de validation

- Si aucun ecart : validation simple possible.
- Si ecart mineur : validation chef de caisse ou responsable agence.
- Si ecart critique : validation controle interne, DAF ou direction selon seuil.
- Le caissier concerne ne doit pas valider son propre ecart.

## 22. Controles inopines

### 22.1 Acces

```text
Agences SOFITOUL > Controles et audit
```

### 22.2 Types de controle

- manuel ;
- programme ;
- aleatoire ;
- declenche sur anomalie.

### 22.3 Donnees controlees

- agence ;
- caisse ;
- session ;
- caissier ;
- DAS ;
- solde theorique ;
- solde physique ;
- paiements electroniques ;
- BC ;
- BST ;
- instructions ;
- remboursements ;
- avoirs ;
- annulations ;
- ecarts ;
- observations ;
- signature controleur ;
- signature ou refus du caissier.

### 22.4 Gel de caisse

Lors d'un controle, la caisse peut etre gelee.

Pendant le gel :

- les operations doivent etre suspendues ;
- le comptage doit etre effectue ;
- les anomalies doivent etre documentees ;
- la reprise doit etre autorisee.

## 23. Versements coffre

### 23.1 Objectif

Le versement coffre trace un transfert entre une caisse source et une caisse/coffre destination.

### 23.2 Informations principales

- caisse source ;
- coffre destination ;
- montant ;
- detail coupures ;
- remettant ;
- receptionnaire ;
- date/heure ;
- motif ;
- piece jointe ;
- signature ;
- statut.

### 23.3 Regles

- Le transfert doit etre valide par les deux parties lorsque requis.
- Le montant doit etre coherent avec la session source.
- Le coffre doit etre une caisse de type coffre ou assimilee.

## 24. Depots banque

### 24.1 Acces

```text
Agences SOFITOUL > Depots banque
```

### 24.2 Informations principales

- agence ;
- caisse source ;
- session ;
- montant ;
- devise ;
- compte bancaire Dolibarr ;
- ligne bancaire Dolibarr ;
- date preparation ;
- date depot ;
- date rapprochement ;
- deposant ;
- validateur ;
- numero bordereau ;
- scan bordereau ;
- reference rapprochement ;
- statut.

### 24.3 Cycle recommande

1. Preparation du depot.
2. Validation interne.
3. Depot physique ou bancaire.
4. Scan du bordereau.
5. Rattachement a la banque Dolibarr.
6. Rapprochement.
7. Preparation comptable.

### 24.4 Regle critique

Tout depot banque doit etre rapproche.

## 25. Workflows et validations

### 25.1 Acces

```text
Agences SOFITOUL > Workflows et validations
```

### 25.2 Objectif

Les workflows permettent de configurer les validations selon :

- type d'operation ;
- montant ;
- agence ;
- DAS ;
- mode de paiement ;
- client ;
- niveau de risque ;
- role utilisateur ;
- historique client ;
- historique caissier.

### 25.3 Types de validation

- validation simple ;
- validation multi-niveaux ;
- validation sequentielle ;
- validation parallele ;
- rejet motive ;
- demande de complement ;
- delegation temporaire ;
- urgence.

### 25.4 Bonnes pratiques

- Definir les seuils par type d'operation.
- Ne pas donner de droit de validation globale sans perimetre.
- Tester chaque workflow avec un cas simple.
- Documenter les exceptions.

## 26. Alertes

### 26.1 Types d'alerte

Alertes prevues :

- caisse non cloturee ;
- session ouverte trop longtemps ;
- ecart ;
- ecart critique ;
- remboursement important ;
- avoir important ;
- annulation suspecte ;
- paiement differe sans justificatif ;
- BC expire ;
- BST non facture ;
- instruction non validee ;
- facture differee non payee ;
- client depassant son plafond ;
- depot banque non confirme ;
- controle inopine avec anomalie ;
- modification sensible ;
- acces non autorise.

### 26.2 Traitement

Une alerte doit etre :

- consultee ;
- qualifiee ;
- traitee ;
- cloturee ;
- conservee dans l'historique.

## 27. Audit trail

### 27.1 Acces

```text
Agences SOFITOUL > Journal d'audit
```

### 27.2 Evenements journalises

Le module journalise ou prevoit de journaliser :

- ouverture ;
- cloture ;
- encaissement ;
- paiement differe ;
- ajout BC ;
- ajout BST ;
- ajout instruction ;
- remboursement ;
- avoir ;
- annulation ;
- controle ;
- validation ;
- rejet ;
- depot banque ;
- versement coffre ;
- deversement comptable ;
- modification droits ;
- modification seuil ;
- modification caisse ;
- modification agence ;
- impression ;
- export ;
- telechargement.

### 27.3 Contenu d'une trace

Une trace contient :

- utilisateur ;
- role ;
- agence ;
- caisse ;
- session ;
- action ;
- objet ;
- date ;
- heure ;
- IP ;
- terminal ;
- ancienne valeur ;
- nouvelle valeur ;
- motif ;
- piece jointe ;
- statut.

### 27.4 Regle importante

Les traces d'audit ne doivent pas etre modifiees par les utilisateurs metier.

## 28. Reporting et statistiques

### 28.1 Acces

```text
Agences SOFITOUL > Statistiques et reporting
```

### 28.2 Rapports disponibles

Le module fournit une base de reporting :

- indicateurs globaux ;
- encaissements par agence, caisse et mode de paiement ;
- paiements differes ;
- ecarts de caisse ;
- depots banque ;
- export CSV.

### 28.3 Indicateurs principaux

Indicateurs affiches :

- agences actives ;
- caisses actives ;
- sessions ouvertes ;
- encaissements de la periode ;
- solde paiements differes ;
- depots non rapproches ;
- ecarts ouverts ;
- alertes ouvertes.

### 28.4 Filtre de periode

Le reporting permet de filtrer par :

- date debut ;
- date fin.

Le filtre s'applique aux datasets temporels tels que les encaissements.

### 28.5 Export CSV

Si l'utilisateur a le droit d'export, un bouton **Exporter CSV** est disponible sur les tableaux.

Utilisation :

1. Choisir la periode.
2. Actualiser.
3. Cliquer sur **Exporter CSV**.
4. Ouvrir le fichier dans Excel ou LibreOffice.

## 29. Pilotage transversal

### 29.1 Acces

```text
Agences SOFITOUL > Pilotage transversal
```

### 29.2 Objectif

Le pilotage transversal permet a la direction, au DAF, a l'audit ou au controle interne de suivre plusieurs agences en un seul tableau.

### 29.3 Donnees consolidees

Par agence :

- reference ;
- libelle ;
- ville ;
- statut ;
- nombre de caisses ;
- sessions ouvertes ;
- solde paiements differes ;
- montant des ecarts ouverts ;
- depots non rapproches ;
- alertes ouvertes.

### 29.4 Utilisation

Le tableau doit etre utilise pour :

- identifier les agences a risque ;
- suivre les anomalies ;
- prioriser les controles ;
- suivre les creances ;
- suivre les depots non rapproches ;
- preparer les points de direction.

## 30. Documents PDF

### 30.1 Principe

Le module fournit un modele PDF generique pour les objets SOFITOUL.

Le PDF resume les champs principaux de l'objet :

- agence ;
- caisse ;
- session ;
- reference ;
- montant ;
- statut ;
- dates ;
- utilisateur ;
- notes ;
- informations metier.

### 30.2 Generation

Depuis une fiche :

1. Ouvrir l'objet.
2. Cliquer sur **Generer PDF**.
3. Le document est genere dans l'espace document du module.

### 30.3 Cas d'utilisation

- rapport de session ;
- fiche de controle ;
- justificatif de depot banque ;
- trace de paiement differe ;
- synthese d'avoir ;
- synthese de workflow ;
- fiche agence ou caisse.

## 31. Liaisons avec les objets Dolibarr

### 31.1 Tiers

Les clients restent dans Dolibarr Tiers.

Le module peut rattacher :

- paiements differes ;
- avoirs ;
- bons de commande ;
- BST ;
- instructions ;
- profils credit.

### 31.2 Factures et avoirs

Les factures et avoirs restent dans Dolibarr.

Le module ajoute :

- agence ;
- caisse ;
- session ;
- DAS ;
- statut de suivi ;
- lien avec un paiement differe ;
- reporting.

### 31.3 Paiements

Les paiements reels restent dans Dolibarr.

Le module ajoute :

- details de paiement ;
- rattachement agence/caisse/session ;
- mode detaille ;
- reference transaction ;
- statut de rapprochement ;
- justificatif.

### 31.4 Banque

Les comptes bancaires et lignes bancaires restent dans Dolibarr.

Le module ajoute :

- depot banque ;
- lien agence ;
- statut de rapprochement ;
- bordereau ;
- preparation comptable.

### 31.5 TakePOS

Le module prevoit le rattachement TakePOS :

- terminal ;
- agence ;
- caisse ;
- session ;
- ticket ;
- mode paiement ;
- cloture POS ;
- consolidation journaliere.

## 32. Comptabilite

### 32.1 Principe

Le module ne cree pas une comptabilite parallele.

Il prepare ou rattache les donnees necessaires a la comptabilite Dolibarr.

### 32.2 Statuts prevus

- non comptabilise ;
- en attente ;
- en brouillard ;
- comptabilise ;
- rejete ;
- a corriger ;
- verrouille.

### 32.3 Ecritures types a preparer

- encaissement especes ;
- paiement differe ;
- encaissement ulterieur ;
- remboursement ;
- avoir ;
- ecart manquant ;
- ecart excedent ;
- versement banque.

### 32.4 Mapping comptable

Le mapping comptable permet de definir :

- type d'operation ;
- agence ;
- DAS ;
- mode de paiement ;
- compte debit ;
- compte credit ;
- journal ;
- code analytique ;
- statut.

## 33. Procedure de demarrage recommande

### 33.1 Phase 1 - Parametrage

1. Activer les modules Dolibarr standards requis.
2. Activer le module Agences SOFITOUL.
3. Verifier les tables avec `quick_check.php`.
4. Creer les groupes utilisateurs.
5. Affecter les droits.
6. Creer les agences.
7. Creer les DAS.
8. Creer les caisses.
9. Rattacher les utilisateurs.
10. Definir les workflows et seuils.

### 33.2 Phase 2 - Pilote

1. Choisir une agence pilote.
2. Creer une caisse de test.
3. Creer une session.
4. Saisir des operations fictives.
5. Tester les paiements differes.
6. Tester un depot banque.
7. Tester un controle.
8. Exporter les rapports.
9. Generer les PDF.
10. Valider les droits.

### 33.3 Phase 3 - Mise en production

1. Corriger les parametrages.
2. Former les utilisateurs.
3. Verrouiller les droits.
4. Activer le reporting direction.
5. Suivre les alertes quotidiennement.
6. Faire une revue hebdomadaire des ecarts.
7. Faire une revue mensuelle des paiements differes.

## 34. Utilisation quotidienne par profil

### 34.1 Caissier

Le caissier doit :

1. ouvrir sa session ;
2. verifier le fonds initial ;
3. enregistrer ou rattacher les operations autorisees ;
4. documenter les paiements differes ;
5. signaler les anomalies ;
6. saisir le comptage ;
7. demander la cloture ;
8. ne jamais valider sa propre anomalie.

### 34.2 Chef de caisse

Le chef de caisse doit :

1. superviser les sessions ouvertes ;
2. verifier les comptages ;
3. analyser les ecarts ;
4. valider les clotures selon seuil ;
5. preparer les versements coffre ;
6. signaler les anomalies critiques.

### 34.3 Responsable d'agence

Le responsable d'agence doit :

1. surveiller le tableau de bord agence ;
2. valider les operations selon habilitation ;
3. suivre les paiements differes ;
4. suivre les depots banque ;
5. traiter les alertes ;
6. consulter les rapports journaliers.

### 34.4 Controle interne

Le controle interne doit :

1. lancer les controles inopines ;
2. geler une caisse si necessaire ;
3. documenter les anomalies ;
4. exploiter l'audit trail ;
5. produire les rapports de controle ;
6. remonter les alertes critiques.

### 34.5 Comptabilite / DAF

La comptabilite et le DAF doivent :

1. verifier les depots banque ;
2. rapprocher les depots ;
3. suivre les paiements differes ;
4. suivre les avoirs et remboursements ;
5. preparer le deversement comptable ;
6. controler les mappings comptables.

### 34.6 Direction

La direction doit :

1. consulter la consolidation multi-agences ;
2. surveiller les alertes critiques ;
3. analyser les tendances ;
4. suivre les risques par agence ;
5. suivre les creances et paiements differes ;
6. arbitrer les exceptions majeures.

## 35. Depannage

### 35.1 Le menu Agences SOFITOUL n'apparait pas

Verifier :

- le module est installe dans `htdocs/custom/agence` ;
- le module est active ;
- l'utilisateur a au moins un droit de lecture ;
- le cache Dolibarr a ete vide si necessaire.

### 35.2 Une page affiche une erreur de table absente

Verifier :

- l'activation du module ;
- la presence des tables `llx_sof_*` ;
- le prefixe SQL configure ;
- le resultat de `test/quick_check.php`.

### 35.3 Un utilisateur ne voit pas un menu

Verifier :

- les droits du groupe ;
- les droits directs de l'utilisateur ;
- le perimetre agence ;
- la connexion/deconnexion apres modification des droits.

### 35.4 Un objet est verrouille

Un objet peut etre verrouille si :

- la session est cloturee ;
- la session est validee ;
- la session est comptabilisee ;
- le statut metier interdit la modification.

La correction doit passer par une regularisation, une validation ou une operation inverse, jamais par suppression directe.

### 35.5 Les exports CSV ne sont pas visibles

Verifier :

- le droit `Exporter les rapports` ;
- le navigateur ;
- les restrictions de telechargement ;
- le filtre de periode.

### 35.6 Le PDF ne se genere pas

Verifier :

- les droits de lecture sur l'objet ;
- les droits d'ecriture dans le repertoire documents Dolibarr ;
- la configuration du repertoire de sortie ;
- les logs Dolibarr.

## 36. Regles de securite et de controle interne

Regles a appliquer :

- donner les droits minimaux necessaires ;
- separer saisie, validation et controle ;
- documenter les exceptions ;
- utiliser les pieces jointes ;
- suivre les seuils ;
- verifier les journaux d'audit ;
- ne pas supprimer physiquement les operations sensibles ;
- ne pas modifier le core Dolibarr ;
- utiliser les workflows pour les operations a risque ;
- revoir les droits regulierement.

## 37. Glossaire

Agence :

Perimetre operationnel SOFITOUL regroupant des caisses, utilisateurs, DAS, operations et rapports.

Caisse :

Point de gestion d'operations financieres ou de suivi de flux dans une agence.

Session :

Periode controlee d'exploitation d'une caisse.

DAS :

Domaine d'activite strategique ou activite metier suivie analytiquement.

Paiement differe :

Creance ou prestation a facturer/payer plus tard. Ce n'est pas un paiement recu.

Bon de commande client :

Document client autorisant une prestation dans une limite definie.

BST :

Bon Special de Transport.

Instruction manageriale :

Autorisation documentee emise par une personne habilitee.

Avoir :

Credit client, rattache a la logique d'avoir Dolibarr.

Remboursement :

Sortie de tresorerie, soumise a validation.

Audit trail :

Journal des actions sensibles.

Workflow :

Regle de validation configurable.

Depot banque :

Transfert de fonds vers un compte bancaire, a rapprocher.

Deversement comptable :

Preparation ou generation des ecritures compatibles avec la comptabilite Dolibarr.

## 38. Checklist administrateur avant exploitation

- Module active.
- Tables `llx_sof_*` presentes.
- Groupes utilisateurs crees.
- Droits affectes.
- Agences creees.
- DAS crees.
- Caisses creees.
- Comptes banque/caisse Dolibarr verifies.
- Utilisateurs rattaches a leur perimetre.
- Roles transversaux definis.
- Seuils financiers definis.
- Workflows crees.
- Reporting teste.
- Export CSV teste.
- PDF teste.
- Audit teste.
- Formation utilisateurs realisee.

## 39. Checklist utilisateur quotidienne

- Verifier son agence.
- Verifier sa caisse.
- Ouvrir la session.
- Controler le fonds initial.
- Saisir ou rattacher les operations.
- Documenter les justificatifs.
- Signaler les anomalies.
- Cloturer la session.
- Faire valider si necessaire.
- Consulter les alertes.

## 40. Notes de version fonctionnelle

La version 2.0 est la version operationnelle unifiee du module Agence. Elle reprend les fonctions du module `sofops`, qui doit rester desactive, et les complete dans le perimetre agence :

- moteur transactionnel central avec controles de droits, agence, DAS, caisse et session ;
- paiements Dolibarr reels, mixtes et ventiles vers les comptes propres a chaque mode ;
- acompte client natif, paiements differes, creances, avoirs et remboursement controle ;
- comptage par coupures, cloture et workflow sequentiel multi-niveaux ;
- controles, gel, ecarts, alertes automatiques et audit ;
- versement coffre, reception, depot banque et rapprochement natif ;
- integration TakePOS par terminal et rattachement automatique des flux standards ;
- supervision, validations personnelles, reporting, CSV, PDF et comptabilisation par lot.

Les factures, paiements, avoirs et lignes bancaires restent des objets Dolibarr standards. Agence ajoute leur contexte operationnel et ne modifie aucun fichier du noyau.

Avant mise en production, reinitialiser le module depuis l'administration, configurer les comptes par mode de chaque caisse, renseigner les modes autorises des DAS/produits, affecter les perimetres utilisateurs et executer les scripts `quick_check.php` et `operational_check.php` depuis le dossier `test`.
