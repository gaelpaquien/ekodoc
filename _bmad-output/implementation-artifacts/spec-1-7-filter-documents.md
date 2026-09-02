---
title: 'Story 1.7 - Filtrer les documents par catégorie et par type'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: '7b5de8bd56682161b255918bc96828f8f49d7d0d'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sans filtre, affiner la bibliothèque exige de taper un mot-clé précis (Story 1.6) — impossible de simplement isoler "tous les PDF" ou "tous les documents d'une catégorie".

**Approach:** Filtres multi-sélection catégorie et type (PDF/Word/Excel/Créé), combinés (ET entre les deux groupes, OU à l'intérieur de chaque groupe) et appliqués dans `DocumentController::index()`, le même point d'entrée que la recherche (AD-8) — jamais un second chemin de requête.

## Boundaries & Constraints

**Always:** `index()` reste l'unique méthode/requête, recherche et filtres confondus — la logique d'application des filtres est factorisée une seule fois et réutilisée par la branche recherche vide et la branche Scout. Filtres multi-sélection (catégorie et type). Filtres reflétés dans l'URL (`category_id[]=`, `type[]=`), comme `?search=` (Story 1.6). Filtres actifs visibles et retirables en un clic.

**Ask First:** _Aucun._

**Never:** Pas de pagination (hors scope épique). Pas de nouvelle catégorie ni de modification de `CategorizeDocumentAction`. Pas de cinquième type au-delà de PDF/Word/Excel/Créé.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Filtre catégorie seul | `?category_id[]=2` | Documents de cette catégorie uniquement | N/A |
| Filtre type seul | `?type[]=pdf` | Documents PDF uniquement | N/A |
| Filtres catégorie + type combinés | `?category_id[]=2&type[]=pdf` | ET entre groupes : PDF **et** dans cette catégorie | N/A |
| Type "Créé" sélectionné | `?type[]=created` | Documents `source=created` uniquement | N/A |
| Filtres + recherche combinés | `?search=facture&type[]=pdf` | Résultat recherche restreint aux PDF, même point d'entrée | N/A |
| Aucun document ne correspond | filtre(s) actif(s) sans correspondance | "Aucun document ne correspond..." + suggestion de retirer les filtres | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php:47-67` (`index()`) -- lit `category_id[]`/`type[]` en plus de `search` ; méthode privée partagée par les deux branches (vide vs Scout) -- AD-8.
- `app/Enums/DocumentSource.php` -- `Created`/`Imported` existants, réutilisés pour le type "Créé".
- `resources/js/Components/DocumentTypeBadge.vue:17-21` -- `MIME_TYPE_LABELS` existant (PDF/Word/Excel), patron pour la correspondance type→mime côté UI.
- `resources/js/Pages/Documents/Index.vue:8-62` -- props/`watch`/`router.get` existants (recherche) à étendre : `categoryFilters`/`typeFilters` dans la même navigation, chips retirables.
- `resources/js/Components/CategoryPicker.vue` -- **non réutilisable tel quel** (single-select d'assignation) ; sert de référence pour `page.props.categories` (prop déjà exposée via `HandleInertiaRequests`).
- `tests/Feature/SearchDocumentsTest.php` -- patron Pest pour `FilterDocumentsTest.php`.

## Tasks & Acceptance

**Execution:**
- [x] `app/Http/Controllers/DocumentController.php` -- lire `category_id[]` (int[]) et `type[]` (`pdf`/`word`/`excel`/`created`), méthode privée `applyFilters()` appelée par les deux branches, exposer `categoryFilters`/`typeFilters` en props Inertia -- point d'entrée unique (AD-8).
- [x] `resources/js/Pages/Documents/Index.vue` -- checkboxes catégorie (depuis `page.props.categories`) + type (4 valeurs fixes), chips de filtres actifs avec retrait en un clic, filtres inclus dans le `router.get` existant.
- [x] `tests/Feature/FilterDocumentsTest.php` -- couvre la matrice I/O, patron `SearchDocumentsTest.php`.

**Acceptance Criteria:**
- Given la Bibliothèque affichée, when je sélectionne une ou plusieurs catégories et/ou types, then la liste se filtre en combinant les critères (multi-sélection).
- Given des filtres actifs, then ils sont visibles et retirables en un clic.
- Given `DocumentController::index()`, then c'est la seule méthode qui construit la requête documents, recherche et/ou filtres actifs ou non.

## Design Notes

`applyFilters(Builder $query, array $categoryIds, array $types): Builder` : `whereIn('category_id', $categoryIds)` si non vide ; pour les types, un seul `where(fn ($q) => ...)` avec `orWhereIn('mime_type', [...])` pour les mimes reconnus et `orWhere('source', DocumentSource::Created)` si `created` est sélectionné -- ET entre catégorie et type, OU dans chaque groupe. Appelée identiquement par `Document::query()` (recherche vide) et par le callback `Document::search(...)->query(fn ($q) => ...)` (Scout) -- aucune divergence.

Correspondance type→mime, colocalisée : `pdf` => `application/pdf`, `word` => mime docx, `excel` => mime xlsx, `created` => teste `source`, pas de mime.

Filtres dans la même charge `router.get('/', {...}, {preserveState:true, replace:true, only:[...]})` déjà posée par Story 1.6 -- pas de debounce (sélection discrète).

## Verification

**Commands:**
- `herd php artisan test --filter=FilterDocumentsTest` -- expected: verts.
- `herd php artisan test` -- expected: suite complète verte, aucune régression 1.1-1.6.

**Manual checks (if no CLI):**
- Depuis la bibliothèque, cocher une catégorie puis un type : la liste se restreint aux deux à la fois. Retirer un filtre via sa chip : la liste se met à jour immédiatement. Combiner un filtre type avec un terme de recherche : les deux s'appliquent ensemble.

## Suggested Review Order

**Point d'entrée unique de requête (AD-8)**

- Bascule entre recherche vide et Scout, filtres appliqués identiquement aux deux via `applyFilters()`.
  [`DocumentController.php:70`](../../app/Http/Controllers/DocumentController.php#L70)

- ET entre catégorie et type, OU à l'intérieur de chaque groupe, sans divergence entre les deux branches.
  [`DocumentController.php:103`](../../app/Http/Controllers/DocumentController.php#L103)

- Parsing tolérant de `category_id[]` : `FILTER_VALIDATE_INT` rejette une valeur comme `"2.5"` plutôt que de la tronquer silencieusement (patch review).
  [`DocumentController.php:137`](../../app/Http/Controllers/DocumentController.php#L137)

- `type[]` restreint aux quatre valeurs reconnues, le reste est silencieusement écarté.
  [`DocumentController.php:158`](../../app/Http/Controllers/DocumentController.php#L158)

**Filtres en direct côté client**

- Un seul `navigate()` partagé par la recherche (debounce) et les filtres (immédiat), annule le debounce en attente pour éviter une requête dupliquée.
  [`Index.vue:94`](../../resources/js/Pages/Documents/Index.vue#L94)

- Deux drapeaux de synchro indépendants (`isSyncingFiltersFromProps`) pour distinguer une sélection utilisateur d'une resynchro depuis les props serveur (retour arrière navigateur).
  [`Index.vue:136`](../../resources/js/Pages/Documents/Index.vue#L136)

- Bascule catégorie/type et retrait individuel via chip, sur le modèle du `toggleType`/`removeTypeFilter` déjà posé.
  [`Index.vue:170`](../../resources/js/Pages/Documents/Index.vue#L170)

- Libellé du bouton de retrait conditionnel selon qu'un terme de recherche est aussi actif, pour que le texte corresponde à l'action réelle (patch review).
  [`Index.vue:372`](../../resources/js/Pages/Documents/Index.vue#L372)

**Périphériques**

- Couvre la matrice I/O (catégorie seule, type seul, combinés, "Créé", combiné à la recherche, sans correspondance) plus l'OR multi-valeurs, le tri, et la tolérance aux valeurs malformées (patch review : test OR multi-types corrigé, OR multi-catégories et tri ajoutés).
  [`FilterDocumentsTest.php:1`](../../tests/Feature/FilterDocumentsTest.php#L1)
