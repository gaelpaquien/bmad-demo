---
title: 'Retirer le raccourci clavier `/` de la surface Recherche'
type: 'chore'
created: '2026-09-28'
status: 'done'
route: 'one-shot'
---

# Retirer le raccourci clavier `/` de la surface Recherche

## Intent

**Problem:** La surface Recherche interceptait encore la touche `/` au niveau de `window` pour donner le focus au champ. Depuis qu'un onglet Recherche dédié existe et place déjà le focus sur le champ au chargement, ce raccourci n'a plus d'intérêt. En plus, il va à l'encontre de WCAG 2.2 SC 2.1.4 (raccourci à caractère unique sans modificateur).

**Approach:** Supprimer l'écouteur `keydown` global et la mention « appuyez sur / » du placeholder dans `Search.vue`, en gardant le focus automatique au montage. Marquer UX-DR34 et les critères d'acceptation des stories 1.6 et 3.4 comme `[REMOVED 2026-09-28]` dans les artefacts de planification. Compromis accepté : pour revenir au champ en cours de recherche, il faut passer par Shift+Tab ou la souris.

## Suggested Review Order

**Retrait du raccourci**

- Le focus automatique au montage reste le seul point d'entrée clavier ; l'écouteur global est supprimé.
  [`Search.vue:132`](../../resources/js/Pages/Documents/Search.vue#L132)

- Le placeholder ne promet plus un raccourci qui n'existe plus.
  [`Search.vue:172`](../../resources/js/Pages/Documents/Search.vue#L172)

**Traçabilité de la planification**

- Exigence UX barrée, avec l'historique des amendements et la justification WCAG.
  [`epics.md:106`](../planning-artifacts/epics.md#L106)

- Critère d'acceptation de la Story 1.6 (historique) barré.
  [`epics.md:265`](../planning-artifacts/epics.md#L265)

- Critère d'acceptation de la Story 3.4 barré.
  [`epics.md:528`](../planning-artifacts/epics.md#L528)

- Hypothèse UX abandonnée ; date `updated` du frontmatter rafraîchie.
  [`EXPERIENCE.md:97`](../planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md#L97)
