---
title: 'Notifications de succès sur les actions importantes'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'dispatch'
review_loop_iteration: 0
context: []
baseline_commit: 'd930a477d1dffab633da4af4894a68f2712ca5ce'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Après une création, une modification ou une suppression (documents, tags, pièces jointes), l'application ne confirme rien : l'utilisateur constate seulement le changement de page. Seuls l'export PDF (toast local à `Show.vue`) et la suppression de tag (message en ligne dans `Configuration.vue`) confirment aujourd'hui.

**Approach:** Un mécanisme unique de toast de succès : le serveur pose un flash Inertia natif (`Inertia::flash('toast', ['type' => 'success', 'message' => …])`) sur chaque action listée ci-dessous ; un store de toasts au niveau module, alimenté par `router.on('flash')` (enregistré une fois dans `app.js`), est affiché par un composant monté dans `AppLayout`. Le toast survit ainsi à la navigation qui suit la redirection.

## Boundaries & Constraints

**Always:**
- Messages en français, factuels : « Document importé. », « Document créé. », « Document modifié. », « Document supprimé. », « Tags du document mis à jour. » (`updateTags`), « Tag créé. », « Tag renommé. », « Tag supprimé — détaché de N documents. » (libellé existant), « Pièce jointe ajoutée. », « Pièce jointe supprimée. » (documents déjà enregistrés uniquement), « Export PDF généré. » (existant, migré).
- Le flash n'est posé qu'après succès de l'action (les échecs de validation gardent leurs erreurs de champ, sans toast).
- Style repris du toast actuel de `Show.vue` (`bg-foreground text-background`, jetons du thème, aucune couleur en dur), avec une icône de coche ; `role="status"` + `aria-live="polite"` ; disparition automatique après ~4 s, fermeture manuelle possible ; empilement si plusieurs toasts. Positionné en haut à droite pour ne pas recouvrir `ExtractionTasksPanel` (bas centré).
- Les contrôleurs restent minces ; les Actions/DTO ne changent pas.

**Never:**
- Pas de toast pour les uploads de brouillon (image d'éditeur, pièce jointe de brouillon), l'annulation de brouillon (`destroyDraft`), ni les téléchargements/aperçus.
- Pas de nouvelle dépendance ; pas de toast d'erreur (hors périmètre — les erreurs gardent leur affichage actuel).
- Ne pas conserver le message en ligne `tagDeleted` ni le flash de session `tagDeleted` : remplacés par le toast.

## I/O & Edge-Case Matrix

| Scénario | Entrée / État | Comportement attendu | Gestion d'erreur |
|----------|--------------|---------------------|-----------------|
| Création/import | `POST /documents` ou `/documents/create` valide | Redirection vers la fiche + toast « Document importé/créé. » | Validation KO : pas de toast |
| Suppression document | `DELETE /documents/{id}` | Redirection Bibliothèque + toast « Document supprimé. » | N/A |
| Deux actions consécutives identiques | Deux tags créés à la suite | Deux toasts distincts (pas dédupliqués) | N/A |
| Navigation après toast | Toast affiché puis changement de page | Le toast reste jusqu'à son échéance | N/A |
| Rechargement partiel (poll extraction) | `router.reload({only})` | Aucun toast rejoué | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php` -- `store` (l.217), `storeCreated` (l.293), `update` (l.469), `updateTags` (l.488), `destroy` (l.504) : ajouter le flash toast avant la redirection existante. Ne pas toucher aux autres méthodes (uploads de brouillon, `destroyDraft`).
- `app/Http/Controllers/TagController.php` -- `store`, `update`, `destroy` ; `destroy` remplace `with('tagDeleted', …)` par le toast (« détaché de N documents »).
- `app/Http/Controllers/DocumentAttachmentController.php` -- `store`, `destroy` : flash toast.
- `app/Http/Middleware/HandleInertiaRequests.php:80-98` -- retirer `tagDeleted` du prop partagé `flash` (commentaire inclus).
- `resources/js/Composables/useToasts.js` (nouveau) -- store module réactif : `toasts`, `notifySuccess(message)`, `dismissToast(id)`, timers ; modèle : `useSidebarCollapsed.js`.
- `resources/js/Components/ToastContainer.vue` (nouveau) -- rendu des toasts ; classes reprises de `Show.vue:743-750`.
- `resources/js/Layouts/AppLayout.vue` -- monter `<ToastContainer />` à côté de `ExtractionTasksPanel`.
- `resources/js/app.js` -- `router.on('flash', …)` : pousse `flash.toast` dans le store.
- `resources/js/Pages/Documents/Show.vue:158-232,739-751` -- remplacer `showExportPdfToast`/timer/markup par `notifySuccess('Export PDF généré.')`.
- `resources/js/Pages/Documents/Configuration.vue:16-24,252-254` -- retirer `tagDeletedMessage` et son `<p>`.
- Tests à adapter : `tests/Feature/ManageTagsTest.php:169-196`, `resources/js/Pages/Documents/__tests__/Show.spec.js:201`, `Configuration.spec.js:378-395`, `Layouts/__tests__/AppLayout.spec.js` (monte le vrai `ToastContainer`, `usePage` inchangé).

## Tasks & Acceptance

**Execution:**
- [x] `DocumentController.php`, `TagController.php`, `DocumentAttachmentController.php` -- `Inertia::flash('toast', ['type' => 'success', 'message' => …])` sur les actions listées -- confirmation serveur unique
- [x] `HandleInertiaRequests.php`, `Configuration.vue` -- retirer `tagDeleted` -- remplacé par le toast
- [x] `useToasts.js`, `ToastContainer.vue`, `AppLayout.vue`, `app.js` -- store + composant + écoute du flash -- affichage global qui survit à la navigation
- [x] `Show.vue` -- migrer le toast d'export PDF vers `notifySuccess` -- un seul mécanisme
- [x] `tests/Feature/*` (ManageTagsTest, CreateDocumentTest, ImportDocumentTest, UpdateDocumentTest, DeleteDocumentTest, SyncDocumentTagsTest, AttachDocumentFileTest, DetachDocumentFileTest) -- `assertInertiaFlash('toast.message', …)` par action, absence sur échec de validation -- non-régression
- [x] `useToasts.spec.js`, `ToastContainer.spec.js` + specs existantes adaptées -- empilement, auto-dismiss (timers factices), fermeture manuelle, pas de déduplication

**Acceptance Criteria:**
- Given une action listée réussie, when la réponse arrive, then un toast au message ci-dessus s'affiche puis disparaît seul.
- Given une redirection vers une autre page, when elle se charge, then le toast est visible sur la page d'arrivée.
- Given un échec de validation, when la réponse revient, then aucun toast n'apparaît.

## Implementation Notes

- Implémenté directement (sans sous-agent d'implémentation). Backend : `App\Support\Toast::success()` (flash Inertia natif `toast`), appelé dans `DocumentController` (`store`, `storeCreated`, `update`, `updateTags`, `destroy`), `TagController` (`store`, `update`, `destroy`) et `DocumentAttachmentController` (`store`, `destroy`). `tagDeleted` retiré du prop partagé `flash`.
- Front : `Composables/useToasts.js` (store module + `handleFlash`), `Components/ToastContainer.vue` (zone live persistante `role="status"`, montée dans `AppLayout`), `app.js` (`router.on('flash', handleFlash)`). Toast d'export PDF de `Show.vue` migré ; message en ligne de `Configuration.vue` supprimé.
- Écart de libellé : « détaché de N document(s) » s'accorde au singulier pour 0 et 1 (correction issue de la revue).

## Review Triage Log

| Constat | Verdict | Route / preuve |
|---|---|---|
| Pluriel « 1/0 documents » (3 relecteurs) | low | patch — corrigé (singulier pour 0 et 1) + test 1 document |
| Listener `flash` d'`app.js` sans test (2 relecteurs) | medium | patch — extrait en `handleFlash`, testé (message, absence, vide, non-chaîne) |
| Absence de toast sur échec de validation non couverte (create/update/rename/attach) | medium | patch — assertions ajoutées |
| Ligne vide retirée dans `ManageTagsTest` | low | patch — rétablie |
| Zone `aria-live` recréée avec chaque toast | low | patch — conteneur persistant `role="status"` |
| Code Map obsolète (stub `ToastContainer`), Implementation Notes vides | low | patch — spec mise à jour |
| Pas de pause au survol/focus, perte du focus à la fermeture, plafond de toasts | low | rejeté — mono-utilisateur, pas de cumul réaliste ; le correctif ajoute de la complexité |
| Toast perdu si un poll d'extraction consomme le flash | maybe-false | rejeté (low) — fenêtre de quelques ms ; correctif complexe |
| `type` du toast ignoré côté client / futur toast d'erreur | false | rejeté — les toasts d'erreur sont hors périmètre |
| Fuite d'état du store entre tests ; pas de test d'auto-dismiss dans `ToastContainer.spec` | low | rejeté — nettoyage déjà fait là où le store est utilisé ; auto-dismiss couvert par `useToasts.spec` |
| Messages en dur / fichiers de langue | false | rejeté — convention du projet : chaînes françaises en dur |
| Flash au chargement initial non-Inertia | false | rejeté — les actions passent toujours par une visite Inertia |
| `computed` mort dans `Configuration.vue`, scaffolding `flash` du spec | false | `computed` encore utilisé ; scaffolding inoffensif |

## Verification

**Commands:**
- `php artisan test --compact` (fichiers concernés) -- expected: pass
- `npm test` -- expected: pass
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart

**Manual checks (if no CLI):**
- `npm run build`, puis créer/modifier/supprimer un document et un tag : toast en haut à droite, thèmes clair et sombre.
