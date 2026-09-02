---
title: 'Story 1.5 - Classer un document par catégorie'
type: 'feature'
created: '2026-09-01'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: 'e10b8f21efefa164aa1fd658aab74ddbc0cf1cb6'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les documents n'ont aucun moyen d'être classés — la bibliothèque affiche "Non classé" en dur, et rien ne permet d'assigner ou de créer une catégorie.

**Approach:** Table `categories` plate (id, name) + `documents.category_id` nullable. Un composant sélecteur réutilisable (choisir / créer / vider) monté à l'identique dans la modale d'import et la Fiche document, via `CategorizeDocumentAction` comme unique point d'écriture de `category_id`.

## Boundaries & Constraints

**Always:** `CategorizeDocumentAction` est le seul endroit qui écrit `category_id` (jamais un `Document::update()` direct ailleurs, y compris à l'import). Catégorie toujours optionnelle, jamais bloquante ; vide = "Non classé". Libellés lisibles sans jargon interne (UX-DR26). Sélecteur = un seul composant Vue réutilisé identiquement dans les deux contextes.

**Ask First:** _Aucun — comportement entièrement déterminé par AD-5/AD-16 et l'Epic 1 Context._

**Never:** Pas de hiérarchie/dossiers imbriqués (AD-5). Pas de suppression/renommage de catégorie dans cette story. Pas d'API JSON (Inertia uniquement).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Import sans catégorie | pas de `category_id` | `category_id = null`, "Non classé" | N/A |
| Import avec catégorie existante | `category_id` valide à l'import | Document créé puis assigné via l'Action | id invalide → erreur validation |
| Réassignation | `PATCH .../category` | `category_id` mis à jour | id invalide → erreur validation |
| Retour à "Non classé" | `category_id = null` | mis à `null` | N/A |
| Création catégorie | `POST /categories` `name` | dispo immédiatement dans le sélecteur | nom dupliqué (insensible casse) → erreur, pas de doublon |

</frozen-after-approval>

## Code Map

- `database/migrations/2026_09_01_115918_add_extraction_status_to_documents_table.php` -- patron ALTER à répliquer (`Schema::table`, `after()`, `down()` symétrique).
- `app/Models/Document.php:14-21` -- `$fillable` + relation `category(): BelongsTo`.
- `app/Actions/ImportDocumentAction.php` -- ne touche jamais `category_id`, reste inchangé.
- `app/DataTransferObjects/ImportDocumentData.php` -- patron readonly DTO.
- `app/Http/Requests/ImportDocumentRequest.php:21-29` -- ajouter règle `category_id`.
- `app/Http/Controllers/DocumentController.php:35-65` (`index`/`store`/`show`) -- props `category_id`/`category`.
- `resources/js/Pages/Documents/Index.vue:69` -- `"Non classé"` en dur à remplacer.
- `resources/js/Pages/Documents/Show.vue:134-145` -- `<dl>` existant, y ajouter "Catégorie".
- `resources/js/Components/ImportModal.vue:18-20,60-66` -- `useForm({file:null})` + `forceFormData` à étendre.
- `resources/js/Components/DocumentTypeBadge.vue` -- modèle de style Tailwind.
- `resources/js/Components/ExtractionTasksPanel.vue` + `app/Http/Middleware/HandleInertiaRequests.php` -- patron de prop partagée globale (`pendingExtractions`) à répliquer pour `categories`.
- `tests/Feature/{Import,Preview,BrowseLibrary}*Test.php` -- style Pest existant à suivre.

## Tasks & Acceptance

**Execution:**
- [x] Migrations `create_categories_table` + `add_category_id_to_documents_table` (FK nullable, `nullOnDelete`, AD-5 plat).
- [x] `app/Models/Category.php` (+ `documents(): HasMany`), `Document::category()`.
- [x] `CategorizeDocumentData`/`CategorizeDocumentAction` -- unique point d'écriture (AD-16).
- [x] `CreateCategoryData`/`CreateCategoryAction` -- unicité du nom insensible à la casse.
- [x] `app/Http/Controllers/CategoryController.php` (`store`) et `DocumentController::updateCategory()` -- redirections Inertia, jamais de JSON.
- [x] Étendre `DocumentController::store/index/show` pour `category_id`/`category`.
- [x] `HandleInertiaRequests` -- prop partagée `categories`.
- [x] `routes/web.php` -- `POST /categories`, `PATCH /documents/{document}/category`.
- [x] `resources/js/Components/CategoryPicker.vue` -- nouveau composant (choisir/créer/vider).
- [x] Intégrer dans `ImportModal.vue`, `Show.vue`, `Index.vue`.
- [x] `tests/Feature/CategorizeDocumentTest.php` -- couvre la matrice I/O.

**Acceptance Criteria:**
- Given aucune catégorie n'existe, when j'ouvre le sélecteur, then je peux en créer une depuis la modale d'import ou la Fiche document, identiquement.
- Given un document catégorisé, when je choisis "Non classé", then `category_id` redevient `null` sans bloquer aucune autre action.
- Given `CategorizeDocumentAction`, then c'est la seule voie du code qui écrit `category_id`.

## Design Notes

`CategoryPicker.vue` lit `usePage().props.categories` (prop partagée, patron `ExtractionTasksPanel`). Création inline : `router.post('/categories', data, { preserveScroll: true, preserveState: true })` -- ni la modale d'import ni la Fiche document ne sont démontées, seule la prop `categories` se met à jour ; présélectionner par nom (connu du formulaire) plutôt que dépendre d'un id retourné hors bande.

## Verification

**Commands:**
- `herd php artisan test --filter=CategorizeDocumentTest` -- expected: verts.
- `herd php artisan test` -- expected: suite complète verte, aucune régression 1.1-1.4.

**Manual checks (if no CLI):**
- Créer une catégorie à la volée depuis la modale d'import, vérifier qu'elle apparaît sur la carte bibliothèque et la Fiche document.

## Suggested Review Order

**Schéma & modèle**

- Point d'entrée : table plate `(id, name)`, sans hiérarchie (AD-5).
  [`create_categories_table.php:18`](../../database/migrations/2026_09_01_120000_create_categories_table.php#L18)

- FK nullable, `nullOnDelete` — un document reste toujours consultable même sans catégorie.
  [`add_category_id_to_documents_table.php:19`](../../database/migrations/2026_09_01_120100_add_category_id_to_documents_table.php#L19)

- `category_id` volontairement absent de `$fillable` — seule `CategorizeDocumentAction` peut l'écrire.
  [`Document.php:38`](../../app/Models/Document.php#L38)

**Point d'écriture unique (AD-16)**

- `forceFill()` contourne `$fillable` — seul chemin de code qui touche `category_id`.
  [`CategorizeDocumentAction.php:24`](../../app/Actions/CategorizeDocumentAction.php#L24)

- Vérification d'unicité insensible à la casse + repli sur la contrainte SQL en cas de course concurrente (patch review).
  [`CreateCategoryAction.php:22`](../../app/Actions/CreateCategoryAction.php#L22)

**Concurrence & validation**

- Import + assignation dans une seule transaction — évite un document orphelin si l'assignation échoue après l'import (patch review).
  [`DocumentController.php:62`](../../app/Http/Controllers/DocumentController.php#L62)

- `Rule::exists` + normalisation `''` → `null` (Inertia sérialise un vide en chaîne vide).
  [`CategorizeDocumentRequest.php:23`](../../app/Http/Requests/CategorizeDocumentRequest.php#L23)

- Même normalisation côté import, catégorie optionnelle dès la modale.
  [`ImportDocumentRequest.php:26`](../../app/Http/Requests/ImportDocumentRequest.php#L26)

**Contrôleurs & routes**

- Seule route qui réassigne/vide `category_id`, toujours via l'Action.
  [`DocumentController.php:106`](../../app/Http/Controllers/DocumentController.php#L106)

- Création de catégorie : redirection Inertia, jamais de JSON.
  [`CategoryController.php:21`](../../app/Http/Controllers/CategoryController.php#L21)

- Prop partagée globalement (patron `pendingExtractions`) — dispo dans la modale d'import et la Fiche document sans prop dédiée par page.
  [`HandleInertiaRequests.php:53`](../../app/Http/Middleware/HandleInertiaRequests.php#L53)

**UI cliente**

- Sélecteur réutilisable : choisir / créer / vider ; garde anti double-soumission (patch review).
  [`CategoryPicker.vue:62`](../../resources/js/Components/CategoryPicker.vue#L62)

- Création inline sans aller-retour d'id : présélection par nom sur la prop partagée rafraîchie.
  [`CategoryPicker.vue:44`](../../resources/js/Components/CategoryPicker.vue#L44)

- Retour du focus clavier après annulation/Échap (patch review, accessibilité).
  [`CategoryPicker.vue:94`](../../resources/js/Components/CategoryPicker.vue#L94)

- Mise à jour optimiste avec retour arrière explicite si le serveur rejette (patch review).
  [`Show.vue:46`](../../resources/js/Pages/Documents/Show.vue#L46)

- Erreur d'import sur `category_id` désormais visible (patch review — était silencieuse).
  [`ImportModal.vue:31`](../../resources/js/Components/ImportModal.vue#L31)

- Nom de catégorie réel remplace le "Non classé" en dur.
  [`Index.vue:69`](../../resources/js/Pages/Documents/Index.vue#L69)

**Périphériques**

- Couvre l'intégralité de la matrice I/O (import avec/sans catégorie, réassignation, retrait, création, doublon).
  [`CategorizeDocumentTest.php:1`](../../tests/Feature/CategorizeDocumentTest.php#L1)

- Espace de noms uniques élargi pour éviter l'épuisement du pool de mots (patch review).
  [`CategoryFactory.php:22`](../../database/factories/CategoryFactory.php#L22)
