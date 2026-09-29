---
title: 'Recherche : encart d''aide animé et reformulé, filtre par tag utilisable seul'
type: 'feature'
created: '2026-09-29'
status: 'done'
baseline_commit: '786060a181a21e5a889770248e100ff6188bfea6'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** L'encart « Comment fonctionne la recherche ? » s'ouvre et se ferme d'un coup (sans animation, contrairement à la sidebar), sa hiérarchie visuelle est incohérente (le titre du bouton n'est pas plus gros que ses sous-titres), et les explications d'opérateurs sont des phrases longues peu lisibles. Surtout, sélectionner un tag sans mot-clé n'affiche rien, alors que l'utilisateur veut pouvoir parcourir les documents d'un tag.

**Approach:** Page Recherche : dépliage animé en hauteur, titre de l'encart plus marqué que ses sous-titres, sous-titres en questions quand c'est pertinent, opérateurs reformulés en fragments sans majuscule reliés par une icône flèche, phrase de l'expression exacte raccourcie. Serveur : un terme vide avec au moins un tag renvoie les documents portant l'un des tags, du plus récent au plus ancien, toujours via `Document::search()` (AD-8 amendée).

## Boundaries & Constraints

**Always:** un seul chemin de requête (`Document::search()` + `applyFilters()` dans le callback `query()`) ; le contenu replié reste hors de l'ordre de tabulation et de l'arbre d'accessibilité ; l'état replié/déplié reste mémorisé (clé `bmad-demo-search-help-open`) ; animation ~200 ms comme la sidebar.

**Ask First:** toute modification de la sémantique des opérateurs ou du classement.

**Never:** terme vide sans tag → jamais la bibliothèque entière ; un terme sans mot-clé positif (`-speed`, `+`) continue de ne rien renvoyer, même avec un tag ; pas de nouvelle dépendance (icône en SVG inline).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Tag seul | `?tag_id[]=3`, terme vide | Documents portant le tag 3, plus récent d'abord | N/A |
| Plusieurs tags seuls | `?tag_id[]=3&tag_id[]=4` | Documents portant 3 ou 4, chacun une fois | N/A |
| Rien | ni terme ni tag | `documents: []`, état neutre | N/A |
| Espaces seuls + tag | `?search=%20%20&tag_id[]=3` | Comme « Tag seul » | N/A |
| Exclusion seule + tag | `?search=-speed&tag_id[]=3` | Aucun résultat (règle inchangée) | N/A |
| Tag sans document | tag seul, aucun document ne le porte | Message « Aucun document ne correspond à votre recherche. » | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php:95-118` -- `search()` : aujourd'hui `$search === ''` ⇒ `[]` ; devient « terme vide ET aucun tag ».
- `app/Support/KeywordDatabaseEngine.php:71-99` -- `addTextSearchConstraints()` : `keywordsFrom('')` ⇒ `$ranked === []` ⇒ `1 = 0`. Le parent Scout (`vendor/laravel/scout/src/Engines/DatabaseEngine.php:272`) sort sans contrainte si `blank($builder->query)` — reprendre ce garde-fou en tête pour qu'un terme vide ne filtre pas.
- `resources/js/Pages/Documents/Search.vue:236-369` -- encart d'aide (`v-if="isHelpOpen"`, `id="search-help"`, bouton `aria-controls`) ; `:425` condition du message « aucun résultat » (`trimmedSearchTerm` seul).
- `resources/js/Components/Sidebar.vue:98,238` -- référence d'animation (`transition-[width] duration-200`, chevron `transition-transform`).
- `resources/js/Pages/Documents/__tests__/Search.spec.js:105-116,293-330` -- tests « tag seul reste neutre » et encart (`#search-help` absent quand replié) à adapter.
- `tests/Feature/SearchDocumentsTest.php:53-70` -- test « tag seul ⇒ vide » à inverser.
- `_bmad-output/planning-artifacts/architecture/*/ARCHITECTURE-SPINE.md:91-96` -- AD-8 à compléter (changelog).

## Tasks & Acceptance

**Execution:**
- [x] `app/Support/KeywordDatabaseEngine.php` -- retour anticipé sans contrainte si le terme est vide (après trim) -- permettre le parcours par tag sur le chemin unique.
- [x] `app/Http/Controllers/DocumentController.php` -- n'interroger que si terme non vide ou tag sélectionné -- terme vide sans tag reste neutre.
- [x] `resources/js/Pages/Documents/Search.vue` -- (1) contenu toujours rendu dans un conteneur `grid` animé `grid-rows-[0fr]`↔`[1fr]` (`transition-[grid-template-rows] duration-200`, enfant `overflow-hidden`), `inert` + `aria-hidden` quand replié, chevron `duration-200` ; (2) libellé du bouton dans un `h2` en `text-base font-semibold`, sous-titres en `h3` `text-sm font-medium` ; (3) « Où et comment les mots sont cherchés ? » ; (4) opérateurs : `dt` = code + flèche droite SVG (`M5 12h14`, `m12 5 7 7-7 7`) alignée, `dd` = fragment en minuscule sans point final (voir Design Notes) ; (5) section tag : « Sans mot-clé, les documents portant un des tags sont affichés, du plus récent au plus ancien. » ; (6) message « aucun résultat » aussi quand un tag est sélectionné.
- [x] `tests/Feature/SearchDocumentsTest.php` -- remplacer le test tag seul, ajouter ni-terme-ni-tag, espaces + tag, exclusion seule + tag.
- [x] `resources/js/Pages/Documents/__tests__/Search.spec.js` -- adapter tests encart (`inert`/`aria-expanded` au lieu de l'absence du nœud) et tag seul (message si vide).
- [x] `ARCHITECTURE-SPINE.md` -- AD-8 : règle et changelog 2026-09-29 « terme vide + tag ⇒ documents du tag ».

**Acceptance Criteria:**
- Given l'encart replié, when on clique le bouton, then il se déplie en ~200 ms et le chevron pivote ; replié, aucun lien/élément de l'encart n'est focusable au clavier.
- Given l'encart déplié, then le titre « Comment fonctionne la recherche ? » est visuellement plus gros que chaque sous-titre.
- Given la liste des opérateurs, then chaque ligne montre code → flèche → explication commençant par une minuscule, flèches alignées en colonne.

## Design Notes

Explications d'opérateurs cibles :
- `cubiscan speed` → au moins un des mots (OU)
- `+cubiscan +speed` → tous les mots précédés de `+` (ET)
- `+cubiscan speed` → « cubiscan » obligatoire, « speed » fait seulement remonter les documents qui le contiennent
- `cubiscan -speed` → « cubiscan », sauf les documents contenant « speed » (pièces jointes comprises)
- `"cubiscan speed"` → cette suite exacte, espace compris, sans retour à la ligne entre les mots ; accepte `+` et `-` (`-"mode test"`)
- `speed -speed` → le plus strict l'emporte (`-`, puis `+`, puis sans opérateur) : ici aucun résultat

Les précisions retirées vont dans « Cas particuliers » (guillemet non fermé → jusqu'à la fin de la saisie) et la puce « intérieur d'un mot » devient « Un mot ou une expression… ». Espaces en bord d'expression ignorés : retiré (évident).

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/SearchDocumentsTest.php` -- vert
- `npx vitest run resources/js/Pages/Documents/__tests__/Search.spec.js` -- vert
- `vendor/bin/pint --dirty --format agent` -- propre

## Suggested Review Order

**Recherche par tag seul (serveur)**

- Point d'entrée : terme vide sans tag reste neutre, sinon chemin unique `Document::search()`
  [`DocumentController.php:106`](../../app/Http/Controllers/DocumentController.php#L106)

- Même garde que le moteur Scout d'origine : terme vide ⇒ aucune contrainte texte
  [`KeywordDatabaseEngine.php:78`](../../app/Support/KeywordDatabaseEngine.php#L78)

- Départage par `id` pour un ordre stable à `created_at` égal (revue)
  [`DocumentController.php:112`](../../app/Http/Controllers/DocumentController.php#L112)

**Encart d'aide (UI)**

- Dépliage animé 0fr↔1fr, `inert` quand replié, respect de `prefers-reduced-motion`
  [`Search.vue:305`](../../resources/js/Pages/Documents/Search.vue#L305)

- Titre de l'encart en `h2` plus marqué que les `h3`
  [`Search.vue:274`](../../resources/js/Pages/Documents/Search.vue#L274)

- Opérateurs en données : fragments en minuscule, code inline
  [`Search.vue:169`](../../resources/js/Pages/Documents/Search.vue#L169)

- Ligne code → flèche ; flèche masquée sous `sm` (revue)
  [`Search.vue:344`](../../resources/js/Pages/Documents/Search.vue#L344)

- Textes tag et exclusions alignés sur la nouvelle règle
  [`Search.vue:403`](../../resources/js/Pages/Documents/Search.vue#L403)

**Message « aucun résultat »**

- Basé sur les critères renvoyés par le serveur : pas de flash avant la réponse
  [`Search.vue:232`](../../resources/js/Pages/Documents/Search.vue#L232)

**Périphériques**

- AD-8 amendée : terme vide + tag ⇒ documents du tag
  [`ARCHITECTURE-SPINE.md:91`](../planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md#L91)

- Tests serveur de la matrice (tag seul, plusieurs tags, espaces, exclusion + tag, égalité)
  [`SearchDocumentsTest.php:55`](../../tests/Feature/SearchDocumentsTest.php#L55)

- Tests encart (`inert`) et message tag seul
  [`Search.spec.js:108`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L108)
