---
title: 'Seeders de volume : 1000 documents et 10 tags'
type: 'chore'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La base de dev est presque vide : impossible de tester la pagination et la recherche avec un volume réaliste.

**Approche :** Un seeder (faker) crée 500 documents `created` et 500 documents `imported`, 10 tags fixes, répartis ainsi : 30 % sans tag, 40 % avec un seul tag, 30 % avec plusieurs tags. Les données actuelles peuvent être écrasées (`migrate:fresh --seed`), décision explicite de l'utilisateur.

</frozen-after-approval>

## Implementation Notes

- `DocumentFactory` : ajout des états `created()` (contenu HTML + texte extrait dérivé, sans fichier ni mime) ; l'état par défaut reste `imported` (fichiers non écrits sur disque : aperçu/téléchargement des importés répondent 404 « source manquante », acceptable pour le but de test volume).
- `Database\Seeders\DocumentSeeder` : 10 tags nommés, 1000 documents, mélange puis répartition exacte 300/400/300 (multi-tags : 2 à 4 tags), pivot `document_tag` écrit en insertion groupée (le seeder n'est pas un chemin applicatif).
- `DatabaseSeeder` appelle `DocumentSeeder`.
- Test Pest `DocumentSeederTest` : comptes et répartition.
