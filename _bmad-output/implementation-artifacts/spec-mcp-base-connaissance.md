---
title: 'Serveur MCP : base de connaissance interrogeable par une IA'
type: 'feature'
created: '2026-09-30'
status: 'done'
baseline_commit: '55ee5e7a6e365b72646b6322f2bf7b68feddc299'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** les documents de bmad-demo (importés ou créés, pièces jointes incluses) ne sont consultables que par l'interface web ; on ne peut pas s'en servir comme base de connaissance depuis Claude, ChatGPT ou un autre assistant IA.

**Approche :** exposer la bibliothèque via un serveur MCP (package officiel `laravel/mcp`) offrant des outils de recherche par mots-clés et de lecture du texte d'un document, qui réutilisent la recherche existante (Scout, `KeywordDatabaseEngine`, AD-8) sans la dupliquer.

## Boundaries & Constraints

**Always :** un seul chemin de recherche (AD-8) : une `SearchDocumentsAction` (DTO en entrée, AD-2/AD-3) porte termes, filtre tag, bornes et pagination, et sert à la fois `DocumentController@search` et l'outil MCP. Les outils MCP sont des adaptateurs minces (équivalent d'un controller, AD-1). Les fichiers originaux et leurs chemins disque (`file_path`) ne sont jamais exposés, seulement le texte extrait. Les échecs (document introuvable, texte non extrait) sont des réponses d'outil explicites, jamais une exception brute (AD-9). Code en anglais, textes destinés au client MCP en français. Outils en **lecture seule** (rechercher, lire, lister les tags) ; `laravel/mcp` passe dans `require` (accord donné).

**Never :** pas d'API JSON applicative (AD-13 : seul le protocole MCP est ajouté). Pas de second moteur de recherche ni de vector store. Pas d'OCR. Ne pas modifier le comportement de la page Recherche. Aucun outil d'écriture (création de document, tags, suppression).

## I/O & Edge-Case Matrix

| Scénario | Entrée / État | Comportement attendu | Gestion d'erreur |
|----------|--------------|----------------------|------------------|
| Recherche | `query` = « facture janvier » | Page de résultats (id, titre, type, tags, extrait autour de la correspondance), même classement que la page Recherche | N/A |
| Recherche vide | `query` vide et sans tag | Aucun résultat, jamais la bibliothèque entière | Message expliquant qu'il faut un terme ou un tag |
| Lecture | `document_id` valide | Titre, type, tags, texte extrait (document + pièces jointes nommées), tronqué à N caractères avec `offset` pour la suite | N/A |
| Texte indisponible | `extraction_status` ≠ `completed` | Métadonnées + mention « texte non disponible (statut) » | N/A |
| Id inconnu | `document_id` inexistant | Réponse d'erreur d'outil claire | Pas d'exception |
| Accès HTTP autorisé | `POST /mcp/...` avec `Authorization: Bearer <MCP_ACCESS_TOKEN>` | Le serveur répond normalement | N/A |
| Accès HTTP refusé | Jeton absent, erroné, ou `MCP_ACCESS_TOKEN` non défini | 401, aucun outil exécuté | Jeton comparé en temps constant ; un jeton non défini refuse tout, jamais d'accès ouvert |

**Décisions :** le serveur est exposé en local (stdio, `Mcp::local`) et en HTTP (`Mcp::web`, jeton bearer statique dans `.env`) afin que ChatGPT et claude.ai puissent s'y connecter ; l'exposition HTTPS (Herd Share, ngrok…) reste hors du code. Cette entrée HTTP déroge à NFR3 (« sans authentification ») pour elle seule : l'interface web de l'application reste inchangée. Outils en lecture seule.

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php` -- `search()` (bornes `MAX_SEARCH_LENGTH`, `MAX_TAG_FILTERS`, `SEARCH_PER_PAGE`, `tagIdsFromQuery()`, `applyFilters()`) : à extraire dans `SearchDocumentsAction`, le controller n'en garde que le parsing de requête et la réponse Inertia. `show()` : liste de colonnes de référence, `file_path` jamais exposé.
- `app/Support/KeywordDatabaseEngine.php` -- syntaxe des mots-clés (`+`, `-`, guillemets), `MAX_KEYWORDS`, `exceedsKeywordLimit()` ; à réutiliser tel quel.
- `app/Models/Document.php`, `DocumentAttachment.php`, `Tag.php` -- `extracted_text`, `attachments()` (texte par pièce jointe), `tags()`, `Enums/ExtractionStatus`, `Enums/DocumentSource`.
- `app/Actions/DeleteTagAction.php` + `app/DataTransferObjects/DeleteTagData.php` -- modèle de style Action/DTO à suivre.
- `tests/Feature/SearchDocumentsTest.php` -- filet de non-régression de la page Recherche pendant l'extraction ; style Pest + factories (`Document::factory()`).
- `vendor/laravel/mcp` (v1.0.0) -- `Mcp::local()` / `Mcp::web()` dans `routes/ai.php` (à créer), `make:mcp-server` / `make:mcp-tool`, tests via `Server::tool(...)->assertOk()->assertSee()`.

## Tasks & Acceptance

**Execution:**
- [x] `composer.json` -- déplacer `laravel/mcp` dans `require` (après accord) -- dépendance d'exécution
- [x] `app/Actions/SearchDocumentsAction.php` + `app/DataTransferObjects/SearchDocumentsData.php` -- extraire la recherche (bornes, filtre tag, pagination) de `DocumentController::search` -- un seul chemin (AD-8)
- [x] `app/Http/Controllers/DocumentController.php` -- déléguer `search()` à l'Action, comportement inchangé -- pas de divergence
- [x] `app/Mcp/Servers/KnowledgeBaseServer.php`, `app/Mcp/Tools/SearchDocumentsTool.php`, `ReadDocumentTool.php`, `ListTagsTool.php` -- serveur et outils en lecture, schémas d'entrée décrits en français pour guider l'IA -- cœur de la fonctionnalité
- [x] `routes/ai.php`, `app/Http/Middleware/AuthenticateMcpToken.php`, `config/mcp.php`, `.env.example` -- enregistrer `Mcp::local` et `Mcp::web` (protégé par le middleware de jeton bearer, `MCP_ACCESS_TOKEN` lu via config, jamais `env()` direct) -- points d'entrée
- [x] `tests/Feature/Mcp/*Test.php` -- tester chaque outil (I/O Matrix) et le refus HTTP sans jeton / avec mauvais jeton / jeton non défini -- couverture des cas d'échec

**Acceptance Criteria:**
- Given des documents indexés, when l'outil de recherche reçoit des mots-clés, then les résultats et leur ordre sont identiques à ceux de la page Recherche.
- Given un document dont le texte dépasse la limite, when l'outil de lecture est appelé avec `offset`, then la suite du texte est renvoyée sans doublon ni perte.
- Given la suite de tests existante, when l'extraction est faite, then `SearchDocumentsTest` passe sans modification.

## Implementation Notes

- Implémenté directement (pas de sous-agent). `SearchDocumentsAction` porte les bornes et la requête Scout ; `DocumentController@search` ne garde que le parsing de requête. `applyFilters()` (privé, `$types` toujours vide) a été supprimé avec l'extraction. Les colonnes retournées restent celles de la page Recherche : l'extrait de l'outil MCP lit `extracted_text` dans une requête séparée, pour ne pas alourdir les props Inertia.
- Ajouts hors liste de tâches : `App\Mcp\DocumentPresenter` (forme des documents côté IA), `App\Support\TextExcerpt` (extrait autour de la correspondance), `KeywordDatabaseEngine::matchableWordsFrom()` (mots positifs pour l'extrait), `tests/Unit/TextExcerptTest.php`.
- `composer require` a aussi fait passer `laravel/mcp` de v1.0.0 à v1.0.1 (correctif), sans autre changement dans `composer.lock`.
- Le `README.md` prévu au départ a été retiré des tâches : `AGENTS.md` n'autorise la documentation que sur demande explicite.
- Vérifié : 90 tests passent (`tests/Feature/Mcp`, `tests/Unit`, `SearchDocumentsTest`, `BrowseLibraryTest`) ; serveur stdio `php artisan mcp:start bmad-demo` répond à `initialize` et `tools/list`.

## Spec Change Log

## Review Triage Log

Passe 1 (3 relecteurs : lecture aveugle, cas limites, trous de vérification).

| Constat | Verdict | Route | Preuve |
|---------|---------|-------|--------|
| Extrait pris dans `extracted_text` même si la correspondance n'est que dans une pièce jointe (aveugle, cas limites, vérification) | medium | patch | Le `?:` ne bascule que si le texte principal est vide ; `TextExcerpt::aroundFirstMatch()` choisit désormais le texte qui contient un mot, testé. |
| Plafond de 20 tags de l'action non testé côté MCP | medium | patch | Test ajouté : 25 tags, seul le 21ᵉ+ porte un document. |
| Page très grande / `document_id` non borné | low | patch | `page` limité à 100000, `document_id` à `min:1` (correction directe, une règle). |
| Pièces jointes lues sans ordre, tranches instables | medium | patch | `orderBy('id')` ajouté à la relation chargée par `read_document`. |
| Jeton court ou faible accepté | medium | patch | `AuthenticateMcpToken::MINIMUM_TOKEN_LENGTH = 32`, testé ; documenté dans `.env.example`. |
| Règle « terme vide » dupliquée dans le controller | low | patch | `withQueryString()` appliqué toujours ; `SearchDocumentsTest` passe inchangé. |
| Références périmées à `applyFilters()` dans `DocumentMimeTypes` | low | patch | Commentaire corrigé (le commentaire historique d'un test existant est conservé). |
| `matchableWordsFrom()` sans test ; `tools/call` HTTP avec jeton non testé | low | patch | Tests ajoutés (`KeywordDatabaseEngineTest`, `McpEndpointTest`). |
| Commentaires disant que ChatGPT/claude.ai accèdent à `/mcp` avec un jeton statique | maybe-false | defer | Non vérifiable sans connexion réelle ; formulations retirées de `.env.example` et `routes/ai.php`. Voir `deferred-work.md`. |
| Extrait non insensible aux accents (`mb_stripos`) | low | rejeté | Purement cosmétique (repli sur le début du texte) ; le correctif demande de replier les accents avec recalcul des positions. |
| Terme sans mot-clé positif (`-foo`) renvoie 0 sans explication ; `offset` > longueur sans note ; note « non disponible (completed) » pour un texte vide | low | rejeté | Même comportement que la page Recherche (AC2), sans incidence en usage courant ; les correctifs ajoutent des branches. |
| Troncature silencieuse à 255 caractères / 20 tags sans signal au client | low | rejeté | Les bornes tronquent partout par décision d'AD-8 ; un signal ajouterait des champs de réponse. |
| Pas de limitation de débit sur `/mcp` | low | rejeté | Jeton d'au moins 32 caractères aléatoires ; un throttle par IP pénaliserait aussi le client légitime derrière un tunnel. |
| Texte UTF-8 invalide fait échouer `Response::structured` | false | rejeté | Le texte vient de colonnes MySQL `utf8mb4` qui refusent l'UTF-8 invalide, et `mb_substr` ne coupe pas au milieu d'un caractère. |
| Statut d'extraction nul lève une erreur | false | rejeté | Colonne `enum` non nulle avec valeur par défaut. |
| Chemin de pagination de l'action hors requête HTTP | false | rejeté | Branche jamais atteinte côté MCP (l'outil refuse en amont un terme vide sans tag) ; `resolveCurrentPath()` retombe sur `/`. |
| Filtre `type[]` supprimé avec `applyFilters()` | false | rejeté | Voulu : `search()` passait toujours `$types = []` ; les imports restants sont encore utilisés (Pint OK). |
| Contenu modifié entre deux lectures paginées | low | rejeté | Inhérent à toute lecture par offset ; sans incidence sur un corpus mono-utilisateur. |
| Pas d'instructions d'installation du serveur stdio | low | rejeté | `AGENTS.md` : documentation seulement sur demande explicite. |

## Design Notes

Le texte renvoyé vient d'`extracted_text` (déjà dérivé de `content_html` pour les documents créés, AD-9) : pas de HTML transmis à l'IA. La lecture est bornée par appel (environ 20 000 caractères) pour ne pas saturer le contexte de l'assistant ; `offset` permet de paginer.

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/Mcp tests/Feature/SearchDocumentsTest.php` -- expected: tout passe
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart restant
- `php artisan mcp:inspector <handle>` -- expected: les outils apparaissent et répondent

**Manual checks (if no CLI):**
- Brancher le serveur dans Claude Desktop ou Claude Code et poser une question dont la réponse est dans un document importé.
