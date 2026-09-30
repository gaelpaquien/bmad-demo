---
title: 'Refonte layout Documents : liste paginée en menu secondaire, consultation dans le 3e volet'
type: 'refactor'
created: '2026-09-30'
status: 'done'
baseline_commit: '864a7cfd1bb194190e103e13179b910ded8fe467'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La page Documents affiche la liste en pleine largeur et chaque document s'ouvre sur une page de consultation distincte : on perd la liste à chaque ouverture.

**Approach:** Triple layout pour Documents, comme Configuration : menu principal (existant) | menu secondaire (liste paginée des documents) | contenu du document actif. La consultation se fait entièrement dans ce layout ; il n'y a plus de page de consultation « à part » (le contenu de `Show` devient le 3e volet).

## Boundaries & Constraints

**Always:** Menu principal, mode réduit et état persistant inchangés. Contenu de consultation (aperçu, pièces jointes, tags, actions Modifier / Exporter / Supprimer) repris tel quel, seul le conteneur change. Liste triée du plus récent au plus ancien, 20 par page, lien actif marqué `aria-current="page"`. Tokens Tailwind du thème respectés. URLs et noms de routes conservés : `/` (liste, aucun document actif) et `/documents/{document}` (document actif) ; les liens venant de Recherche, des redirections après création/import/modification restent valides.

**Décisions (prises à la planification, modifiables au checkpoint) :**
- Sans document actif (`/`), le 3e volet affiche un message d'invitation (« Sélectionnez un document… »), pas d'ouverture automatique du plus récent.
- Chaque ligne du menu secondaire : titre (tronqué), date courte et tags (chips) ; largeur fixe (`w-80`), qui ne suit pas le mode réduit du menu principal.
- Changer de page de la liste conserve le document actif (`/documents/{id}?page=N`). Ouvrir `/documents/{id}` sans `page` affiche la page de la liste qui contient ce document.
- Après suppression du document actif, retour à `/` (comportement actuel).

**Never:** Ne pas toucher à Recherche, Import, Éditeur, Configuration. Pas de nouvelle dépendance, pas de migration. Pas de changement de logique métier (tags, pièces jointes, export, suppression).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Entrée Documents | GET `/` | Liste paginée + invitation à choisir | Aucun document : « Aucun document pour l'instant. » dans la liste |
| Ouvrir un document | Clic sur une ligne | Contenu à droite, ligne active, liste inchangée | N/A |
| Accès direct | GET `/documents/{id}` d'un document en page 3 | Liste en page 3, ligne active | Document inconnu : 404 |
| Pagination | Clic « page 2 » avec document actif | Liste en page 2, même document actif | `page` hors bornes : comportement Laravel actuel |

</frozen-after-approval>

## Code Map

- `resources/js/Layouts/ConfigurationLayout.vue` -- modèle du triple layout (AppLayout + `nav` sticky h-screen + zone de contenu `min-w-0 flex-1`) à reprendre.
- `resources/js/Layouts/DocumentsLayout.vue` -- à créer : lit `usePage().props.documents` (paginateur) et `props.document?.id`, rend la liste, `Pagination`, `TagChip`.
- `resources/js/Pages/Documents/Index.vue` -- devient : `DocumentsLayout` + message d'invitation ; la liste pleine largeur disparaît (`formatDate` déplacé dans le layout).
- `resources/js/Pages/Documents/Show.vue` -- `<AppLayout>` → `<DocumentsLayout>` (racine, l. 415/737) ; contenu inchangé.
- `app/Http/Controllers/DocumentController.php` -- `index()` (l. 75) et `show()` (l. 374) : méthode privée commune pour la liste (`with('tags:id,name')`, `latest()` + `latest('id')`, 20/page, colonnes `id,title,source,mime_type,created_at`) ; `show()` ajoute la prop `documents` et calcule la page du document si `page` absent (position = documents plus récents / 20).
- `resources/js/Components/Pagination.vue` -- réutilisé tel quel (flex-wrap suffit en 20rem).
- `resources/js/Components/Sidebar.vue` -- inchangé : `Documents/Index` et `Documents/Show` déjà dans `LIBRARY_SURFACES`.
- Tests à ajuster : `tests/Feature/BrowseLibraryTest.php`, `PreviewDocumentTest.php`, `SyncDocumentTagsTest.php` (prop `documents` sur show) ; `Index.spec.js`, `Show.spec.js` (stub `DocumentsLayout`), nouveau `DocumentsLayout.spec.js`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Layouts/DocumentsLayout.vue` -- créer : menu secondaire (titre « Documents » + icône, liste, pagination, état vide, ligne active) + zone de contenu -- triple layout
- [x] `resources/js/Pages/Documents/Index.vue`, `Show.vue` -- utiliser le nouveau layout ; Index = invitation -- fin de la liste pleine largeur
- [x] `app/Http/Controllers/DocumentController.php` -- liste partagée par `index()`/`show()`, page du document actif -- accès direct cohérent
- [ ] Tests Pest et Vitest -- prop `documents` sur show, page calculée, `page` explicite prioritaire, ligne active, état vide, invitation

**Acceptance Criteria:**
- Given une page quelconque, when je clique sur « Documents », then j'arrive sur `/` avec la liste à droite du menu principal et l'invitation à droite de la liste.
- Given la liste, when je clique sur un document, then son contenu s'affiche dans le 3e volet, la liste reste visible et la ligne est active.
- Given plus de 20 documents, when je change de page de la liste, then le document actif reste affiché.
- Given une page hors Documents (Recherche, Configuration, Éditeur, Import), then aucune liste n'apparaît.

## Implementation Notes

- Implémenté directement, sans sous-agent ; revue faite en ligne (aucun sous-agent lancé). Fichiers : `DocumentsLayout.vue` (nouveau), `Index.vue` (invitation), `Show.vue` (layout + remise à zéro des messages d'erreur au changement de document), `DocumentController.php` (`libraryPage()` partagée, prop `documents` sur `show()`), tests Pest (`BrowseLibraryTest`) et Vitest (`DocumentsLayout.spec.js`, `Index.spec.js`, `Show.spec.js`).
- Le tri de la liste ajoute `id` décroissant comme départage (ordre déterministe pour les documents créés à la même seconde).

## Spec Change Log

## Review Triage Log

| Constat | Verdict | Route | Preuve / suite |
|---------|---------|-------|----------------|
| Message d'échec d'export/suppression d'un document affiché sur le document suivant (composant `Show` réutilisé par Inertia) | medium | patch | Remise à zéro dans le watcher existant + test |
| `?page=99` hors bornes : liste vide avec « Aucun document pour l'instant. » | low | rejeté | Comportement Laravel déjà présent sur `/`, prévu par la matrice ; correctif = garde supplémentaire |
| Payload complet de `show()` renvoyé à chaque changement de page de la liste | low | rejeté | Coût négligeable en mono-utilisateur ; l'optimiser ajouterait des rechargements partiels |
| Défilement de la liste réinitialisé au passage `/` → document (le layout est remonté) | low | rejeté | Une seule fois à l'ouverture ; layout persistant hors intention (convention existante) |

## Design Notes

`Documents/Index` et `Documents/Show` restent deux composants Inertia (les tests Pest et le composant actif de la Sidebar restent valides) ; l'utilisateur, lui, ne voit qu'un seul écran. Le calcul de page : `Document::where('created_at', '>', $d->created_at)->orWhere(fn => created_at = et id > $d->id)->count()` puis `intdiv(n, 20) + 1`, le tri secondaire par `id` rendant l'ordre déterministe.

## Verification

**Commands:**
- `php artisan test --compact --filter="BrowseLibrary|PreviewDocument|SyncDocumentTags|DeleteDocument"` -- expected: succès
- `npx vitest run` -- expected: succès
- `vendor/bin/pint --dirty --format agent` -- expected: aucun problème
