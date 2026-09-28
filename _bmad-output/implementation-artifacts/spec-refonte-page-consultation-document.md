---
title: 'Refonte de la page de consultation d''un document'
type: 'feature'
created: '2026-09-28'
status: 'done'
review_loop_iteration: 0
baseline_commit: '39fb26e2997d5c8a7431296f22b13085b4cfbd1e'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La page de consultation (`Show.vue`) est bridée à `max-w-3xl`, ce qui rend l'aperçu PDF difficilement lisible ; elle affiche un champ « Type » inutile ; le sélecteur de tags (« Rechercher un tag… ») toujours visible ressemble à une recherche et brouille la lecture ; les actions sont reléguées sous les métadonnées au lieu d'être à côté du titre.

**Approach:** Page pleine largeur ; suppression du champ « Type » ; en-tête titre + actions alignés (titre tronqué si nécessaire) ; consultation en lecture seule par défaut, avec un mode modification explicite déclenché par « Modifier » où les tags deviennent éditables et sont enregistrés via « Enregistrer » / abandonnés via « Annuler ».

## Boundaries & Constraints

**Always:**
- Consultation par défaut à chaque arrivée sur la page et à chaque navigation vers un autre document : tags affichés en pastilles lecture seule (`TagChip`), aucun champ de saisie.
- En-tête sur une ligne : `h1` tronqué (`truncate`, `min-w-0`, `title` = titre complet) à gauche, actions à droite en `shrink-0` — les boutons ne sont jamais tronqués ni masqués.
- Document **importé** : « Modifier » bascule en mode modification dans la page. Document **créé** : « Modifier » reste le lien existant vers `/documents/{id}/edit` (l'éditeur gère déjà les tags) — un seul bouton « Modifier » par document.
- En mode modification : les actions de l'en-tête sont remplacées par « Enregistrer » (primaire) et « Annuler » ; les tags sont modifiés localement via `TagSelector` (placeholder « Ajouter un tag… ») et envoyés en **un seul** `PATCH /documents/{id}/tags` au clic sur « Enregistrer ».
- Conserver telles quelles la modale de suppression, les exports PDF/Word, les toasts, l'aperçu (PDF/Office/créé) et la liste des pièces jointes.

**Ask First:** Toute modification backend (route, contrôleur, Action) ; rendre éditable autre chose que les tags (titre, pièces jointes).

**Never:** Changer le comportement de `TagSelector` pour les autres pages (Import, Editor, Search) ; supprimer `DocumentTypeBadge.vue` (utilisé ailleurs) ; menu « ⋯ » pour les actions ; limite `max-w-*` sur le conteneur principal de la page.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Enregistrement | Mode modif., tags modifiés, clic « Enregistrer » | 1 PATCH avec `tag_ids` locaux ; succès → retour consultation, pastilles à jour | N/A |
| Échec enregistrement | PATCH renvoie une erreur | Reste en mode modification, sélection locale conservée, message `role="alert"` | `errors.tag_ids` ou « Impossible de mettre à jour les tags. » |
| Annulation | Mode modif., tags modifiés, clic « Annuler » | Retour consultation, tags = `document.tags`, aucun PATCH | N/A |
| Double clic | Clic « Enregistrer » pendant un PATCH en cours | Aucun second PATCH (boutons désactivés + garde) | N/A |
| Navigation | Mode modif. puis Show → autre document | Consultation, tags du nouveau document | N/A |
| Aucun tag | `document.tags` vide en consultation | « Aucun tag. » en texte discret | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Pages/Documents/Show.vue` -- seul fichier de page touché. L.429 conteneur `mx-auto max-w-3xl px-4 py-10` ; L.430 `h1` ; L.434-453 `dl` (Type L.435-440 à retirer, Tags L.445-453 avec `TagSelector` à écriture immédiate) ; L.491-558 barre d'actions (Télécharger / Modifier (créé) / Exporter PDF / Exporter Word / Supprimer) à déplacer dans l'en-tête ; L.560-565 erreurs d'export ; L.19-59 `tagIds`/`isSavingTags`/`tagsError`/`onTagsChange` (PATCH immédiat) à remplacer par un état brouillon + `saveTags()` ; L.32 watcher `document.id` à étendre (sortie du mode modif.).
- `app/Http/Controllers/DocumentController.php:457` `updateTags` -- lecture seule : renvoie `back()`, donc `props.document.tags` est rafraîchi après succès ; sortir du mode modif. dans `onSuccess` et initialiser le brouillon depuis `document.tags` à chaque entrée en mode modif.
- `resources/js/Components/TagChip.vue` -- pastille lecture seule (prop `name`) à réutiliser, cf. `Index.vue:52`.
- `resources/js/Components/TagSelector.vue` -- L.201 `placeholder` codé en dur → prop `placeholder` (défaut « Rechercher un tag… », inchangé ailleurs) ; mettre à jour les commentaires L.12-15 et L.26 qui décrivent Show comme « persist immediately ». Ne pas modifier sa logique.
- `resources/js/Pages/Documents/__tests__/Show.spec.js` -- test existant basé sur l'écriture immédiate (spec-fix-multi-tag-selection) : à réécrire pour le nouveau flux (mode modif. → sélection multiple sans PATCH → Enregistrer → 1 PATCH).
- `resources/js/Components/__tests__/TagSelector.spec.js` -- vérifier qu'aucun test ne dépend du placeholder codé en dur.
- `tests/Feature/SyncDocumentTagsTest.php` -- backend `PATCH /documents/{id}/tags` inchangé, lecture seule.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/TagSelector.vue` -- ajouter la prop `placeholder` avec le défaut actuel -- permettre « Ajouter un tag… » sur Show sans impacter les autres pages.
- [x] `resources/js/Pages/Documents/Show.vue` -- conteneur pleine largeur (`px-6 py-8`, sans `max-w`) ; en-tête flex titre tronqué + actions ; retirer le champ Type et l'import `DocumentTypeBadge` ; état `isEditing` + brouillon de tags, `saveTags()`/`cancelEditing()` avec garde anti-double-envoi ; tags en `TagChip` en consultation, `TagSelector` en modification ; erreurs d'export sous l'en-tête -- intention ci-dessus.
- [x] `resources/js/Pages/Documents/__tests__/Show.spec.js` -- réécrire le test tags et couvrir la matrice I/O (enregistrement, échec, annulation, double clic, navigation, absence du champ Type, lien Modifier pour un document créé) -- régression du nouveau flux.

**Acceptance Criteria:**
- Given un document importé ouvert, when la page s'affiche, then aucun champ de saisie de tag ni champ « Type » n'est présent et les boutons d'action sont dans l'en-tête, à droite du titre.
- Given un titre très long, when la page s'affiche sur un écran étroit, then le titre est tronqué avec « … » et tous les boutons restent visibles.
- Given un document créé, when on clique « Modifier », then on navigue vers `/documents/{id}/edit` (pas de mode modification dans la page).
- Given un PDF importé, when la page s'affiche, then l'aperçu occupe toute la largeur disponible à côté de la sidebar.

## Spec Change Log

## Design Notes

Passer de l'écriture immédiate à un brouillon + « Enregistrer » est volontaire : le mode modification explicite demandé par l'utilisateur n'a de sens que si les changements sont validés ou annulés d'un bloc. Le backend accepte déjà un `tag_ids` complet (sémantique `sync`), donc aucun changement serveur.

## Verification

**Commands:**
- `npx vitest run resources/js/Pages/Documents/__tests__/Show.spec.js resources/js/Components/__tests__/TagSelector.spec.js` -- expected: tous verts
- `npm run build` -- expected: build sans erreur

**Manual checks (if no CLI):**
- Ouvrir un PDF importé : aperçu pleine largeur ; cliquer Modifier → ajouter/retirer des tags → Enregistrer → pastilles à jour.

## Suggested Review Order

**Mode modification des tags (brouillon + enregistrement unique)**

- Point d'entrée : un seul PATCH `sync`, garde anti-double-envoi, reste en modif. sur erreur.
  [`Show.vue:62`](../../resources/js/Pages/Documents/Show.vue#L62)

- Brouillon initialisé depuis `document.tags` à chaque entrée, focus vers le champ tag.
  [`Show.vue:38`](../../resources/js/Pages/Documents/Show.vue#L38)

- Sortie commune (annuler / succès) qui rend le focus à « Modifier ».
  [`Show.vue:46`](../../resources/js/Pages/Documents/Show.vue#L46)

- Navigation Show → Show : retour systématique en consultation.
  [`Show.vue:95`](../../resources/js/Pages/Documents/Show.vue#L95)

**Mise en page et en-tête**

- Conteneur pleine largeur, sans `max-w`.
  [`Show.vue:469`](../../resources/js/Pages/Documents/Show.vue#L469)

- Titre tronqué (`min-w-0 truncate`, `title` complet) à côté des actions `shrink-0`.
  [`Show.vue:473`](../../resources/js/Pages/Documents/Show.vue#L473)

- Actions remplacées par Enregistrer / Annuler en mode modification.
  [`Show.vue:477`](../../resources/js/Pages/Documents/Show.vue#L477)

- « Modifier » en page pour un importé, lien éditeur conservé pour un créé.
  [`Show.vue:528`](../../resources/js/Pages/Documents/Show.vue#L528)

- Tags : `TagSelector` en modif., pastilles `TagChip` ou « Aucun tag. » sinon.
  [`Show.vue:585`](../../resources/js/Pages/Documents/Show.vue#L585)

**Composant partagé**

- Prop `placeholder` avec défaut inchangé pour Import/Editor/Search.
  [`TagSelector.vue:37`](../../resources/js/Components/TagSelector.vue#L37)

**Tests**

- Consultation, en-têtes importé/créé, matrice I/O et focus.
  [`Show.spec.js:100`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L100)

- Garde-fou du placeholder par défaut.
  [`TagSelector.spec.js:38`](../../resources/js/Components/__tests__/TagSelector.spec.js#L38)
