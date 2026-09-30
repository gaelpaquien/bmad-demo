---
title: 'Séparateurs de la barre d''outils WYSIWYG plus visibles'
type: 'bugfix'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Dans l'éditeur WYSIWYG (création/édition de document), les séparateurs verticaux entre groupes d'outils (titres H1-H3 / listes / tableau…) sont quasi invisibles : `bg-border` sur fond `bg-surface-alt` donne un contraste minimal.

**Approach:** Renforcer le contraste des trois séparateurs de la barre d'outils de `Editor.vue` avec la teinte `foreground` à opacité réduite, convention déjà utilisée dans l'app (`border-foreground/40`), valable en clair et en sombre.

</frozen-after-approval>

## Implementation Notes

- `resources/js/Pages/Documents/Editor.vue` : les 3 séparateurs `bg-border` → `bg-foreground/40` (lignes 513, 537, 572). Tests `Editor.spec.js` OK (23/23). Revue par sous-agent sautée (changement purement CSS).
