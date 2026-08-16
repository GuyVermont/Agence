# Tests rapides du module Agence

Depuis le dossier `htdocs/custom/agence/test`, lancer :

```bash
php quick_check.php
php operational_check.php
```

Le test rapide verifie le chargement du descripteur, des permissions, des menus, du registre CRUD, des classes metier, des helpers de reporting, du modele PDF et la presence des fichiers SQL/tables.

Le test operationnel execute dans une transaction les parcours critiques : session, encaissement mixte, paiement ulterieur sans doublon, acompte natif, remboursement et avoir, versement coffre, depot et rapprochement bancaire, comptage, workflow multi-niveaux et cloture. Toutes ses donnees de recette sont annulees par rollback.
