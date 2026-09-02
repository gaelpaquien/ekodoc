---
title: 'Story 1.6 - Rechercher un document par son contenu'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: '768f19b67a5a836bbc61bd19f3e75f45250fc2a2'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Aucune recherche n'existe — retrouver un document parmi ~350 fichiers exige de parcourir la bibliothèque manuellement.

**Approach:** Laravel Scout (driver `database`, indexé sur `extracted_text` uniquement) monté sur `Document`. `DocumentController::index()` reste le point d'entrée unique de requête (recherche et futurs filtres Story 1.7 y convergent, AD-8). Barre de recherche en direct (debounce, sans bouton), raccourci clavier `/`.

## Boundaries & Constraints

**Always:** Index Scout limité à `extracted_text` (jamais titre/métadonnées). `index()` reste l'unique point d'entrée de requête, recherche vide = comportement actuel inchangé. Debounce client, aucun indicateur de chargement dédié. Terme reflété dans l'URL (`?search=`, Inertia `replace: true`/`preserveState: true`). Raccourci `/` ignoré si un champ a déjà le focus.

**Ask First:** _Aucun._

**Never:** Pas de moteur tiers (Meilisearch/Algolia) — driver `database` uniquement. Pas de pagination. Pas de filtre catégorie/type (Story 1.7).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Recherche avec correspondance | `?search=facture` | Documents dont `extracted_text` contient le terme, en moins d'1s | N/A |
| Recherche sans terme | `?search=` ou absent | Comportement actuel inchangé (tri par date, aucun filtre) | N/A |
| Recherche sans résultat | terme sans correspondance | "Aucun document ne correspond à votre recherche." + suggestion de vider la recherche | N/A |
| Raccourci `/` hors champ | touche `/` pressée, aucun input actif | Focus posé sur la barre de recherche, pas de caractère `/` inséré | N/A |
| Raccourci `/` dans un champ actif | touche `/` pressée, un input/textarea a déjà le focus | Ignoré, comportement natif du champ préservé | N/A |

</frozen-after-approval>

## Code Map

- `composer.json` -- `laravel/scout` absent -- à ajouter.
- `config/scout.php` -- absent, à publier (`SCOUT_DRIVER=database` dans `.env`/`.env.example`, près de `QUEUE_CONNECTION` `.env.example:38`).
- `app/Models/Document.php` (~40 lignes, pas de `Searchable`) -- trait + `toSearchableArray()` sur `extracted_text` (`longText` nullable confirmée).
- `app/Http/Controllers/DocumentController.php:34-44` (`index()`) -- remplace le commentaire "Search... out of scope" (L28-33) ; accepte `?search=`, reste l'unique méthode/requête (AD-8).
- `resources/js/Pages/Documents/Index.vue` (89 lignes, aucune recherche existante) -- input, debounce, message vide, raccourci `/`.
- `resources/js/Components/ExtractionTasksPanel.vue` -- seul patron voisin de nettoyage temporisé, à adapter (pas de composable debounce existant).
- `resources/js/Components/ImportModal.vue:101-132` -- scoping clavier existant (Escape/Tab) à ne pas casser avec `/`.
- `tests/Feature/BrowseLibraryTest.php` -- patron Pest à suivre.

## Tasks & Acceptance

**Execution:**
- [x] `composer.json`/`composer.lock` -- `composer require laravel/scout` -- prérequis.
- [x] `config/scout.php`, `.env`, `.env.example` -- `SCOUT_DRIVER=database` -- pas d'index séparé, requêtes directes en base.
- [x] `app/Models/Document.php` -- `Searchable` + `toSearchableArray()` sur `extracted_text` seul.
- [x] `DocumentController::index()` -- lire `?search=`, `Document::search($term)->query(...)` vs `Document::query()` inchangé dans la même méthode, exposer `search` en prop Inertia -- point d'entrée unique (AD-8).
- [x] `resources/js/Pages/Documents/Index.vue` -- input + debounce (~300ms, `router.get('/', {search}, {preserveState:true, replace:true, only:['documents']})`) + message vide + raccourci `/`.
- [x] `tests/Feature/SearchDocumentsTest.php` -- couvre la matrice I/O, patron `BrowseLibraryTest.php`.

**Acceptance Criteria:**
- Given Scout configuré (driver `database`), when je tape un terme, then les résultats se filtrent en direct sans bouton ni indicateur de chargement dédié.
- Given un terme actif dans l'URL, when je recharge ou partage le lien, then le même résultat filtré s'affiche.
- Given `DocumentController::index()`, then c'est la seule méthode qui construit la requête documents, recherche active ou non.

## Design Notes

Driver `database` : pas d'index séparé, chaque recherche exécute un `LIKE`/fulltext direct sur `documents` -- `extracted_text` toujours à jour, aucun `scout:import` requis.

`index()` garde une seule forme de requête : `Document::query()->with('category:id,name')->latest()` si vide, `Document::search($term)->query(fn ($q) => $q->with('category:id,name'))` sinon -- convergent vers le même `get([...])`, prêt pour les filtres Story 1.7.

Raccourci `/` : `keydown` global posé/retiré au montage/démontage d'`Index.vue`, ignoré si `document.activeElement` est un `input`/`textarea`/`contenteditable`.

## Verification

**Commands:**
- `herd php artisan test --filter=SearchDocumentsTest` -- expected: verts.
- `herd php artisan test` -- expected: suite complète verte, aucune régression 1.1-1.5.

**Manual checks (if no CLI):**
- Depuis la bibliothèque, presser `/` (aucun champ actif) : le focus doit se poser sur la barre de recherche. Taper un terme présent dans un document importé : la liste se filtre sans clic. Vider le champ : la liste complète revient.

## Suggested Review Order

**Point d'entrée unique de requête (AD-8)**

- Bascule entre comportement inchangé et recherche Scout dans la même méthode, sans forker le chemin de requête.
  [`DocumentController.php:47`](../../app/Http/Controllers/DocumentController.php#L47)

- Terme échappé contre les jokers LIKE (`%`/`_`) avant Scout ; requête brute toujours renvoyée telle quelle en prop (patch review).
  [`DocumentController.php:56`](../../app/Http/Controllers/DocumentController.php#L56)

- Garde contre un `search` non scalaire (`?search[]=`) avant le `trim()` (patch review).
  [`DocumentController.php:49`](../../app/Http/Controllers/DocumentController.php#L49)

**Index Scout limité à `extracted_text` (AD-16-like boundary)**

- Trait + tableau indexable réduit au seul contenu extrait, jamais titre/métadonnées.
  [`Document.php:51`](../../app/Models/Document.php#L51)

**Recherche en direct côté client**

- Debounce 300ms piloté par un seul `watch`, avec garde anti-boucle pour la synchro depuis `props.search` (patch review).
  [`Index.vue:45`](../../resources/js/Pages/Documents/Index.vue#L45)

- Raccourci `/` : ignoré si un champ a le focus, si la modale d'import est ouverte, ou si un modificateur est actif (patch review).
  [`Index.vue:81`](../../resources/js/Pages/Documents/Index.vue#L81)

- Message "aucun résultat" basé sur le terme trimmé, pour rester cohérent avec le serveur sur un terme composé d'espaces (patch review).
  [`Index.vue:68`](../../resources/js/Pages/Documents/Index.vue#L68)

- Région `aria-live` autour de la liste/messages pour notifier les lecteurs d'écran des changements en direct (patch review, WCAG 2.2 AA).
  [`Index.vue:158`](../../resources/js/Pages/Documents/Index.vue#L158)

**Périphériques**

- Couvre la matrice I/O (correspondance, terme vide/absent, sans résultat, espaces, tri) plus l'isolation titre/contenu (patch review : espaces + tri ajoutés).
  [`SearchDocumentsTest.php:1`](../../tests/Feature/SearchDocumentsTest.php#L1)

- Driver `database` activé, sans moteur tiers.
  [`scout.php:334`](../../config/scout.php#L334)
