---
title: 'Thème sombre plus clair et plus lisible'
type: 'chore'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Le thème sombre est jugé trop sombre, peu lisible et peu agréable : fond `#1B1815`, menu secondaire `#211D19`, menu principal `#24201B` — trois brun quasi noirs séparés de quelques points de luminosité, bordures à peine visibles.

**Approach:** Éclaircir l'échelle sombre (fond du contenu principal, menu secondaire, menu principal), renforcer l'écart entre les trois niveaux, les bordures et le contraste du texte, en conservant la teinte beige/brun chaud et l'accent lime. Thème sombre uniquement : la palette claire ne change pas.

</frozen-after-approval>

## Implementation Notes

- Tokens `.dark` dans `resources/css/app.css` (source unique) ; miroir `PALETTES.dark` dans `resources/js/Composables/useTheme.js` ; `DESIGN.md` aligné.
- Nouvelles valeurs : background `#2A2621`, surface `#332E28` (menu secondaire), surface-alt `#3B352E` (menu principal), border `#504839`, foreground `#F2EDE6`, muted `#B9AFA0`. `primary-hover` inchangé.
- Suite JS : 277/277. Revue sous-agent sautée (changement de valeurs de tokens uniquement, couvert par les tests de thème). Contrôle visuel à faire par l'utilisateur (`npm run build` / `composer run dev`).
