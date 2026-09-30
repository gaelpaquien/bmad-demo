---
title: 'Thèmes : lexique des couleurs pliable'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur Configuration > Thèmes, le lexique des couleurs occupe toute la fin de la page en permanence.

**Approach:** Le placer dans un container pliable/dépliable, sur le modèle de l'aide de la recherche (Search.vue) : replié par défaut, choix mémorisé.

</frozen-after-approval>

## Implementation Notes

- `Themes.vue` : section (titre « Découvrez le lexique des couleurs », placée en haut de page, avant les thèmes) reprend le motif de l'aide de Recherche (bouton dans le `h2`, `aria-expanded`/`aria-controls`, chevron, animation `grid-rows`, `inert` replié). État persisté dans `localStorage` (`bmad-demo-theme-lexicon-open`), lecture/écriture protégées. Le `data-testid="theme-lexicon"` est conservé.
- Le motif est dupliqué (pas de composant partagé) pour ne pas toucher Search.vue ; à extraire si un 3e usage apparaît.
- `Themes.spec.js` : test du repli par défaut, de la bascule et de la mémorisation. Suite JS 277/277. Revue sous-agent sautée.
