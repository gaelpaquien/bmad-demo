---
title: 'Thème clair par défaut'
type: 'chore'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sans choix enregistré, l'app suivait `prefers-color-scheme` (UX-DR2) et s'affichait en sombre sur un OS sombre.

**Approach:** Décision utilisateur : le mode clair, plus agréable à utiliser, devient le défaut. Un choix enregistré via la page Thèmes reste prioritaire ; la préférence système n'est plus lue.

</frozen-after-approval>

## Implementation Notes

- `resources/views/app.blade.php` : `prefersDark` supprimé, `.dark` posé seulement si thème `dark` stocké (ou personnalisé de base sombre valide). `useTheme.js` : `systemMode()` supprimé, défaut `'light'`.
- Tests `prePaintThemeScript.spec.js` / `useTheme.spec.js` adaptés (défaut clair même sur système sombre) + 1 test ajouté. Suite JS : 274/274.
- UX-DR2 aligné dans `epics.md`, `EXPERIENCE.md`, `epic-1-context.md` (le cache de contexte d'épic sera recompilé au prochain besoin, `epics.md` étant plus récent).
- Revue sous-agent sautée (changement à 2 fichiers, couvert par tests).
