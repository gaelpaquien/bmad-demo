---
title: 'Retrait des titres et micro-descriptions de pages'
type: 'chore'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les pages (Recherche, Importer un document, Tags, MCP, Thèmes) affichent un titre de page et parfois une micro-description redondants avec le menu actif : le design doit parler de lui-même.

**Approach:** Retirer ces titres visibles et micro-descriptions, sans toucher aux titres de contenu (titre du document dans Show, sections h2, aide repliable de la recherche, aide du champ Titre à l'import).

</frozen-after-approval>

## Implementation Notes

- Le `<h1>` visible devient `<h1 class="sr-only">` (même texte) dans `Configuration.vue`, `Import.vue`, `Mcp.vue`, `Search.vue`, `Themes.vue` : conserve la hiérarchie de titres et la navigation lecteur d'écran.
- `Themes.vue` : paragraphe « Le thème choisi est conservé dans ce navigateur. » supprimé.
- Inchangés : `Show.vue` (h1 = titre du document), sections h2, panneau d'aide de la recherche, aide du champ Titre (Import). Suite JS 274/274. Revue sous-agent sautée.
