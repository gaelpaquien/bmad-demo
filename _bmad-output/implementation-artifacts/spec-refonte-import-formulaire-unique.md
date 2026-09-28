---
title: 'Refonte de l''import en formulaire unique'
type: 'feature'
created: '2026-09-28'
status: 'done'
review_loop_iteration: 0
baseline_commit: '5d2347cba34883c7aaee446ee9d64901893dcd67'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Choisir un fichier sur la page d'import crée immédiatement le document et lance l'extraction de texte ; les tags ne s'ajoutent qu'ensuite (étape de revue) et les pièces jointes pas du tout. Se tromper de fichier oblige à supprimer un document déjà créé.

**Approach:** Transformer la page d'import en formulaire classique : fichier choisi (retirable/remplaçable), tags, pièces jointes, puis « Enregistrer » qui envoie le tout en une seule requête — c'est seulement là que le document est créé et l'extraction lancée — ou « Annuler ».

## Boundaries & Constraints

**Always:**
- Choisir un fichier n'envoie aucune requête : il est gardé localement (validation client existante `useFileDropZone`), affiché (nom + type) avec un bouton « Retirer » qui ramène à la zone de dépôt.
- Tags via `TagSelector`, pièces jointes via `AttachmentsPanel` en mode `draft` (même mécanique que l'éditeur : `draft_token` généré côté client, upload vers `/documents/create/attachments`).
- « Enregistrer » (désactivé sans fichier, pendant l'envoi, et pendant un upload de pièce jointe) : un seul `POST /documents` multipart avec `file`, `tag_ids`, `draft_token`, `draft_attachments`. Succès → redirection vers la fiche du document. Création, tags et pièces jointes dans une même transaction ; une extraction par pièce jointe dispatchée après commit (comme `storeCreated`).
- Le titre reste le nom du fichier ; aucun champ titre.
- « Annuler » (secondaire) → `/documents`, inerte pendant l'envoi ou un upload de pièce jointe. Aucune confirmation de sortie (ni garde Inertia, ni `beforeunload`).
- Erreur de validation serveur : formulaire conservé (fichier, tags, pièces jointes), message affiché.

**Ask First:** Toute modification de `AttachmentsPanel.vue`, `TagSelector.vue`, `useFileDropZone`, des routes, ou du flux de l'éditeur au-delà du partage de la relocalisation des pièces jointes.

**Never:** Champ titre ; création du document avant « Enregistrer » ; nettoyage des fichiers brouillon abandonnés (trou déjà accepté) ; changement du job d'extraction.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Choix du fichier | Fichier valide déposé/parcouru | Fichier affiché + « Retirer », aucune requête, aucun document créé | N/A |
| Fichier invalide | Mauvais format / > 20 Mo | Reste sur la zone de dépôt | Message client existant |
| Retirer | Fichier choisi, clic « Retirer » | Retour à la zone de dépôt, tags/pièces jointes conservés | N/A |
| Enregistrer complet | Fichier + 2 tags + 1 pièce jointe | 1 POST ; document, tags et pièce jointe créés ; 2 jobs d'extraction ; redirection fiche | N/A |
| Enregistrer sans fichier | Aucun fichier | Bouton désactivé, aucune requête | N/A |
| Refus serveur | Fichier rejeté (taille, type réel) | Reste sur la page, saisie conservée | `form.errors.file` affiché |
| Annuler | N'importe quel état hors envoi | Navigation vers `/documents`, aucune requête, aucune confirmation | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Pages/Documents/Import.vue` -- réécriture : retirer l'étape 2 (`uploadedDocument`, flash watcher, `tagsForm`, `deleteUploadedDocument`, `saveTags`), la garde `router.on('before')` et `beforeunload`. Reprendre la zone de dépôt L.252-281 et `useFileDropZone` ; carte fichier sur le modèle L.293-306 (`DocumentTypeBadge` avec `file.type`). Modèle de formulaire/`draftToken`/`AttachmentsPanel`/Annuler inerte : `Editor.vue` (`draftToken` L.35, `useForm` L.37, `AttachmentsPanel` + `v-model:uploading`, bouton Annuler en fin de template).
- `app/Http/Controllers/DocumentController.php:209` `store()` -- passer `draft_token`/`draft_attachments` au DTO, dispatcher `ExtractDocumentTextJob` par pièce jointe après la transaction, `to_route('documents.show', $document)`. Supprimer les branches devenues mortes : `?redirect=show` dans `updateTags()` (L.462) et `?redirect=import` dans `destroy()` (L.488), avec leurs docblocks.
- `app/Actions/CreateDocumentAction.php:114` `relocateDraftAttachments()` -- à déplacer tel quel dans un trait `app/Actions/Concerns/RelocatesDraftAttachments.php` (modèle : `SanitizesDocumentContent`), utilisé par `CreateDocumentAction` et `ImportDocumentAction`.
- `app/Actions/ImportDocumentAction.php:35` -- dans sa transaction, après `file_path`, relocaliser les pièces jointes et `setRelation('attachments', …)` comme `CreateDocumentAction:85-86`.
- `app/DataTransferObjects/ImportDocumentData.php` -- ajouter `?string $draftToken = null`, `array $draftAttachments = []` (docblock de forme comme `CreateDocumentData`).
- `app/Http/Requests/ImportDocumentRequest.php` -- ajouter normalisation + règles/messages `draft_token`, `draft_attachments.*` copiés de `CreateDocumentRequest.php:31-41,61-81,98-103`.
- `tests/Feature/ImportDocumentTest.php` -- L.21 et L.42 (redirection vers l'import) à adapter vers la fiche ; L.230 et L.242 (`?redirect=`) à supprimer ; ajouter import avec tags + pièce jointe brouillon et rejet d'un `filename` invalide.
- `resources/js/Pages/Documents/__tests__/Import.spec.js` -- blocs étape 2, garde, erreur `saveError`, `beforeunload` (L.118-350) sans objet → à remplacer par la matrice I/O.

## Tasks & Acceptance

**Execution:**
- [x] `app/Actions/Concerns/RelocatesDraftAttachments.php` + `CreateDocumentAction.php` -- extraire la relocalisation sans changer son comportement -- partage avec l'import.
- [x] `ImportDocumentData.php`, `ImportDocumentRequest.php`, `ImportDocumentAction.php` -- accepter et relocaliser les pièces jointes brouillon.
- [x] `DocumentController.php` -- `store()` + retrait des branches `?redirect=`.
- [x] `resources/js/Pages/Documents/Import.vue` -- formulaire unique.
- [x] `tests/Feature/ImportDocumentTest.php` -- adapter/supprimer/ajouter comme indiqué dans la Code Map.
- [x] `resources/js/Pages/Documents/__tests__/Import.spec.js` -- couvrir la matrice I/O.

**Acceptance Criteria:**
- Given un fichier choisi puis retiré, when on consulte la bibliothèque, then aucun document n'a été créé.
- Given l'éditeur de création, when on enregistre un document avec une pièce jointe, then le comportement est inchangé (tests existants `CreateDocument*`/pièces jointes verts).

## Design Notes

L'envoi du fichier au clic sur « Enregistrer » (et non en tâche de fond au choix) garantit qu'aucun document n'existe avant validation — c'est l'objet même de la demande. Suppression de tests (étape 2, garde, `?redirect=`) approuvée avec cette spec : le comportement testé disparaît.

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/ImportDocumentTest.php` -- expected: vert
- `php artisan test --compact tests/Feature/CreateDocumentTest.php tests/Feature/AttachDocumentFileTest.php` -- expected: vert (non-régression relocalisation)
- `npx vitest run resources/js/Pages/Documents/__tests__/Import.spec.js` -- expected: vert
- `vendor/bin/pint --dirty --format agent` puis `npm run build` -- expected: sans erreur

## Suggested Review Order

**Enregistrement en une seule requête**

- Point d'entrée : création + tags + pièces jointes dans une transaction, puis redirection vers la fiche.
  [`DocumentController.php:205`](../../app/Http/Controllers/DocumentController.php#L205)

- Une extraction par pièce jointe, dispatchée seulement après commit.
  [`DocumentController.php:223`](../../app/Http/Controllers/DocumentController.php#L223)

- Relocalisation dans la transaction de l'import ; en cas d'échec, le fichier d'origine est effacé du disque.
  [`ImportDocumentAction.php:58`](../../app/Actions/ImportDocumentAction.php#L58)

- Relocalisation extraite telle quelle de `CreateDocumentAction`, partagée par les deux flux.
  [`RelocatesDraftAttachments.php:47`](../../app/Actions/Concerns/RelocatesDraftAttachments.php#L47)

- `draft_token` exigé dès que des pièces jointes sont envoyées (sinon ignorées en silence).
  [`ImportDocumentRequest.php:65`](../../app/Http/Requests/ImportDocumentRequest.php#L65)

**Formulaire d'import**

- « Enregistrer » : pièces jointes gardées → payload, un seul POST multipart.
  [`Import.vue:118`](../../resources/js/Pages/Documents/Import.vue#L118)

- Enregistrement possible seulement avec un fichier et hors envoi/upload en cours.
  [`Import.vue:77`](../../resources/js/Pages/Documents/Import.vue#L77)

- « Retirer » : retour à la zone de dépôt sans toucher aux tags ni aux pièces jointes.
  [`Import.vue:108`](../../resources/js/Pages/Documents/Import.vue#L108)

- Erreurs par tag (`tag_ids.N`) affichées, comme celles des pièces jointes.
  [`Import.vue:69`](../../resources/js/Pages/Documents/Import.vue#L69)

- Zone de dépôt tant qu'aucun fichier n'est choisi ; « Annuler » vers la liste.
  [`Import.vue:147`](../../resources/js/Pages/Documents/Import.vue#L147)

**Nettoyage**

- Branches `?redirect=` de l'ancienne étape de revue retirées.
  [`DocumentController.php:446`](../../app/Http/Controllers/DocumentController.php#L446)

**Tests**

- Import complet (tags + pièce jointe, 2 jobs), refus sans token, rollback sur échec de déplacement.
  [`ImportDocumentTest.php:223`](../../tests/Feature/ImportDocumentTest.php#L223)

- Matrice I/O du formulaire côté front.
  [`Import.spec.js:92`](../../resources/js/Pages/Documents/__tests__/Import.spec.js#L92)
