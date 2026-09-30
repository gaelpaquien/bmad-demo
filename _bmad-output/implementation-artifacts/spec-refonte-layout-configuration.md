---
title: 'Refonte layout Configuration : menu secondaire (Tags, MCP, Thèmes)'
type: 'refactor'
created: '2026-09-30'
status: 'done'
baseline_commit: '0aeeff573556ff9d15a0ad1311a63d886011f02c'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les pages de configuration sont dispersées : Configuration et MCP sont deux entrées du menu principal, et le thème n'est qu'un bouton clair/sombre. Il n'y a pas de zone dédiée à la configuration.

**Approach:** Triple layout pour Configuration : menu principal (existant) | menu secondaire (Tags, MCP, Thèmes) | contenu de la page choisie. Cliquer sur « Configuration » affiche le menu secondaire à droite du menu principal, puis le contenu à droite du menu secondaire. La partie Documents (liste paginée + contenu d'un document) suivra plus tard et n'est pas concernée.

## Boundaries & Constraints

**Always:** Menu principal, mode réduit et état persistant inchangés. Contenu des pages Tags et MCP repris tel quel (seul le conteneur change). Le menu secondaire marque la page active (`aria-current="page"`). Tokens Tailwind du thème respectés.

**Décisions de l'utilisateur :**
- Le bouton « MCP » quitte le menu principal ; le bouton de bascule clair/sombre du menu principal est retiré (le thème se change depuis Configuration › Thèmes).
- Page Thèmes : choix entre Clair, Sombre et Personnalisé, avec le détail des couleurs (pastilles + valeur hex) de chacun ; le thème Personnalisé permet de définir ses propres couleurs.
- Thème et couleurs personnalisées stockés en `localStorage` (pas de base de données), appliqués avant l'affichage sans flash.

**Never:** Ne pas toucher aux pages Documents/Recherche/Import/Éditeur. Pas de nouvelle dépendance. Pas de migration. Pas de changement de logique métier des tags ni du serveur MCP.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Entrée Configuration | Clic « Configuration » | Page Tags + menu secondaire | N/A |
| Ancienne URL | GET `/configuration` | Redirection vers `/configuration/tags` | N/A |
| Thème personnalisé | Couleur modifiée | Appliquée immédiatement et conservée au rechargement | `localStorage` indisponible : appliquée sans persistance |
| Réinitialisation | Clic « Réinitialiser » | Retour aux couleurs de la base (clair ou sombre) | N/A |
| Valeur invalide stockée | JSON corrompu | Retour au thème par défaut (préférence système) | Ignorée silencieusement |

</frozen-after-approval>

## Code Map

- `resources/js/Layouts/AppLayout.vue` -- flex `Sidebar` + `<main>` ; base du nouveau layout.
- `resources/js/Components/Sidebar.vue` -- retirer l'entrée « MCP » et le bouton thème (`isDark`/`toggleTheme`) ; « Configuration » actif sur les 3 pages (`Documents/Configuration`, `Documents/Mcp`, `Documents/Themes`) ; lien vers `/configuration/tags`.
- `resources/js/Pages/Documents/Configuration.vue`, `Mcp.vue` -- enveloppées dans `AppLayout` ; passer au nouveau layout (contenu inchangé).
- `app/Http/Controllers/TagController.php`, `McpController.php` -- inchangés ; un nouveau contrôleur `ThemeController::index` rend `Documents/Themes`.
- `routes/web.php` -- `/configuration` → redirection ; `/configuration/tags` (tags.index), `/configuration/mcp` (mcp.index), `/configuration/themes` (themes.index) ; noms conservés. Le `postJson('/mcp')` de `McpPageTest` doit rester sans effet.
- `resources/css/app.css` -- 9 tokens `--color-*` (`@theme`, surchargés par `.dark`) : source des pastilles et des couleurs personnalisées.
- `resources/views/app.blade.php` -- script pré-rendu lisant `bmad-demo-theme` ; à étendre pour appliquer le thème personnalisé (variables CSS sur `<html>`) avant affichage.
- Tests à ajuster : `tests/Feature/ManageTagsTest.php`, `McpPageTest.php`, `Sidebar.spec.js`, `AppLayout.spec.js`, `Configuration.spec.js`, `Mcp.spec.js`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Layouts/ConfigurationLayout.vue` -- créer : `AppLayout` + menu secondaire + zone de contenu -- triple layout
- [x] `resources/js/Composables/useTheme.js` -- créer : thème (clair/sombre/personnalisé), couleurs, persistance `localStorage`, application sur `<html>` -- logique partagée avec le script d'`app.blade.php`
- [x] `resources/js/Pages/Documents/Themes.vue`, `ThemeController`, `routes/web.php` -- page Thèmes et nouvelles URLs
- [x] `resources/js/Pages/Documents/Configuration.vue`, `Mcp.vue` -- utiliser le nouveau layout
- [x] `resources/js/Components/Sidebar.vue`, `resources/views/app.blade.php` -- retirer MCP et bouton thème ; script pré-rendu étendu
- [x] Tests Pest et Vitest -- menu secondaire, état actif, redirection, thème personnalisé

**Acceptance Criteria:**
- Given une page quelconque, when je clique sur « Configuration », then j'arrive sur Tags avec le menu secondaire (Tags, MCP, Thèmes) à droite du menu principal et le contenu à droite.
- Given le menu secondaire, when je clique sur MCP ou Thèmes, then le contenu correspondant s'affiche, l'entrée est active et « Configuration » reste actif dans le menu principal.
- Given une page hors Configuration, then aucun menu secondaire n'apparaît.
- Given la page Thèmes, when je choisis Sombre puis recharge, then le thème sombre est appliqué sans flash.
- Given le thème Personnalisé, when je change la couleur primaire, then l'app l'utilise immédiatement et après rechargement.

## Implementation Notes

- Implémenté directement (sans sous-agent). Fichiers : `ConfigurationLayout.vue`, `Themes.vue`, `useTheme.js`, `ThemeController.php`, routes, `Sidebar.vue` (MCP et bouton thème retirés), script pré-affichage de `app.blade.php`, tests Pest et Vitest.
- Le titre de la page Tags passe de « Configuration » à « Tags » (cohérence avec le menu secondaire).
- `GET /mcp` n'existe plus (URL déplacée en `/configuration/mcp`, sans redirection).

## Spec Change Log

## Review Triage Log

| Constat | Verdict | Route | Preuve / suite |
|---------|---------|-------|----------------|
| Test « pas d'endpoint MCP » repointé sur `/configuration/mcp` (405 trivial) | medium | patch | Test repointé sur `POST /mcp`, attend 404 |
| Actif du menu secondaire testé pour Tags seulement | medium | patch | `it.each` sur les 3 pages |
| Script pré-affichage sans test | medium | patch | `prePaintThemeScript.spec.js` évalue le script du Blade pour chaque état |
| Validation divergente script / `useTheme` (`colors: null`, couleur manquante, clés inconnues) | medium | patch | Validation stricte identique des deux côtés (9 couleurs hex valides) |
| Amorçage du thème personnalisé ignoré si entrée corrompue non nulle | medium | patch | Amorçage si `!isUsable` + test |
| `Route::redirect` répond 302 à tout verbe sur `/configuration` | low | patch | `Route::get` + redirection vers `tags.index` |
| Commentaire obsolète « theme toggle then footer » dans Sidebar.spec | low | patch | Commentaire corrigé |
| `PALETTES` recopié à la main d'`app.css` | low | defer | Valeurs identiques aujourd'hui ; dérive seulement si `app.css` change |
| Thème personnalisé illisible sans garde-fou ni échappatoire | medium (non vérifié) | defer | Dépend d'un choix de design (contraste, réinitialisation hors page) |
| Accessibilité (nom accessible des radios), responsive, layout persistant, `<Head>` | low | defer | Améliorations de finition hors intention ; le layout par page suit la convention existante |
| Ancienne URL `/mcp` sans redirection | low | false | Décision consignée dans les notes ; ajouter un alias n'est pas demandé |
| Titre « Tags » au lieu de « Configuration » | low | false | Changement voulu pour la cohérence avec le menu secondaire, noté dans les notes |
| `AppLayout.spec.js` cité dans la Code Map sans changement | false | — | Aucun changement nécessaire : AppLayout est inchangé |
| Retrait du bouton thème du menu principal | false | — | Décision de l'utilisateur |
| `useTheme` non singleton, pas d'écoute `storage`, modes/bases invalides, radio « choisi » sur préférence système, tests `localStorage` indisponible, 302 vs 301 | low | rejeté | Un seul consommateur, appels internes à valeurs constantes ; correctif plus lourd que le gain |

## Design Notes

Le thème personnalisé garde une base (clair ou sombre) qui pilote la classe `.dark` (variantes `dark:` de Tailwind) ; changer de base bascule les couleurs encore à leur valeur par défaut vers la palette de la nouvelle base (les couleurs modifiées sont conservées) ; ses couleurs surchargent les 9 tokens en variables CSS sur `<html>`. Clé `localStorage` existante `bmad-demo-theme` (`light`/`dark`/`custom`) ; les couleurs personnalisées sous une clé dédiée.

## Verification

**Commands:**
- `php artisan test --compact --filter="ManageTags|McpPage|Theme"` -- expected: succès
- `npx vitest run` -- expected: succès
- `vendor/bin/pint --dirty --format agent` -- expected: aucun problème
