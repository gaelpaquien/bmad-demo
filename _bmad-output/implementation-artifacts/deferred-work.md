# Deferred Work

<!-- Chaque entrée : source_spec, summary, evidence. Nouvelles entrées : les ajouter en fin de section « À garder » ou « Prioritaire ». Une entrée qui se ferme passe dans « Clos » avec son motif (jamais supprimée). -->

> Tri complet du 2026-09-29 (Winston) : chaque entrée vérifiée contre le code à `a9e7743`. Le texte intégral des entrées closes reste consultable via `git show a9e7743:_bmad-output/implementation-artifacts/deferred-work.md`.

## Prioritaire

Décisions du 2026-09-29, à traiter avant de poursuivre l'app.

### P1 — Indexation du contenu des documents créés

Décision : corriger `deriveExtractedText()` (espacer seulement les balises de bloc, puis `html_entity_decode`). Pas de commande de réindexation : les données existantes sont des données de test, elles seront purgées.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: L'indexation du contenu des documents créés dans l'éditeur a deux défauts, ce qui fausse la recherche :
    - `deriveExtractedText()` insère une espace avant chaque balise, donc un mot mis en forme en partie (`<strong>Cubi</strong>scan`) est indexé en deux (« Cubi scan ») et `cubiscan` ne le trouve pas ;
    - `strip_tags()` ne décode pas les entités HTML, donc « R&D » est indexé `R&amp;D`, et un espace insécable `&nbsp;` reste tel quel.
  evidence: Acceptance Auditor (seconde revue de code, 2026-09-29), `app/Actions/Concerns/SanitizesDocumentContent.php` `deriveExtractedText()`. Défaut préexistant, antérieur à la recherche par mots-clés, qui le rend plus visible. La correction (espacer seulement les balises de bloc, puis `html_entity_decode`) demande de réindexer les documents existants.

### P2 — Bornes de la recherche

Décision : 20 mots-clés maximum et 255 caractères maximum pour le terme ; page Recherche paginée par 10, sans plafond sur le nombre total de résultats. Borner aussi `tag_id[]`.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: Aucun plafond sur le nombre de mots-clés ni sur la longueur du terme de recherche. Chaque mot-clé ajoute un `LIKE '%…%'` par colonne dans le filtre et dans les deux tris. Un paragraphe collé produit une requête énorme, lente, et peut dépasser la limite de paramètres liés.
  evidence: Blind Hunter + Edge Case Hunter (code review f6456b3..9b2497d, 2026-09-29), `KeywordDatabaseEngine::addTextSearchConstraints()` et `DocumentController::search()` (pas de validation `max`). Usage mono-utilisateur, lié à l'écart NFR2 déjà noté dans la spec. Corriger suppose de choisir un plafond (par exemple 20 mots-clés ou 255 caractères) et le comportement au-delà (tronquer ou refuser).

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-aide-et-filtre-tag-seul.md`
  summary: La page Recherche n'est ni paginée ni bornée ; un tag porté par la plupart des documents renvoie désormais presque toute la bibliothèque en un seul payload Inertia.
  evidence: le parcours par tag seul (sans mot-clé) rend ce cas courant ; un mot-clé très fréquent le permettait déjà (revues Blind Hunter et Edge Case Hunter).

### P3 — Fichiers orphelins sur disque

Décision : les fichiers orphelins n'ont aucune utilité, on les évite là où c'est possible et on purge le reste.
- « Annuler » (éditeur et page Import) supprime `documents/tmp/{token}` côté serveur.
- Purge au fil de l'eau, sans scheduler (aucune tâche planifiée ne tourne) : à chaque envoi de brouillon (image ou pièce jointe), supprimer les dossiers `documents/tmp/*` de plus de 24 h. Couvre l'onglet fermé et le plantage.
- Nettoyage sur échec : quand l'enregistrement échoue après un déplacement de fichiers, supprimer les fichiers déjà déplacés (images de l'éditeur, fichier principal de l'import), comme le fait déjà `RelocatesDraftAttachments` pour les pièces jointes.
- Écarté : ne plus rien envoyer avant « Enregistrer » (refonte de l'éditeur et de l'import, dépassement de `post_max_size` avec 10 pièces jointes de 20 Mo).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun nettoyage automatique des dossiers `documents/tmp/{token}/` abandonnés — un brouillon dans lequel une ou plusieurs images ont été insérées puis jamais enregistré (`Enregistrer` jamais cliqué, page fermée) laisse ses fichiers indéfiniment sur le disque privé, sans qu'aucun `Document` ne les référence.
  evidence: Boundaries & Constraints, spec-2-2 ("Never") — exclusion de périmètre délibérée et documentée par l'humain dès l'intent ; corriger nécessiterait une tâche planifiée (purge des dossiers `tmp/*` plus vieux qu'un certain âge) ou un nettoyage côté client (`beforeunload`, peu fiable), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Le déplacement disque des images de brouillon (`relocateDraftImages`) n'est pas couvert par la `DB::transaction()` qui l'englobe — si le `forceFill()->save()` final échoue après un déplacement déjà réussi, les fichiers restent orphelins dans `documents/{id}/images/` alors que la ligne `Document` est annulée par le rollback.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review, spec-2-2) — scénario rare (nécessite un échec DB juste après un `Document::create()` réussi dans la même transaction) ; même nature que le trou déjà accepté sur les dossiers `tmp/{token}` abandonnés (orphelins sans document propriétaire), pas de mécanisme de nettoyage existant à réutiliser.

- source_spec: `_bmad-output/implementation-artifacts/spec-refonte-import-formulaire-unique.md`
  summary: `RelocatesDraftAttachments` supprime (au lieu de remettre dans `tmp/{token}`) les pièces jointes déjà déplacées quand la relocalisation échoue, et ignore silencieusement une pièce jointe listée mais absente de `tmp/{token}` — un nouvel « Enregistrer » après échec crée alors le document sans ces pièces jointes, sans aucun message.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review) — comportement préexistant du code extrait tel quel de `CreateDocumentAction` (même effet dans l'éditeur) ; désormais aussi exposé par l'import. De même, un échec de `SyncDocumentTagsAction` après l'import laisse le fichier d'origine et les pièces jointes relocalisées sur disque (rollback DB uniquement).

## À garder

### Robustesse et risques dormants

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: L'extraction de texte de très gros fichiers Word/Excel (proche de la limite 20 Mo) pourrait épuiser `memory_limit` PHP avec une erreur fatale non rattrapable, court-circuitant la garantie "l'extraction échoue silencieusement, jamais l'import".
  evidence: Edge Case Hunter (step-04 review) — risque réel mais faible en pratique (limite 20 Mo + `memory_limit` PHP par défaut généralement suffisant pour ce volume) ; nécessiterait un parsing par flux pour être vraiment corrigé, hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: `DeleteDocumentAction` n'a aucune gestion d'échec réel (permissions, disque indisponible, exception levée par `unsearchable()`/`delete()`) — pas de try/catch, pas de log, pas de transaction, contrairement au précédent posé par `ImportDocumentAction::storeFile()`.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review) — un échec en cours de séquence dégraderait vers l'état déjà géré par `sourceMissing()` (fichier absent, ligne toujours présente), ce qui borne l'impact pratique ; corriger correctement suppose une décision produit sur la politique d'échec souhaitée (best-effort silencieux vs. transactionnel vs. journalisé et remonté à l'utilisateur), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Course rare possible entre une conversion de prévisualisation en cours (`ConvertDocumentToPreviewAction`, verrou `Cache::lock` par document) et une suppression concurrente : le fichier `previews/{id}.pdf` pourrait être réécrit après la suppression et rester orphelin indéfiniment.
  evidence: Edge Case Hunter (step-04 review) — improbable dans un usage local mono-utilisateur (nécessite deux requêtes concurrentes sur le même document) ; un correctif correct suppose de décider si la suppression doit bloquer sur le même verrou et combien de temps, un arbitrage produit plutôt qu'un correctif mécanique.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: La conversion LibreOffice tourne de façon synchrone dans la requête HTTP `preview()` (jusqu'à 120s de timeout) — un gros fichier peut épuiser un worker PHP-FPM ou dépasser le timeout du serveur web avant celui du process, laissant un `soffice` orphelin.
  evidence: Blind Hunter (step-04 review) — risque réel mais décision d'architecture déjà tranchée (route synchrone dédiée, pas de job/polling, cf. Boundaries "Never" de spec-1-3) ; à revisiter seulement si des timeouts réels sont observés en usage.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Aucune invalidation du cache `previews/{id}.pdf` si le fichier source d'un document est un jour remplacé/ré-importé — le cache resterait périmé indéfiniment.
  evidence: Blind Hunter (step-04 review) — aucun mécanisme de remplacement de fichier source n'existe encore dans l'app (pas de story de ré-import) ; `epic-1-context.md` anticipe explicitement cette invalidation ("invalidated only by delete/re-import"), à traiter quand cette fonctionnalité sera construite.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Un fichier annoncé `mime_type=application/pdf` mais dont le contenu est en réalité corrompu/invalide n'est jamais détecté côté serveur — l'iframe pointée directement sur la route de preview affiche un rendu vide/cassé au lieu du message explicite "Aperçu indisponible" que reçoit un Word/Excel corrompu.
  evidence: Edge Case Hunter (step-04 review) — confirmé : `preview()` ne valide que l'existence/lisibilité du fichier (`sourceMissing`), jamais la structure PDF elle-même ; aucun des 4 AC métier de la story (issus d'epics.md) ne couvre ce cas, hors du périmètre approuvé par l'humain pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: La conversion LibreOffice headless de fichiers Word/Excel importés (potentiellement porteurs de macros) tourne sans isolation (pas de profil utilisateur restreint, pas de désactivation explicite des macros) — risque de sécurité si un fichier malveillant est un jour importé.
  evidence: Blind Hunter (step-04 review) — décision d'architecture déjà tranchée au niveau epic (invocation `soffice --headless --convert-to pdf` brute, epic-1-context.md) ; risque jugé faible en pratique pour un usage local mono-utilisateur v1 (l'utilisateur importe ses propres fichiers), mais à durcir si l'app s'ouvre un jour à des fichiers non maîtrisés.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `Editor.vue` porte à la fois la création et l'édition ; une navigation Inertia qui réutilise cette même instance de composant sans démontage intermédiaire (ex. retour/avance navigateur à travers l'historique Inertia) ne réinitialiserait pas `draftToken`/`form`/`initialSnapshot`/l'instance TipTap depuis les nouveaux props, laissant potentiellement le contenu d'un autre document affiché.
  evidence: Blind Hunter (step-04 review) — aucun chemin de navigation actuel dans l'app ne relie directement deux pages Editor.vue sans passer par la Bibliothèque/la Fiche document (qui démontent le composant), donc non reproductible via le parcours normal ; resterait à vérifier si l'historique navigateur (retour/avance) peut recréer ce cas via le cache de pages d'Inertia.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: Un échec de `relocateDraftImages()` (`Storage::move()` retournant `false`) pendant une mise à jour se propage en exception non rattrapée jusqu'à une erreur générique côté client, alors que l'I/O Matrix de cette story exige un "message explicite" ; le même trou existe déjà côté création (spec-2-1/2-2) et n'a jamais été comblé.
  evidence: Blind Hunter (step-04 review) — confirmé par lecture de `DocumentController::update()`/`storeCreated()` : aucun `catch` autour de l'appel à l'Action, aucun gestionnaire d'exception applicatif dans `app/Exceptions`. Pré-existant, pas introduit par cette story ; corriger suppose de décider d'un mécanisme de message d'erreur explicite pour toute l'app (flash d'erreur), hors proportion pour cette seule story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `DeleteDocumentTest::it_permanently_deletes_the_file__the_preview_cache_and_the_document_row__then_redirects_to_the_library` échoue de façon reproductible (`Directory [documents/1] is not empty.`), y compris isolé et sur l'état du dépôt antérieur à cette story (confirmé par un `git stash` de reproduction).
  evidence: Vérification step-04 (`php artisan test`) — échec confirmé pré-existant, sans rapport avec ce diff (spec-1-8, déjà `done`) ; surfacé incidemment par l'exécution de la suite complète exigée par la section Verification de cette story.
  triage: Tri 2026-09-29 : flake connu ; `spec-fix-full-suite-test-flake.md` rejetée et exclusion Windows Defender abandonnée (décision du 2026-09-29).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: `phpunit.xml` fixe `BROWSERSHOT_CHROME_PATH` à un chemin Windows absolu — `ExportDocumentToPdfTest` échouerait uniformément sur toute machine/CI où Chrome n'est pas installé à cet exact emplacement.
  evidence: Blind Hunter + Edge Case Hunter + Verification Gap Reviewer (step-04 review, convergent) — non bloquant pour l'usage actuel (poste de dev Windows unique, pas de pipeline CI en v1 — NFR1) ; à revisiter si le projet s'ouvre un jour à plusieurs postes/CI.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: Le timeout Browsershot de 60s n'est arrimé à aucun timeout du serveur web/PHP-FPM en production — un rendu lent pourrait déclencher une erreur 504 du serveur avant que le 422 explicite prévu par la spec ne se déclenche.
  evidence: Blind Hunter (step-04 review) — même nature que le risque déjà accepté sur le timeout LibreOffice 120s de `ConvertDocumentToPreviewAction` (spec-1-3) ; non observé en pratique, à revisiter si des timeouts réels apparaissent en usage.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToWordAction::resolveImageSources()` construit le chemin disque directement à partir du nom de fichier capturé par la regex, sans le valider contre un format attendu (ex. UUID), avant l'appel à `Storage::disk('local')->exists()`/`->path()` — repose entièrement sur la désinfection en amont (`SanitizesDocumentContent`) plutôt que de se défendre elle-même contre un `src` malformé/de type traversée de chemin.
  evidence: Blind Hunter (step-04 review) — même schéma déjà présent dans `ExportDocumentToPdfAction::resolveImageSources()` (spec-2-4, déjà `done`) ; impact limité en usage local mono-utilisateur (NFR3, pas d'auth), à durcir si l'app s'ouvre un jour à des entrées non maîtrisées.
  triage: Tri 2026-09-29 : l'export Word a été retiré (c7ed7e3), le constat reste valable pour `ExportDocumentToPdfAction::resolveImageSources()`.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Deux requêtes concurrentes (double-clic, deux onglets) peuvent toutes deux passer la validation de doublon insensible à la casse avant qu'aucune n'ait encore inséré sa ligne, créant deux tags variantes de casse de la même chaîne.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review, convergents) — la contrainte unique DB existante sur `tags.name` (migration spec-3-1) est sensible à la casse, donc ne bloque pas ce cas ; corriger proprement nécessiterait un index unique insensible à la casse (migration) ou une transaction avec re-vérification, hors proportion pour ce diff. Impact quasi nul pour un outil interne mono-utilisateur (NFR3, pas d'authentification).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: Coller un extrait HTML externe contenant un `<table>` alors que le curseur est déjà dans un tableau existant n'est pas gardé par le correctif de cette story — seul le bouton "Insérer un tableau" est protégé, pas le collage, donc le même bug (tableau imbriqué) reste atteignable par ce second chemin.
  evidence: Blind Hunter (step-04 review) — vecteur jugé rare pour un outil interne mono-utilisateur (usage typique : rédaction native dans l'Éditeur, pas de collage de HTML externe avec tableaux) ; corriger nécessiterait une garde côté schéma ProseMirror (`appendTransaction`) plutôt qu'un simple guard sur le bouton, hors proportion pour cette story.

### Arbitrages produit sur la recherche

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucun index `FULLTEXT` sur `documents.extracted_text` — la recherche reste un scan `LIKE '%terme%'` sans index ; l'objectif "<1s pour ~350 documents" (NFR2) n'est donc pas techniquement garanti si le corpus grossit significativement au-delà de cet ordre de grandeur.
  evidence: Blind Hunter (step-04 review) — non bloquant à l'échelle actuelle (~350 documents, scan trivial), et le driver `database` de Scout n'utilise `FULLTEXT` que si la colonne est explicitement déclarée comme telle ; à revisiter si la volumétrie dépasse l'ordre de grandeur documenté dans l'epic.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: Les mots-clés se comparent en sous-chaîne, pas en mot entier. « on » trouve « bonjour », et en OU un mot court (« le », « de ») fait remonter presque toute la bibliothèque. Une expression entre guillemets est elle aussi une sous-chaîne (« cubiscan speed » trouve « xcubiscan speedy »).
  evidence: Blind Hunter + Edge Case Hunter (code review f6456b3..9b2497d, 2026-09-29), `KeywordDatabaseEngine.php` `$bindingsFor` (`'%'.…'%'`). L'encart d'aide de `Search.vue` décrit désormais ce comportement (corrigé pendant la revue). Il reste à choisir entre une longueur minimale, des mots vides ou des frontières de mot : c'est un arbitrage produit.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: L'aide « sans tenir compte des majuscules ni des accents » n'est vraie qu'en production (MySQL, collation `utf8mb4_unicode_ci`). Sous SQLite (tests), `LIKE` n'ignore la casse que pour l'ASCII (« Été » ne trouve pas « été »). Sous MySQL, cela dépend de la collation. Les accents ne sont jamais normalisés, et le SQL brut du moteur n'est testé que sous SQLite (`phpunit.xml`), jamais sous MySQL.
  evidence: Blind Hunter + Edge Case Hunter + Verification Gap (code review f6456b3..9b2497d, 2026-09-29), `KeywordDatabaseEngine.php` (seul `pgsql` passe par `ilike`). En production MySQL, une collation `utf8mb4_*_ci` couvre la casse et les accents. Corriger demande de normaliser explicitement (`lower()`) ou de faire tourner les tests sous MySQL.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: Le découpage du terme a des cas limites non documentés ni testés. `++foo` et `+-foo` gardent l'opérateur en trop dans le motif, qui ne trouve alors rien. Un guillemet non fermé transforme toute la fin du terme en expression. Un terme qui commence vraiment par un tiret (`-5`, `-20%`) est toujours lu comme une exclusion. Une expression dont les mots sont séparés par un retour à la ligne dans `extracted_text` n'est pas trouvée.
  evidence: Blind Hunter + Edge Case Hunter (code review f6456b3..9b2497d, 2026-09-29), `KeywordDatabaseEngine::keywordsFrom()` (regex `([+-]?)(?:"([^"]*)"?|(\S+))`). Impact faible, saisies rares. Corriger suppose de fixer des règles (opérateurs multiples, échappement du tiret, normalisation des espaces).

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: `KeywordDatabaseEngine` remplace entièrement `addTextSearchConstraints()` de Scout : les colonnes `#[SearchUsingPrefix]`, `#[SearchUsingFullText]` et d'embedding sont ignorées sans erreur, et un terme vide renvoie zéro résultat au lieu de toutes les lignes.
  evidence: Blind Hunter + Edge Case Hunter (code review f6456b3..9b2497d, 2026-09-29). `Document` n'utilise aucun de ces attributs aujourd'hui, et le terme vide est déjà court-circuité par `DocumentController::search()`, ce qui reste cohérent avec la règle « jamais toute la bibliothèque ». À traiter si un de ces attributs est ajouté un jour (lever une exception, ou les prendre en charge).

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: Une recherche contradictoire ou faite uniquement d'exclusions (`speed -speed`, `-speed`) affiche le message ordinaire « Aucun document ne correspond », sans expliquer pourquoi. L'aide le documente, mais la page ne le signale pas au moment où cela arrive.
  evidence: Blind Hunter (seconde revue de code, 2026-09-29), `KeywordDatabaseEngine::addTextSearchConstraints()` (`whereRaw('1 = 0')`). Il faudrait que le contrôleur renvoie un indicateur à la page : amélioration UX à faible impact.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-aide-et-filtre-tag-seul.md`
  summary: Une recherche faite uniquement d'exclusions (`-speed`) avec un tag sélectionné ne renvoie rien, alors que le tag seul liste ses documents ; envisager « documents du tag sauf ceux contenant speed ».
  evidence: incohérence relevée au plan et par la revue (Blind Hunter) ; laissée hors périmètre car elle touche la règle « aucun mot-clé positif ⇒ aucun résultat » du moteur.

### Dette légère — quick wins à grouper

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-browse-library.md`
  summary: Le formatage de date en français (`Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' })`) est dupliqué entre `Index.vue` (`formatDate()`) et `Show.vue` (`formattedDate`) au lieu d'être centralisé dans un helper partagé.
  evidence: Blind Hunter (step-04 review) — même schéma que la duplication du libellé de type déjà résolue dans cette story ; coût de duplication encore faible (une seule ligne de logique), à centraliser si une troisième page a besoin d'afficher une date.
  triage: Tri 2026-09-29 : désormais 3 occurrences (`Index.vue`, `Search.vue`, `Show.vue`) — règle de trois atteinte, extraire un helper.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: `Show.vue` calcule `isCreated` en comparant `document.source` à la chaîne littérale `'created'` au lieu de référencer une source commune avec l'enum backend `DocumentSource`.
  evidence: Blind Hunter (step-04 review) — même schéma que la duplication type→mime déjà différée (spec-1-7) ; à centraliser si un troisième point de comparaison sur `source` apparaît.
  triage: Tri 2026-09-29 : 2 occurrences restantes (`Show.vue`, `DocumentTypeBadge.vue`).

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: `App\Support\DocumentMimeTypes` centralise le mapping mime-type PHP mais ne l'a pas étendu à `resources/js/Components/DocumentTypeBadge.vue`, qui garde sa propre copie (`MIME_TYPE_LABELS`) — une 3ᵉ occurrence du même mapping subsiste. Le commentaire du composant ("Single source of truth for the document type label") est maintenant trompeur puisqu'une vraie source unique existe désormais côté PHP/Inertia sans que ce composant s'y raccorde.
  evidence: Blind Hunter (step-04 review). Exclusion de périmètre délibérée de `spec-corrections-post-retrospective.md` ("Never : ne pas toucher à DocumentTypeBadge.vue::MIME_TYPE_LABELS"), donc non traité dans cette itération ; prochaine étape naturelle si le mapping mime-type est retouché à nouveau — reconnecter ce composant à la prop `documentTypeOptions` (ou une prop `documentTypeLabels` dédiée) et corriger/retirer le commentaire devenu inexact.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: `App\Support\DocumentMimeTypes::MIME_TO_FORMAT` et `::TYPE_TO_MIME` continuent de répéter indépendamment les 3 mêmes littéraux de mime-type complets (`application/pdf`, etc.) dans deux tableaux distincts de la même classe — la centralisation réduit la duplication inter-fichiers mais n'élimine pas la duplication interne à la classe ; une faute de frappe dans l'un et pas l'autre désynchronisant les deux tables silencieusement.
  evidence: Blind Hunter (step-04 review). Pas bloquant (les deux tableaux sont désormais testés — voir patch appliqué dans la même revue), mais une vraie table canonique unique (mime → {format, type, label}) serait plus robuste ; à envisager si un 4ᵉ mapping mime-type apparaît un jour dans le projet.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective-epic-3.md`
  summary: Le tri insensible à la casse des tags (`Str::lower()`) est dupliqué mot pour mot entre `TagController::index()` et `HandleInertiaRequests::share()` plutôt que factorisé (ex. scope/méthode sur le modèle `Tag`) — seul un commentaire ("rester en lockstep") empêche les deux de diverger à une future modification.
  evidence: Blind Hunter (step-04 review) — extraction légitime mais touche `app/Models/Tag.php`, hors périmètre de fichiers annoncé par cette spec (Code Map limité à `TagController.php`/`HandleInertiaRequests.php`) ; à faire lors d'une prochaine session touchant l'un des deux endroits.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective-epic-3.md`
  summary: Le docblock de `app/Models/Tag.php` est obsolète — il indique encore que la création/le renommage/la suppression d'un tag sont "hors périmètre de la story 3.5" et que les lignes viennent de `TagFactory`/tinker, alors que `TagController` gère déjà entièrement le CRUD (confirmé par cette même spec et par `HandleInertiaRequests.php`, dont le commentaire miroir a déjà été corrigé).
  evidence: Blind Hunter (step-04 review) — désynchronisation préexistante (non introduite par cette spec), simple mise à jour de commentaire, hors périmètre de fichiers annoncé (`Tag.php` non listé dans le Code Map).

- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-pint-code-mort-titre-doc.md`
  summary: EXPERIENCE.md n'a pas de State Patterns pour la page Import (quitter après saisie perd tout sans alerte, « Enregistrer » désactivé pendant l'envoi d'une pièce jointe, échec serveur après « Enregistrer ») ni de ligne dans la table Information Architecture.
  evidence: Relevé par la revue du nettoyage du 2026-09-29 ; l'Éditeur a sa ligne « modifications non enregistrées », la page Import (spec-refonte-import-formulaire-unique) n'en a aucune.

- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-pint-code-mort-titre-doc.md`
  summary: DESIGN.md (composant Sidebar, L.183) ne liste que les 3 racines et « 5 surfaces », sans les actions « Importer un document » / « Créer un document » ajoutées à la sidebar.
  evidence: Dérive antérieure (spec-sidebar-document-actions) relevée par la revue du nettoyage du 2026-09-29 ; EXPERIENCE.md a été réaligné, pas DESIGN.md.

### Accessibilité

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: La création et le renommage d'un tag ne produisent aucune confirmation annoncée (pas de `role="status"`/`aria-live`), contrairement à la suppression qui a son message factuel.
  evidence: Blind Hunter (step-04 review) — non requis par les Boundaries & Constraints ni les Acceptance Criteria de spec-3-5 (qui exigent seulement la navigation clavier/focus visible), amélioration d'accessibilité au-delà du périmètre figé.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Les champs de création/renommage de tag n'ont pas d'`aria-describedby` reliant le texte d'erreur (`role="alert"`) au champ concerné.
  evidence: Blind Hunter (step-04 review) — l'erreur est déjà annoncée via `role="alert"`, ceci est un raffinement d'association programmatique supplémentaire, hors périmètre figé de spec-3-5.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Le reste de la page (formulaire de création, liste) n'est pas marqué `inert`/`aria-hidden` pendant que la boîte de dialogue de suppression est ouverte.
  evidence: Blind Hunter (step-04 review) — pattern préexistant identique dans `Show.vue` (dialogue de suppression document, spec-1-8), reproduit fidèlement par spec-3-5 sans régression propre à cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-fix-multi-tag-selection.md`
  summary: L'état inerte de `TagSelector.vue` pendant `disabled` (readonly + `aria-disabled="true"`) n'annonce rien explicitement aux lecteurs d'écran (pas d'`aria-live`) qu'une sélection vient d'être prise en compte — l'ancien signal implicite (fermeture de la liste) a disparu sans équivalent non-visuel, et `aria-readonly` pourrait mieux refléter l'état DOM réel qu'`aria-disabled` seul.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review, convergents) — raffinement d'accessibilité au-delà des Acceptance Criteria approuvés (navigation clavier/focus visible, pas de confirmation non-visuelle explicite) ; à traiter si un usage avec lecteur d'écran remonte une confusion réelle.

- source_spec: `_bmad-output/implementation-artifacts/spec-sidebar-menu-adjustments.md`
  summary: Le bouton toggle thème de `Sidebar.vue` a un libellé visible qui décrit l'état courant ("Thème clair"/"Thème sombre") mais un `aria-label` qui décrit l'action cible ("Passer en mode sombre"/"Passer en mode clair") — l'un dit le contraire de l'autre, ce qui viole WCAG 2.5.3 (Label in Name) et est trompeur pour un utilisateur de commande vocale.
  evidence: Blind Hunter (revue de ce diff) — préexistant : logique identique (même paire libellé/`aria-label` inversée) dans l'ancien `AppHeader.vue` avant sa migration vers la sidebar (spec-3-2) ; ce diff déplace le bouton mais ne touche pas sa logique de libellé. Corriger suppose de choisir une convention unique (état ou action) pour les deux textes, hors périmètre de cette retouche visuelle.

- source_spec: `_bmad-output/implementation-artifacts/spec-ajustements-formulaires-sidebar.md`
  summary: Accessibilité de l'éditeur et de la sidebar :
    - le libellé « Contenu » est un `<p>` non relié à l'éditeur (pas d'`aria-labelledby`) ;
    - `aria-required` est posé sur le contenteditable sans rôle explicite ;
    - le bouton « Réduire le menu » change à la fois son `aria-label` et `aria-expanded`, sans `aria-controls`, donc son état est annoncé deux fois ;
    - le titre-lien de la sidebar double l'arrêt de tabulation vers « Documents » ;
    - le logo Laravel par défaut est toujours là (commentaire « à remplacer ») ;
    - quelques lignes du bloc barre d'outils de `Editor.vue` ne contiennent que des espaces.
  evidence: Blind Hunter (code review f6456b3..9b2497d, 2026-09-29), `Editor.vue` (bloc Contenu et barre d'outils) et `Sidebar.vue` L.107 et L.233-234. Des raffinements, hors critères d'acceptation de la spec a posteriori.
  triage: Tri 2026-09-29 : le logo Laravel par défaut (`Sidebar.vue`) est un quick win isolable.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: La région `aria-live` de la page Recherche englobe aussi la liste des résultats (`aria-atomic="true"`), si bien qu'un lecteur d'écran peut relire toute la liste à chaque recherche. Il faudrait une région live réservée aux messages d'état (chargement, aucun résultat, nombre de résultats).
  evidence: Blind Hunter (seconde revue de code, 2026-09-29), `Search.vue`, conteneur des résultats. Préexistant : la seconde revue a seulement retiré l'annonce en double du chargement.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-chips-tag-sans-doublon.md`
  summary: Sur la page Recherche, retirer un filtre tag via sa chip lime fait tomber le focus sur `<body>` (le bouton cliqué est démonté) ; le ramener sur le champ de tags ou sur la chip voisine.
  evidence: comportement antérieur, mais la chip lime est désormais le seul moyen de retrait sur la page (les chips grises de TagSelector, qui refocalisaient le champ, y sont masquées) ; relevé par Edge Case Hunter et Blind Hunter.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-chips-tag-sans-doublon.md`
  summary: Sur la page Recherche, la sélection de tags n'est plus exposée dans le `<fieldset>` « Filtrer par tag » ; la rangée « Filtres par tag actifs » est hors du fieldset et n'est reliée au combobox ni par `aria-describedby` ni par une annonce live.
  evidence: relevé par Blind Hunter ; les chips grises masquées étaient jusqu'ici dans le fieldset.

### UX — à reprendre si l'usage réel s'en plaint

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: Déposer plusieurs fichiers à la fois dans la modale d'import n'affiche aucun message — seul le premier est importé silencieusement.
  evidence: Edge Case Hunter (step-04 review) — `resources/js/Components/ImportModal.vue` `onDrop()` ne vérifie pas `event.dataTransfer.files.length > 1`. Faible impact (usage solo), amélioration UX à bas coût si une session future touche ce fichier.
  triage: Tri 2026-09-29 : `ImportModal.vue` n'existe plus, mais `Documents/Import.vue` garde le comportement (`fileFromDropEvent()` ne lit que `files[0]`, sans message).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Aucune confirmation visuelle (toast/flash) n'apparaît sur la Bibliothèque après une suppression réussie — l'utilisateur constate seulement l'absence du document.
  evidence: Blind Hunter (step-04 review) — pas spécifique à cette story : `store()`/`updateCategory()` n'ont eux non plus jamais eu de système de flash-message ; l'app n'en a jamais eu nulle part.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: L'enregistrement d'un document créé redirige vers la Fiche document sans afficher le texte "Enregistré." littéral prévu par la voix/ton (EXPERIENCE.md) — aucun mécanisme de flash message n'existe dans l'app pour le porter.
  evidence: Design Notes, step-02 planning — même trou déjà noté à la Story 1.8 (pas de flash/toast après suppression). Nécessiterait d'introduire le partage de session flash côté `HandleInertiaRequests` (absent aujourd'hui), hors proportion pour une seule story ; à traiter comme un ajout transverse si une future story en a de nouveau besoin (ex. export réussi, Story 2.4/2.5).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: `export-pdf.blade.php` ne définit aucune règle de pagination CSS (`@page`, `break-inside`/`page-break-*`) — un titre, une ligne de tableau ou une image peut se couper de façon disgracieuse en travers d'un saut de page sur un document long.
  evidence: Blind Hunter (step-04 review) — NFR5 borne explicitement la garantie de fidélité "critique" aux images inline (position/rendu), pas à la pagination générale ; polish visuel hors du périmètre approuvé pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `useFileDropZone` (extrait d'`ImportModal.vue`, désormais partagé avec `AttachmentsPanel.vue`) traite un sélecteur de fichier annulé par l'utilisateur (aucun fichier choisi) comme un format non supporté, affichant "Format non supporté" au lieu de ne rien faire.
  evidence: Blind Hunter (step-04 review) — défaut préexistant dans `ImportModal.vue` avant cette story ; le refactor en composable le duplique désormais vers un second composant au lieu de le corriger. Correctif trivial (`if (!file) return;` en tête de `validationError()`) si une session future retouche ce fichier.
  triage: Tri 2026-09-29 : `AttachmentsPanel.vue` est gardé (`if (!file)`), `Import.vue::handleFile()` ne l'est pas.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucune distinction visuelle (icône/couleur) entre pièces jointes PDF/Word/Excel dans `AttachmentsPanel.vue`/`Show.vue` — la liste n'affiche qu'un nom de fichier brut, alors que `mime_type` est chargé sur chaque ligne.
  evidence: Blind Hunter (step-04 review) — non requis par la spec ; amélioration UX à bas coût si une session future retouche ces composants (réutiliser `DocumentTypeBadge.vue`, déjà disponible pour le document parent).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: "Supprimer le tableau" retire le tableau sans boîte de dialogue de confirmation — un clic accidentel supprime instantanément le tableau et son contenu.
  evidence: Blind Hunter (step-04 review) — atténué par l'historique d'annulation TipTap (Ctrl+Z, `StarterKit`) disponible tant que le document n'est pas rechargé, contrairement aux suppressions permanentes (document/tag) qui ont, elles, une boîte de confirmation ; à revisiter si des pertes accidentelles sont rapportées en usage réel.

- source_spec: `_bmad-output/implementation-artifacts/spec-fix-multi-tag-selection.md`
  summary: `TagSelector.vue` réinitialise systématiquement `query` à chaque sélection — filtrer sur un terme (ex. "com" pour plusieurs tags "Comptes…") oblige à retaper le filtre avant chaque sélection suivante au lieu de rester actif entre deux choix.
  evidence: Blind Hunter (step-04 review) — contradiction partielle avec l'objectif "enchaîner plusieurs sélections rapidement" de cette story, mais non couvert par la matrice I/O approuvée (qui ne teste que la sélection sans filtre actif) ; amélioration UX à envisager si des utilisateurs filtrent réellement sur des tags au nommage proche.

- source_spec: `_bmad-output/implementation-artifacts/spec-refonte-page-consultation-document.md`
  summary: Sur un écran très étroit, l'en-tête de `Show.vue` (titre + actions `shrink-0`, sans `flex-wrap`) peut déborder horizontalement pour un document créé (4 boutons) — le titre se réduit à zéro et les actions sortent de la vue.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review) — préexistant : l'ancienne barre d'actions (`flex gap-3`, sans wrap) débordait déjà de la même façon ; l'intention validée impose un en-tête « sur une ligne » sans menu « ⋯ », donc corriger (retour à la ligne responsive ou regroupement) demande une décision de design.

- source_spec: `_bmad-output/implementation-artifacts/spec-ajustements-formulaires-sidebar.md`
  summary: Dans l'éditeur, « Enregistrer » dépend de `editor.isEmpty` de TipTap, qui considère un tableau aux cellules vides comme un contenu vide, alors que le serveur (`content_html` `required`) accepterait `<table>…</table>`. Le bouton désactivé n'explique pas non plus pourquoi il l'est.
  evidence: Acceptance Auditor + Blind Hunter (code review f6456b3..9b2497d, 2026-09-29), `Editor.vue` L.143/153 et `@tiptap/core` `isNodeEmpty`. Un tableau vide n'est sans doute pas un contenu utile. Le client est donc plus strict que le serveur, contrairement à la règle « le serveur reste l'autorité ». Impact faible, arbitrage produit.

- source_spec: `_bmad-output/implementation-artifacts/spec-recherche-mots-cles.md`
  summary: Sur la page Recherche, le message « Recherche en cours… » remplace les résultats précédents, qui clignotent à chaque recherche de plus de 300 ms. Le placeholder long (avec l'exemple de syntaxe) est tronqué sur écran étroit.
  evidence: Blind Hunter (code review f6456b3..9b2497d, 2026-09-29), `Search.vue` L.231 et L.260-265. Choix de présentation, à revoir avec le design (garder les résultats atténués pendant le chargement, raccourcir le placeholder).

### Couverture de tests

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Le comportement interactif de la boîte de dialogue de confirmation de suppression (`Show.vue` : piège de focus, Échap, restauration du focus) n'est vérifié par aucun test automatisé.
  evidence: Blind Hunter (step-04 review) — même constat déjà différé pour la Story 1.7 (filtres) : le dépôt ne contient aucun outil de test JS (pas de Vitest/Jest).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun test JS/composant ne couvre le dialogue de saisie du texte alternatif ni le glisser-déposer d'image dans `Editor.vue` (calcul de la position d'insertion via `posAtCoords`, piège de focus, Échap) — même absence d'outillage de test JS que le reste du projet (pas de Vitest/`@vue/test-utils`).
  evidence: Même constat déjà différé pour `CategoryPicker.vue`/le dialogue de suppression de `Show.vue` (spec-1-5, spec-1-8) ; introduire un outillage de test frontend reste hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun test ne force l'échec de `relocateDraftImages()` (ex. `Storage::move()` retournant `false`) pour vérifier que la transaction annule bien la création du `Document` et que le dossier de destination est nettoyé.
  evidence: Verification Gap Reviewer (step-04 review) — même trou déjà accepté sur la branche échec-de-stockage structurellement identique de `ImportDocumentAction::storeFile()` ; aucun pattern de mock/fake d'échec disque n'existe ailleurs dans le projet (`Storage::fake('local')` réussit toujours), introduire ce tooling est hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `UpdateDocumentTest.php` ne couvre pas : un titre de plus de 255 caractères à la mise à jour, un `draft_token` invalide (non-UUID), ni le scénario "image insérée puis retirée avant enregistrement" côté édition (son équivalent existe côté création, spec-2-2).
  evidence: Blind Hunter (step-04 review) — mêmes règles de validation que `CreateDocumentRequest` (déjà testées côté création) ; complète la matrice I/O mais n'en fait pas partie explicitement, hors du périmètre strictement approuvé pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: Aucun test ne couvre l'export d'un document `source=created` dont `content_html` est vide/`null`.
  evidence: Blind Hunter (step-04 review) — cas limite réel mais mineur (repli `?? ''` déjà en place dans `ExportDocumentToPdfAction`) ; ne fait pas partie de la matrice I/O approuvée par l'humain pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: `Editor.spec.js` (nouveau) ne suit pas la convention `attachTo: document.body` + `wrapper.unmount()` déjà en place dans `Configuration.spec.js`/`Search.spec.js` — les 5 montages du fichier n'appellent jamais `onBeforeUnmount`, donc le nettoyage du listener `beforeunload` et de l'abonnement `router.on('before', ...)` d'`Editor.vue` n'est jamais exercé par ce test (accumulation silencieuse across les 5 `mount()`, sans échec observé sur la suite actuelle).
  evidence: Blind Hunter (step-04 review, itération 2) — suite complète (80/80) verte malgré cette lacune ; à corriger si `Editor.spec.js` grossit ou si des avertissements de fuite apparaissent, en alignant sur le patron déjà établi par les specs voisines du même dossier.

## Clos

Motif du tri 2026-09-29 pour chaque entrée fermée.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-browse-library.md`
  summary: La logique de dérivation du libellé dans `DocumentTypeBadge.vue` (mapping mime-type, repli `source=created`, repli générique "Document") n'est vérifiée par aucun test automatisé — le dépôt ne contient aucun outil de test JS (pa…
  motif: Résolu — `DocumentTypeBadge.spec.js` existe (Vitest installé).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: `ImportModal.vue` a le même défaut préexistant que celui corrigé dans le nouveau dialogue de suppression : son piège de focus (`trapFocus()`) n'exclut pas les éléments `disabled`, alors que son bouton de fermeture peut être dés…
  motif: Obsolète — `ImportModal.vue` supprimé (refonte de l'import) ; correctif déjà appliqué (entrée 50).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Aucun nettoyage de `storage/app/private/previews/{document_id}.pdf` n'existe lors de la suppression d'un document — la Story 1.8 (Supprimer un document) devra faire de ce cache un des éléments nettoyés par `DeleteDocumentAction`.
  motif: Résolu — `DeleteDocumentAction` supprime `previews/{id}.pdf` (étape 4).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Le mapping mime-type→format est dupliqué entre `DocumentController::previewFormat()` (nouveau) et `ExtractDocumentTextJob::formatFromMimeType()` (existant) au lieu d'être centralisé.
  motif: Résolu — centralisé dans `App\Support\DocumentMimeTypes`.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-categorize-document.md`
  summary: Si l'utilisateur navigue vers un autre document pendant qu'une réassignation de catégorie est encore en vol (`PATCH /documents/{id}/category`), la visite Inertia suivante peut annuler/faire courir en concurrence cette requête,…
  motif: Obsolète — catégories remplacées par les tags (epic 3).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-categorize-document.md`
  summary: Aucun test au niveau composant Vue ne couvre `CategoryPicker.vue` (flux créer/annuler/Échap, mise à jour optimiste) — le dépôt ne contient toujours aucun outil de test JS (pas de Vitest/`@vue/test-utils`).
  motif: Obsolète — `CategoryPicker.vue` supprimé.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Le raccourci clavier `/` (focus barre de recherche, ignoré si un champ ou la modale d'import a déjà le focus) et le debounce de saisie dans `Index.vue` ne sont couverts par aucun test automatisé — même absence d'outillage de te…
  motif: Obsolète — raccourci `/` retiré (39fb26e).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Le terme de recherche (`?search=`) n'a aucune limite de longueur avant d'être transmis à `Document::search()` — une chaîne arbitrairement longue peut être soumise sans garde-fou sur le coût de la requête `LIKE`.
  motif: Fusionné dans P2 (bornes de la recherche).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucun test ne vérifie la casse/les accents du terme de recherche (ex. variante majuscule ou sans accent d'un mot accentué) — le comportement de repli de casse/accents peut différer entre SQLite (tests) et MySQL (production).
  motif: Fusionné dans l'entrée casse/accents de spec-recherche-mots-cles (À garder).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucune protection contre les réponses Inertia qui arrivent dans le désordre (une requête de recherche plus ancienne mais plus lente pourrait résoudre après une plus récente et écraser des résultats plus à jour) si l'utilisateur…
  motif: Résolu — `Search.vue` suit la navigation la plus récente, Inertia annule la visite précédente.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: Aucun test au niveau composant Vue ne couvre la nouvelle logique de filtrage d'`Index.vue` (bascule des checkboxes, retrait des chips, les deux drapeaux `isSyncing*FromProps`, l'annulation du debounce de recherche lors d'un cha…
  motif: Obsolète — la Bibliothèque (`Index.vue`) ne filtre plus.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: `categoryIdsFromQuery()` n'impose aucune limite au nombre d'identifiants acceptés avant de les passer à `whereIn('category_id', ...)` — un `category_id[]=...` arbitrairement long est accepté sans garde-fou de taille.
  motif: Fusionné dans P2 — `categoryIdsFromQuery()` remplacé par `tagIdsFromQuery()`, même absence de borne.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: Aucun index base de données sur `documents.mime_type`/`documents.source` alors que le filtre type exécute désormais un `whereIn('mime_type', ...)`/`where('source', ...)` sur chaque requête filtrée — `category_id` bénéficie d'un…
  motif: Obsolète — le filtre par type n'est plus utilisé (`search()` passe `[]`).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: La correspondance type→mime est dupliquée entre `DocumentController::TYPE_MIME_MAP` (PHP) et `TYPE_OPTIONS` (Vue, `Index.vue`) sans source commune — un commentaire est le seul lien entre les deux.
  motif: Obsolète — `TYPE_MIME_MAP`/`TYPE_OPTIONS` n'existent plus.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: Un second clic sur "Enregistrer" avant de quitter l'Éditeur crée un nouveau document distinct plutôt que de mettre à jour celui déjà enregistré — pas de garde contre le doublon.
  motif: Résolu — la story 2.3 met à jour le même document.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: `Editor.vue::onSaveClick()` focus le sélecteur de catégorie via `document.getElementById('category-picker-select')`, en passant par un id DOM interne à `CategoryPicker.vue` plutôt que par son API de composant — couplage fragile…
  motif: Obsolète — `CategoryPicker.vue` supprimé.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: La barre d'outils insère un tableau fixe 3×3 (`insertTable`) sans aucun contrôle pour ajouter/supprimer des lignes ou colonnes ensuite.
  motif: Résolu — boutons + / − Ligne et Colonne dans la barre d'outils (spec-3-6).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: Aucun test automatisé n'observe quelle branche du template `Show.vue` s'affiche réellement pour un document créé (`isCreated` vrai/faux) — `CreateDocumentTest.php` ne vérifie que les props Inertia envoyées, pas le rendu. Une ré…
  motif: Résolu — `Show.spec.js` couvre le rendu d'un document créé.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToPdfAction::resolveImageSources()` et `ExportDocumentToWordAction::resolveImageSources()` sont deux implémentations quasi-jumelles (même scan regex des `<img src>`, substitution différente : data URI vs. chemin…
  motif: Obsolète — export Word retiré (c7ed7e3).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: Quand une image référencée dans `content_html` est absente du disque, l'export Word la retire silencieusement et affiche quand même le toast de succès standard ("Export Word généré.") — l'utilisateur n'a aucun signal que le `.d…
  motif: Obsolète — export Word retiré (c7ed7e3).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: Un `content_html` très volumineux (beaucoup d'images/de contenu) pourrait épuiser la mémoire ou le temps d'exécution PHP pendant `Html::addHtml()`/`writer->save()`, provoquant une erreur fatale 500 au lieu du `422` explicite pr…
  motif: Obsolète — export Word retiré (c7ed7e3).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToWordTest.php` appelle `createDocumentForExport()`/`uploadDraftImageForExport()` sans les définir — ces fonctions globales ne sont déclarées que dans `ExportDocumentToPdfTest.php` (spec-2-4) ; fonctionne aujourd…
  motif: Obsolète — export Word retiré (c7ed7e3), `ExportDocumentToWordTest.php` n'existe plus.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: RÉSOLU (2026-09-09) — Le piège de focus (`trapFocus()`) d'`ImportModal.vue` (et de manière identique celui de `Show.vue`, son modèle de référence) n'excluait que les `<button>` désactivés — pas les `<select>`/`<input>` désactiv…
  motif: Résolu — déjà marqué RÉSOLU (2026-09-09).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-1-tag-documents.md`
  summary: Pas de protection anti-doublon insensible à la casse pour `Tag` (équivalent au `LOWER(name)` de l'ancien `CreateCategoryAction`) — deux tags de casse différente (ex. "Facture" / "facture") pourraient un jour coexister.
  motif: Résolu — validation insensible à la casse livrée par la story 3.5.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-1-tag-documents.md`
  summary: `sameTagIds()`, la comparaison order-insensitive utilisée par le dirty-check d'`Editor.vue` (`isDirty`), n'a aucun test unitaire dédié — rien ne garantit qu'une régression future (ex. inverser le sens de la comparaison) serait…
  motif: Obsolète — `sameTagIds()` n'existe plus.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-2-visual-identity-sidebar.md`
  summary: `resources/js/Components/ExtractionTasksPanel.vue` tronque le titre de tâche (`<span class="truncate">{{ task.title }}</span>`) sans `min-w-0` sur ce span flex — un titre long ne tronque jamais réellement et peut déborder de la…
  motif: Résolu — le titre est en `block truncate`.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-2-visual-identity-sidebar.md`
  summary: Dans la barre d'outils de l'Éditeur (`Editor.vue`), l'état "actif" d'un bouton de mise en forme (`editor?.isActive(...)`) utilise la même classe de fond (`bg-surface`) que l'état `:hover`, rendant un bouton actif indiscernable…
  motif: Résolu — état actif en `bg-primary`, distinct du survol.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `extraction_status` d'une pièce jointe (`pending`/`processing`/`completed`/`failed`) est chargé côté client (`Editor.vue`, `Show.vue`) mais n'est affiché nulle part — un échec d'extraction reste invisible, contrairement au trai…
  motif: Résolu — statut d'extraction des pièces jointes suivi dans le panneau d'extraction (dad0753).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucun test ne monte le vrai composant `ImportModal.vue` — `Index.spec.js` le stub entièrement (`ImportModal: true`) — alors que sa logique de validation/glisser-déposer a été extraite vers `useFileDropZone` par cette story ; un…
  motif: Obsolète — `ImportModal.vue` supprimé ; `Import.spec.js` couvre la page d'import.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `DocumentAttachmentController` (store/destroy/preview/download) n'impose aucune restriction aux documents `source=created` — un document importé pourrait recevoir/perdre des pièces jointes via une requête directe à ces routes,…
  motif: Obsolète — l'import accepte désormais des pièces jointes, la restriction à `source=created` n'a plus de sens.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucune limite n'existe sur le nombre de pièces jointes par document ; `Document::syncAttachmentsExtractedText()` concatène sans borne le texte extrait de toutes les pièces jointes dans `attachments_extracted_text`, ensuite scan…
  motif: Résolu — limite de 10 pièces jointes par document (f6456b3).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-4-search-surface.md`
  summary: `Sidebar.vue` calcule `isLibraryActive` comme `!isSearchActive` (binaire) plutôt qu'une vérification explicite par surface — fonctionne tant qu'il n'existe que deux items de nav, mais un 3ᵉ item (Configuration, Story 3.5) ferai…
  motif: Résolu — vérification explicite par surface (action item epic 3, done).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: Le comportement exact de `deleteTable()` lorsqu'il est invoqué depuis l'intérieur d'un tableau imbriqué préexistant (créé avant ce correctif) n'est ni spécifié ni testé — seul le chargement sans erreur de ce contenu legacy est…
  motif: Sans objet — données de test uniquement, purge prévue avec P1 : aucun tableau imbriqué legacy ne subsistera.

- source_spec: `_bmad-output/implementation-artifacts/spec-import-document-page.md`
  summary: Aucun test au niveau composant Vue ne couvre `resources/js/Pages/Documents/Import.vue` (glisser-déposer, validation format/taille, message d'erreur, désactivation pendant `form.processing`) — même absence de coverage que l'anci…
  motif: Résolu — `Import.spec.js` existe.

- source_spec: `_bmad-output/implementation-artifacts/spec-import-document-page.md`
  summary: Sur `Documents/Import.vue`, seul le bouton "Parcourir" est désactivé pendant `form.processing` — la zone de glisser-déposer et `TagSelector` restent interactifs, permettant de déposer un second fichier ou de modifier les tags p…
  motif: Résolu — la zone de dépôt n'est plus rendue une fois un fichier choisi, `TagSelector` est `:disabled` pendant l'envoi.

- source_spec: none
  summary: Refonte de l'import en formulaire unique — fichier retirable/remplaçable, tags et pièces jointes dès la création, bouton « Annuler », traitement (création du document + extraction de texte) lancé uniquement au clic sur « Enregi…
  motif: Résolu — livré (72e175f, spec-refonte-import-formulaire-unique).

- source_spec: `_bmad-output/implementation-artifacts/spec-ajustements-consultation-editeur.md`
  summary: `AttachmentsPanel.vue` émet toujours `before-request`/`after-request` et ses commentaires (L.16, L.57-67) décrivent encore la garde de navigation de l'éditeur, alors qu'`Editor.vue` n'écoute plus ces événements depuis la suppre…
  motif: Résolu — plus aucune émission `before-request`/`after-request`.

- source_spec: none
  summary: Limiter à 10 le nombre de pièces jointes par document (brouillons + pièces jointes déjà rattachées, sur les trois points d'entrée : import, éditeur, document existant) et ajouter le pré-contrôle client des 20 Mo sur les pièces…
  motif: Résolu — livré (f6456b3, spec-limite-pieces-jointes), pré-contrôle client 20 Mo en place.
