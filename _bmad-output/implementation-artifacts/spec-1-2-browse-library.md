---
title: 'Parcourir la bibliothèque de documents'
type: 'feature'
created: '2026-09-01'
status: 'done'
review_loop_iteration: 0
baseline_commit: '1c3511b8d16f95c3d641b9a372e914159614b183'
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `Documents/Index.vue` n'est qu'une liste de titres (pas de badge de type, catégorie ou date), avec un état vide en texte brut sans action — ne remplit pas la Story 1.2 ni les specs UX/DESIGN (carte cliquable : badge type + titre + catégorie + date ; état vide avec CTA).

**Approach:** Remplacer la liste par une grille de cartes cliquables (badge type, titre, catégorie, date). `category_id` n'existe pas encore (Story 1.5) : chaque carte affiche un placeholder statique "Non classé".

## Boundaries & Constraints

**Always:** `DocumentController@index` reste l'unique point d'entrée de la liste (1.6/1.7 l'étendront, ne le dupliqueront pas) ; badge de type dérivé de `mime_type`, repli sur `source` pour `created` ; date en français (`Intl.DateTimeFormat('fr-FR')`, cohérent avec `Show.vue`) ; carte entière cliquable vers `documents.show` ; libellé de type centralisé dans un composant partagé réutilisé par `Index.vue` et `Show.vue`.

**Ask First:** Aucune décision bloquante anticipée.

**Never:** Pas de `category_id`/modèle `Category`, recherche, filtres ou pagination (hors périmètre — 1.5/1.6/1.7) ; pas de skeleton/spinner ; ne pas toucher à `ImportModal.vue`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Bibliothèque vide | Aucun `Document` | "Aucun document pour l'instant." + bouton "Importer un document" | N/A |
| Plusieurs documents | ≥2 `Document`, `mime_type` variés | Une carte/document, tri récent→ancien, badge/titre/"Non classé"/date, carte cliquable | N/A |
| Type reconnu | `mime_type` pdf/docx/xlsx | Badge "PDF"/"Word"/"Excel" | N/A |
| Type non reconnu | `mime_type` absent/inattendu | Badge de repli générique ("Document") | Pas d'exception Vue |
| `source=created` | `mime_type` null | Badge "Créé" | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php:15-27` -- `index()` sélectionne déjà `id, title, source, mime_type, created_at` (pas de changement de colonnes) ; docblock à mettre à jour (dit encore "out of scope").
- `resources/js/Pages/Documents/Index.vue` -- `<ul>` de titres (37-43) → grille de cartes ; état vide (33-35) → texte + CTA ; bouton "Importer"/`ImportModal` inchangés.
- `resources/js/Pages/Documents/Show.vue:13-18` -- `typeLabels` inline (source seulement) → composant de badge partagé.
- `resources/js/Components/ExtractionTasksPanel.vue` -- référence Tailwind pour carte (`rounded-lg border ... dark:bg-neutral-900`).
- `app/Models/Document.php` -- `source` cast `App\Enums\DocumentSource` (`imported`|`created`) ; pas de `category_id`.
- `database/factories/` -- seulement `UserFactory.php`, pas de `DocumentFactory` (nécessaire pour peupler plusieurs cartes en test).
- `tests/Feature/ImportDocumentTest.php:186` -- test existant sur `index`, style `assertInertia(...)->has(...)->where(...)` à suivre.
- `_bmad-output/implementation-artifacts/deferred-work.md` -- entrées "DocumentFactory manquante" et "duplication libellé DocumentSource" à retirer une fois résolues.

## Tasks & Acceptance

**Execution:**
- [x] `database/factories/DocumentFactory.php` -- créer (titre, `source=Imported`, `mime_type` variable, `extraction_status=Completed`) -- peupler plusieurs documents en test.
- [x] `resources/js/Components/DocumentTypeBadge.vue` -- composant partagé `mime_type`+`source` → libellé ("PDF"/"Word"/"Excel"/"Créé"/repli) -- source unique du libellé, utilisé carte + Fiche.
- [x] `resources/js/Pages/Documents/Index.vue` -- grille de cartes (badge, titre, "Non classé", date `fr-FR`, carte en `<Link>`) ; état vide UX-conforme.
- [x] `resources/js/Pages/Documents/Show.vue` -- remplacer `typeLabels` par `DocumentTypeBadge`.
- [x] `app/Http/Controllers/DocumentController.php` -- mettre à jour le docblock de `index()`.
- [x] `tests/Feature/BrowseLibraryTest.php` -- état vide, plusieurs documents (badge/type, "Non classé", date, tri), type non reconnu.
- [x] `_bmad-output/implementation-artifacts/deferred-work.md` -- retirer les deux entrées résolues.

**Acceptance Criteria:**
- Given au moins un document existe, when j'ouvre EkoDoc, then la Bibliothèque affiche une carte par document (badge type, titre, "Non classé", date), carte cliquable vers la Fiche.
- Given aucun document n'existe, when j'ouvre EkoDoc, then "Aucun document pour l'instant." s'affiche avec un bouton primaire "Importer un document".
- Given plusieurs documents existent, when j'ouvre la Bibliothèque, then les cartes sont triées de la plus récente à la plus ancienne.

## Spec Change Log

## Design Notes

`DocumentTypeBadge.vue` est la seule source du libellé : pdf→"PDF", docx→"Word", xlsx→"Excel", sinon `source===created`→"Créé", sinon repli "Document". `Show.vue` doit consommer ce même composant plutôt que sa propre map.

## Verification

**Commands:**
- `php artisan test --filter=BrowseLibraryTest` -- expected: tous les tests passent.
- `php artisan test` -- expected: suite complète verte (pas de régression `ImportDocumentTest`).

**Manual checks (if no CLI):**
- Ouvrir `/` avec 0 puis plusieurs documents (formats variés), vérifier cartes et état vide en clair/sombre.

## Suggested Review Order

**Libellé de type partagé**

- Source unique de vérité pour le badge : mapping mime-type, puis repli `source=created`, puis repli générique.
  [`DocumentTypeBadge.vue:23`](../../resources/js/Components/DocumentTypeBadge.vue#L23)

**Grille de cartes (Bibliothèque)**

- Liste de titres remplacée par une grille de cartes cliquables intégrant le badge partagé.
  [`Index.vue:58`](../../resources/js/Pages/Documents/Index.vue#L58)

- Chaque carte consomme le badge avec `mime_type`/`source` du document.
  [`Index.vue:64`](../../resources/js/Pages/Documents/Index.vue#L64)

**Fiche document (réutilisation)**

- `typeLabels` inline retiré au profit du composant partagé, pour cohérence avec la carte.
  [`Show.vue:41`](../../resources/js/Pages/Documents/Show.vue#L41)

**Backend**

- Docblock mis à jour : le browsing n'est plus hors périmètre (aucun changement de requête).
  [`DocumentController.php:16`](../../app/Http/Controllers/DocumentController.php#L16)

**Tests et fixtures**

- `DocumentFactory` créée pour peupler plusieurs documents réalistes en test.
  [`DocumentFactory.php:22`](../../database/factories/DocumentFactory.php#L22)

- Cinq tests couvrant les 5 lignes de la matrice I/O (vide, tri, types reconnus, type non reconnu, `created`/mime nul).
  [`BrowseLibraryTest.php:6`](../../tests/Feature/BrowseLibraryTest.php#L6)
