---
title: 'Recherche par mots-clés avec opérateurs, titre prioritaire et indicateur de chargement'
type: 'feature'
created: '2026-09-29'
status: 'done'
route: 'one-shot'
baseline_commit: '63e300f37fd00266d4ddb0bcf7d1bd4ef6ac3e23'
implementation_commit: '9b2497dde7582d12ed33086702440223d9ec47cb'
---

# Recherche par mots-clés avec opérateurs, titre prioritaire et indicateur de chargement

> Spec rédigée a posteriori (audit du 2026-09-29) : le changement a été développé hors `bmad-build`. Elle décrit ce qui a été livré dans `9b2497d`, sans revue adversariale. Voir § Écarts relevés à l'audit.

## Intent

**Problem:** Le driver Scout `database` cherchait le terme entier comme une seule chaîne `LIKE '%…%'` : « cubiscan speed » ne trouvait que cette séquence exacte. Le titre n'était pas indexé, si bien qu'un document n'était pas trouvé par son propre nom quand son contenu ne le répétait pas. Enfin, la recherche partait à chaque pause de frappe de 300 ms, sans retour visuel quand la réponse tardait.

**Approach:** Un moteur `App\Support\KeywordDatabaseEngine`, dérivé de `Laravel\Scout\Engines\DatabaseEngine`, branché uniquement sur `Document` via `searchableUsing()` (aucune variable d'environnement modifiée, aucun autre modèle touché). Le terme est découpé en mots-clés : au moins un mot suffit par défaut (OU), `+mot` est obligatoire (ET), `-mot` est exclu, et une `"expression exacte"` entre guillemets forme un seul mot-clé. `title` rejoint les champs indexés et passe en tête du classement. Côté page Recherche : debounce porté à 500 ms, Entrée qui lance la recherche sans attendre, encart d'aide sur les opérateurs, message « Recherche en cours… » au-delà de 300 ms. AD-8 est amendée en conséquence (`[AMENDED 2026-09-29]`).

## Boundaries & Constraints

**Always:**
- Un seul chemin de requête (AD-8) : la recherche passe toujours par `Document::search()`, les filtres tag par le même callback `query()`.
- Échappement `LIKE` (`%`, `_` et le caractère d'échappement `!`) déclaré explicitement sur chaque clause, SQLite (tests) n'ayant pas de caractère d'échappement par défaut.
- `coalesce(colonne, '')` pour qu'une colonne `NULL` (document sans pièce jointe) ne transforme jamais un `not (...)` d'exclusion en `NULL`.
- Classement : d'abord le nombre de mots-clés (obligatoires + optionnels) trouvés dans le titre, puis dans l'ensemble des champs indexés ; le tri de l'appelant (`latest()`) ne départage que les égalités.

**Never:**
- Les noms de tags ne sont jamais un terme de recherche : les tags restent un filtre.
- Un terme sans mot-clé positif (uniquement des `-mot`, ou un `+`/`-` isolé) ne renvoie jamais toute la bibliothèque : il ne renvoie rien.
- Un mot-clé répété (insensible à la casse) ne compte jamais deux fois dans le classement.

## I/O & Edge-Case Matrix

| Scenario | Input | Expected Output |
|----------|-------|-----------------|
| Plusieurs mots | `cubiscan speed` | Documents contenant au moins un des deux mots, ceux qui contiennent les deux en premier |
| Mot obligatoire | `+cubiscan +speed` | Seulement les documents contenant les deux mots |
| Obligatoire + optionnel | `+cubiscan speed` | Documents contenant « cubiscan » ; « speed » ne sert qu'au classement |
| Exclusion | `cubiscan -speed` | Documents contenant « cubiscan » mais pas « speed », y compris dans leurs pièces jointes |
| Expression | `"cubiscan speed"` | Seulement cette séquence exacte |
| Titre | Mot présent dans le titre d'un document et dans le contenu d'un autre | Le document dont le titre correspond passe en premier |
| Pièce jointe | Mot présent uniquement dans une pièce jointe | Trouvé, et compté dans le classement |
| Jokers | `100%` ou `a_b` | Correspondance littérale |
| Rien de positif | `-speed`, `+`, `-` | Aucun résultat |
| Frappe | Saisie puis pause | Recherche 500 ms après la dernière frappe ; Entrée la lance tout de suite, sans seconde requête ensuite |
| Réponse lente | Requête en cours depuis plus de 300 ms | « Recherche en cours… » à la place des résultats ; jamais affiché sous 300 ms ; une requête annulée ne masque pas le message de la suivante |

## Code Map

- `app/Support/KeywordDatabaseEngine.php` -- nouveau moteur : découpage des mots-clés, clauses `LIKE`, classement.
- `app/Models/Document.php` -- `title` ajouté à `toSearchableArray()`, `searchableUsing()` branche le moteur avec `priorityColumns: ['title']`.
- `app/Http/Controllers/DocumentController.php` -- `search()` passe le terme brut (l'échappement a migré dans le moteur).
- `resources/js/Pages/Documents/Search.vue` -- délais, Entrée, encart d'aide, placeholder, indicateur de chargement.
- `_bmad-output/planning-artifacts/architecture/.../ARCHITECTURE-SPINE.md` -- AD-8 amendée.
- Tests : `tests/Feature/SearchDocumentsTest.php`, `resources/js/Pages/Documents/__tests__/Search.spec.js`.

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/SearchDocumentsTest.php` -- expected: tous verts
- `npx vitest run resources/js/Pages/Documents/__tests__/Search.spec.js` -- expected: tous verts

## Écarts relevés à l'audit

- Aucune mesure ne vérifie NFR2 (moins d'1 s sur ~350 documents) avec le nouveau classement. Chaque mot-clé est évalué par `LIKE` sur chaque colonne dans le filtre et dans les deux tris, sans index utilisable.
- Revue adversariale menée le 2026-09-29 (`bmad-code-review` sur `f6456b3..9b2497d`). Corrigé :
  - un mot répété avec des opérateurs contradictoires garde désormais le plus strict (exclu, puis obligatoire, puis facultatif), quel que soit l'ordre ;
  - tests ajoutés pour l'échappement de `_` et de `!`, pour plusieurs mots-clés combinés à un filtre tag, et pour un terme entre guillemets commençant par `-` ;
  - l'encart d'aide décrit tout le comportement, garde IME sur Entrée, et le chargement n'est plus annoncé deux fois.

  Différé dans `deferred-work.md` : plafond de mots-clés, comparaison en sous-chaîne, casse et accents selon la base, cas limites du découpage, colonnes prefix/fulltext ignorées.
- Seconde revue du 2026-09-29, sur les corrections ci-dessus. Corrigé :
  - les mots qui ne diffèrent que par les accents fusionnent (« ete » et « été ») ;
  - l'encart d'aide est repliable (replié par défaut, choix mémorisé dans `localStorage`) et documente aussi les expressions entre guillemets, les opérateurs empilés, le filtre par tag, le classement par mots-clés distincts et les PDF scannés ;
  - le garde IME couvre Safari (`keyCode` 229).

  Différé : indexation des mots mis en forme en partie et des entités HTML (`deriveExtractedText()`), région `aria-live` qui englobe les résultats, absence de message pour une recherche contradictoire.
- Documents de planification réalignés le 2026-09-29 : PRD (FR6), `epics.md` (FR6, UX-DR7, stories 1.6 et 3.4), `EXPERIENCE.md` (Recherche, Barre de recherche, Recherche en cours).

## Suggested Review Order

**Moteur de recherche**

- Point d'entrée : pourquoi un moteur dérivé plutôt qu'un nouveau driver, et sémantique des opérateurs.
  [`KeywordDatabaseEngine.php:31`](../../app/Support/KeywordDatabaseEngine.php#L31)

- Découpage du terme : expressions entre guillemets, `+`/`-` isolés ignorés, doublons fusionnés en gardant l'opérateur le plus strict.
  [`KeywordDatabaseEngine.php:145`](../../app/Support/KeywordDatabaseEngine.php#L145)

- Clauses : obligatoires en ET, optionnels en OU seulement sans obligatoire, exclusions ; terme vide → aucun résultat.
  [`KeywordDatabaseEngine.php:71`](../../app/Support/KeywordDatabaseEngine.php#L71)

- Classement : titre d'abord, puis tous les champs indexés.
  [`KeywordDatabaseEngine.php:119`](../../app/Support/KeywordDatabaseEngine.php#L119)

- Échappement `LIKE` portable SQLite/MySQL.
  [`KeywordDatabaseEngine.php:189`](../../app/Support/KeywordDatabaseEngine.php#L189)

**Branchement sur le modèle**

- Titre indexé et moteur branché sur `Document` uniquement.
  [`Document.php:73`](../../app/Models/Document.php#L73)
  [`Document.php:85`](../../app/Models/Document.php#L85)

- Le contrôleur passe désormais le terme brut.
  [`DocumentController.php:108`](../../app/Http/Controllers/DocumentController.php#L108)

**Page Recherche**

- Délais de frappe et d'affichage du message de chargement.
  [`Search.vue:34`](../../resources/js/Pages/Documents/Search.vue#L34)

- Identifiant de visite : une requête annulée ne masque pas le message de la suivante.
  [`Search.vue:45`](../../resources/js/Pages/Documents/Search.vue#L45)

- Encart d'aide repliable, Entrée (hors saisie IME), message de chargement.
  [`Search.vue:236`](../../resources/js/Pages/Documents/Search.vue#L236)
  [`Search.vue:381`](../../resources/js/Pages/Documents/Search.vue#L381)
  [`Search.vue:412`](../../resources/js/Pages/Documents/Search.vue#L412)

**Tests**

- Opérateurs, classement, pièces jointes, jokers, termes sans mot-clé positif.
  [`SearchDocumentsTest.php:286`](../../tests/Feature/SearchDocumentsTest.php#L286)

- Délai de frappe, Entrée, apparition et masquage du message de chargement.
  [`Search.spec.js:142`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L142)
