---
title: 'Recherche : chips de filtre par tag sans doublon'
type: 'bugfix'
created: '2026-09-29'
status: 'done'
baseline_commit: '4961c100af49d62306589b95c216499990066890'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur la page Recherche, chaque tag sélectionné dans « Filtrer par tag » s'affiche deux fois : une chip grise (`bg-surface-alt`) rendue par `TagSelector` au-dessus du champ, et une chip lime (`bg-primary`) rendue par `Search.vue` sous le champ, dans la ligne « Filtres actifs ». La croix de suppression de la chip lime est trop petite.

**Approach:** Permettre à `TagSelector` de ne pas afficher ses propres chips (nouvelle prop opt-in, défaut inchangé) et l'utiliser dans `Search.vue` ; seule la chip lime subsiste, avec une croix agrandie. Le libellé « Filtres actifs : » devient « Filtres par tag actifs : ».

## Boundaries & Constraints

**Always:** Le comportement par défaut de `TagSelector` reste identique pour les autres hôtes (Import, Editor, Show) : chips grises affichées. La chip lime garde son `aria-label` « Retirer le filtre tag … », sa couleur `bg-primary` et son comportement (clic → retrait + nouvelle recherche immédiate). La croix reste `aria-hidden`.

**Ask First:** Toute modification visuelle des chips grises de `TagSelector` ou d'un autre hôte que Search.

**Never:** Supprimer la ligne lime au profit des chips grises ; toucher au backend ou aux paramètres de navigation ; changer la gestion du focus après retrait d'une chip (hors périmètre).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Tags sélectionnés | Recherche, `tagFilters: [1, 2]` | Une seule chip par tag, lime, sous le champ ; aucune chip grise au-dessus ; libellé « Filtres par tag actifs : » | N/A |
| Aucun tag | `tagFilters: []` | Ni chips, ni libellé | N/A |
| Autre hôte | Import/Editor/Show, tags sélectionnés | Chips grises de `TagSelector` toujours affichées | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Components/TagSelector.vue` -- composant partagé ; props `modelValue`, `disabled`, `showLabel`, `placeholder` (l. 18-42) ; bloc des chips grises `v-if="selectedTags.length > 0"` (l. 177-194). Suivre le modèle documenté de `showLabel` pour la nouvelle prop.
- `resources/js/Pages/Documents/Search.vue` -- `<TagSelector v-model="selectedTagIds" :show-label="false" />` (l. 443) ; ligne « Filtres actifs : » et chips lime avec `<span aria-hidden="true">×</span>` (l. 446-459).
- `resources/js/Components/__tests__/TagSelector.spec.js` -- tests existants des chips (l. 44-56) et de `showLabel` (~l. 354-370), modèle pour le test de la nouvelle prop.
- `resources/js/Pages/Documents/__tests__/Search.spec.js` -- mocks `usePage` avec tags `Finance`(1)/`RH`(2) ; aucun test actuel sur la ligne des filtres actifs.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/TagSelector.vue` -- ajouter une prop booléenne `showSelected` (défaut `true`, commentaire dans le style de `showLabel` expliquant que Search affiche ses propres chips) et conditionner le bloc des chips grises à cette prop -- supprimer le doublon sans impacter les autres hôtes.
- [x] `resources/js/Pages/Documents/Search.vue` -- passer `:show-selected="false"` ; remplacer « Filtres actifs : » par « Filtres par tag actifs : » ; agrandir la croix de la chip lime (ex. `text-base leading-none`, contre `text-xs` hérité actuellement) -- demande utilisateur.
- [x] `resources/js/Components/__tests__/TagSelector.spec.js` -- tester qu'avec `showSelected: false` et `modelValue: [1, 3]`, aucun bouton « Retirer le tag » n'est rendu -- régression de la prop.
- [x] `resources/js/Pages/Documents/__tests__/Search.spec.js` -- tester qu'avec `tagFilters: [1]`, le libellé « Filtres par tag actifs » apparaît et qu'un seul élément affiche « Finance » en chip (un bouton « Retirer le filtre tag Finance », aucun « Retirer le tag Finance ») -- régression du doublon.

**Acceptance Criteria:**
- Given la page Recherche, when je sélectionne un tag, then il n'apparaît qu'une fois, en chip lime sous le champ, avec une croix visiblement plus grande qu'avant.
- Given la page Import, Editor ou Show, when des tags sont sélectionnés, then les chips grises de `TagSelector` s'affichent comme avant.

## Spec Change Log

## Verification

**Commands:**
- `npx vitest run resources/js/Components/__tests__/TagSelector.spec.js resources/js/Pages/Documents/__tests__/Search.spec.js` -- expected: tous les tests passent

**Manual checks (if no CLI):**
- Page `/recherche` : sélectionner deux tags → une seule rangée de chips lime, croix lisible, libellé « Filtres par tag actifs : ».

## Suggested Review Order

**Suppression du doublon**

- Point d'entrée : Recherche désactive les chips grises de TagSelector.
  [`Search.vue:443`](../../resources/js/Pages/Documents/Search.vue#L443)

- Nouvelle prop opt-in, défaut `true` : les autres hôtes ne changent pas.
  [`TagSelector.vue:40`](../../resources/js/Components/TagSelector.vue#L40)

- Le bloc des chips grises est conditionné à la prop.
  [`TagSelector.vue:185`](../../resources/js/Components/TagSelector.vue#L185)

**Chip lime**

- Libellé renommé en « Filtres par tag actifs : ».
  [`Search.vue:447`](../../resources/js/Pages/Documents/Search.vue#L447)

- Croix agrandie (`text-base leading-none`), toujours `aria-hidden`.
  [`Search.vue:457`](../../resources/js/Pages/Documents/Search.vue#L457)

**Tests**

- Deux tags : une seule chip lime chacun, aucune chip grise.
  [`Search.spec.js:122`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L122)

- Le clic sur une chip lime retire le tag et relance la recherche.
  [`Search.spec.js:134`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L134)

- `showSelected: false` masque les chips de TagSelector.
  [`TagSelector.spec.js:375`](../../resources/js/Components/__tests__/TagSelector.spec.js#L375)
