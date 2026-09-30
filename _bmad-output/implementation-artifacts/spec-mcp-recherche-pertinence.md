---
title: 'Pertinence du tool MCP search_documents'
type: 'feature'
created: '2026-09-30'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problème :** Le tool MCP `search_documents` guide peu le modèle (requêtes en phrases, OU par défaut), ne dit pas pourquoi un résultat est pertinent, coupe l'extrait à la première occurrence, ne départage pas par fréquence des mots et n'a pas de filtre par type.

**Approche :**
1. Description du tool avec consignes d'usage (2 à 4 mots distinctifs, `+mot`, pas de phrases, affiner si `total` est grand plutôt que paginer).
2. Chaque résultat indique le nombre de mots-clés trouvés sur le total, et où (titre, contenu, pièce jointe).
3. Extrait d'environ 250 caractères, centré sur la zone contenant le plus de mots-clés distincts.
4. Classement : après le nombre de mots distincts (titre, puis tous champs), départager par le nombre total d'occurrences, avant la date. Le classement est partagé avec la page Recherche : son aide est mise à jour.
5. Filtre optionnel `source` (`imported` / `created`). Il ne remplace pas `query`/`tag_ids` : le MCP exige toujours au moins un mot-clé ou un tag.

La taille de page du MCP reste à 10. Le comportement de l'interface web ne change que par ce départage à égalité.

</frozen-after-approval>

## Implementation Notes

- `KeywordDatabaseEngine` : nouvel ordre `occurrences` (somme sur mots-clés × colonnes de `length(lower(col)) - length(replace(lower(col), mot, ''))` / `length(mot)`), insensible à la casse mais pas aux accents (départage seulement).
- `TextExcerpt::aroundBestMatch()` : fenêtre de ±125 caractères ayant le plus de mots distincts.
- `KeywordMatchReport` (Support) : mots trouvés et emplacements, comparaison insensible à la casse et aux accents comme le moteur.
- `SearchDocumentsTool` : schéma `source`, champs `keywords_matched`, `keywords_total`, `matched_in`.

- Aide de la page Recherche et texte de la page MCP mis à jour (départage par occurrences, filtre par type).
- Mesure sur la base de dev (MySQL, 1000 documents) : 44 à 64 ms par recherche, 1 à 5 mots-clés.
