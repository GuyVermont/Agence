# Dossier de qualification du module Agence

Date de qualification : 16 août 2026

Périmètre : `htdocs/custom/agence`

Version qualifiée : `2.0.1`
Décision : **NO GO**, faute de preuve positive sur les parcours Web authentifiés et le DAST de staging. Les contrôles serveur, base de données et SAST réalisables sont verts après correction.

## Livrables

- `RAPPORT_QUALIFICATION.md` : rapport consolidé et décision A–T.
- `MATRICE_TRACABILITE.md` : exigences annoncées, surfaces, tests, preuves et résultats.
- `MATRICE_AUTORISATIONS.md` : droits, rôles, ressources, actions et contextes.
- `SECURITE_MODELE_MENACES.md` : frontières de confiance, référentiels OWASP et findings normalisés.
- `GUIDE_EXECUTION.md` : reproduction locale, scanners, CI cible et actions de déblocage.

Les rapports bruts des scanners ne sont pas versionnés. Ils se trouvent dans `C:\Users\User\AppData\Local\Temp\agence-qualification` et doivent devenir des artifacts de CI lors de l’intégration du module dans un dépôt Git.
