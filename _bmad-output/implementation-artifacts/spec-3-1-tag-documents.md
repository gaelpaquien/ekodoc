---
title: 'Classer et retrouver ses documents par tags illimités'
type: 'feature'
created: '2026-09-09'
status: 'done'
review_loop_iteration: 1
context: []
baseline_commit: '92e4e9990baf81f167440da8ce2f0f638cfd45f7'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Le classement par catégorie unique (Epic 1, story 1.5) est trop rigide et devient obsolète (sprint-change-proposal 2026-09-09, AD-5 amendée) : un document ne peut porter qu'une seule catégorie, alors que le besoin réel est de le retrouver sous plusieurs angles à la fois.

**Approche :** Retirer entièrement le mécanisme de catégorie (DB + code + UI), sans migration des données existantes, et le remplacer par un système de tags illimités (table `tags` + pivot `document_tag`), assignables depuis l'import, la sauvegarde éditeur et la fiche document via un composant `TagSelector.vue` unique et partagé, avec filtrage combiné tag + type sur la Bibliothèque.

## Boundaries & Constraints

**Always :** table `tags` à plat (id, name), sans hiérarchie ; relation many-to-many via pivot `document_tag`, sans limite de tags par document ; `SyncDocumentTagsAction` est la seule Action qui écrit ce pivot, toujours via `sync()` complet (jamais `attach()`/`detach()` incrémental), appelée depuis import, sauvegarde éditeur (création + modification) et fiche document ; `TagSelector.vue` n'offre que des tags déjà existants (aucune saisie libre, aucune création à la volée) ; laisser le champ vide n'est jamais bloquant ; le filtre tag et le filtre type de la Bibliothèque passent par le même point d'entrée de requête (`DocumentController::applyFilters`), jamais une branche `whereHas` séparée.

**Ask First :** aucune décision bloquante identifiée — si un point d'ambiguïté apparaît en cours d'implémentation (ex. faut-il indexer les tags dans Scout), HALT et demander avant de trancher.

**Never :** pas de migration des catégories existantes vers des tags équivalents ; pas de création/renommage/suppression de tags dans cette story (page Configuration = story 3.5, hors scope ici — pour rendre `TagSelector.vue` testable en attendant, alimenter la table `tags` via `TagFactory`/tinker) ; les tags ne rentrent pas dans l'index fulltext Scout (`toSearchableArray()` inchangé) — le tag reste un filtre, jamais un critère de recherche plein texte.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Assignation multiple | Import ou sauvegarde éditeur avec 3 tags sélectionnés | `document_tag` contient les 3 lignes, chips affichées sur la fiche/ligne | N/A |
| Aucun tag | Import/sauvegarde sans tag sélectionné | Document enregistré normalement, zéro chip affichée | N/A |
| Remplacement complet | Modification des tags d'un document déjà tagué (retrait de 2, ajout de 1) | `sync()` remplace intégralement le pivot — plus aucune ancienne ligne orpheline | N/A |
| Filtre combiné | Sélection d'un tag + d'un type de fichier sur la Bibliothèque | Résultats à l'intersection des deux filtres, chips de filtre actives retirables | N/A |
| Filtre sans résultat | Combinaison tag/type ne correspondant à aucun document | "Aucun document ne correspond à ces filtres." affiché | N/A |
| Suppression catégorie | Ancien document avec `category_id` renseigné (avant migration) | Après migration, colonne absente — aucune erreur, aucune référence résiduelle dans le code | N/A |

</frozen-after-approval>

## Code Map

- `database/migrations/2026_09_01_120000_create_categories_table.php`, `..._120100_add_category_id_to_documents_table.php` -- à annuler via nouvelle migration `DROP`, jamais éditées rétroactivement
- `database/migrations/2026_09_02_090000_add_content_html_to_documents_table.php` -- migration la plus récente ; les nouvelles migrations tags doivent être numérotées après (ex. `2026_09_09_100000_...`)
- `app/Models/Category.php`, `app/Http/Controllers/CategoryController.php`, `app/Actions/CreateCategoryAction.php`, `app/Actions/CategorizeDocumentAction.php`, `app/DataTransferObjects/{CategorizeDocumentData,CreateCategoryData}.php`, `app/Http/Requests/{CategorizeDocumentRequest,CreateCategoryRequest}.php`, `database/factories/CategoryFactory.php` -- à supprimer intégralement
- `resources/js/Components/CategoryPicker.vue` + `resources/js/Components/__tests__/CategoryPicker.spec.js` -- à supprimer, remplacés par `TagSelector.vue`
- `app/Actions/CreateCategoryAction.php` -- patron de convention Action à suivre : classe invocable, un seul DTO en paramètre, méthodes privées d'aide, `ValidationException` pour les erreurs de domaine
- `app/Models/Document.php:41-44` (`category(): BelongsTo`) -- remplacer par `tags(): BelongsToMany` ; `toSearchableArray()` (L52-57) reste inchangée (tags hors fulltext)
- `app/Http/Controllers/DocumentController.php` -- L56-99 `index()`, L110-132 `applyFilters(Builder, categoryIds, types)` (point d'entrée unique à étendre avec `tagIds`), L144-158 `categoryIdsFromQuery()` (patron pour `tagIdsFromQuery()`), L180-210 `store()`, L231-253 `storeCreated()`, L310-327 `show()`, L337-349 `edit()`, L361-388 `update()`/`updateCategory()` -- toute la logique catégorie de ces méthodes à retirer/remplacer par l'équivalent tags
- `app/Actions/UpdateDocumentAction.php:42-71` -- injection `CategorizeDocumentAction` à remplacer par `SyncDocumentTagsAction`
- `app/Http/Middleware/HandleInertiaRequests.php:6,50-56` -- share Inertia `categories` global à remplacer par share `tags` (liste complète pour `TagSelector.vue`)
- `routes/web.php:3,46,49` -- routes catégorie (`PATCH .../category`, `POST /categories`) à retirer ; ajouter `PATCH /documents/{document}/tags`
- `resources/js/Pages/Documents/Index.vue` -- L17,28,44,75-78,108-109,119,138,154,162,168,172-193,297-313,343-350,425 -- tout l'état/UI catégorie (filtre, chip, badge L425) à remplacer par filtre tag + `TagChip` (style visuellement distinct de la chip de filtre)
- `resources/js/Pages/Documents/Show.vue` -- L5,20-55,443-446 -- `CategoryPicker` + état à remplacer par `TagSelector`, PATCH vers `/documents/{document}/tags`
- `resources/js/Components/ImportModal.vue` -- L4,21,31,210 -- champ `category_id` à remplacer par `tag_ids[]` via `TagSelector`
- `resources/js/Pages/Documents/Editor.vue` -- L12,38,44-49,57-86,417-420,582-585 -- `CategoryPicker`/`category_id` à remplacer par `TagSelector`/`tag_ids[]` (création et modification)
- `resources/js/Components/DocumentTypeBadge.vue` -- patron de badge/chip à suivre pour un nouveau `TagChip.vue` (lecture seule)
- `tests/Feature/CategorizeDocumentTest.php` -- à supprimer, remplacé par `tests/Feature/SyncDocumentTagsTest.php` (même style route-driven, sans mock)
- `tests/Feature/{CreateDocumentTest,FilterDocumentsTest,UpdateDocumentTest}.php` -- références catégorie à remplacer par tags

## Tasks & Acceptance

**Execution :**
- [x] `database/migrations/2026_09_09_100000_create_tags_table.php` -- créer table `tags` (id, name unique) -- fondation du modèle de données
- [x] `database/migrations/2026_09_09_100100_create_document_tag_table.php` -- créer pivot `document_tag` (document_id, tag_id, `cascadeOnDelete()` des deux côtés, contrainte unique de la paire) -- relation many-to-many sans limite
- [x] `database/migrations/2026_09_09_100200_drop_categories_and_category_id.php` -- `DROP COLUMN documents.category_id`, `DROP TABLE categories`, sans migration de données -- retrait explicite (AD-5)
- [x] `app/Models/Tag.php` -- modèle flat, `documents(): BelongsToMany` -- symétrique à `Document::tags()`
- [x] `app/Models/Document.php` -- retirer `category()`, ajouter `tags(): BelongsToMany` -- alignement avec le nouveau modèle
- [x] `app/DataTransferObjects/SyncDocumentTagsData.php` -- DTO (document, tagIds) -- entrée unique de l'Action
- [x] `app/Actions/SyncDocumentTagsAction.php` -- `__invoke(SyncDocumentTagsData): Document`, appelle `sync($tagIds)` -- seul point d'écriture du pivot (AD-5)
- [x] `database/factories/TagFactory.php` -- factory de test -- alimente `TagSelector` en attendant la story 3.5
- [x] Suppression : `app/Models/Category.php`, `CategoryController.php`, `CreateCategoryAction.php`, `CategorizeDocumentAction.php`, DTOs/Requests catégorie, `CategoryFactory.php`, `CategoryPicker.vue`, `CategoryPicker.spec.js`, `CategorizeDocumentTest.php` -- retrait complet du mécanisme remplacé
- [x] `app/Http/Controllers/DocumentController.php` -- retirer toute logique catégorie, ajouter `tagIdsFromQuery()` + paramètre `tagIds` dans `applyFilters()`, charger/partager `tags` sur `index/show/edit`, gérer `PATCH /documents/{document}/tags` -- point d'entrée de requête unique étendu
- [x] `app/Actions/UpdateDocumentAction.php` -- remplacer l'injection `CategorizeDocumentAction` par `SyncDocumentTagsAction`
- [x] `app/Http/Middleware/HandleInertiaRequests.php` -- remplacer le share `categories` par un share `tags`
- [x] `routes/web.php` -- retirer routes catégorie, ajouter `PATCH /documents/{document}/tags`
- [x] `resources/js/Components/TagSelector.vue` -- champ filtrant en direct sur la liste de tags partagée, chip au clic/Entrée, aucune saisie libre -- composant unique partagé (UX-DR11)
- [x] `resources/js/Components/TagChip.vue` -- chip lecture seule, style distinct de la chip de filtre -- affichage sur ligne/fiche (UX-DR12)
- [x] `resources/js/Pages/Documents/Index.vue` -- retirer filtre/badge catégorie, ajouter filtre tag combiné au filtre type + chips actives retirables + message vide -- FR7
- [x] `resources/js/Pages/Documents/Show.vue` -- remplacer `CategoryPicker` par `TagSelector`, PATCH tags
- [x] `resources/js/Components/ImportModal.vue` -- remplacer champ catégorie par `TagSelector` (`tag_ids[]`)
- [x] `resources/js/Pages/Documents/Editor.vue` -- remplacer `CategoryPicker` par `TagSelector` en création et modification
- [x] `tests/Feature/SyncDocumentTagsTest.php` -- couvrir la matrice I/O (assignation, vide, remplacement complet) -- non-régression sur `sync()`
- [x] `tests/Feature/FilterDocumentsTest.php` -- remplacer les cas catégorie par des cas tag + type combinés, y compris le cas sans résultat
- [x] `tests/Feature/{CreateDocumentTest,UpdateDocumentTest}.php` -- mettre à jour les références catégorie vers tags
- [x] `resources/js/Components/__tests__/TagSelector.spec.js` -- filtrage live, ajout/retrait de chip, aucune saisie libre acceptée

**Acceptance Criteria :**
- Given la table `categories` et `documents.category_id`, when cette story est livrée, then elles n'existent plus en base et plus aucune référence catégorie ne subsiste dans le code (Actions, modèles, contrôleurs, composants, tests)
- Given un document sans tag, when il est affiché sur la Bibliothèque ou la fiche, then aucune chip n'apparaît et rien n'empêche son enregistrement/import
- Given la Bibliothèque avec des filtres tag et type actifs simultanément, when la liste se rafraîchit, then elle combine les deux critères via `applyFilters()` sans branche de requête séparée
- Given le `TagSelector`, when il est utilisé au clavier, then il reste intégralement navigable avec focus visible (UX-DR37)

### Review Findings

- [x] [Review][Decision] `TagChip.vue` jamais monté sur la Fiche document (`Show.vue`) malgré le Code Map ("ligne/fiche", UX-DR12) et le commentaire du composant lui-même — `Show.vue` n'affiche les tags que via `TagSelector` (éditable), jamais via `TagChip` (lecture seule). [resources/js/Pages/Documents/Show.vue:443, resources/js/Components/TagChip.vue:5-6] — Décision (Gaël, 2026-09-10) : implémentation actuelle acceptée telle quelle, aucun changement requis.
- [x] [Review][Decision] `TagSelector.vue` réutilisé comme filtre tag de la Bibliothèque — un 4ᵉ contexte de montage non documenté dans `epic-3-context.md` (qui n'en liste que 3 : Import modal, Editor, Document Detail) — extension de portée assumée dans le code (commentaire `HandleInertiaRequests.php`) mais jamais actée par un "Ask First" explicite avec l'humain. [resources/js/Pages/Documents/Index.vue:290, app/Http/Middleware/HandleInertiaRequests.php:50-55] — Décision (Gaël, 2026-09-10) : réutilisation acceptée telle quelle, aucun changement requis.
- [x] [Review][Patch] Pas d'index secondaire sur `document_tag.tag_id` pour la requête `whereHas('tags', ...)` d'`applyFilters()` — seul l'index composite unique `(document_id, tag_id)` existe, qui ne couvre pas efficacement une recherche par `tag_id` seul. [database/migrations/2026_09_09_100100_create_document_tag_table.php:22-27] — Corrigé : nouvelle migration `2026_09_10_120000_add_tag_id_index_to_document_tag_table.php`.
- [x] [Review][Patch] Aucun test ne combine le filtre tag avec un terme de recherche actif (seuls tag+type et type+search sont couverts). [tests/Feature/FilterDocumentsTest.php] — Corrigé : test `combines a tag filter with an active search term...` ajouté.
- [x] [Review][Patch] `Document::tags()` n'a pas d'ordre défini (pas de `orderBy`), contrairement à la liste globale partagée (`orderBy('name')` dans `HandleInertiaRequests`) — ordre des chips potentiellement incohérent entre rechargements. [app/Models/Document.php:41-44] — Corrigé : `->orderBy('tags.name')` ajouté à la relation.
- [x] [Review][Patch] La règle de validation `tag_ids`/`tag_ids.*` est dupliquée à l'identique dans 4 `Requests` sans règle `distinct`. [app/Http/Requests/CreateDocumentRequest.php:44-45, app/Http/Requests/ImportDocumentRequest.php:33-34, app/Http/Requests/UpdateDocumentRequest.php:50-51, app/Http/Requests/SyncDocumentTagsRequest.php:24-25] — Corrigé : règle `distinct` ajoutée sur `tag_ids.*` dans les 4 Requests.
- [x] [Review][Patch] `TagChip.vue` n'a aucun test unitaire dédié, contrairement à `TagSelector.vue`. [resources/js/Components/TagChip.vue] — Corrigé : `resources/js/Components/__tests__/TagChip.spec.js` ajouté.
- [x] [Review][Patch] `TagSelector.vue` : le combobox ne renseigne jamais `aria-activedescendant` sur l'input, et `aria-controls="tag-selector-listbox"` référence un élément absent du DOM quand la liste est fermée ou sans résultat. [resources/js/Components/TagSelector.vue:159-174] — Corrigé : `aria-activedescendant` lié à l'option survolée, `aria-controls` conditionné à `isOpen`, listbox toujours rendue (avec message vide interne) tant qu'elle est ouverte.
- [x] [Review][Patch] `Show.vue::onTagsChange()` — le revert en cas d'erreur restaurait `previousTagIds` (capturé localement) plutôt que `props.document.tags`. [resources/js/Pages/Documents/Show.vue:37-58] — Corrigé : revert basé sur `props.document.tags`, la source de vérité serveur.
- [x] [Review][Patch] Le filtre tag de la Bibliothèque perd le regroupement `fieldset`/`legend` contextuel et le message d'état vide distinguant "aucun tag dans le système" de "aucun tag ne correspond à la saisie". [resources/js/Pages/Documents/Index.vue:288-291] — Corrigé : `fieldset`/`legend` "Filtrer par tag" ajouté autour du filtre ; message "Aucun tag n'existe encore." ajouté dans `TagSelector.vue` lui-même (bénéficie à tous ses points de montage).
- [x] [Review][Patch] Aucun test ne vérifie que les anciennes routes catégorie (`PATCH .../category`, `POST /categories`) renvoient bien une erreur. [routes/web.php] — Corrigé : 2 tests ajoutés dans `SyncDocumentTagsTest.php` (assertNotFound).
- [x] [Review][Patch] `tag_ids` soumis en `null` explicite est bloqué par la règle `array` (pas de `nullable`/normalisation). [app/Http/Requests/UpdateDocumentRequest.php:50, app/Http/Requests/CreateDocumentRequest.php:44, app/Http/Requests/ImportDocumentRequest.php:33, app/Http/Requests/SyncDocumentTagsRequest.php:24] — Corrigé : `prepareForValidation()` normalise `null` → `[]` dans les 4 Requests.
- [x] [Review][Patch] `TagSelector::closeSuggestions()` ne réinitialise pas `query` lors d'un blur/Échap sans sélection. [resources/js/Components/TagSelector.vue:63-66] — Corrigé : `query.value = ''` ajouté dans `closeSuggestions()`.
- [x] [Review][Patch] Aucun test ne vérifie explicitement que les tags n'entrent jamais dans la recherche plein texte. [tests/Feature/SearchDocumentsTest.php:68] — Corrigé : test `never matches a document by its tag name...` ajouté.
- [x] [Review][Defer] Pas de protection anti-doublon insensible à la casse pour `Tag` (équivalent au `LOWER(name)` de l'ancien `CreateCategoryAction`) — deferred, pre-existing scope gap: aucune UI de création de tag n'existe dans cette story (arrive en 3.5), donc non actionnable maintenant. [app/Models/Tag.php]
- [x] [Review][Defer] `sameTagIds()` (dirty-check d'`Editor.vue`) n'a pas de test unitaire dédié — deferred, pre-existing scope gap: aucun `Editor.spec.js` n'existe et sa création n'est pas dans les tâches listées par ce spec. [resources/js/Pages/Documents/Editor.vue:75-84]

Dismissed as noise/handled elsewhere (5) : migration `category_id`→tags sans backfill ni rollback restaurateur (décision produit explicitement actée, AD-5/sprint-change-proposal) ; absence de création de tag depuis l'UI (hors scope assumé, Design Notes) ; "Tag(s)" non traduit ("tag" est un anglicisme d'usage courant en français technique) ; carte Bibliothèque sans indicateur "Non classé" pour un document sans tag (contredit par l'Acceptance Criteria du spec lui-même) ; `tag_ids` absent → tags vidés sur `update()`/`updateTags()` (comportement explicitement documenté et voulu — "jamais bloquant" — la règle réelle est `['array']` sans `required`, pas telle que décrite par le finding d'origine).

## Design Notes

`TagSelector.vue` n'a pas de mécanisme de création de tag dans cette story : la table `tags` est peuplée via `TagFactory`/tinker jusqu'à ce que la story 3.5 livre la page Configuration. C'est un choix d'incrément assumé, pas un oubli — le documenter dans le commit/PR pour éviter toute confusion en revue.

## Verification

**Commands :**
- `php artisan test --filter=Tag` -- expected: tous les tests Feature liés aux tags passent (Sync, Filter, Create/Update Document)
- `php artisan migrate:fresh --seed` (env local/test) -- expected: migrations tags s'appliquent proprement, `categories`/`category_id` absents, aucune erreur
- `npm run test -- TagSelector` -- expected: tests du composant Vue passent

**Manual checks (if no CLI) :**
- Vérifier visuellement que la chip de tag (lecture seule) et la chip de filtre actif sont bien distinguables sur la Bibliothèque
