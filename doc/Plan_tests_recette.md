# Plan de tests et recette - Module Agence SOFITOUL 2.0

## 1. Installation automatisee

Depuis `htdocs/custom/agence/test`, executer :

```bash
php quick_check.php
php operational_check.php
```

`quick_check.php` controle le descripteur, les droits, menus, classes, modele PDF, registre CRUD, SQL et tables installees. `operational_check.php` cree une recette temporaire dans une transaction PostgreSQL, teste les flux reels puis effectue un rollback complet.

## 2. Referentiels et droits

- Creer une agence active, un DAS actif et une caisse rattachee.
- Configurer un compte caisse et un compte distinct pour chaque mode non-especes utilise.
- Affecter les caissiers et DAS autorises a la caisse.
- Declarer les perimetres locaux et, si necessaire, les roles transversaux dates.
- Verifier qu'un utilisateur hors perimetre ne peut ni lire ni agir sur l'agence.
- Verifier que les champs systeme des objets operationnels ne sont pas modifiables par le CRUD generique.

## 3. Session et encaissements

- Ouvrir une session ; verifier le refus d'une seconde session active sur la meme caisse.
- Encaisser une facture validee en especes puis en paiement mixte.
- Verifier un paiement Dolibarr par composante, le compte financier correct et un seul mouvement SOFITOUL par composante.
- Creer et encaisser un acompte ; verifier la facture native de type acompte.
- Verifier les restrictions de modes du DAS et du rattachement produit/DAS.
- Tenter une operation sans session ouverte, sur caisse gelee ou hors perimetre et verifier le refus.

## 4. Cloture et validation

- Saisir le comptage detaille avec les coupures configurees.
- Cloturer avec et sans ecart et verifier les seuils d'alerte.
- Configurer un workflow a deux niveaux ; verifier que seule la premiere etape est proposee.
- Verifier l'interdiction d'auto-validation par defaut.
- Approuver les deux etapes depuis « Mes validations » et verifier l'etat final.

## 5. Credit, avoirs et remboursements

- Creer et faire progresser un paiement differe, BC, BST et instruction manageriale.
- Verifier qu'aucun de ces documents ne cree un paiement bancaire avant encaissement.
- Ouvrir la liste des creances, filtrer les echeances et encaisser une facture.
- Creer, valider et consommer partiellement un suivi d'avoir sans depasser son solde.
- Demander, approuver puis executer un remboursement ; verifier l'avoir Dolibarr et la sortie de caisse.
- Verifier le refus d'un remboursement superieur au montant paye ou hors perimetre.

## 6. Tresorerie et controle

- Executer un versement coffre, puis confirmer sa reception ; verifier le debit et le credit uniques.
- Preparer et executer un depot banque ; verifier la ligne native negative sur le compte caisse.
- Rapprocher avec une ligne positive du meme montant sur le compte cible ; verifier le refus d'une ligne incompatible ou deja utilisee.
- Executer un controle inopine, tester le gel/degel et documenter les ecarts.
- Executer le detecteur d'alertes deux fois et verifier son idempotence.

## 7. Integrations et restitution

- Associer un terminal TakePOS a une caisse active et verifier le blocage des ventes sans session valide.
- Creer une facture et un paiement standards hors moteur Agence ; verifier leur rattachement automatique sans double journalisation.
- Tester la supervision, les rapports par periode/agence/DAS, les exports CSV et le respect du perimetre.
- Generer les PDF d'agence, caisse, session et operation ; verifier le controle d'acces.
- Tester la comptabilisation par lot et le message explicite lorsque la comptabilite avancee n'est pas activee.

## 8. Non-regression et mise en production

- Confirmer que `sofops` est desactive et qu'aucun de ses hooks ou menus ne subsiste en exploitation.
- Reactiver Agence apres deploiement pour appliquer les migrations additives.
- Rejouer les deux scripts automatises avec PHP CLI.
- Faire une recette manuelle avec les profils caissier, chef de caisse, responsable agence, controle interne et comptabilite.
- Sauvegarder la base avant passage en production et verifier le premier cycle complet ouverture/encaissement/cloture/validation.
