---
title: 'Bornes de la recherche et pagination de la page Recherche'
type: 'feature'
created: '2026-09-30'
status: 'done'
review_loop_iteration: 0
baseline_commit: '0f31ccec72c69052122e7d9892b4b3b04a074290'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La recherche n'a aucune borne : un terme collé très long ou très riche en mots produit un `LIKE '%…%'` par mot et par colonne (filtre et deux tris), une requête lente qui peut dépasser la limite de paramètres liés ; `tag_id[]` n'est pas borné ; la page Recherche renvoie tous les résultats en un seul payload (un tag courant renvoie presque toute la bibliothèque).

**Approach:** Décision P2 du 2026-09-29 : terme limité à 255 caractères, 20 mots-clés distincts au maximum, 20 tags au maximum, page Recherche paginée par 10 sans plafond sur le total, avec le nombre total de résultats affiché. L'encart d'aide décrit ces limites.

## Boundaries & Constraints

**Always:**
- Au-delà des bornes, on tronque, on ne refuse jamais (pas de 422) : terme coupé à 255 caractères côté serveur (puis re-trim) et renvoyé tronqué dans `search` ; seuls les 20 premiers mots-clés distincts (après fusion des doublons, dans l'ordre de première apparition) sont appliqués ; seuls les 20 premiers `tag_id` distincts valides sont gardés.
- Le champ de saisie porte `maxlength="255"`.
- Quand des mots-clés ont été ignorés, le serveur renvoie `keywordLimitReached: true` et la page affiche sous le champ : « Seuls les 20 premiers mots-clés sont pris en compte. »
- Les liens de page conservent `search` et `tag_id[]` ; une nouvelle recherche (frappe, Entrée, tag) repart de la page 1.
- Règles existantes inchangées : terme vide sans tag ⇒ aucun résultat ; tri pertinence puis `latest()` puis `id desc`.
- La barre de pagination d'`Index.vue` est extraite dans un composant partagé réutilisé par Bibliothèque et Recherche, rendu identique pour la Bibliothèque.

**Ask First:** changer une valeur de borne (255 / 20 / 20 / 10) ou passer d'une troncature à un refus.

**Never:** message pour la borne des tags (seule une URL forgée la dépasse) ; plafond sur le nombre total de résultats ; modifier la sémantique des opérateurs ou la dé-duplication existantes.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Page 1 | 25 documents correspondants | 10 résultats, total 25, liens vers 3 pages | N/A |
| Page 2 | `?search=facture&tag_id[]=3&page=2` | résultats 11–20 ; liens de page contenant `search` et `tag_id[]` | N/A |
| Terme trop long | 300 caractères | recherche sur les 255 premiers, `search` renvoyé tronqué | troncature silencieuse |
| 21 mots-clés | `m1 … m21` | `m21` ignoré, `keywordLimitReached` vrai | message sous le champ |
| Doublons | 20 mots distincts + répétitions | aucun mot ignoré, `keywordLimitReached` faux | N/A |
| Tags en trop | 25 `tag_id` valides | filtre sur les 20 premiers ; `tagFilters` en contient 20 | troncature silencieuse |
| Critères vides | ni terme ni tag | page vide (0 résultat, 0 au total) | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php` -- `search()` L.97-120 : `->get()` → pagination ; troncature du terme ; prop `keywordLimitReached`. `tagIdsFromQuery()` L.172-186 : borner à 20 après `array_unique`. Mettre à jour le docblock (« never paginated »).
- `app/Support/KeywordDatabaseEngine.php` -- `keywordsFrom()` L.154-184 (privé) : appliquer la limite après fusion ; exposer une constante `MAX_KEYWORDS = 20` et un helper public statique (par ex. `exceedsKeywordLimit(string $term): bool`) que le contrôleur utilise pour l'indicateur.
- `resources/js/Pages/Documents/Index.vue` -- `<nav aria-label="Pagination">` L.59-82 : source à extraire.
- `resources/js/Components/Pagination.vue` -- nouveau, prop `links`.
- `resources/js/Pages/Documents/Search.vue` -- prop `documents` devient un paginateur `{ data, links, total }` ; `navigate()` L.84 (n'envoie pas `page` ⇒ page 1) ; ajouter `keywordLimitReached` à `only` L.109 ; commentaire « no pagination » L.79 à corriger ; encart d'aide L.407-418.
- `tests/Feature/SearchDocumentsTest.php` -- les assertions `documents`/`documents.N.id` passent en `documents.data`.
- `resources/js/Pages/Documents/__tests__/Search.spec.js`, `Index.spec.js` -- les montages passent `documents: []` : les adapter à la forme paginée.

## Tasks & Acceptance

**Execution:**
- [x] `app/Support/KeywordDatabaseEngine.php` -- limite de 20 mots-clés distincts + helper public -- borne du coût SQL
- [x] `app/Http/Controllers/DocumentController.php` -- troncature 255, borne des tags, `paginate(10)` avec la query string, paginateur vide pour des critères vides, prop `keywordLimitReached` -- intent
- [x] `resources/js/Components/Pagination.vue` + `Index.vue` -- extraire la barre de pagination -- réutilisation
- [x] `resources/js/Pages/Documents/Search.vue` -- `documents.data`, nombre total (« 1 document trouvé » / « N documents trouvés »), `<Pagination>`, `maxlength`, message de limite relié au champ par `aria-describedby`, aide mise à jour (255 caractères, 20 premiers mots-clés distincts, 10 résultats par page)
- [x] `tests/Feature/SearchDocumentsTest.php` -- adapter l'existant + un test par ligne de la matrice
- [x] `Search.spec.js`, `Index.spec.js` -- adapter les montages ; tester le total, le message de limite et la pagination

**Acceptance Criteria:**
- Given une recherche sur 3 pages, when je clique sur la page 2 puis tape un nouveau mot, then la nouvelle recherche affiche sa page 1.
- Given la Bibliothèque, when elle est paginée, then son rendu de pagination est identique à celui d'avant l'extraction.

## Design Notes

Piège Scout : `Builder::paginate()` ajoute `->appends('query', $this->query)`, donc les liens porteraient un `?query=…` parasite. Neutraliser avec `->withQueryString()->appends('query', null)` (`http_build_query` ignore les valeurs `null`) et le vérifier par un test sur `documents.links`.

Critères vides : renvoyer un `LengthAwarePaginator` vide (`new LengthAwarePaginator([], 0, 10)`) pour que la page reçoive toujours la même forme.

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/SearchDocumentsTest.php tests/Feature/BrowseLibraryTest.php` -- expected: vert
- `npx vitest run resources/js/Pages/Documents/__tests__` -- expected: vert
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart

## Suggested Review Order

**Bornes côté serveur**

- Point d'entrée : terme tronqué à 255, pagination par 10, `query` de Scout neutralisé.
  [`DocumentController.php:122`](../../app/Http/Controllers/DocumentController.php#L122)

- Pagination avec query string ; `appends('query', null)` retire le paramètre parasite de Scout.
  [`DocumentController.php:135`](../../app/Http/Controllers/DocumentController.php#L135)

- Indicateur de mots-clés ignorés renvoyé à la page.
  [`DocumentController.php:143`](../../app/Http/Controllers/DocumentController.php#L143)

- Fusion des doublons d'abord, puis 20 premiers mots-clés distincts.
  [`KeywordDatabaseEngine.php:165`](../../app/Support/KeywordDatabaseEngine.php#L165)

- Helper public utilisé par le contrôleur pour l'indicateur.
  [`KeywordDatabaseEngine.php:174`](../../app/Support/KeywordDatabaseEngine.php#L174)

- 20 tags au plus, silencieusement.
  [`DocumentController.php:213`](../../app/Http/Controllers/DocumentController.php#L213)

**Page Recherche**

- `keywordLimitReached` ajouté au rechargement partiel.
  [`Search.vue:121`](../../resources/js/Pages/Documents/Search.vue#L121)

- `maxlength` et message de limite annoncé (région live).
  [`Search.vue:458`](../../resources/js/Pages/Documents/Search.vue#L458)

- Page au-delà de la dernière : total et liens de page plutôt que « aucun document ».
  [`Search.vue:519`](../../resources/js/Pages/Documents/Search.vue#L519)

- Total trouvé et barre de pagination sous les résultats.
  [`Search.vue:533`](../../resources/js/Pages/Documents/Search.vue#L533)

- Aide mise à jour : limites et pages de 10.
  [`Search.vue:409`](../../resources/js/Pages/Documents/Search.vue#L409)

**Composant partagé**

- Barre extraite d'`Index.vue`, rendu inchangé.
  [`Pagination.vue:1`](../../resources/js/Components/Pagination.vue#L1)
  [`Index.vue:60`](../../resources/js/Pages/Documents/Index.vue#L60)

**Tests**

- Un test par ligne de la matrice I/O.
  [`SearchDocumentsTest.php:630`](../../tests/Feature/SearchDocumentsTest.php#L630)

- Total, liens, page 1, `maxlength`, message de limite, page hors limites.
  [`Search.spec.js:205`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L205)
