---
title: 'Page « MCP » : expliquer le serveur MCP et retirer l’accès HTTP inutilisé'
type: 'feature'
created: '2026-09-30'
status: 'done'
baseline_commit: '4b6f18ecc881c48fcb3541545476fe2cb25c50fa'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/spec-mcp-base-connaissance.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** le serveur MCP (`bmad-demo`) n'est décrit nulle part dans l'interface : rien n'explique son rôle, ses outils, ni comment le brancher sur Claude ou ChatGPT. Par ailleurs `MCP_ACCESS_TOKEN` (`.env.example`) ne sert qu'à l'endpoint HTTP `/mcp`, qui n'est utilisé par personne aujourd'hui.

**Approche :** ajouter une page « MCP » au menu de navigation, en lecture seule, qui documente le fonctionnement actuel (local uniquement) et annonce que l'accès distant (jeton ou URL) se configurera plus tard depuis cette même page. Retirer en même temps tout l'accès HTTP au MCP (route, middleware, config, variable d'environnement, tests).

## Boundaries & Constraints

**Always :** la page suit le paradigme Thin Controller → Inertia (`AppLayout`, sidebar, thème clair/sombre existants). Textes en français, code en anglais. La config JSON affichée contient le vrai chemin absolu du projet (fourni par le serveur, pas codé en dur), avec un bouton pour la copier. L'entrée « MCP » de la sidebar est active uniquement sur cette page et suit le même style que les autres entrées (icône, mode replié `sr-only` + `title`).

**Never :** aucun formulaire ni réglage de jeton/URL dans cette page (seulement une mention « à venir »). Ne pas modifier le serveur local (`Mcp::local`), ses outils ni leur comportement. Ne pas ajouter de dépendance. Pas de nouveau README ni fichier de documentation.

## I/O & Edge-Case Matrix

| Scénario | Entrée / État | Comportement attendu | Gestion d'erreur |
|----------|--------------|----------------------|------------------|
| Ouverture de la page | `GET /mcp` | Rôle du serveur, les 3 outils (`search_documents`, `read_document`, `list_tags`), usage avec Claude Desktop / Claude Code, config JSON avec le chemin réel du projet | N/A |
| ChatGPT | Section « ChatGPT » | Indique qu'il exige un serveur distant (URL HTTPS), indisponible tant que l'accès distant n'existe pas | N/A |
| Accès distant | Section « Accès distant » | Mention « bientôt » : la configuration d'un jeton ou d'une URL se fera ici | N/A |
| Copie de la config | Clic sur « Copier » | Le JSON est copié ; le bouton confirme brièvement | Presse-papiers indisponible : le JSON reste sélectionnable, aucun message d'erreur bloquant |
| Ancien endpoint | `POST /mcp` | 404 / 405 (plus d'endpoint MCP HTTP) | N/A |

</frozen-after-approval>

## Code Map

- `routes/web.php` -- `GET /configuration` (`tags.index`) : modèle pour ajouter `GET /mcp` (`mcp.index`). Aujourd'hui `GET /mcp` est déjà pris par `Mcp::web` (à retirer).
- `routes/ai.php` -- ne garder que `Mcp::local('bmad-demo', KnowledgeBaseServer::class)` ; supprimer `Mcp::web` et l'import du middleware.
- `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php` -- à supprimer (seul usage du jeton). `.env.example` : supprimer le bloc `MCP_ACCESS_TOKEN` (l. 81-84, commentaire compris).
- `tests/Feature/Mcp/McpEndpointTest.php` -- teste uniquement l'endpoint HTTP : à supprimer (suppression approuvée par l'utilisateur). Les tests d'outils du même dossier restent.
- `app/Http/Controllers/TagController.php` (`index()`) -- style de controller d'une page Inertia simple à imiter ; `app/Mcp/Servers/KnowledgeBaseServer.php` et `app/Mcp/Tools/*` -- source des noms, rôles et paramètres des outils à décrire (ne pas modifier).
- `resources/js/Components/Sidebar.vue` -- ajouter l'item « MCP » après « Configuration » avec `isMcpActive` (`page.component === 'Documents/Mcp'`). `resources/js/Components/__tests__/Sidebar.spec.js` (l. 78 : liste d'items ordonnée) à mettre à jour.
- `resources/js/Pages/Documents/Configuration.vue` + `__tests__/Configuration.spec.js` -- modèle de page hors « documents » et de spec (mock d'`@inertiajs/vue3`, stub d'`AppLayout`).

## Tasks & Acceptance

**Execution:**
- [ ] `routes/ai.php`, `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php`, `.env.example`, `tests/Feature/Mcp/McpEndpointTest.php` -- retirer l'accès HTTP et `MCP_ACCESS_TOKEN` -- fonctionnalité inutilisée, libère `/mcp`
- [ ] `app/Http/Controllers/McpController.php`, `routes/web.php` -- `index()` rend `Documents/Mcp` avec `projectPath` (`base_path()`), route nommée `mcp.index` -- point d'entrée mince
- [ ] `resources/js/Pages/Documents/Mcp.vue` -- rôle, outils, Claude Desktop/Claude Code, ChatGPT, config JSON copiable (`JSON.stringify` sur `projectPath`), section « Accès distant à venir » -- contenu de la page
- [ ] `resources/js/Components/Sidebar.vue` -- entrée « MCP » (icône, actif sur la page) -- accès via le menu
- [ ] `tests/Feature/McpPageTest.php`, `resources/js/Pages/Documents/__tests__/Mcp.spec.js`, `Sidebar.spec.js` -- page rendue avec `projectPath` ; chemin présent dans le JSON, outils listés ; item « MCP » présent et actif -- couverture

**Acceptance Criteria:**
- Given l'application, when on clique sur « MCP » dans le menu, then la page s'affiche avec l'entrée « MCP » active et l'explication du MCP local.
- Given le dépôt après changement, when on cherche `MCP_ACCESS_TOKEN` ou `AuthenticateMcpToken`, then plus aucune occurrence n'existe hors historique/spec.
- Given la suite de tests des outils MCP, when elle est exécutée, then elle passe sans modification.

## Implementation Notes

- Implémenté directement (pas de sous-agent). `GET /mcp` (`mcp.index`) rend `Documents/Mcp` avec `projectPath` ; l'entrée « MCP » vient après « Configuration ». Chemin normalisé en `/` dans la config affichée pour rester valide en JSON sous Windows.
- Retrait de l'accès HTTP : `Mcp::web`, `AuthenticateMcpToken`, `config/mcp.php`, bloc `.env.example`, `McpEndpointTest` (suppression approuvée). Le package `laravel/mcp` fusionne sa propre config `mcp` : rien ne dépend de celle supprimée. `POST /mcp` répond 405 (testé).
- Vérifié : suite PHP complète (274 tests), suite Vitest complète (212 tests), Pint OK. Un `npm run build` (ou `npm run dev`) est nécessaire pour voir la page.

## Spec Change Log

## Review Triage Log

Passe 1 : revue en ligne de l'agent principal. Passe 2 (à la demande de l'utilisateur) : 3 relecteurs indépendants (lecture aveugle, cas limites, trous de vérification).

| Constat | Verdict | Route | Preuve |
|---------|---------|-------|--------|
| Occurrence résiduelle de `MCP_ACCESS_TOKEN` / `AuthenticateMcpToken` / `mcp.access_token` | false | rejeté | Recherche sur tout le dépôt hors `vendor`, `_bmad-output` : aucune occurrence. |
| Suppression de `config/mcp.php` casse le package | false | rejeté | `McpServiceProvider` fait `mergeConfigFrom` de sa propre config ; suite complète verte. |
| `projectPath` exposé côté client | low | rejeté | Application mono-utilisateur locale, chemin voulu par la spec. |
| Nom de l'outil, handle `bmad-demo` et liste d'outils codés en dur dans `Mcp.vue`, sans lien avec le serveur ; `McpEndpointTest` supprimé = plus aucun test au niveau serveur (aveugle, vérification) | medium | patch | Vérifié : aucun test ne lit `KnowledgeBaseServer::$tools` ni `Mcp::local`. Test `KnowledgeBaseServerTest` ajouté (handle enregistré, liste exacte des 3 outils). |
| Tests `Mcp.spec.js` : `unstubAllGlobals` en fin de test (fuite si assertion échoue), pas de test sans API presse-papiers, ni du bouton Claude Code, ni du retour à « Copier » après 2 s (cas limites, vérification, aveugle) | low | patch | `afterEach` nettoie maintenant stubs et timers ; 2 tests ajoutés. |
| Route `/mcp` insérée entre `/configuration` et les routes `/tags`, scindant le bloc tags (aveugle) | low | patch | Déplacée avant le commentaire du bloc tags. |
| Test « POST /mcp = 405 » fragile si la page change d'URL (aveugle) | low | rejeté | Si la page bouge, le test casse et signale l'écart ; le corriger ajouterait de la logique de test. |
| `/mcp` entre en collision avec un futur endpoint distant (aveugle) | low | rejeté | Spéculatif ; l'accès distant sera conçu depuis cette page, à ce moment-là. |
| Variable `MCP_ACCESS_TOKEN` résiduelle dans d'autres `.env` ; entrée `deferred-work.md` sur le jeton bearer devenue caduque (aveugle) | low | rejeté | `.env` local vide ; les entrées existantes de `deferred-work.md` ne se modifient pas (règle du workflow), l'entrée reste lisible comme historique. |
| `base_path()` exposé au navigateur, chemin faux sur Docker/WSL (aveugle) | low | rejeté | Voulu par la spec pour une appli mono-utilisateur locale. |
| `php` absent du PATH, chemin contenant `"` ou `---
title: 'Page « MCP » : expliquer le serveur MCP et retirer l’accès HTTP inutilisé'
type: 'feature'
created: '2026-09-30'
status: 'done'
baseline_commit: '4b6f18ecc881c48fcb3541545476fe2cb25c50fa'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/spec-mcp-base-connaissance.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** le serveur MCP (`bmad-demo`) n'est décrit nulle part dans l'interface : rien n'explique son rôle, ses outils, ni comment le brancher sur Claude ou ChatGPT. Par ailleurs `MCP_ACCESS_TOKEN` (`.env.example`) ne sert qu'à l'endpoint HTTP `/mcp`, qui n'est utilisé par personne aujourd'hui.

**Approche :** ajouter une page « MCP » au menu de navigation, en lecture seule, qui documente le fonctionnement actuel (local uniquement) et annonce que l'accès distant (jeton ou URL) se configurera plus tard depuis cette même page. Retirer en même temps tout l'accès HTTP au MCP (route, middleware, config, variable d'environnement, tests).

## Boundaries & Constraints

**Always :** la page suit le paradigme Thin Controller → Inertia (`AppLayout`, sidebar, thème clair/sombre existants). Textes en français, code en anglais. La config JSON affichée contient le vrai chemin absolu du projet (fourni par le serveur, pas codé en dur), avec un bouton pour la copier. L'entrée « MCP » de la sidebar est active uniquement sur cette page et suit le même style que les autres entrées (icône, mode replié `sr-only` + `title`).

**Never :** aucun formulaire ni réglage de jeton/URL dans cette page (seulement une mention « à venir »). Ne pas modifier le serveur local (`Mcp::local`), ses outils ni leur comportement. Ne pas ajouter de dépendance. Pas de nouveau README ni fichier de documentation.

## I/O & Edge-Case Matrix

| Scénario | Entrée / État | Comportement attendu | Gestion d'erreur |
|----------|--------------|----------------------|------------------|
| Ouverture de la page | `GET /mcp` | Rôle du serveur, les 3 outils (`search_documents`, `read_document`, `list_tags`), usage avec Claude Desktop / Claude Code, config JSON avec le chemin réel du projet | N/A |
| ChatGPT | Section « ChatGPT » | Indique qu'il exige un serveur distant (URL HTTPS), indisponible tant que l'accès distant n'existe pas | N/A |
| Accès distant | Section « Accès distant » | Mention « bientôt » : la configuration d'un jeton ou d'une URL se fera ici | N/A |
| Copie de la config | Clic sur « Copier » | Le JSON est copié ; le bouton confirme brièvement | Presse-papiers indisponible : le JSON reste sélectionnable, aucun message d'erreur bloquant |
| Ancien endpoint | `POST /mcp` | 404 / 405 (plus d'endpoint MCP HTTP) | N/A |

</frozen-after-approval>

## Code Map

- `routes/web.php` -- `GET /configuration` (`tags.index`) : modèle pour ajouter `GET /mcp` (`mcp.index`). Aujourd'hui `GET /mcp` est déjà pris par `Mcp::web` (à retirer).
- `routes/ai.php` -- ne garder que `Mcp::local('bmad-demo', KnowledgeBaseServer::class)` ; supprimer `Mcp::web` et l'import du middleware.
- `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php` -- à supprimer (seul usage du jeton). `.env.example` : supprimer le bloc `MCP_ACCESS_TOKEN` (l. 81-84, commentaire compris).
- `tests/Feature/Mcp/McpEndpointTest.php` -- teste uniquement l'endpoint HTTP : à supprimer (suppression approuvée par l'utilisateur). Les tests d'outils du même dossier restent.
- `app/Http/Controllers/TagController.php` (`index()`) -- style de controller d'une page Inertia simple à imiter ; `app/Mcp/Servers/KnowledgeBaseServer.php` et `app/Mcp/Tools/*` -- source des noms, rôles et paramètres des outils à décrire (ne pas modifier).
- `resources/js/Components/Sidebar.vue` -- ajouter l'item « MCP » après « Configuration » avec `isMcpActive` (`page.component === 'Documents/Mcp'`). `resources/js/Components/__tests__/Sidebar.spec.js` (l. 78 : liste d'items ordonnée) à mettre à jour.
- `resources/js/Pages/Documents/Configuration.vue` + `__tests__/Configuration.spec.js` -- modèle de page hors « documents » et de spec (mock d'`@inertiajs/vue3`, stub d'`AppLayout`).

## Tasks & Acceptance

**Execution:**
- [ ] `routes/ai.php`, `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php`, `.env.example`, `tests/Feature/Mcp/McpEndpointTest.php` -- retirer l'accès HTTP et `MCP_ACCESS_TOKEN` -- fonctionnalité inutilisée, libère `/mcp`
- [ ] `app/Http/Controllers/McpController.php`, `routes/web.php` -- `index()` rend `Documents/Mcp` avec `projectPath` (`base_path()`), route nommée `mcp.index` -- point d'entrée mince
- [ ] `resources/js/Pages/Documents/Mcp.vue` -- rôle, outils, Claude Desktop/Claude Code, ChatGPT, config JSON copiable (`JSON.stringify` sur `projectPath`), section « Accès distant à venir » -- contenu de la page
- [ ] `resources/js/Components/Sidebar.vue` -- entrée « MCP » (icône, actif sur la page) -- accès via le menu
- [ ] `tests/Feature/McpPageTest.php`, `resources/js/Pages/Documents/__tests__/Mcp.spec.js`, `Sidebar.spec.js` -- page rendue avec `projectPath` ; chemin présent dans le JSON, outils listés ; item « MCP » présent et actif -- couverture

**Acceptance Criteria:**
- Given l'application, when on clique sur « MCP » dans le menu, then la page s'affiche avec l'entrée « MCP » active et l'explication du MCP local.
- Given le dépôt après changement, when on cherche `MCP_ACCESS_TOKEN` ou `AuthenticateMcpToken`, then plus aucune occurrence n'existe hors historique/spec.
- Given la suite de tests des outils MCP, when elle est exécutée, then elle passe sans modification.

## Implementation Notes

- Implémenté directement (pas de sous-agent). `GET /mcp` (`mcp.index`) rend `Documents/Mcp` avec `projectPath` ; l'entrée « MCP » vient après « Configuration ». Chemin normalisé en `/` dans la config affichée pour rester valide en JSON sous Windows.
- Retrait de l'accès HTTP : `Mcp::web`, `AuthenticateMcpToken`, `config/mcp.php`, bloc `.env.example`, `McpEndpointTest` (suppression approuvée). Le package `laravel/mcp` fusionne sa propre config `mcp` : rien ne dépend de celle supprimée. `POST /mcp` répond 405 (testé).
- Vérifié : suite PHP complète (274 tests), suite Vitest complète (212 tests), Pint OK. Un `npm run build` (ou `npm run dev`) est nécessaire pour voir la page.

## Spec Change Log

## Review Triage Log

Passe 1 : revue en ligne de l'agent principal. Passe 2 (à la demande de l'utilisateur) : 3 relecteurs indépendants (lecture aveugle, cas limites, trous de vérification).

| Constat | Verdict | Route | Preuve |
|---------|---------|-------|--------|
| Occurrence résiduelle de `MCP_ACCESS_TOKEN` / `AuthenticateMcpToken` / `mcp.access_token` | false | rejeté | Recherche sur tout le dépôt hors `vendor`, `_bmad-output` : aucune occurrence. |
| Suppression de `config/mcp.php` casse le package | false | rejeté | `McpServiceProvider` fait `mergeConfigFrom` de sa propre config ; suite complète verte. |
, `projectPath` vide (aveugle, cas limites) | low | rejeté | Le contrôleur fournit toujours `base_path()` ; un chemin Windows n'a ni `"` ni `---
title: 'Page « MCP » : expliquer le serveur MCP et retirer l’accès HTTP inutilisé'
type: 'feature'
created: '2026-09-30'
status: 'done'
baseline_commit: '4b6f18ecc881c48fcb3541545476fe2cb25c50fa'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/spec-mcp-base-connaissance.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** le serveur MCP (`bmad-demo`) n'est décrit nulle part dans l'interface : rien n'explique son rôle, ses outils, ni comment le brancher sur Claude ou ChatGPT. Par ailleurs `MCP_ACCESS_TOKEN` (`.env.example`) ne sert qu'à l'endpoint HTTP `/mcp`, qui n'est utilisé par personne aujourd'hui.

**Approche :** ajouter une page « MCP » au menu de navigation, en lecture seule, qui documente le fonctionnement actuel (local uniquement) et annonce que l'accès distant (jeton ou URL) se configurera plus tard depuis cette même page. Retirer en même temps tout l'accès HTTP au MCP (route, middleware, config, variable d'environnement, tests).

## Boundaries & Constraints

**Always :** la page suit le paradigme Thin Controller → Inertia (`AppLayout`, sidebar, thème clair/sombre existants). Textes en français, code en anglais. La config JSON affichée contient le vrai chemin absolu du projet (fourni par le serveur, pas codé en dur), avec un bouton pour la copier. L'entrée « MCP » de la sidebar est active uniquement sur cette page et suit le même style que les autres entrées (icône, mode replié `sr-only` + `title`).

**Never :** aucun formulaire ni réglage de jeton/URL dans cette page (seulement une mention « à venir »). Ne pas modifier le serveur local (`Mcp::local`), ses outils ni leur comportement. Ne pas ajouter de dépendance. Pas de nouveau README ni fichier de documentation.

## I/O & Edge-Case Matrix

| Scénario | Entrée / État | Comportement attendu | Gestion d'erreur |
|----------|--------------|----------------------|------------------|
| Ouverture de la page | `GET /mcp` | Rôle du serveur, les 3 outils (`search_documents`, `read_document`, `list_tags`), usage avec Claude Desktop / Claude Code, config JSON avec le chemin réel du projet | N/A |
| ChatGPT | Section « ChatGPT » | Indique qu'il exige un serveur distant (URL HTTPS), indisponible tant que l'accès distant n'existe pas | N/A |
| Accès distant | Section « Accès distant » | Mention « bientôt » : la configuration d'un jeton ou d'une URL se fera ici | N/A |
| Copie de la config | Clic sur « Copier » | Le JSON est copié ; le bouton confirme brièvement | Presse-papiers indisponible : le JSON reste sélectionnable, aucun message d'erreur bloquant |
| Ancien endpoint | `POST /mcp` | 404 / 405 (plus d'endpoint MCP HTTP) | N/A |

</frozen-after-approval>

## Code Map

- `routes/web.php` -- `GET /configuration` (`tags.index`) : modèle pour ajouter `GET /mcp` (`mcp.index`). Aujourd'hui `GET /mcp` est déjà pris par `Mcp::web` (à retirer).
- `routes/ai.php` -- ne garder que `Mcp::local('bmad-demo', KnowledgeBaseServer::class)` ; supprimer `Mcp::web` et l'import du middleware.
- `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php` -- à supprimer (seul usage du jeton). `.env.example` : supprimer le bloc `MCP_ACCESS_TOKEN` (l. 81-84, commentaire compris).
- `tests/Feature/Mcp/McpEndpointTest.php` -- teste uniquement l'endpoint HTTP : à supprimer (suppression approuvée par l'utilisateur). Les tests d'outils du même dossier restent.
- `app/Http/Controllers/TagController.php` (`index()`) -- style de controller d'une page Inertia simple à imiter ; `app/Mcp/Servers/KnowledgeBaseServer.php` et `app/Mcp/Tools/*` -- source des noms, rôles et paramètres des outils à décrire (ne pas modifier).
- `resources/js/Components/Sidebar.vue` -- ajouter l'item « MCP » après « Configuration » avec `isMcpActive` (`page.component === 'Documents/Mcp'`). `resources/js/Components/__tests__/Sidebar.spec.js` (l. 78 : liste d'items ordonnée) à mettre à jour.
- `resources/js/Pages/Documents/Configuration.vue` + `__tests__/Configuration.spec.js` -- modèle de page hors « documents » et de spec (mock d'`@inertiajs/vue3`, stub d'`AppLayout`).

## Tasks & Acceptance

**Execution:**
- [ ] `routes/ai.php`, `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php`, `.env.example`, `tests/Feature/Mcp/McpEndpointTest.php` -- retirer l'accès HTTP et `MCP_ACCESS_TOKEN` -- fonctionnalité inutilisée, libère `/mcp`
- [ ] `app/Http/Controllers/McpController.php`, `routes/web.php` -- `index()` rend `Documents/Mcp` avec `projectPath` (`base_path()`), route nommée `mcp.index` -- point d'entrée mince
- [ ] `resources/js/Pages/Documents/Mcp.vue` -- rôle, outils, Claude Desktop/Claude Code, ChatGPT, config JSON copiable (`JSON.stringify` sur `projectPath`), section « Accès distant à venir » -- contenu de la page
- [ ] `resources/js/Components/Sidebar.vue` -- entrée « MCP » (icône, actif sur la page) -- accès via le menu
- [ ] `tests/Feature/McpPageTest.php`, `resources/js/Pages/Documents/__tests__/Mcp.spec.js`, `Sidebar.spec.js` -- page rendue avec `projectPath` ; chemin présent dans le JSON, outils listés ; item « MCP » présent et actif -- couverture

**Acceptance Criteria:**
- Given l'application, when on clique sur « MCP » dans le menu, then la page s'affiche avec l'entrée « MCP » active et l'explication du MCP local.
- Given le dépôt après changement, when on cherche `MCP_ACCESS_TOKEN` ou `AuthenticateMcpToken`, then plus aucune occurrence n'existe hors historique/spec.
- Given la suite de tests des outils MCP, when elle est exécutée, then elle passe sans modification.

## Implementation Notes

- Implémenté directement (pas de sous-agent). `GET /mcp` (`mcp.index`) rend `Documents/Mcp` avec `projectPath` ; l'entrée « MCP » vient après « Configuration ». Chemin normalisé en `/` dans la config affichée pour rester valide en JSON sous Windows.
- Retrait de l'accès HTTP : `Mcp::web`, `AuthenticateMcpToken`, `config/mcp.php`, bloc `.env.example`, `McpEndpointTest` (suppression approuvée). Le package `laravel/mcp` fusionne sa propre config `mcp` : rien ne dépend de celle supprimée. `POST /mcp` répond 405 (testé).
- Vérifié : suite PHP complète (274 tests), suite Vitest complète (212 tests), Pint OK. Un `npm run build` (ou `npm run dev`) est nécessaire pour voir la page.

## Spec Change Log

## Review Triage Log

Passe 1 : revue en ligne de l'agent principal. Passe 2 (à la demande de l'utilisateur) : 3 relecteurs indépendants (lecture aveugle, cas limites, trous de vérification).

| Constat | Verdict | Route | Preuve |
|---------|---------|-------|--------|
| Occurrence résiduelle de `MCP_ACCESS_TOKEN` / `AuthenticateMcpToken` / `mcp.access_token` | false | rejeté | Recherche sur tout le dépôt hors `vendor`, `_bmad-output` : aucune occurrence. |
| Suppression de `config/mcp.php` casse le package | false | rejeté | `McpServiceProvider` fait `mergeConfigFrom` de sa propre config ; suite complète verte. |
 ; correctifs = branches et échappements. |
| Échec de copie silencieux, pas d'`aria-live`, deux boutons « Copier » (aveugle) | low | rejeté | Silence voulu par la spec (« aucun message bloquant ») ; l'aria-label créerait un écart entre nom accessible et texte visible. |
| Démontage pendant une copie en attente laisse un minuteur (cas limites) | low | rejeté | Mise à jour d'un ref détaché, sans effet visible ; le garde ajoute un drapeau. |
| `config/mcp.php` supprimé : valeurs par défaut du package actives (cas limites) | false | rejeté | Le package fusionne sa propre config ; OAuth/redirections n'existent que pour `Mcp::web`, retiré. |
| `href="/mcp"` en dur dans la sidebar, icône à points de 0,01, commentaire de spec dans le test (aveugle) | low | rejeté | Même convention que les autres entrées de la sidebar. |

## Design Notes

Contenu de la page statique côté Vue (noms et rôles des outils) : trois outils stables, pas de mécanisme de découverte à construire. Config Claude Desktop : `{"mcpServers":{"bmad-demo":{"command":"php","args":["<projectPath>/artisan","mcp:start","bmad-demo"]}}}` ; Claude Code : `claude mcp add bmad-demo -- php <projectPath>/artisan mcp:start bmad-demo`. Page dans `Pages/Documents/` comme `Configuration.vue` (pas de nouveau dossier).

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/McpPageTest.php tests/Feature/Mcp` -- expected: tout passe
- `npx vitest run resources/js/Pages/Documents/__tests__/Mcp.spec.js resources/js/Components/__tests__/Sidebar.spec.js` -- expected: tout passe
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart restant
- `php artisan route:list --path=mcp` -- expected: uniquement `GET /mcp` (`mcp.index`)

**Manual checks (if no CLI):**
- Ouvrir `/mcp` en clair et en sombre, sidebar dépliée et repliée ; copier la config et vérifier le chemin.
