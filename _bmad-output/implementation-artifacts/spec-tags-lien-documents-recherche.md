---
title: 'Tags : compteur de documents cliquable vers la recherche filtrée'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur Configuration > Tags, le compteur « X documents » d'un tag est un texte inerte : impossible de voir directement les documents concernés.

**Approach:** Rendre le compteur cliquable, avec redirection vers la page Recherche dont le filtre par tag est actif pour ce tag.

</frozen-after-approval>

## Implementation Notes

- `Configuration.vue` : le compteur devient un `Link` vers `/recherche?tag_id[]={id}` (paramètre déjà géré par `DocumentController::search`, tag seul sans mot-clé supporté) ; `aria-label` explicite. Décision : à 0 document, le compteur reste un texte non cliquable (la recherche filtrée serait vide).
- `Configuration.spec.js` : mock `Link` ajouté + test du lien (présent à 3 docs, absent à 0). Suite JS 275/275. Revue sous-agent sautée.
