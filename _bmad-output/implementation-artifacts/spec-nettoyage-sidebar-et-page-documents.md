---
title: 'Nettoyage UI : footer sidebar, titre et filtres de la page Documents'
type: 'chore'
created: '2026-09-15'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '5d5fa2902c64a3ca09699219e645bd64138f6855'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Le side menu affiche un footer "Made with ... Claude" jugé superflu. La page Documents (Index.vue, route `/`) affiche un titre "Bibliothèque de documents" (le libellé "Bibliothèque" a déjà été abandonné partout ailleurs au profit de "Documents", cf. Sidebar.vue). Elle expose aussi un bloc de filtres (tag + type) redondant avec la page Recherche dédiée (`/recherche`).

**Approach:** Retirer le footer et son séparateur du side menu. Renommer le titre de la page Documents en "Documents". Retirer entièrement le bloc de filtres de cette page (UI Vue, props/logique serveur `DocumentController::index()`, tests dédiés) — filtrer un document ne se fait plus que via `/recherche`. Le filtrage par tag reste disponible sur `/recherche` (`search()`), qui partage `applyFilters()`/`tagIdsFromQuery()` avec `index()` et n'est pas concerné.

## Boundaries & Constraints

**Always:** `search()` (Recherche) doit continuer à fonctionner à l'identique — ne pas toucher `applyFilters()` ni `tagIdsFromQuery()`, seulement leurs appelants dans `index()`. La liste paginée (20/page, tri plus récent d'abord) reste inchangée.

**Ask First:** Aucune décision supplémentaire attendue — le périmètre (retrait complet, pas juste un masquage UI) a déjà été validé par l'humain.

**Never:** Ne pas toucher `Documents/Search.vue`, `Documents/Show.vue`, ni la prop globale partagée `documentTypeOptions` (`HandleInertiaRequests.php`) — seule sa consommation locale dans `Index.vue` disparaît.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Visite `/` | Aucun query param | Liste paginée complète, titre "Documents", aucun bloc filtre, aucun footer visible dans la sidebar | N/A |
| Visite `/?tag_id[]=1&type[]=pdf` | Query params désormais ignorés côté `index()` | Liste complète non filtrée (les query params n'ont plus d'effet sur cette route) | N/A |
| Visite `/recherche` avec un tag sélectionné | inchangé | Comportement de filtrage par tag identique à avant (non affecté) | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Components/Sidebar.vue:187-197` -- `<hr>` + `<p>` "Made with ... Claude" à supprimer.
- `resources/js/Components/__tests__/Sidebar.spec.js:42-82` -- tests couvrant le footer/cœur à retirer ou réécrire (compte de `<hr>` passe de 2 à 1).
- `resources/js/Pages/Documents/Index.vue:1-141` (script) et `143-283` (template) -- retirer tout ce qui sert au filtrage (props `tagFilters`/`typeFilters`, `selectedTagIds`/`selectedTypes`, watchers, `navigate()`, `hasActiveFilters`, `clearFilters`, `toggleType`, `removeTagFilter`, `removeTypeFilter`, `tagName`, `typeLabel`, `TYPE_OPTIONS`, `allTags`, imports `usePage`/`router`/`TagSelector`) ; renommer le `<h1>` (ligne 148) en "Documents" ; simplifier le bloc vide (ligne 217-234) à un seul message ("Aucun document pour l'instant.") sans la branche "filtres actifs". `TagChip`/`DocumentTypeBadge` restent utilisés pour les lignes de document.
- `resources/js/Pages/Documents/__tests__/Index.spec.js` -- retirer le test du message "filtres actifs" (L42-50) et celui du partial reload sur changement de filtre (L149-159) ; retirer `tagFilters`/`typeFilters` des props montées dans les tests restants ; simplifier le mock `@inertiajs/vue3` en conséquence.
- `app/Http/Controllers/DocumentController.php:71-90` (`index()`) -- retirer `tagIdsFromQuery()`/`typesFromQuery()`/`applyFilters()` de ce chemin et les clés `tagFilters`/`typeFilters` du rendu Inertia ; mettre à jour le docblock (ne plus mentionner Story 1.7). `typesFromQuery()` (L201-213) devient mort code une fois `index()` changé -- à supprimer. `tagIdsFromQuery()`/`applyFilters()` restent (utilisés par `search()`).
- `tests/Feature/FilterDocumentsTest.php` -- fichier entier obsolète (ne teste que le filtrage sur `index()`) -- à supprimer.
- `tests/Feature/TagsInertiaPropTest.php:9-11` -- commentaire à corriger ("`index()` rendait `tagFilters`/`typeFilters`" devient faux).

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/Sidebar.vue` -- retirer le `<hr>` + `<p>` du footer -- demande explicite.
- [x] `resources/js/Components/__tests__/Sidebar.spec.js` -- adapter les tests au footer disparu (compte `<hr>` = 1, retrait des assertions "Made with"/cœur).
- [x] `resources/js/Pages/Documents/Index.vue` -- renommer le titre, retirer tout le bloc/logique de filtre.
- [x] `resources/js/Pages/Documents/__tests__/Index.spec.js` -- aligner les tests sur la page sans filtres.
- [x] `app/Http/Controllers/DocumentController.php` -- simplifier `index()`, supprimer `typesFromQuery()`, garder `applyFilters()`/`tagIdsFromQuery()` pour `search()`.
- [x] `tests/Feature/FilterDocumentsTest.php` -- supprimer.
- [x] `tests/Feature/TagsInertiaPropTest.php` -- corriger le commentaire obsolète.

**Note d'implémentation :** `tests/Feature/CreateDocumentTest.php` (hors Code Map initial) contenait aussi un test dépendant du filtrage désormais retiré de `index()` (`is immediately filterable via type=created ...`, Story 1.7) ; réécrit pour vérifier que `/?type[]=created` est bien ignoré (liste complète, créé + importé).

**Round de revue (step-04, Blind Hunter + Edge Case Hunter + Verification Gap Reviewer) — 4 correctifs triviaux appliqués :**
- `tests/Feature/SearchDocumentsTest.php` -- portage de 3 cas de `FilterDocumentsTest.php` (récupérés via `git show <baseline_commit>:tests/Feature/FilterDocumentsTest.php`), adaptés à `/recherche?search=...&tag_id[]=...` (forme `documents`/pas de pagination) : OR entre tags multiples, dédoublonnage d'un document portant plusieurs tags sélectionnés, tag_id malformé silencieusement ignoré -- comblent une perte de couverture sur `applyFilters()`/`tagIdsFromQuery()` (toujours exercées par `search()`).
- `app/Http/Controllers/DocumentController.php` -- docblocks de `search()` et `applyFilters()` corrigés : `search()` est désormais le seul appelant des deux méthodes partagées, `index()` ne filtrant plus.
- `resources/js/Components/__tests__/Sidebar.spec.js` -- assertion obsolète `not.toContain('💔')` retirée du test renommé (l'emoji faisait partie du footer déjà supprimé par ce diff).

Vérification post-correctifs : `npx vitest run` (11 fichiers, 103 tests) et `php artisan test` (167 tests, 1029 assertions) verts ; `./vendor/bin/pint --test` vert sur tous les fichiers PHP touchés.

**Note d'implémentation (audit matrice I/O) :** la ligne `/?tag_id[]=1&type[]=pdf` n'était couverte que côté `type[]` (via `CreateDocumentTest`) — aucun test ne vérifiait que `tag_id[]` est lui aussi ignoré par `index()`. Ajout de `tests/Feature/BrowseLibraryTest.php::'ignores a tag_id query param on the library index, listing every document regardless of tags'`.

**Acceptance Criteria:**
- Given la sidebar affichée, when la page se charge, then aucun texte "Made with"/"Claude" ni le `<hr>` associé n'apparaît, et le reste de la sidebar (nav, toggle thème) est inchangé.
- Given la page Documents (`/`), when elle se charge, then le titre affiche "Documents" (jamais "Bibliothèque") et aucun contrôle de filtre/tri n'est visible.
- Given `/?tag_id[]=X&type[]=pdf`, when la page se charge, then la liste complète non filtrée s'affiche (les query params sont ignorés).
- Given `/recherche` avec un filtre tag actif, when la page se charge, then le comportement de filtrage reste identique à avant ce changement.

## Verification

**Commands:**
- `npm run test:unit -- Sidebar Index` (ou équivalent Vitest du projet) -- expected: suites JS vertes.
- `php artisan test --filter=BrowseLibraryTest` -- expected: vert (comportement non-filtré inchangé).
- `php artisan test --filter=SearchDocumentsTest` -- expected: vert (Recherche non affectée).
- `php artisan test --filter=TagsInertiaPropTest` -- expected: vert.

**Manual checks (if no CLI):**
- Ouvrir `/` dans le navigateur : vérifier visuellement l'absence du footer sidebar et du bloc filtre, et le titre "Documents".

## Suggested Review Order

**Suppression du footer sidebar**

- Point d'entrée le plus simple : le `<hr>` + `<p>` "Made with ... Claude" ont été retirés en bloc.
  [`Sidebar.vue:185`](../../resources/js/Components/Sidebar.vue#L185)

- Tests alignés : plus d'assertion sur "Made with", compte de `<hr>` passé de 2 à 1.
  [`Sidebar.spec.js:42`](../../resources/js/Components/__tests__/Sidebar.spec.js#L42)
  [`Sidebar.spec.js:55`](../../resources/js/Components/__tests__/Sidebar.spec.js#L55)

**Retrait du filtrage sur la page Documents (`index()`)**

- `index()` simplifié en simple requête paginée, sans plus prendre `Request` ni appeler `applyFilters()`/`tagIdsFromQuery()`/`typesFromQuery()` (supprimée) — le cœur de la décision produit.
  [`DocumentController.php:70`](../../app/Http/Controllers/DocumentController.php#L70)

- `Index.vue` allégé à une seule prop `documents` : plus de `tagFilters`/`typeFilters`, plus de state/watchers de filtre.
  [`Index.vue:7`](../../resources/js/Pages/Documents/Index.vue#L7)

- Titre renommé "Bibliothèque de documents" → "Documents", cohérent avec le libellé du menu.
  [`Index.vue:31`](../../resources/js/Pages/Documents/Index.vue#L31)

- État vide simplifié à un seul message, la branche "filtres actifs" n'a plus de raison d'être.
  [`Index.vue:38`](../../resources/js/Pages/Documents/Index.vue#L38)

**`search()`/Recherche non affectée — docblocks et couverture de test réalignés (round de revue)**

- `search()` reste l'unique appelant d'`applyFilters()`/`tagIdsFromQuery()` — docblock corrigé pour ne plus citer `index()`.
  [`DocumentController.php:97`](../../app/Http/Controllers/DocumentController.php#L97)

- `applyFilters()` : docblock corrigé, un seul point d'appel désormais (`search()`).
  [`DocumentController.php:137`](../../app/Http/Controllers/DocumentController.php#L137)

- 3 cas de test portés depuis `FilterDocumentsTest.php` vers `/recherche` — comblent la perte de couverture sur l'OR multi-tags/dédoublonnage/tag_id malformé.
  [`SearchDocumentsTest.php:203`](../../tests/Feature/SearchDocumentsTest.php#L203)
  [`SearchDocumentsTest.php:228`](../../tests/Feature/SearchDocumentsTest.php#L228)
  [`SearchDocumentsTest.php:245`](../../tests/Feature/SearchDocumentsTest.php#L245)

**Périphériques**

- Nouveau test confirmant que `tag_id[]` est bien ignoré sur `/` (comble un trou de la matrice I/O).
  [`BrowseLibraryTest.php:11`](../../tests/Feature/BrowseLibraryTest.php#L11)

- Test Story 1.7 réécrit : `type[]=created` est désormais ignoré par `index()`.
  [`CreateDocumentTest.php:159`](../../tests/Feature/CreateDocumentTest.php#L159)

- Commentaire corrigé, `index()` ne rend plus que `documents`.
  [`TagsInertiaPropTest.php:9`](../../tests/Feature/TagsInertiaPropTest.php#L9)

- Fichier entier supprimé (255 lignes) — ne testait que le filtrage sur `index()`, retiré de cette route.
  `tests/Feature/FilterDocumentsTest.php` (supprimé)
