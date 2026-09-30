---
title: 'Uniformisation du design des tags'
type: 'refactor'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les tags avaient 3 tailles de `TagChip` (Recherche compact, détail regular, liste Documents small) et 2 rendus codés à part (chips du TagSelector, chips de filtre actif de Recherche).

**Approach:** Un seul composant `TagChip`, au design du détail d'un document, utilisé partout. Décision utilisateur : périmètre complet (y compris la liste Documents). Les chips de filtre actif gardent leur couleur primaire pour signaler l'état actif.

</frozen-after-approval>

## Implementation Notes

- `TagChip.vue` : prop `size` supprimée (style unique `rounded-md px-2.5 py-1 text-sm`) ; ajout de `active` (couleurs primaires), `removeLabel` (bouton × + emit `remove`), `disabled`.
- Utilisé désormais par `Show.vue`, `Search.vue` (résultats + filtres actifs), `DocumentsLayout.vue`, `TagSelector.vue` (chips retirables). Labels aria inchangés.
- Tests `TagChip.spec.js` réécrits. Suite JS 276/276. Revue sous-agent sautée. Les lignes de la page Configuration > Tags (liste de gestion) ne sont pas des chips et sont inchangées.
