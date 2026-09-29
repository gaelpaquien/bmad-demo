---
title: 'Nettoyage : Pint, code mort AttachmentsPanel, titre de secours sans extension, doc UX'
type: 'chore'
created: '2026-09-29'
status: 'done'
route: 'one-shot'
---

# Nettoyage : Pint, code mort AttachmentsPanel, titre de secours sans extension, doc UX

## Intent

**Problem:** Quatre restes après la revue globale : 16 fichiers PHP anciens hors mise en forme Pint ; `AttachmentsPanel.vue` émet encore `before-request`/`after-request` alors que plus personne ne les écoute ; le titre de secours d'un import sans titre garde l'extension côté serveur, contrairement au client ; `EXPERIENCE.md` décrit encore l'import comme une modale et une confirmation avant de quitter l'éditeur qui n'existe plus.

**Approach:** Commit `style: pint` séparé ; suppression des émissions, de leur commentaire et des 3 tests qui les vérifiaient ; `ImportDocumentAction::titleFromFilename()` reproduit la règle d'`Import.vue` (dernière extension retirée, nom seul d'extension conservé, coupe à 255 caractères) ; réalignement d'`EXPERIENCE.md` (page Import, parcours 1, sidebar, éditeur sans confirmation) et de `DESIGN.md` (plus de modale d'import).

## Suggested Review Order

**Titre de secours côté serveur**

- Point d'entrée : le repli n'utilise plus le nom brut du fichier.
  [`ImportDocumentAction.php:47`](../../app/Actions/ImportDocumentAction.php#L47)

- Même règle que le client : dernière extension, nom seul d'extension conservé, 255 caractères.
  [`ImportDocumentAction.php:83`](../../app/Actions/ImportDocumentAction.php#L83)

**Code mort AttachmentsPanel**

- Les émissions `before-request`/`after-request` et leur commentaire disparaissent.
  [`AttachmentsPanel.vue:58`](../../resources/js/Components/AttachmentsPanel.vue#L58)

**Documentation UX**

- L'import est une page atteinte depuis la sidebar, plus une modale.
  [`EXPERIENCE.md:35`](../planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md#L35)

- L'éditeur quitte sans confirmation, avec renvoi à la spec qui l'a décidé.
  [`EXPERIENCE.md:91`](../planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md#L91)

- Parcours 1 réécrit pour le formulaire unique.
  [`EXPERIENCE.md:117`](../planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md#L117)

**Tests**

- Assertions de titre passées à `contract` ; cas `Rapport.v2.pdf` ajouté.
  [`ImportDocumentTest.php:50`](../../tests/Feature/ImportDocumentTest.php#L50)
