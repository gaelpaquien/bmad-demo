---
title: 'Sidebar réductible, titre modifiable à l''import, champs obligatoires et ajustements éditeur / pièces jointes'
type: 'feature'
created: '2026-09-29'
status: 'done'
route: 'one-shot'
baseline_commit: 'f6456b3e3d6e80920cacb46fb0b4f4a1272b1442'
implementation_commits: ['64a2d4e', '6b12460', '63e300f']
---

# Sidebar réductible, titre modifiable à l'import, champs obligatoires et ajustements éditeur / pièces jointes

> Spec rédigée a posteriori (audit du 2026-09-29) : ces changements ont été développés hors `bmad-build`, en trois commits. Elle décrit ce qui a été livré, sans revue adversariale. Les commits de pur style `4dbcec8` (consultation présentée comme un article) et `c2ea7d9` (séparateurs plus épais) n'ont pas de spec, comme les retouches visuelles précédentes. Voir § Écarts relevés à l'audit.

## Intent

**Problem:** La sidebar occupait toujours 220px, sans moyen de la réduire. À l'import, le titre était imposé par le nom du fichier, extension comprise. Les formulaires n'indiquaient pas quels champs étaient obligatoires, et « Enregistrer » restait cliquable avec un formulaire incomplet, l'erreur n'arrivant qu'au retour du serveur. Le panneau de pièces jointes n'acceptait qu'un fichier à la fois. Les tableaux de l'éditeur ne pouvaient pas changer de taille après leur insertion en 3×3. Enfin, le survol des boutons lime était presque invisible en thème clair.

**Approach:**
- **Sidebar (`64a2d4e`)** : un bouton « Réduire le menu » bascule entre icônes seules (64px) et icônes + libellés. Les libellés restent présents en `sr-only`, avec une info-bulle `title` en mode réduit. Le choix est mémorisé dans `localStorage` (`bmad-demo-sidebar-collapsed`), comme le thème. Le titre « BMAD Démo », précédé du logo Laravel en `currentColor`, devient un lien vers l'accueil. Les icônes sont harmonisées (20px, trait 1.5).
- **Titre à l'import (`63e300f`)** : le champ Titre est désactivé tant qu'aucun fichier n'est choisi. Il est ensuite prérempli avec le nom du fichier sans sa dernière extension, reste modifiable et se vide au retrait du fichier. Côté serveur, `title` est optionnel (`nullable|string|max:255`), avec repli sur le nom de fichier d'origine dans `ImportDocumentAction`.
- **Champs obligatoires (`63e300f`)** : un composant `FieldRequirement` affiche « * » (masqué aux lecteurs d'écran, le champ portant `aria-required`) ou « optionnel ». Les champs désactivés sont plus nettement grisés. L'éditeur a une bordure de 2px commune à la barre d'outils et au contenu, mise en évidence au focus comme les autres champs.
- **Éditeur (`6b12460`)** : « Enregistrer » est désactivé tant que le titre ou le contenu est vide (`editor.isEmpty` de TipTap) ou qu'un envoi est en cours. L'état actif de la barre d'outils est plus visible. Des boutons +/− colonne et ligne apparaissent quand le curseur est dans un tableau.
- **Pièces jointes (`6b12460`)** : sélection ou dépôt de plusieurs fichiers, envoyés un par un en file (les fichiers valides sont refusés en bloc s'ils dépassent la limite de 10 ; les fichiers invalides, signalés par leur nom, ne comptent pas dans la limite ; une autre visite qui interrompt l'envoi arrête la file). « Parcourir » est désactivé à la limite. Le message « Aucune pièce jointe » disparaît, et les consignes de format, de nombre et de poids tiennent sur trois lignes.
- **Survol (`6b12460`)** : nouveau token `--color-primary-hover` (lime assombri, plus marqué en sombre), sans effet sur un bouton désactivé.

## Boundaries & Constraints

**Always:**
- Chaque entrée de la sidebar garde son nom accessible, réduite ou non.
- Le serveur reste l'autorité : le blocage de « Enregistrer » côté client ne remplace aucune règle de validation.
- Les envois de pièces jointes restent séquentiels, pour que le watcher `flash.uploadedAttachment` du mode brouillon ne voie jamais deux réponses arriver en même temps.

**Never:**
- La sidebar n'est jamais masquée ; elle ne change jamais de largeur d'elle-même selon la surface.
- Une sélection de pièces jointes qui dépasse la limite n'est jamais tronquée en silence.

## Code Map

- `resources/js/Components/Sidebar.vue` -- mode réduit, titre cliquable, icônes.
- `resources/js/Components/FieldRequirement.vue` -- nouveau composant « * » / « optionnel ».
- `resources/js/Components/TextInput.vue` -- apparence des champs désactivés.
- `resources/js/Components/TagSelector.vue` -- mention « optionnel ».
- `app/Http/Requests/ImportDocumentRequest.php`, `app/DataTransferObjects/ImportDocumentData.php`, `app/Actions/ImportDocumentAction.php`, `app/Http/Controllers/DocumentController.php` -- titre choisi à l'import.
- `resources/js/Pages/Documents/Import.vue` -- champ Titre et `canSave`.
- `resources/js/Pages/Documents/Editor.vue` -- `canSave`, cadre commun, boutons de structure du tableau.
- `resources/js/Components/AttachmentsPanel.vue`, `resources/js/Composables/useFileDropZone.js` -- sélection multiple et file d'envoi.
- `resources/css/app.css` -- token `--color-primary-hover`.
- Planification réalignée le 2026-09-29 : `epics.md` (UX-DR3, UX-DR26, stories 1.1 et 3.2), `EXPERIENCE.md` (Sidebar, Indication obligatoire/optionnel, Aucune pièce jointe, Bannis en v1), `DESIGN.md` (tokens `primary-hover` et `sidebar-width-collapsed`, Sidebar, Do/Don't).

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/ImportDocumentTest.php` -- expected: tous verts
- `npx vitest run resources/js/Components/__tests__ resources/js/Pages/Documents/__tests__` -- expected: tous verts

## Écarts relevés à l'audit

- Sans titre envoyé, le serveur garde le nom de fichier **avec** son extension, alors que le client la retire. Seul un appelant autre que la page d'import est concerné.
- `AttachmentsPanel.vue` émet encore `before-request`/`after-request` sans aucun consommateur (déjà noté dans `deferred-work.md`) ; le fichier a été retouché sans ce nettoyage.
- Revue adversariale menée le 2026-09-29 (`bmad-code-review` sur `f6456b3..9b2497d`). Corrigé :
  - la file d'envoi s'arrête quand une autre visite interrompt l'envoi en cours (elle relançait le fichier suivant et annulait la navigation) et se vide au démontage ;
  - les fichiers invalides ne comptent plus dans la limite de 10, et le message de limite accorde le singulier et le pluriel ;
  - « Retirer » est désactivé pendant un envoi en mode immédiat ;
  - les erreurs identiques ont chacune leur clé ;
  - « optionnel » est séparé du libellé pour les lecteurs d'écran ;
  - le titre à l'import est limité à 255 caractères ;
  - tests ajoutés pour la file (erreur serveur, annulation, démontage, plusieurs flashs en brouillon).

  Différé dans `deferred-work.md` : `isEmpty` et les tableaux vides, raffinements d'accessibilité de l'éditeur et de la sidebar.

## Suggested Review Order

**Sidebar réductible**

- Point d'entrée : état réduit mémorisé, tolérant un `localStorage` indisponible.
  [`Sidebar.vue:26`](../../resources/js/Components/Sidebar.vue#L26)

- Titre cliquable vers l'accueil.
  [`Sidebar.vue:102`](../../resources/js/Components/Sidebar.vue#L102)

- Bouton de bascule et son nom accessible.
  [`Sidebar.vue:233`](../../resources/js/Components/Sidebar.vue#L233)

**Titre à l'import**

- Titre optionnel côté serveur, repli sur le nom de fichier.
  [`ImportDocumentRequest.php:62`](../../app/Http/Requests/ImportDocumentRequest.php#L62)
  [`ImportDocumentAction.php:47`](../../app/Actions/ImportDocumentAction.php#L47)

- Préremplissage sans extension et blocage d'« Enregistrer ».
  [`Import.vue:84`](../../resources/js/Pages/Documents/Import.vue#L84)

**Champs obligatoires et éditeur**

- Composant « * » / « optionnel ».
  [`FieldRequirement.vue:1`](../../resources/js/Components/FieldRequirement.vue#L1)

- Contenu vide détecté par TipTap, `canSave` de l'éditeur.
  [`Editor.vue:102`](../../resources/js/Pages/Documents/Editor.vue#L102)
  [`Editor.vue:404`](../../resources/js/Pages/Documents/Editor.vue#L404)

- Boutons de structure du tableau.
  [`Editor.vue:183`](../../resources/js/Pages/Documents/Editor.vue#L183)

**Pièces jointes**

- Fichiers invalides signalés par leur nom et écartés, puis fichiers valides refusés en bloc au-delà de la limite.
  [`AttachmentsPanel.vue:258`](../../resources/js/Components/AttachmentsPanel.vue#L258)

- File d'envoi séquentielle, arrêtée quand une autre visite interrompt l'envoi.
  [`AttachmentsPanel.vue:243`](../../resources/js/Components/AttachmentsPanel.vue#L243)

**Survol des boutons lime**

- Token clair et sombre.
  [`app.css:24`](../../resources/css/app.css#L24)

**Tests**

- Titre à l'import, côté serveur et côté page.
  [`ImportDocumentTest.php:39`](../../tests/Feature/ImportDocumentTest.php#L39)
  [`Import.spec.js:104`](../../resources/js/Pages/Documents/__tests__/Import.spec.js#L104)

- Blocage d'« Enregistrer » dans l'éditeur.
  [`Editor.spec.js:410`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L410)

- Limite et sélection multiple des pièces jointes.
  [`AttachmentsPanel.spec.js:185`](../../resources/js/Components/__tests__/AttachmentsPanel.spec.js#L185)

- Sidebar réduite, mémorisation et titre cliquable.
  [`Sidebar.spec.js:254`](../../resources/js/Components/__tests__/Sidebar.spec.js#L254)
