---
title: 'Limiter à 10 le nombre de pièces jointes par document'
type: 'feature'
created: '2026-09-28'
status: 'done'
baseline_commit: '693949dd6b91570020c99c9e42a0eda117ecff6d'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Un document accepte un nombre illimité de pièces jointes, quel que soit le point d'entrée (import, éditeur de brouillon, document existant). Par ailleurs, un fichier trop lourd doit toujours produire une erreur lisible en français, jamais une 500 ni un message anglais.

**Approach:** Plafond de 10 pièces jointes par document. Il est appliqué côté client dans `AttachmentsPanel` (refus avant envoi) et revalidé côté serveur aux trois points d'entrée. La politique de poids reste inchangée (pdf/docx/xlsx ≤ 20 Mo, pas de plafond total). On complète seulement les messages d'échec d'upload côté serveur et on les couvre par des tests.

## Boundaries & Constraints

**Always:**
- Une seule source pour la valeur 10 côté serveur (constante sur `DocumentAttachment`) et une seule côté client (`AttachmentsPanel`).
- Tous les messages sont en français et sur le même ton que les messages existants.
- Le contrôle serveur reste l'autorité : le contrôle client ne fait qu'éviter un aller-retour.

**Ask First:** changer la limite de 20 Mo, les formats acceptés ou la configuration PHP (`upload_max_filesize`/`post_max_size`).

**Never:**
- Pas de plafond de poids total.
- Pas de nettoyage des fichiers orphelins dans `tmp/{token}` (écart déjà accepté).
- Pas de verrou ni de gestion de concurrence : l'application est mono-utilisateur et le panneau refuse déjà un second envoi pendant un envoi en cours.
- Pas de comptage côté serveur des fichiers de `tmp/{token}` : un brouillon retiré n'y est pas supprimé, le compte serait faux.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Ajout sous la limite | 9 pièces jointes, ajout d'une 10ᵉ | Acceptée | N/A |
| Ajout au-delà (client) | 10 pièces jointes listées dans le panneau, 11ᵉ déposée | Aucune requête ; message « 10 pièces jointes maximum par document. » | Affiché dans le panneau |
| Document existant (serveur) | POST `/documents/{id}/attachments` avec 10 déjà rattachées | Aucune ligne ni fichier créé | Erreur de validation `file`, redirection `back()` |
| Brouillon (serveur) | Import ou création avec 11 `draft_attachments` | Aucun document créé | Erreur `draft_attachments`, affichée sous le panneau (import **et** éditeur) |
| Fichier > 20 Mo | Envoi hors du client (contournement) | Aucune création | Message « Fichier trop volumineux (20 Mo maximum)… » (déjà existant) |
| Upload échoué côté PHP | Fichier > `upload_max_filesize` (22 Mo) mais < `post_max_size` | Aucune création | Message français « Fichier trop volumineux… » via la règle `uploaded`, pas de 500 |
| Corps > `post_max_size` | `CONTENT_LENGTH` > 25 Mo | Aucune création | `PostTooLargeException` → `back()->withErrors(file)` (déjà existant), à couvrir par un test |

</frozen-after-approval>

## Code Map

- `app/Models/DocumentAttachment.php` -- y ajouter la constante `MAX_PER_DOCUMENT = 10`.
- `app/Http/Requests/AttachDocumentFileRequest.php` -- règles `file` : ajouter une closure qui compte `$this->route('document')->attachments()->count()` ; messages `file.uploaded`.
- `app/Http/Requests/UploadDraftAttachmentRequest.php` -- message `file.uploaded` uniquement (aucun compte possible, cf. Never).
- `app/Http/Requests/ImportDocumentRequest.php:70` et `CreateDocumentRequest.php:75` -- `draft_attachments` : ajouter `max:10`, message `draft_attachments.max` ; message `file.uploaded` dans `ImportDocumentRequest`.
- `bootstrap/app.php` -- rendu de `PostTooLargeException` déjà en place (lecture seule).
- `resources/js/Components/AttachmentsPanel.vue` -- `handleFile()` : refuser si `attachments.length >= 10` avant `validationError()` ; le pré-contrôle des 20 Mo existe déjà via `useFileDropZone` (lecture seule).
- `resources/js/Pages/Documents/Import.vue:60` -- `attachmentsErrorMessage` : modèle à reproduire.
- `resources/js/Pages/Documents/Editor.vue:546` -- n'affiche aucune erreur `draft_attachments` ; ajouter le même message sous le panneau.
- Tests : `tests/Feature/AttachDocumentFileTest.php`, `ImportDocumentTest.php`, `CreateDocumentTest.php`, `resources/js/Components/__tests__/AttachmentsPanel.spec.js`.

## Tasks & Acceptance

**Execution:**
- [x] `app/Models/DocumentAttachment.php` -- constante `MAX_PER_DOCUMENT` -- source unique côté serveur.
- [x] `app/Http/Requests/AttachDocumentFileRequest.php` -- closure de comptage + `file.uploaded` -- limite du document existant, pas de message anglais.
- [x] `app/Http/Requests/ImportDocumentRequest.php`, `CreateDocumentRequest.php` -- `max:` sur `draft_attachments` + messages -- limite des brouillons.
- [x] `app/Http/Requests/UploadDraftAttachmentRequest.php` -- `file.uploaded` -- message français.
- [x] `resources/js/Components/AttachmentsPanel.vue` -- garde du nombre dans `handleFile()` -- refus sans requête.
- [x] `resources/js/Pages/Documents/Editor.vue` -- afficher l'erreur `draft_attachments` sous le panneau -- l'erreur serveur ne doit pas être silencieuse.
- [x] Tests Pest et Vitest couvrant chaque ligne de la matrice (sauf les lignes déjà couvertes).

**Acceptance Criteria:**
- Given une limite atteinte, when l'utilisateur retire une pièce jointe, then il peut de nouveau en ajouter une.
- Given n'importe quel scénario de la matrice, when la requête échoue, then la réponse n'est jamais une 500 et le message est en français.

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/AttachDocumentFileTest.php tests/Feature/ImportDocumentTest.php tests/Feature/CreateDocumentTest.php` -- expected: tous verts
- `npx vitest run resources/js/Components/__tests__/AttachmentsPanel.spec.js resources/js/Pages/Documents/__tests__` -- expected: tous verts
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart restant

## Suggested Review Order

**Limite de 10 pièces jointes**

- Point d'entrée : source unique de la limite côté serveur.
  [`DocumentAttachment.php:28`](../../app/Models/DocumentAttachment.php#L28)

- Document existant : compte des pièces jointes déjà rattachées avant stockage.
  [`AttachDocumentFileRequest.php:47`](../../app/Http/Requests/AttachDocumentFileRequest.php#L47)

- Brouillons : plafond vérifié à l'enregistrement, pas à l'envoi (fichiers retirés restent en tmp).
  [`ImportDocumentRequest.php:74`](../../app/Http/Requests/ImportDocumentRequest.php#L74)

- Même règle côté éditeur.
  [`CreateDocumentRequest.php:76`](../../app/Http/Requests/CreateDocumentRequest.php#L76)

- Refus client avant envoi, message effacé au retrait.
  [`AttachmentsPanel.vue:228`](../../resources/js/Components/AttachmentsPanel.vue#L228)

- L'éditeur affiche enfin l'erreur de pièces jointes renvoyée à l'enregistrement.
  [`Editor.vue:86`](../../resources/js/Pages/Documents/Editor.vue#L86)

**Échecs d'upload lisibles, jamais de 500**

- Taille refusée par PHP → message 20 Mo ; autre échec → message générique.
  [`DescribesUploadFailure.php:15`](../../app/Http/Requests/Concerns/DescribesUploadFailure.php#L15)

**Tests**

- Helper : la session JSON n'est reconstruite qu'à la requête suivante.
  [`Pest.php:58`](../../tests/Pest.php#L58)

- Limite serveur, upload refusé par PHP, `post_max_size`, message visible sur la page suivante.
  [`AttachDocumentFileTest.php:119`](../../tests/Feature/AttachDocumentFileTest.php#L119)

- Mêmes cas pour l'import et l'envoi de brouillon.
  [`ImportDocumentTest.php:308`](../../tests/Feature/ImportDocumentTest.php#L308)
  [`CreateDocumentTest.php:338`](../../tests/Feature/CreateDocumentTest.php#L338)

- Refus client et retrait puis ajout.
  [`AttachmentsPanel.spec.js:170`](../../resources/js/Components/__tests__/AttachmentsPanel.spec.js#L170)
