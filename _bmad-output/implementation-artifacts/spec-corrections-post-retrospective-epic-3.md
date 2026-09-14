---
title: 'Corrections issues de la rétrospective Epic 3'
type: 'chore'
created: '2026-09-14'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '99230c81a80dbe31ad669b2e2eef8b9b16f55d4d'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La rétrospective de l'Epic 3 (`epic-3-retro-2026-09-11.md`) a laissé 6 éléments d'action ouverts dans `sprint-status.yaml` (items 6, 7, 9, 10, 11, 12) : collision d'ids DOM entre deux instances de `TagSelector`, une garde anti-perte de modifications déclenchée à tort par `AttachmentsPanel`, des rechargements partiels qui oublient la prop `tags`, un tri des tags sensible à la casse, l'absence de test reliant mutation de tag et prop Inertia, et une logique de surbrillance de sidebar fragile par exclusion.

**Approach:** Six correctifs indépendants et ciblés, chacun isolé à son fichier, regroupés dans une seule spec "chore" (même pattern que `spec-corrections-post-retrospective.md` pour Epic 1+2) : (1) générer un id d'instance unique dans `TagSelector.vue`, sur le modèle déjà en place dans `AttachmentsPanel.vue` (`panelId`) ; (2) exposer un mécanisme (prop/emit) entre `Editor.vue` et `AttachmentsPanel.vue` pour que les requêtes immediate-mode du panneau basculent le flag `programmaticNavigation` ; (3) ajouter `'tags'` aux tableaux `only:[...]` des rechargements partiels d'`Index.vue` et `Search.vue` ; (4) trier les tags de façon insensible à la casse via `Str::lower()` (comme la dédup existante), dans `TagController::index()` et `HandleInertiaRequests::share()` ; (5) ajouter un test Pest (mutation → prop Inertia `tags`) complétant les tests Vitest déjà en place — pas de framework E2E navigateur dans ce projet ; (6) remplacer l'exclusion d'`isLibraryActive` par une liste blanche explicite des surfaces `Documents/*`.

## Boundaries & Constraints

**Always:** Chaque correctif reste isolé à son périmètre annoncé, sans refactor opportuniste. Les suites Pest et Vitest existantes restent vertes. Le tri insensible à la casse doit produire le même ordre pour `TagController::index()` et la prop partagée `HandleInertiaRequests::share()` (même mécanisme dans les deux, pas de divergence).

**Ask First:** Aucune — les 6 correctifs sont déjà tranchés par la rétrospective et cette spec.

**Never:** Ne pas installer de framework E2E navigateur (Dusk/Playwright/Cypress) pour l'item 5 — rester sur Pest + Vitest, conventions existantes. Ne pas modifier le comportement observable de la dédup de tags déjà insensible à la casse (`CreateTagRequest`/`RenameTagRequest`). Ne pas toucher aux surfaces `Documents/Search` / `Documents/Configuration` dans `Sidebar.vue` au-delà de la liste blanche d'`isLibraryActive`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Tri des tags | Tags `"école"`, `"École"`, `"Zebra"` en base | Ordre stable et insensible à la casse, identique entre `TagController::index()` et la prop partagée | N/A |
| Navigation surface future | Nouvelle page `Documents/X` non listée | `isLibraryActive` retourne `false` (pas d'allumage par défaut) | N/A |
| Attach/detach en immediate mode | Titre du document modifié (non enregistré) + clic "détacher" une pièce jointe | Aucune popup de confirmation "modifications non enregistrées" | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Components/TagSelector.vue:139,164,171-174,185,198` -- ids statiques `tag-selector-*` à remplacer par un id d'instance unique (pattern `panelId` de `AttachmentsPanel.vue:74`).
- `resources/js/Pages/Documents/Editor.vue:214-224,679-684` -- flag module-scope `programmaticNavigation` et point de montage d'`AttachmentsPanel` ; à étendre pour recevoir un signal du panneau.
- `resources/js/Components/AttachmentsPanel.vue:101-120,124-141` -- `attachImmediateFile()`/`detachImmediateAttachment()`, aucune coordination actuelle avec le flag de navigation.
- `resources/js/Pages/Documents/Index.vue:86-90` -- `router.get(..., { only: ['documents', 'tagFilters', 'typeFilters'] })`, `tags` absent.
- `resources/js/Pages/Documents/Search.vue:86-90` -- `router.get(..., { only: ['documents', 'search', 'tagFilters'] })`, `tags` absent.
- `app/Http/Controllers/TagController.php:28-33` -- `index()`, `orderBy('name')` sensible à la casse.
- `app/Http/Middleware/HandleInertiaRequests.php:56-58` -- prop partagée `tags`, même `orderBy('name')` à corriger en cohérence.
- `app/Http/Requests/CreateTagRequest.php:56-79` -- référence : dédup déjà insensible à la casse via `Str::lower()`, justifiée par le commentaire sur `LOWER()` SQLite (ASCII only).
- `tests/Feature/ManageTagsTest.php` -- convention Pest existante pour tests de tags, à suivre pour le nouveau test.
- `resources/js/Components/__tests__/TagSelector.spec.js` -- convention Vitest existante.
- `resources/js/Components/Sidebar.vue:32-35` -- `isLibraryActive` par exclusion, à remplacer par liste blanche `['Documents/Index', 'Documents/Editor', 'Documents/Show']`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/TagSelector.vue` -- générer un id d'instance unique (ex. `const instanceId = \`tag-selector-${Math.random().toString(36).slice(2)}\`;`) et l'utiliser pour `label[for]`, `input#id`, `aria-controls`, `aria-activedescendant`, `ul#id`, `li[:id]` -- élimine la collision entre le filtre Bibliothèque et la modale d'import.
- [x] `resources/js/Pages/Documents/Editor.vue` + `resources/js/Components/AttachmentsPanel.vue` -- exposer un mécanisme (prop callback ou emit `before-request`/`after-request`) pour qu'`AttachmentsPanel` signale ses requêtes immediate-mode à `Editor.vue`, qui bascule `programmaticNavigation` autour -- évite le déclenchement à tort de la garde anti-perte de modifications.
- [x] `resources/js/Pages/Documents/Index.vue` + `resources/js/Pages/Documents/Search.vue` -- ajouter `'tags'` aux tableaux `only:[...]` des deux `router.get(...)` -- garde la prop `tags` synchronisée après une mutation ailleurs.
- [x] `app/Http/Controllers/TagController.php` + `app/Http/Middleware/HandleInertiaRequests.php` -- remplacer `orderBy('name')` par un tri PHP insensible à la casse via `Str::lower()` (cohérent entre les deux emplacements) -- même rationale driver-portability que la dédup existante.
- [x] `tests/Feature/ManageTagsTest.php` (ou nouveau fichier `tests/Feature/TagsInertiaPropTest.php`) -- ajouter un test Pest qui mute un tag (rename/delete) puis vérifie que la prop Inertia partagée `tags` reflète le changement.
- [x] `resources/js/Components/Sidebar.vue` -- remplacer `isLibraryActive` par une liste blanche explicite des composants `Documents/*` -- évite qu'une future page allume "Bibliothèque" par défaut.

**Acceptance Criteria:**
- Given la modale d'import ouverte pendant que le filtre de la Bibliothèque est affiché, when les deux `TagSelector` sont montés simultanément, then aucun conflit d'id/aria ne se produit (ids distincts par instance).
- Given un document en cours d'édition avec des modifications non enregistrées, when l'utilisateur attache ou détache une pièce jointe via `AttachmentsPanel` (mode immediate), then aucune popup de confirmation de perte de modifications n'apparaît.
- Given un tag renommé ou supprimé via la Configuration, when la Bibliothèque ou la Recherche effectue son prochain rechargement partiel, then la liste de tags affichée est à jour sans rechargement complet de page.
- Given les tags `"école"`, `"École"`, `"Zebra"` en base, when `TagController::index()` ou la prop partagée `tags` sont résolus, then l'ordre alphabétique est identique et insensible à la casse dans les deux cas.
- Given une mutation de tag via `TagController`, when le test Pest ajouté s'exécute, then il vérifie explicitement le contenu de la prop Inertia `tags` de la réponse.
- Given une page `Documents/X` non listée dans la liste blanche, when `Sidebar.vue` évalue `isLibraryActive`, then le résultat est `false`.

## Design Notes

Item 4 : suivre le rationale déjà documenté dans `CreateTagRequest.php` (commentaire lignes 56-61) — `Str::lower()` (PHP, multibyte-aware) plutôt que `LOWER()` SQL, car SQLite ne folds que l'ASCII. Récupérer la collection puis `->sortBy(fn ($tag) => Str::lower($tag->name))->values()`.

Item 5 : pas de framework E2E navigateur dans ce projet (confirmé : pas de Dusk/Playwright/Cypress). "End-to-end" ici signifie un test Pest côté serveur (mutation → prop Inertia), complémentaire aux tests Vitest déjà en place pour `TagSelector.vue` — ne pas chercher à unifier les deux dans un seul outil.

## Verification

**Commands:**
- `php artisan test` -- expected: suite Pest complète verte, y compris le nouveau test.
- `npm run test` -- expected: suite Vitest verte (TagSelector, AttachmentsPanel, Sidebar, Index, Editor).

**Manual checks (if no CLI):**
- Ouvrir un document existant, modifier le titre sans enregistrer, ouvrir/fermer le panneau de pièces jointes et attacher un fichier : vérifier visuellement qu'aucune popup "modifications non enregistrées" n'apparaît.

## Suggested Review Order

**Tri des tags insensible à la casse (item 10)**

- Point d'entrée : nouvel ordre déterministe (`orderBy('id')`) + comparaison lexicographique (`SORT_STRING`), même mécanisme dupliqué volontairement dans les deux endroits.
  [`TagController.php:45`](../../app/Http/Controllers/TagController.php#L45)

- Même correctif sur la prop partagée, doit rester en lockstep avec `TagController::index()`.
  [`HandleInertiaRequests.php:65`](../../app/Http/Middleware/HandleInertiaRequests.php#L65)

**Garde de navigation vs pièces jointes (item 7)**

- `AttachmentsPanel` émet `before-request`/`after-request` autour de ses propres requêtes immediate-mode.
  [`AttachmentsPanel.vue:67`](../../resources/js/Components/AttachmentsPanel.vue#L67)

- `Editor.vue` bascule `programmaticNavigation` sur ces emits, au même endroit que ses propres save/upload.
  [`Editor.vue:689`](../../resources/js/Pages/Documents/Editor.vue#L689)

**Ids DOM uniques par instance (item 6)**

- `TagSelector` génère un id d'instance, seule vraie surface de risque (deux instances montées en même temps derrière la modale d'import).
  [`TagSelector.vue:41`](../../resources/js/Components/TagSelector.vue#L41)

**Liste blanche de navigation (item 12)**

- `isLibraryActive` passe d'une exclusion à une liste blanche explicite des surfaces `Documents/*`.
  [`Sidebar.vue:39`](../../resources/js/Components/Sidebar.vue#L39)

**Fraîcheur de la prop `tags` (item 9)**

- Rechargement partiel de la Bibliothèque inclut désormais `tags`.
  [`Index.vue:89`](../../resources/js/Pages/Documents/Index.vue#L89)

- Même correctif côté Recherche.
  [`Search.vue:89`](../../resources/js/Pages/Documents/Search.vue#L89)

**Tests**

- Cas accentué explicite ("École"/"école") cité par la rétro, ajouté suite à la revue.
  [`ManageTagsTest.php:45`](../../tests/Feature/ManageTagsTest.php#L45)

- Cohérence de tri entre page-prop et prop partagée (item 11 + item 10).
  [`TagsInertiaPropTest.php`](../../tests/Feature/TagsInertiaPropTest.php#L1)

- Garde de navigation : sanity baseline + non-déclenchement + réarmement.
  [`Editor.spec.js:223`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L223)

- Emits `before-request`/`after-request` vérifiés sur le vrai composant, pas seulement le stub.
  [`AttachmentsPanel.spec.js:250`](../../resources/js/Components/__tests__/AttachmentsPanel.spec.js#L250)

- Collision d'id entre deux instances simultanées.
  [`TagSelector.spec.js:160`](../../resources/js/Components/__tests__/TagSelector.spec.js#L160)

- Couverture positive manquante de `Documents/Editor`.
  [`Sidebar.spec.js:71`](../../resources/js/Components/__tests__/Sidebar.spec.js#L71)

- `only:[...]` vérifié pour la Bibliothèque et la Recherche.
  [`Index.spec.js:147`](../../resources/js/Pages/Documents/__tests__/Index.spec.js#L147)
  [`Search.spec.js:121`](../../resources/js/Pages/Documents/__tests__/Search.spec.js#L121)
