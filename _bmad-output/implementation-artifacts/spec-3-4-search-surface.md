---
title: 'Rechercher un document sur une surface dédiée'
type: 'feature'
created: '2026-09-10'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '682d717d616d56cf7db7773648560b90581ca222'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La recherche fulltexte est aujourd'hui mélangée aux filtres dans la Bibliothèque (`Index.vue`), qui n'a par ailleurs aucune pagination — l'utilisateur ne peut pas distinguer "parcourir" de "chercher un document précis" (epic-3-context, Story 3.4).

**Approche :** Nouvelle surface `Search.vue` dédiée, reliée depuis la sidebar (item "Recherche"), qui reprend recherche fulltexte + filtre tag + raccourci `/` retirés de `Index.vue` ; la Bibliothèque devient un listing paginé (20/page) sans recherche, ne gardant que les filtres tag/type.

## Boundaries & Constraints

**Always:** `Search()` et `index()` du `DocumentController` continuent de converger sur l'unique `applyFilters()` — jamais une seconde branche `whereHas` ; l'action Search renvoie `documents => []` dès que le terme trimé est vide, y compris si un tag est sélectionné (état neutre, AC2) — Search n'affiche jamais la bibliothèque entière ; `index()` abandonne totalement sa branche Scout et utilise systématiquement `applyFilters(Document::query(), …)->paginate(20)` — la réponse Inertia passe d'un tableau brut à `{data, links, …}`, donc toute assertion existante lisant `documents.N` doit devenir `documents.data.N` ; la sidebar détermine l'item actif via `usePage().component` (`'Documents/Search'` vs le reste) au lieu de la constante figée actuelle, sans changer le comportement d'activation de "Bibliothèque" sur Fiche document/Éditeur ; le raccourci `/` déménage tel quel (moins la garde Import modal) vers `Search.vue`, retiré intégralement d'`Index.vue`.

**Ask First:** aucune décision bloquante identifiée — HALT si ambiguïté en cours d'implémentation.

**Never:** pas d'extraction d'un composant `DocumentRow.vue` ni d'un composable de debounce partagé — dupliquer le bloc de ligne et le pattern `setTimeout` 300ms d'`Index.vue` dans `Search.vue`, cohérent avec la convention déjà en place ; pas de pagination sur la surface Recherche (résultat unique live-filtré, seule la Bibliothèque pagine) ; pas de filtre type sur Recherche (reste spécifique à la Bibliothèque).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Recherche vide au chargement | `GET /recherche` sans query | `documents: []`, focus sur le champ, aucun message d'erreur | N/A |
| Terme tapé | `GET /recherche?search=facture` (debounce 300ms) | Résultats fulltexte (contenu + pièces jointes) filtrés par tag actif | N/A |
| Terme sans résultat | `GET /recherche?search=xyz123` | "Aucun document ne correspond à votre recherche." | N/A |
| Filtre tag seul, terme vide | `GET /recherche?tag_id[]=3` | `documents: []` (même règle que terme vide) | N/A |
| Bibliothèque, page 2 | `GET /?page=2` avec 25 documents | 5 documents affichés, liens pagination cohérents | N/A |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php:76-103` (`index`) -- retirer la branche Scout/`search`, passer à `applyFilters(Document::query(), $tagIds, $types)->with('tags:id,name')->latest()->paginate(20, $columns)`.
- `app/Http/Controllers/DocumentController.php` (nouvelle méthode `search`, à côté d'`index`) -- terme trimé vide ⇒ `documents => []` ; sinon `Document::search(addcslashes($search,'%_'))->query(fn($q) => $this->applyFilters($q, $tagIds, [])->select($columns)->with('tags:id,name')->latest())->get()` ; rend `Documents/Search` avec `documents`, `search`, `tagFilters`. Réutilise `applyFilters()`/`tagIdsFromQuery()` (117-167) sans modification.
- `routes/web.php:15` -- ajouter `Route::get('/recherche', [DocumentController::class, 'search'])->name('documents.search')` à côté de la route `/`.
- `resources/js/Pages/Documents/Index.vue` -- retirer la barre de recherche (277-286), `searchTerm`/debounce/`onGlobalKeydown`/`clearSearch`/`trimmedSearchTerm`/`isSyncingSearchFromProps` (45,50-59,65-73,125-136,153,159-161,196-227), la branche "aucun résultat de recherche" (366-377) ; adapter `navigate()` (98-123) pour ne plus poser `search` ; template + `<ul>` itèrent `documents.data`, ajouter les liens de pagination depuis `documents.links`.
- `resources/js/Pages/Documents/Search.vue` (nouveau) -- reprend d'`Index.vue` le sous-ensemble barre de recherche/debounce/raccourci `/`/filtre tag (`TagSelector`)/ligne de résultat, sans filtre type, sans bouton Import, sans pagination ; `navigate()` cible `/recherche` avec `only:['documents','search','tagFilters']` ; état neutre quand `search` est vide, message "Aucun document ne correspond à votre recherche." sinon.
- `resources/js/Components/Sidebar.vue:28,39-50` -- remplacer la constante `isLibraryActive` par des computed dérivés de `usePage().component` (`isSearchActive`/`isLibraryActive`) ; ajouter le `Link` "Recherche" (`href="/recherche"`) sur le même gabarit que "Bibliothèque".
- `resources/js/Components/__tests__/Sidebar.spec.js:39-58` -- mettre à jour le nombre d'éléments focusables (2 liens nav + toggle thème).
- `tests/Feature/SearchDocumentsTest.php` -- retargeter vers `/recherche`/`Documents/Search` ; ajouter "terme vide ⇒ aucun résultat" et "tag sans terme ⇒ aucun résultat".
- `tests/Feature/FilterDocumentsTest.php`, `tests/Feature/BrowseLibraryTest.php` -- migrer les assertions `documents.N` vers `documents.data.N` ; ajouter un cas pagination (21 documents ⇒ 20 en page 1, page 2 accessible).
- `resources/js/Pages/Documents/__tests__/Index.spec.js` -- retirer les assertions de barre de recherche, adapter la fixture `documents` à `{data:[...], links:[...]}`.
- `resources/js/Pages/Documents/__tests__/Search.spec.js` (nouveau) -- mêmes conventions que `Index.spec.js`, adaptées à la nouvelle page.

## Tasks & Acceptance

**Execution:**
- [x] `DocumentController.php` -- `index()` : pagination 20/page, suppression branche Scout -- AC pagination Bibliothèque
- [x] `DocumentController.php` -- nouvelle méthode `search()` -- AC recherche dédiée
- [x] `routes/web.php` -- route `documents.search` -- expose `/recherche`
- [x] `Index.vue` -- suppression recherche/raccourci, adaptation pagination -- AC1
- [x] `Search.vue` (nouveau) -- surface dédiée -- AC1/AC2
- [x] `Sidebar.vue` -- item "Recherche" + activation route-aware -- AC1
- [x] Tests backend (`SearchDocumentsTest`, `FilterDocumentsTest`, `BrowseLibraryTest`) -- matrice I/O + migration `documents.data`
- [x] Tests frontend (`Sidebar.spec.js`, `Index.spec.js`, `Search.spec.js` nouveau)

**Acceptance Criteria:**
- Given la sidebar, when cette story est livrée, then un item "Recherche" mène à `/recherche` (`Search.vue`), et la Bibliothèque n'affiche plus ni barre de recherche ni raccourci `/`
- Given la surface Recherche ouverte sans terme saisi, when elle se charge, then le focus est sur le champ de recherche et aucun résultat n'est affiché
- Given un terme tapé sur Recherche, when la recherche fulltexte s'exécute (documents + pièces jointes) après le debounce, then les résultats filtrés par tag actif s'affichent sans rechargement de page
- Given la Bibliothèque avec plus de 20 documents, when elle se charge, then seuls 20 documents s'affichent avec une pagination fonctionnelle
- Given l'item "Recherche" actif, when je navigue vers un document depuis les résultats, then l'item "Bibliothèque" redevient actif dans la sidebar

## Design Notes

`search()` reste une méthode séparée d'`index()` plutôt qu'un paramètre partagé : les deux surfaces divergent sur la pagination (Bibliothèque seule) et le filtre type (Bibliothèque seule), donc les fusionner forcerait des branches conditionnelles dans une méthode déjà dense ; elles ne partagent que `applyFilters()`, ce qui préserve l'invariant "un seul point de filtrage".

Le changement de forme de la réponse `index()` (tableau brut → `{data, links}`) est un effet de bord assumé de la pagination — il touche toute la suite de tests existante qui lit `documents` comme un tableau ; c'est signalé explicitement en tâche plutôt que découvert en cours de revue.

## Verification

**Commands:**
- `php artisan test --filter=Document` -- expected: tests existants adaptés + nouveaux passent, aucune régression import/filtre/suppression
- `npm run test -- Search Sidebar Index` -- expected: tests des composants touchés/nouveaux passent
- `npm run build` -- expected: build Vite sans erreur

**Manual checks (if no CLI):**
- Recherche/Bibliothèque entièrement navigables au clavier, focus visible, raccourci `/` fonctionnel uniquement sur Recherche
- Pagination de la Bibliothèque utilisable au clavier et à la souris

## Suggested Review Order

**Scission recherche/bibliothèque (backend)**

- Point d'entrée : `index()` devient pur listing paginé, la branche Scout est déplacée dans `search()` juste en dessous — les deux convergent toujours sur `applyFilters()`.
  [`DocumentController.php:71`](../../app/Http/Controllers/DocumentController.php#L71)

- Correctif revue : `withQueryString()` manquant faisait perdre le filtre tag/type actif sur les liens de pagination (page 2+).
  [`DocumentController.php:83`](../../app/Http/Controllers/DocumentController.php#L83)

- `search()` : terme vide → `documents => []` même avec un tag sélectionné (jamais un repli sur la bibliothèque entière, AC2).
  [`DocumentController.php:106`](../../app/Http/Controllers/DocumentController.php#L106)

- Route dédiée, sans collision avec les routes `{document}` génériques.
  [`web.php:16`](../../routes/web.php#L16)

**Pagination Bibliothèque (frontend)**

- `documents` passe d'un tableau à un objet paginé (`data`/`links`) — deux branches d'état vide adaptées.
  [`Index.vue:234`](../../resources/js/Pages/Documents/Index.vue#L234)
  [`Index.vue:247`](../../resources/js/Pages/Documents/Index.vue#L247)

- Liste et liens de pagination itèrent désormais `documents.data`/`documents.links`.
  [`Index.vue:262`](../../resources/js/Pages/Documents/Index.vue#L262)
  [`Index.vue:279`](../../resources/js/Pages/Documents/Index.vue#L279)

**Surface Recherche dédiée (frontend, nouveau)**

- Reprend le sous-ensemble recherche/debounce/raccourci `/` d'`Index.vue`, sans filtre type ni pagination.
  [`Search.vue:3`](../../resources/js/Pages/Documents/Search.vue#L3)

- État neutre distinct de l'état "aucun résultat" — jamais de repli sur la bibliothèque entière.
  [`Search.vue:232`](../../resources/js/Pages/Documents/Search.vue#L232)

- Focus posé sur le champ dès le montage (AC2).
  [`Search.vue:162`](../../resources/js/Pages/Documents/Search.vue#L162)

**Navigation sidebar route-aware**

- Activation par `usePage().component` plutôt que par constante figée — ajoute l'item "Recherche".
  [`Sidebar.vue:29`](../../resources/js/Components/Sidebar.vue#L29)

**Tests**

- Régression correctif pagination+filtre.
  [`FilterDocumentsTest.php:32`](../../tests/Feature/FilterDocumentsTest.php#L32)

- Matrice I/O : pagination Bibliothèque.
  [`BrowseLibraryTest.php:94`](../../tests/Feature/BrowseLibraryTest.php#L94)

- Matrice I/O : filtre tag seul sans terme.
  [`SearchDocumentsTest.php:55`](../../tests/Feature/SearchDocumentsTest.php#L55)

- Écart de vérification comblé : état vide sans filtre, distinct de l'état vide filtré.
  [`Index.spec.js:52`](../../resources/js/Pages/Documents/__tests__/Index.spec.js#L52)
