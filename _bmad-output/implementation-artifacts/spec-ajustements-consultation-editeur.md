---
title: 'Ajustements consultation et éditeur de document'
type: 'feature'
created: '2026-09-28'
status: 'done'
review_loop_iteration: 0
baseline_commit: '5c65b83853768a59a9cfb1f3dc021574b4e205ee'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** L'éditeur n'offre aucun moyen d'abandonner une création/modification et affiche une pastille orange « Modifications non enregistrées » jugée inutile ; la page de consultation présente des actions différentes et dans un ordre différent selon qu'un document est créé (« Exporter ») ou importé (« Télécharger »), et affiche « Aucun tag. » / « Aucune pièce jointe. » pour des champs vides.

**Approach:** Ajouter un bouton « Annuler » à l'éditeur et supprimer entièrement la notion de modifications non enregistrées (pastille, libellé et confirmations de sortie) ; unifier l'en-tête de consultation en « Télécharger · Modifier · Supprimer » pour les deux types de document ; masquer totalement les lignes Tags / Pièces jointes vides.

## Boundaries & Constraints

**Always:**
- Éditeur, « Annuler » (secondaire, à côté d'« Enregistrer ») : modification → `/documents/{id}` ; création → `/documents`. Aucune requête, aucune confirmation. Inopérant pendant `form.processing`.
- Éditeur : plus de pastille orange, plus de libellé « Modifications non enregistrées », plus de `window.confirm` ni de `beforeunload` liés à des modifications non enregistrées — en création comme en modification.
- Consultation hors mode modification, même ordre pour les deux types : **Télécharger** (primaire, icône téléchargement) · **Modifier** (secondaire, contour) · **Supprimer**. Document importé : lien `downloadUrl` (bouton désactivé si `sourceMissing`, comme aujourd'hui). Document créé : déclenche `exportToPdf()` (même garde, même message d'erreur, même toast « Export PDF généré. »), libellé « Téléchargement… » pendant l'export.
- Ligne « Tags : » absente si le document n'a aucun tag, sauf en mode modification des tags (document importé), où elle reste affichée avec `TagSelector`. Ligne « Pièces jointes : » absente si aucune pièce jointe. « Ajouté le » inchangé.

**Ask First:** Toute modification backend ; toute modification de `AttachmentsPanel.vue`, `TagSelector.vue` ou de la page d'import.

**Never:** Toucher au flux d'import (reporté dans `deferred-work.md`) ; changer la route/le contrôleur d'export PDF ; modifier le mode modification des tags de `Show.vue` (Enregistrer/Annuler) ; menu « ⋯ ».

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Annuler en modification | Éditeur d'un document existant, contenu modifié, clic « Annuler » | Navigation vers `/documents/{id}`, aucune confirmation, rien d'enregistré | N/A |
| Annuler en création | Nouveau document, titre saisi, clic « Annuler » | Navigation vers `/documents`, aucune confirmation | N/A |
| Éditeur modifié | Titre/contenu/tags changés | Aucune pastille ni libellé « Modifications non enregistrées » | N/A |
| Télécharger (créé) | Clic « Télécharger » sur un document créé | Export PDF téléchargé + toast | Échec → message `role="alert"` existant, bouton réutilisable |
| Champs vides | Document sans tag et sans pièce jointe | Ni « Tags : » ni « Pièces jointes : » affichés | N/A |
| Modif. tags sans tag | Importé sans tag, clic « Modifier » | Ligne « Tags : » + `TagSelector` visibles | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Pages/Documents/Editor.vue` -- L.84-135 `initialSnapshot`/`snapshotCurrentState`/`sameTagIds`/`isDirty` et L.196-238 garde (`programmaticNavigation`, `router.on('before')`, `onBeforeUnload`, écouteur `beforeunload`) à supprimer ; les affectations `programmaticNavigation` dans `uploadPendingImage()` (L.367/393) et `submit()` (L.517/525) et `@before-request`/`@after-request` sur `AttachmentsPanel` (L.668-669) disparaissent avec ; `onSuccess` de `submit()` ne re-baseline plus rien. `onCreate` (L.173) garde uniquement `currentContentHtml` + `setEditable` + `isLoadingContent`. Barre d'actions L.673-695 : retirer pastille/libellé, ajouter `<Link>` « Annuler » (importer `Link`). Style secondaire : cf. `Show.vue:426`.
- `resources/js/Components/AttachmentsPanel.vue` -- lecture seule : continue d'émettre `before-request`/`after-request`, simplement plus écoutés par l'éditeur (émissions inoffensives, composant non modifié).
- `resources/js/Pages/Documents/Show.vue` -- L.438-527 actions de l'en-tête à réordonner ; L.465-513 « Modifier » créé (primaire → secondaire) et bouton « Exporter » à fusionner dans « Télécharger » ; L.534-593 `dl` : `v-if` sur les lignes Tags (`documentTags.length > 0 || isEditing`) et Pièces jointes (`attachments.length > 0`), retirer « Aucun tag. » / « Aucune pièce jointe. ».
- `resources/js/Pages/Documents/__tests__/Editor.spec.js` -- L.210-262 bloc « garde de navigation vs AttachmentsPanel » : devient sans objet → à remplacer par des tests de l'absence de confirmation et du bouton Annuler.
- `resources/js/Pages/Documents/__tests__/Show.spec.js` -- L.118 (ordre des actions d'un créé) et L.133 (« Aucun tag. ») à réécrire.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Pages/Documents/Editor.vue` -- supprimer état dirty + gardes de sortie ; ajouter « Annuler » (`Link`) avec destination selon `props.document` -- intention ci-dessus.
- [x] `resources/js/Pages/Documents/Show.vue` -- en-tête Télécharger · Modifier · Supprimer unifié ; lignes Tags/Pièces jointes conditionnelles -- intention ci-dessus.
- [x] `resources/js/Pages/Documents/__tests__/Editor.spec.js` -- remplacer le bloc de garde par : aucun `window.confirm` à la navigation après modification, aucune pastille/libellé, href d'« Annuler » en création (`/documents`) et en modification (`/documents/{id}`) -- matrice I/O.
- [x] `resources/js/Pages/Documents/__tests__/Show.spec.js` -- ordre des actions identique importé/créé, « Télécharger » d'un créé appelle l'export PDF, lignes vides absentes, ligne Tags présente en mode modification sans tag -- matrice I/O.

**Acceptance Criteria:**
- Given un document importé et un document créé, when on ouvre leur page, then les libellés des actions de l'en-tête sont identiques et dans le même ordre : Télécharger, Modifier, Supprimer.
- Given l'éditeur ouvert, when on ferme l'onglet après modification, then le navigateur ne demande aucune confirmation.

## Design Notes

La confirmation de sortie est retirée à la demande explicite de l'utilisateur (« ça dégage ») : sans pastille, `isDirty` n'a plus aucun consommateur, donc tout l'appareillage (snapshot, garde Inertia, `beforeunload`, `programmaticNavigation`) part avec — le garder sans usage serait du code mort. Le bloc de tests associé est supprimé en conséquence (approuvé avec cette spec).

## Verification

**Commands:**
- `npx vitest run resources/js/Pages/Documents/__tests__/Editor.spec.js resources/js/Pages/Documents/__tests__/Show.spec.js` -- expected: tous verts
- `npm run build` -- expected: build sans erreur

## Suggested Review Order

**En-tête de consultation unifié**

- Point d'entrée : « Télécharger » d'un créé placé avant le test `sourceMissing` (toujours vrai en prod pour un créé).
  [`Show.vue:447`](../../resources/js/Pages/Documents/Show.vue#L447)

- Importé : lien de téléchargement, ou bouton désactivé si le fichier source manque.
  [`Show.vue:461`](../../resources/js/Pages/Documents/Show.vue#L461)

- « Modifier » d'un créé passé en style secondaire, même rang que pour un importé.
  [`Show.vue:491`](../../resources/js/Pages/Documents/Show.vue#L491)

**Champs vides masqués**

- Ligne Tags masquée si vide, conservée en mode modification des tags.
  [`Show.vue:541`](../../resources/js/Pages/Documents/Show.vue#L541)

- Ligne Pièces jointes masquée si vide.
  [`Show.vue:558`](../../resources/js/Pages/Documents/Show.vue#L558)

**Éditeur : Annuler et fin de la garde de sortie**

- Destination d'« Annuler » : fiche du document en modification, liste en création.
  [`Editor.vue:410`](../../resources/js/Pages/Documents/Editor.vue#L410)

- « Annuler » inerte pendant un enregistrement ou un envoi (un `<Link>` Inertia ne se désactive pas).
  [`Editor.vue:569`](../../resources/js/Pages/Documents/Editor.vue#L569)

- `submit()` sans re-baseline ni `programmaticNavigation` : plus d'état « dirty » du tout.
  [`Editor.vue:386`](../../resources/js/Pages/Documents/Editor.vue#L386)

**Tests**

- Annuler (destinations, aucune requête, inerte pendant save/upload) et absence de garde.
  [`Editor.spec.js:204`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L204)

- Montage d'un créé tel qu'en production (`sourceMissing: true`).
  [`Show.spec.js:74`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L74)

- En-tête identique importé/créé et lignes vides masquées.
  [`Show.spec.js:128`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L128)

- Télécharger d'un créé : export, toast, échec, état en cours.
  [`Show.spec.js:168`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L168)
