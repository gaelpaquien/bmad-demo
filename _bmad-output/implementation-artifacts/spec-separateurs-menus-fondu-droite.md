---
title: 'Séparateurs des menus : fondu vers la droite uniquement'
type: 'chore'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Le séparateur sous le nom de l'app (menu principal) et sous le nom de catégorie (menus secondaires Configuration / Documents) fond des deux côtés, ce qui n'est pas cohérent avec un contenu aligné à gauche.

**Approach:** Remplacer le dégradé par un fondu vers la droite uniquement (plein à gauche, transparent à droite).

</frozen-after-approval>

## Implementation Notes

- Dégradé `transparent → border 20-80% → transparent` remplacé par `border 60% → transparent` dans `Sidebar.vue:82`, `DocumentsLayout.vue:95`, `ConfigurationLayout.vue:48`. Les séparateurs de lignes (liste Documents) et de `Show.vue` sont inchangés (hors périmètre demandé). Tests Sidebar/Layouts OK (40/40). Revue sous-agent sautée (CSS pur).
