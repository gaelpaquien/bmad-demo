---
title: 'Indexation du contenu des documents créés (deferred-work P1)'
type: 'bugfix'
created: '2026-09-29'
status: 'done'
baseline_commit: '43595fa6ffbec30699737bce8ff82a3231d5354a'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `deriveExtractedText()` insère une espace avant chaque balise, donc un mot mis en forme en partie (`<strong>Cubi</strong>scan`) est indexé « Cubi scan » et la recherche `cubiscan` ne le trouve pas ; et `strip_tags()` ne décode pas les entités, donc « R&D » est indexé `R&amp;D` et `&nbsp;` reste tel quel.

**Approach:** N'insérer une espace qu'aux frontières des balises de bloc (et `br`/`img`/`hr`), laisser les balises en ligne (`strong`, `em`, `s`, `code`) coller au texte, puis décoder les entités HTML après `strip_tags()` et normaliser les blancs, espace insécable comprise.

## Boundaries & Constraints

**Always:** `strip_tags()` avant `html_entity_decode()` (un `&lt;script&gt;` saisi reste du texte, jamais supprimé comme une balise). Le contenu sans texte reste `null`. Les deux appelants (`CreateDocumentAction`, `UpdateDocumentAction`) passent par la même méthode du trait, inchangés.

**Ask First:** toute modification de `sanitizeContentHtml()` ou de la liste `ALLOWED_TAGS`.

**Never:** pas de commande ni de migration de réindexation (données existantes = données de test, purgées). Ne pas toucher à l'extraction des documents importés (`ExtractDocumentTextJob`) ni au moteur de recherche.

## I/O & Edge-Case Matrix

| Scenario | Input `content_html` (après sanitization) | `extracted_text` attendu |
|----------|--------------|---------------------------|
| Mot en partie en gras | `<p><strong>Cubi</strong>scan</p>` | `Cubiscan` |
| Blocs adjacents | `<h1>Titre</h1><p>Texte</p>` | `Titre Texte` |
| Cellules de tableau | `<table><tbody><tr><td>A</td><td>B</td></tr></tbody></table>` | `A B` |
| Saut de ligne / image | `<p>ligne1<br>ligne2</p>` | `ligne1 ligne2` |
| Entité `&` | `<p>R&amp;D</p>` | `R&D` |
| Espace insécable | `<p>10&nbsp;kg</p>` | `10 kg` (espace ordinaire) |
| Chevrons saisis | `<p>a &lt;b&gt; c</p>` | `a <b> c` |
| Balises seules | `<ul><li></li></ul>` | `null` |

</frozen-after-approval>

## Code Map

- `app/Actions/Concerns/SanitizesDocumentContent.php:310` -- `deriveExtractedText()` : seule méthode à corriger ; mettre à jour son PHPDoc (le paragraphe sur l'espace « at every tag boundary »). Les balises possibles sont bornées par `ALLOWED_TAGS` (l.38) : en ligne = `strong`, `em`, `s`, `code` ; tout le reste sépare.
- `app/Actions/CreateDocumentAction.php:72`, `app/Actions/UpdateDocumentAction.php:61` -- appelants, lecture seule.
- `tests/Feature/CreateDocumentTest.php:58-125,176-202` -- tests existants sur `extracted_text` (blocs adjacents, balises seules, sanitization) : doivent rester verts sans modification.
- `tests/Feature/UpdateDocumentTest.php:89` -- idem côté mise à jour.
- `extracted_text` n'est rendu nulle part côté front (aucun `v-html` dessus) : décoder les entités ne crée pas de risque d'injection.

## Tasks & Acceptance

**Execution:**
- [x] `app/Actions/Concerns/SanitizesDocumentContent.php` -- réécrire `deriveExtractedText()` : espace avant les balises de bloc/`br`/`img`/`hr` seulement, `strip_tags()`, `html_entity_decode(ENT_QUOTES | ENT_HTML5, 'UTF-8')`, normalisation des blancs Unicode (`/[\s\x{00A0}]+/u`), `trim`, `null` si vide ; ajuster le PHPDoc -- corrige les deux défauts P1.
- [x] `tests/Feature/CreateDocumentTest.php` -- ajouter un test par dataset couvrant la matrice I/O (création via `POST /documents/create`) plus un test de recherche : un document `<p><strong>Cubi</strong>scan</p>` est trouvé par `/recherche?search=cubiscan` -- régression.
- [x] `_bmad-output/implementation-artifacts/deferred-work.md` -- déplacer l'entrée P1 dans « Clos » avec son motif -- convention du fichier.

**Acceptance Criteria:**
- Given un document créé dans l'éditeur contenant `<strong>Cubi</strong>scan`, when l'utilisateur cherche `cubiscan`, then le document figure dans les résultats.
- Given un document mis à jour via `PATCH /documents/{id}`, when son contenu change, then `extracted_text` est dérivé par la même règle.

## Spec Change Log

## Verification

**Commands:**
- `php artisan test --compact tests/Feature/CreateDocumentTest.php tests/Feature/UpdateDocumentTest.php tests/Feature/SearchDocumentsTest.php` -- expected: tout vert
- `vendor/bin/pint --dirty --format agent` -- expected: aucun écart restant

## Suggested Review Order

**Dérivation du texte indexé**

- Point d'entrée : espace seulement hors balises en ligne, puis décodage des entités après `strip_tags()`.
  [`SanitizesDocumentContent.php:323`](../../app/Actions/Concerns/SanitizesDocumentContent.php#L323)

- Liste des balises en ligne, sous-ensemble explicite d'`ALLOWED_TAGS`, source unique de la regex.
  [`SanitizesDocumentContent.php:59`](../../app/Actions/Concerns/SanitizesDocumentContent.php#L59)

**Tests et suivi**

- Dataset calqué sur la matrice I/O, plus `em`/`s`/`code` ajoutés en revue.
  [`CreateDocumentTest.php:193`](../../tests/Feature/CreateDocumentTest.php#L193)

- Bout en bout : `cubiscan` retrouve un mot en partie en gras.
  [`CreateDocumentTest.php:211`](../../tests/Feature/CreateDocumentTest.php#L211)

- Entrée P1 passée dans « Clos ».
  [`deferred-work.md:298`](deferred-work.md#L298)
