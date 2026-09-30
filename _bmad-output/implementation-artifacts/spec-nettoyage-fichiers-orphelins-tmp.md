---
title: 'Nettoyage des fichiers orphelins (brouillons tmp et échecs d''enregistrement)'
type: 'bugfix'
created: '2026-09-30'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '73ad58690578d4aff7acc905a8dd4176b134abd6'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Des fichiers restent orphelins sur `documents/tmp/{token}` (brouillon abandonné via « Annuler », onglet fermé, plantage) et sous `documents/{id}` (fichiers déjà déplacés quand l'enregistrement échoue juste après) — décision P3 de `deferred-work.md` (2026-09-29).

**Approach:** « Annuler » (Éditeur et Import) supprime `documents/tmp/{token}` côté serveur ; chaque envoi de brouillon (image ou pièce jointe) purge en plus, au fil de l'eau et sans scheduler, tout dossier `tmp/*` de plus de 24h ; un échec survenant après un déplacement de fichier réussi (images d'éditeur, fichier importé) supprime ce qui a déjà été déplacé, comme le fait déjà `RelocatesDraftAttachments` pour les pièces jointes.

## Boundaries & Constraints

**Always:**
- « Annuler » (Editor.vue et Import.vue) envoie une requête `DELETE` qui supprime `documents/tmp/{token}` avant de naviguer — jamais bloquante, jamais de confirmation de sortie (comportement inchangé).
- Chaque upload de brouillon (`storeEditorImage`, `storeEditorAttachment`) purge, dans la même requête, tout dossier sous `documents/tmp/*` dont la dernière modification date de plus de 24h — aucun job, aucune tâche planifiée.
- Un échec survenant après un déplacement disque déjà réussi (relocalisation d'images d'éditeur, fichier principal importé) supprime les fichiers/dossier déjà déplacés avant de relancer l'exception — le rollback DB et le disque restent cohérents, même mécanisme que `RelocatesDraftAttachments::relocateDraftAttachments()`.
- La purge des vieux dossiers `tmp/*` est best-effort : une erreur sur un dossier ne doit jamais faire échouer l'upload en cours.

**Ask First:** étendre ce nettoyage à `UpdateDocumentAction` (édition d'un document existant) — hors périmètre de la décision P3, qui ne cite que « images de l'éditeur » et « fichier principal de l'import » au moment de la création.

**Never:** pas de scheduler ni de tâche planifiée (décision explicite) ; pas de nettoyage des images déjà relocalisées pendant une mise à jour d'un document existant ; ne pas faire attendre la navigation après « Annuler » sur la fin de la requête de suppression (fire-and-forget).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Annuler sans brouillon uploadé | `draftToken` jamais utilisé | `DELETE` no-op (dossier absent), navigation immédiate | N/A |
| Annuler après upload d'image/pièce jointe | `documents/tmp/{token}` existant | Dossier supprimé, navigation vers `cancelUrl` | N/A |
| Upload de brouillon avec un vieux dossier tmp d'un autre token | `tmp/{autre}` modifié il y a >24h | Ce dossier est supprimé pendant le traitement de l'upload courant | Échec de purge journalisé (`Log::warning`), upload courant non impacté |
| Upload de brouillon avec un dossier tmp récent | `tmp/{autre}` modifié il y a <24h | Dossier conservé | N/A |
| Enregistrement éditeur : image relocalisée puis relocalisation de pièce jointe en échec | `Storage::move()` échoue seulement sur le chemin de la pièce jointe | `documents/{id}` supprimé en entier (image incluse), transaction annulée | Exception relancée |
| Enregistrement import/éditeur : `SyncDocumentTagsAction` échoue après la relocalisation | Exception forcée après `import()`/`create()` | `documents/{id}` supprimé, aucun `Document` créé | Exception relancée |

</frozen-after-approval>

## Code Map

- `app/Actions/PurgeStaleDraftDirectoriesAction.php` (nouveau) -- scanne `documents/tmp/*` (`Storage::directories()`), supprime chaque dossier dont `lastModified()` date de plus de 24h ; ignore silencieusement (skip) un dossier dont `lastModified()`/`deleteDirectory()` lève.
- `app/Actions/UploadEditorImageAction.php` -- injecte `PurgeStaleDraftDirectoriesAction` (constructeur, patron `UpdateDocumentAction`/`SyncDocumentTagsAction`), l'appelle après son propre stockage réussi, dans un `try/catch` qui journalise sans jamais faire échouer l'upload.
- `app/Actions/UploadDraftAttachmentAction.php` -- même traitement que ci-dessus.
- `app/Actions/DeleteDraftDirectoryAction.php` (nouveau) -- invokable, supprime `documents/tmp/{draftToken}` sans condition (`throw => false` sur le disque `local`, no-op si absent).
- `app/DataTransferObjects/DeleteDraftDirectoryData.php` (nouveau) -- `{draftToken: string}`, patron `DeleteTagData.php`.
- `routes/web.php:44` -- ajoute `Route::delete('/documents/create/draft/{token}', [DocumentController::class, 'destroyDraft'])->whereUuid('token')->name('documents.draft.destroy');` juste après la route `documents.editorAttachments.store`.
- `app/Http/Controllers/DocumentController.php` -- nouvelle méthode `destroyDraft(string $token, DeleteDraftDirectoryAction $action): HttpResponse` -- appelle l'action, retourne `response()->noContent()` (204 : requête `useHttp`, jamais une visite Inertia, donc jamais de redirect 3xx) ; dans `store()` (L.238) et `storeCreated()` (L.305), entoure l'appel à `$syncTags(...)` d'un `try/catch (Throwable $exception)` qui supprime `Storage::disk('local')->deleteDirectory("documents/{$document->id}")` avant de relancer.
- `app/Actions/CreateDocumentAction.php` -- entoure `relocateDraftImages()` + le `forceFill()->save()` conditionnel + `relocateDraftAttachments()` d'un `try/catch (Throwable $exception)` qui supprime `documents/{$document->id}` avant de relancer (mêmes imports `Storage`/`Throwable` que `ImportDocumentAction.php`).
- `resources/js/Pages/Documents/Editor.vue:642-648` -- remplace le `<Link :href="cancelUrl">` par un `<button type="button" @click="onCancelClick">` ; `onCancelClick()` appelle `useHttp({}).delete('/documents/create/draft/'+draftToken)` sans l'attendre puis `router.visit(cancelUrl)` (import `useHttp` depuis `@inertiajs/vue3`, `router` déjà importé).
- `resources/js/Pages/Documents/Import.vue:294-300` -- même traitement ; `cancelUrl` devient une constante `'/'`, import `router`/`useHttp` ajoutés.
- `resources/js/Pages/Documents/__tests__/Editor.spec.js` -- mock `router.visit`/`useHttp` (ajout au mock `@inertiajs/vue3` L.112) ; `findCancelLink` devient `findCancelButton` (cherche un `<button>`, plus un `<a>`) ; les tests "sends no request"/"points Annuler to" sont réécrits pour vérifier l'appel `useHttp` delete + `router.visit`, pas l'absence de requête.
- `resources/js/Pages/Documents/__tests__/Import.spec.js` -- même traitement (mock L.18, helper `findCancelLink` L.74).
- `tests/Feature/UploadEditorImageTest.php` -- ajoute la purge : un vieux dossier `tmp/{autre}` (fichier physique `touch()`é à >24h) est supprimé après un nouvel upload ; un dossier `tmp/{autre}` récent est conservé.
- `tests/Feature/CreateDocumentTest.php` -- réutilise `uploadDraftAttachment()` (L.44) pour ajouter : (a) même couverture de purge que ci-dessus côté pièce jointe si pertinent, (b) un test « image relocalisée puis pièce jointe en échec » (mock sélectif de `Storage::move()`, patron L.381 `ImportDocumentTest.php`) assertant `documents/{id}` totalement absent, (c) un test `SyncDocumentTagsAction` échouant (`$this->mock(SyncDocumentTagsAction::class, …)->andThrow(...)`) assertant `documents/{id}` absent et `Document::count()` à 0.
- `tests/Feature/ImportDocumentTest.php` -- même test `SyncDocumentTagsAction` échouant côté `store()`.
- `tests/Feature/DestroyDraftDirectoryTest.php` (nouveau) -- couvre la matrice I/O de « Annuler » : dossier supprimé, no-op si absent, route contrainte UUID.

## Tasks & Acceptance

**Execution:**
- [x] `app/Actions/PurgeStaleDraftDirectoriesAction.php` -- purge >24h best-effort -- P3 point 2.
- [x] `app/Actions/UploadEditorImageAction.php`, `app/Actions/UploadDraftAttachmentAction.php` -- appellent la purge après leur upload -- au fil de l'eau.
- [x] `app/Actions/DeleteDraftDirectoryAction.php`, `app/DataTransferObjects/DeleteDraftDirectoryData.php` -- suppression inconditionnelle d'un `tmp/{token}` -- P3 point 1.
- [x] `routes/web.php`, `app/Http/Controllers/DocumentController.php` (`destroyDraft`) -- route + entrée serveur pour « Annuler ».
- [x] `app/Http/Controllers/DocumentController.php` (`store()`, `storeCreated()`) -- nettoyage disque si `SyncDocumentTagsAction` échoue après relocalisation.
- [x] `app/Actions/CreateDocumentAction.php` -- nettoyage disque si un échec survient après `relocateDraftImages()`.
- [x] `resources/js/Pages/Documents/Editor.vue`, `resources/js/Pages/Documents/Import.vue` -- « Annuler » déclenche la suppression puis navigue.
- [x] Tests Feature listés au Code Map -- matrice I/O + cas d'échec.
- [x] Tests JS listés au Code Map -- nouveau comportement d'« Annuler ».

**Acceptance Criteria:**
- Given un brouillon avec une image insérée jamais enregistré, when je clique « Annuler », then `documents/tmp/{token}` n'existe plus sur le disque.
- Given deux uploads de brouillon successifs à plus de 24h d'intervalle sur deux tokens différents, when le second upload arrive, then le dossier du premier token a disparu et celui du second existe.
- Given une image d'éditeur déjà relocalisée, when la relocalisation de la pièce jointe qui suit échoue, then `documents/{id}` n'existe plus et aucun `Document` n'est resté en base.

## Design Notes

`useHttp({}).delete(url)` (Inertia v3) envoie la requête sans provoquer de visite Inertia ni changer les props de la page courante — le CSRF est géré automatiquement via le cookie `XSRF-TOKEN`, comme pour `useForm`. Ne pas attendre sa résolution avant `router.visit(cancelUrl)` : l'app reste une SPA, la requête `fetch` survit à la navigation suivante. Le contrôleur doit répondre par un statut 2xx sans corps (`response()->noContent()`) — jamais une redirection 3xx, qu'`useHttp` traiterait comme un échec.

Pour forcer un échec précisément après une relocalisation d'image réussie (test `CreateDocumentTest.php`), stuber sélectivement `Storage::move()` pour qu'il échoue uniquement sur un chemin contenant `/attachments/`, en déléguant le reste au disque réel — patron à adapter de `ImportDocumentTest.php:381` qui, lui, fait échouer tous les déplacements.

## Verification

**Commands:**
- `vendor/bin/pint --dirty --format agent` -- expected: sans erreur.
- `php artisan test --compact tests/Feature/CreateDocumentTest.php tests/Feature/ImportDocumentTest.php tests/Feature/UploadEditorImageTest.php tests/Feature/DestroyDraftDirectoryTest.php` -- expected: verts.
- `npx vitest run resources/js/Pages/Documents/__tests__/Editor.spec.js resources/js/Pages/Documents/__tests__/Import.spec.js` -- expected: verts.
- `npm run build` -- expected: sans erreur.
- `php artisan test --compact` -- expected: suite complète verte (hors flake connu `DeleteDocumentTest`, déjà journalisé).

## Suggested Review Order

**Purge des dossiers `tmp/*` abandonnés (>24h)**

- Point d'entrée : la purge ne se fie plus au mtime du dossier `{token}` lui-même, mais au fichier le plus récent qu'il contient — corrige le bug (revue de code) où un brouillon encore actif pouvait être balayé par son propre envoi.
  [`PurgeStaleDraftDirectoriesAction.php:79`](../../app/Actions/PurgeStaleDraftDirectoriesAction.php#L79)

- Boucle principale : ignore silencieusement (journalise) tout dossier en échec plutôt que d'interrompre la purge ou l'upload en cours.
  [`PurgeStaleDraftDirectoriesAction.php:45`](../../app/Actions/PurgeStaleDraftDirectoriesAction.php#L45)

- Injectée et appelée après un envoi d'image d'éditeur réussi, jamais avant.
  [`UploadEditorImageAction.php:59`](../../app/Actions/UploadEditorImageAction.php#L59)

**Nettoyage disque sur échec après relocalisation**

- Helper partagé : supprime `documents/{id}`, ne masque jamais l'exception d'origine si la suppression elle-même échoue.
  [`CleansUpDocumentDirectoryOnFailure.php:29`](../../app/Actions/Concerns/CleansUpDocumentDirectoryOnFailure.php#L29)

- `CreateDocumentAction` : la relocalisation d'images puis de pièces jointes est désormais entourée d'un `try/catch` qui délègue au helper.
  [`CreateDocumentAction.php:90`](../../app/Actions/CreateDocumentAction.php#L90)

- `DocumentController::store()` : nettoyage si `SyncDocumentTagsAction` échoue après l'import.
  [`DocumentController.php:266`](../../app/Http/Controllers/DocumentController.php#L266)

- `DocumentController::storeCreated()` : même garde côté création.
  [`DocumentController.php:342`](../../app/Http/Controllers/DocumentController.php#L342)

**« Annuler » supprime son brouillon**

- Nouvelle route, jamais en conflit avec `GET /documents/{document}` (verbe et forme de chemin différents).
  [`web.php:53`](../../routes/web.php#L53)

- `destroyDraft()` : délègue à l'action, répond toujours 204 même si le nettoyage disque échoue en coulisses.
  [`DocumentController.php:402`](../../app/Http/Controllers/DocumentController.php#L402)

- `DeleteDraftDirectoryAction` : suppression inconditionnelle, échec journalisé et avalé (jamais remonté à l'appelant fire-and-forget).
  [`DeleteDraftDirectoryAction.php:27`](../../app/Actions/DeleteDraftDirectoryAction.php#L27)

- Éditeur : `@click.prevent` sur un vrai `<a href>` — le clic gauche déclenche la suppression puis navigue, le clic milieu/Ctrl+clic garde son comportement natif.
  [`Editor.vue:668`](../../resources/js/Pages/Documents/Editor.vue#L668)

- Import : même traitement.
  [`Import.vue:321`](../../resources/js/Pages/Documents/Import.vue#L321)

**Tests**

- Régression clé : un dossier de brouillon encore actif survit à sa propre purge malgré un mtime de dossier parent trompeur.
  [`UploadEditorImageTest.php:109`](../../tests/Feature/UploadEditorImageTest.php#L109)

- Couvre le nettoyage-sur-échec après relocalisation d'image puis de pièce jointe.
  [`CreateDocumentTest.php:520`](../../tests/Feature/CreateDocumentTest.php#L520)

- Couvre le nettoyage-sur-échec quand `SyncDocumentTagsAction` échoue après création.
  [`CreateDocumentTest.php:557`](../../tests/Feature/CreateDocumentTest.php#L557)

- Même cas côté import.
  [`ImportDocumentTest.php:406`](../../tests/Feature/ImportDocumentTest.php#L406)

- Matrice I/O de la suppression inconditionnelle du dossier de brouillon.
  [`DestroyDraftDirectoryTest.php:12`](../../tests/Feature/DestroyDraftDirectoryTest.php#L12)
