---
title: 'Ajustements de la recherche : spinner, compteur, type, filtre source'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** Avec un volume réaliste, la page Recherche manque de retours visuels (le spinner n'apparaît qu'après 300 ms, donc jamais sur une recherche rapide), n'affiche pas le compteur sous la pagination, montre un badge de type inutile dans chaque résultat, et ne permet pas de filtrer par origine du document.

**Approche :**
1. Le spinner « Recherche en cours… » s'affiche dès le début de chaque requête de recherche (plus de délai de 300 ms).
2. Sous les boutons de pagination des résultats, afficher le compteur total comme sur le listing des documents (composant `Pagination` avec `total`/`from`/`to`).
3. Retirer le badge de type (PDF, Word, Créé…) des lignes de résultat.
4. Ajouter un filtre « type de document » à choix unique : Tous / Importé / Créé (paramètre d'URL `source`). Décision utilisateur : ce filtre seul, sans mot-clé ni tag, lance une recherche (liste les documents de cette source, du plus récent au plus ancien, paginés), comme le fait un tag seul.

</frozen-after-approval>

## Implementation Notes

- Spinner : affiché à `onStart`, maintenu au moins 400 ms pour rester visible sur une réponse quasi instantanée (choix de l'agent, sans quoi « voir que ça cherche » serait inopérant en local).
- `SearchDocumentsData` reçoit `?DocumentSource $source = null` ; le MCP (`SearchDocumentsTool`) n'est pas modifié. `SearchDocumentsAction` : critère `source` ajouté au court-circuit « rien à chercher » et filtre `where('source', …)`.
- `DocumentController::search()` lit `source` via `DocumentSource::tryFrom` (valeur invalide ignorée) et renvoie la prop `sourceFilter`.
- Aide de la page : ajout d'une puce sur le filtre par type ; le texte « Filtrer par tag » reste inchangé.
- Le compteur du haut (« N documents trouvés ») est conservé.

## Ajustement 2026-09-30

- Filtre par type : remplacé le groupe de boutons radio par un `<select>` (« Tous les types », Importé, Créé) qui affiche un badge « Filtre par type actif : [Importé ×] » sous les champs, comme le filtre par tag ; choix unique, retirer le badge ou choisir « Tous les types » retire le filtre.
- Liste des documents (menu `DocumentsLayout`) : 2 tags maximum par ligne, puis un badge « +X » (X = tags masqués, infobulle avec leurs noms). Les lignes de résultat de la recherche gardent tous leurs tags.

## Ajustement 3 (2026-09-30)

- Compteur « N documents trouvés » du haut retiré (doublon) : le total reste sous la pagination.
- `Pagination` : boutons précédent/suivant en icônes (comme le listing) dans tous les modes ; en mode non compact, les pages numérotées sont fenêtrées côté client (1 à 5, « … », deux dernières ; page courante et voisines quand on est plus loin). Décision utilisateur : barre du listing + pages numérotées limitées.
- Filtre par type : nouveau `SourceSelector` (même champ et même liste de suggestions que `TagSelector`) à la place du `<select>` natif.
