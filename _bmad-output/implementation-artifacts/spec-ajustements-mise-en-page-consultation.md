---
title: 'Ajustements de mise en page de la consultation (date, tags, pièces jointes)'
type: 'feature'
created: '2026-09-28'
status: 'done'
route: 'one-shot'
---

# Ajustements de mise en page de la consultation (date, tags, pièces jointes)

## Intent

**Problem:** Sur `Show.vue`, la date « Ajouté le » occupait une ligne de métadonnées à part. Les pièces jointes s'empilaient en colonne étroite, avec des noms tronqués, et les tags comme les pièces jointes étaient mal alignés verticalement sur leur libellé.

**Approach:** La date passe dans l'en-tête, alignée à droite sous les boutons d'action, avec un libellé « Importé le … » ou « Créé le … » selon la source (`<time>`, masquée si `created_at` est absent). Les tags et les pièces jointes s'affichent en ligne avec `flex-wrap`, alignés sur la ligne de base du libellé. Chaque pièce jointe devient une pastille qui porte son nom complet (coupé à l'intérieur de la pastille si nécessaire, jamais tronqué) et ses liens *Aperçu · Télécharger*. La limite de 10 pièces jointes et le pré-contrôle client des 20 Mo sont reportés dans `deferred-work.md`.

## Suggested Review Order

**Date dans l'en-tête**

- Point d'entrée : colonne d'actions qui porte maintenant la date sous les boutons, alignée à droite.
  [`Show.vue:423`](../../resources/js/Pages/Documents/Show.vue#L423)

- Verbe choisi selon la source ; libellé vide (donc masqué) sans `created_at`.
  [`Show.vue:114`](../../resources/js/Pages/Documents/Show.vue#L114)

- Balisage `<time datetime>` pour garder la sémantique de l'ancienne ligne `<dt>/<dd>`.
  [`Show.vue:542`](../../resources/js/Pages/Documents/Show.vue#L542)

**Tags et pièces jointes en ligne**

- `<dl>` masqué entièrement quand il n'y a plus aucune ligne à afficher.
  [`Show.vue:554`](../../resources/js/Pages/Documents/Show.vue#L554)

- Pastilles en `flex-wrap`, alignées sur la ligne de base du libellé.
  [`Show.vue:580`](../../resources/js/Pages/Documents/Show.vue#L580)

- `overflow-wrap:anywhere` : un nom très long se coupe dans la pastille au lieu de déborder.
  [`Show.vue:586`](../../resources/js/Pages/Documents/Show.vue#L586)

**Tests**

- Libellé Importé/Créé dans l'en-tête ; aucune date affichée sans `created_at`.
  [`Show.spec.js:151`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L151)
