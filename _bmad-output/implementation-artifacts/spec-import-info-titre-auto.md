---
title: 'Import : info sur le titre rempli automatiquement'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur « Importer un document », le champ Titre est désactivé et vide tant qu'aucun document principal n'est ajouté, sans explication.

**Approach:** Ajouter sous le champ une aide (texte muted) précisant que le titre est rempli automatiquement à partir du nom du fichier une fois le document principal ajouté, puis modifiable.

</frozen-after-approval>

## Implementation Notes

- `resources/js/Pages/Documents/Import.vue` : `<p id="document-title-hint" class="text-xs text-muted">` sous le champ, relié par `aria-describedby`. Texte statique (copie seule, pas de nouveau test). Tests `Import.spec.js` OK. Revue sous-agent sautée.
