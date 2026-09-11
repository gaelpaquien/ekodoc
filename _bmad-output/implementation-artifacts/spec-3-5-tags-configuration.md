---
title: 'Gérer les tags depuis une page de configuration'
type: 'feature'
created: '2026-09-11'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '1e9f3c82097595796ed29cdf5bea12599dedfb33'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les tags (story 3.1) n'ont aucune interface de gestion — la table `tags` n'est peuplée que via `TagFactory`/tinker, l'utilisateur ne peut ni créer, ni renommer, ni supprimer un tag, au risque de doublons ("finance"/"Finance") et de tags obsolètes qui s'accumulent (epic-3-context, Story 3.5, FR14).

**Approche :** Nouvelle surface `Configuration.vue` reliée depuis la sidebar (item "Configuration"), listant tous les tags avec leur nombre de documents ; création/renommage via `CreateTagAction`/`RenameTagAction` avec détection de doublon insensible à la casse ; suppression via `DeleteTagAction` derrière une boîte de dialogue de confirmation nommant le nombre de documents concernés, qui détache le tag sans jamais toucher aux documents eux-mêmes.

## Boundaries & Constraints

**Always:** `CreateTagAction`/`RenameTagAction`/`DeleteTagAction` sont les seuls points d'entrée pour créer/renommer/supprimer un tag ; le doublon est détecté insensible à la casse (`LOWER(name)`) côté serveur, à la création comme au renommage (en excluant le tag lui-même) ; `DeleteTagAction` compte les documents liés avant suppression puis supprime la ligne `tags` — le `cascadeOnDelete()` déjà posé sur `document_tag.tag_id` (migration `2026_09_09_100100`) retire les lignes pivot automatiquement, sans jamais toucher aux documents ; `SyncDocumentTagsAction` reste l'unique point d'écriture du pivot pour l'assignation par document, `DeleteTagAction` ne l'appelle jamais ; le message post-suppression est factuel, sans emoji : "Tag supprimé — détaché de N documents." ; la boîte de dialogue de suppression reprend le pattern déjà en place dans `Show.vue` (role="dialog", piège de focus, Escape, focus restauré sur le déclencheur) ; la page est intégralement navigable au clavier, focus visible.

**Ask First:** aucune décision bloquante identifiée — HALT si ambiguïté en cours d'implémentation.

**Never:** pas de hiérarchie de tags, pas d'actions groupées (bulk rename/delete) ; pas de création à la volée depuis `TagSelector.vue` (reste hors périmètre, gouverné par spec-3-1) ; `RenameTagAction` ne touche jamais au pivot `document_tag` (seule la colonne `name` change).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Création, nom libre | `POST /tags {name: "Finance"}` | Tag créé, apparaît dans la liste triée par nom | N/A |
| Création, doublon insensible à la casse | `POST /tags {name: "finance"}` alors que "Finance" existe | Rejeté, champ signale le doublon avant validation serveur | 422, message explicite nommant le doublon |
| Renommage, doublon | `PATCH /tags/{id} {name: "Finance"}` vers un nom déjà pris par un autre tag | Rejeté, même détection que la création | 422, message explicite |
| Suppression, tag utilisé par N documents | `DELETE /tags/{id}` confirmé | Pivot détaché (cascade DB), tag supprimé, message "Tag supprimé — détaché de N documents." | N/A |
| Aucun tag | `GET /configuration` sans tag existant | "Aucun tag pour l'instant." + action "Créer un tag" mise en avant | N/A |

</frozen-after-approval>

## Code Map

- `app/DataTransferObjects/CreateTagData.php`, `RenameTagData.php`, `DeleteTagData.php` (nouveaux) -- DTO simples, même forme que `SyncDocumentTagsData.php`.
- `app/Http/Requests/CreateTagRequest.php`, `RenameTagRequest.php` (nouveaux) -- `name` requis, string, max 255 ; unicité insensible à la casse via `whereRaw('LOWER(name) = ?', [Str::lower($value)])` dans une closure `Rule`, `RenameTagRequest` exclut `route('tag')->id` ; messages d'erreur explicites (pattern `SyncDocumentTagsRequest.php`).
- `app/Actions/CreateTagAction.php` -- `Tag::create(['name' => $data->name])`, retourne le `Tag`.
- `app/Actions/RenameTagAction.php` -- `$data->tag->update(['name' => $data->name])`.
- `app/Actions/DeleteTagAction.php` -- capture `$data->tag->documents()->count()` avant `$data->tag->delete()` (cascade FK gère le pivot, pattern documenté epic-3-context "aucun fichier disque impliqué ici"), retourne le count.
- `app/Http/Controllers/TagController.php` (nouveau) -- `index()` rend `Documents/Configuration` avec `tags => Tag::withCount('documents')->orderBy('name')->get()` (forme spécifique à cette page, remplace le `tags` partagé minimal le temps de ce rendu) ; `store()`/`update()`/`destroy()` délèguent aux Actions via `back()`, `destroy()` flashe `tagDeleted => ['name' => ..., 'count' => $count]` en session avant redirection (pattern `uploadedImage`/`uploadedAttachment`).
- `app/Http/Middleware/HandleInertiaRequests.php:81-84` -- ajouter `'tagDeleted' => session('tagDeleted')` au tableau `flash`, même pattern que `uploadedImage`/`uploadedAttachment`.
- `routes/web.php` (après la ligne 16, route `/recherche`) -- `Route::get('/configuration', [TagController::class, 'index'])->name('tags.index')`, puis `Route::post('/tags', [TagController::class, 'store'])->name('tags.store')`, `Route::patch('/tags/{tag}', [TagController::class, 'update'])->name('tags.update')`, `Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy')`.
- `resources/js/Pages/Documents/Configuration.vue` (nouveau) -- liste des tags (nom + nombre de documents), formulaire de création, action "Renommer" inline, action "Supprimer" ouvrant une boîte de dialogue de confirmation nommant `documents_count` ; état vide "Aucun tag pour l'instant." ; lit `$page.props.flash.tagDeleted` pour le message post-suppression ; structure de page identique à `Search.vue` (`AppLayout`, `max-w-3xl`).
- `resources/js/Components/Sidebar.vue:28-61` -- ajouter `isConfigActive` (`page.component === 'Documents/Configuration'`), redéfinir `isLibraryActive` (`!isSearchActive && !isConfigActive`), ajouter le `Link` "Configuration" (`href="/configuration"`) après "Recherche".
- `resources/js/Components/__tests__/Sidebar.spec.js` -- mettre à jour le nombre d'éléments focusables (3 liens nav + toggle thème) et ajouter un cas d'activation pour "Configuration".
- `tests/Feature/ManageTagsTest.php` (nouveau) -- matrice I/O complète (création, doublon casse-insensible création/renommage, suppression avec count, état vide).
- `resources/js/Pages/Documents/__tests__/Configuration.spec.js` (nouveau) -- rendu liste/état vide, ouverture/fermeture dialogue de suppression, focus.

## Tasks & Acceptance

**Execution:**
- [x] DTOs + `CreateTagRequest`/`RenameTagRequest` -- validation + détection doublon
- [x] `CreateTagAction`/`RenameTagAction`/`DeleteTagAction` -- écriture `tags`
- [x] `TagController.php` + routes -- expose `/configuration` et les 3 mutations
- [x] `HandleInertiaRequests.php` -- flash `tagDeleted`
- [x] `Configuration.vue` (nouveau) -- liste, création, renommage, suppression confirmée, état vide
- [x] `Sidebar.vue` -- item "Configuration" + activation route-aware
- [x] Tests backend (`ManageTagsTest`) -- matrice I/O
- [x] Tests frontend (`Sidebar.spec.js`, `Configuration.spec.js` nouveau)

**Acceptance Criteria:**
- Given la sidebar, when cette story est livrée, then un item "Configuration" mène à `/configuration` listant tous les tags existants
- Given la page Configuration, when je saisis un nom de tag déjà pris (casse différente incluse), then le doublon est signalé avant validation
- Given un tag existant, when je clique "Renommer" et valide un nouveau nom, then le changement est reflété immédiatement partout où le tag est affiché
- Given un tag utilisé par N documents, when je confirme sa suppression, then il est détaché de tous ces documents (jamais supprimés) et un message factuel nomme N
- Given aucun tag, when j'ouvre Configuration, then "Aucun tag pour l'instant." s'affiche avec l'action "Créer un tag" mise en avant
- Given la page Configuration, when je la parcours au clavier, then elle reste intégralement navigable, focus visible

## Design Notes

Le prop Inertia `tags` de `TagController@index` (avec `documents_count`) remplace le `tags` partagé minimal (`id`, `name`) uniquement pour ce rendu de page — Inertia fusionne les props partagées puis les props de page, la page l'emporte. Aucun autre composant sur cette surface ne lit le `tags` partagé (pas de `TagSelector` sur Configuration), donc pas de collision de forme ailleurs.

`DeleteTagAction` ne réplique pas le pattern "jamais de `cascadeOnDelete()` nu" de `DeleteDocumentAction` : cette règle protège des fichiers disque orphelins, absents ici — le pivot `document_tag` est une pure relation DB, déjà couverte par `cascadeOnDelete()` (epic-3-context, Technical Decisions).

## Verification

**Commands:**
- `php artisan test --filter=Tag` -- expected: tous les cas de la matrice I/O passent, aucune régression sur `SyncDocumentTagsTest`
- `npm run test -- Configuration Sidebar` -- expected: tests des composants touchés/nouveaux passent
- `npm run build` -- expected: build Vite sans erreur

**Manual checks (if no CLI):**
- Page Configuration entièrement navigable au clavier, focus visible, dialogue de suppression avec piège de focus et Escape
- Renommage/suppression reflétés sans rechargement complet sur Bibliothèque/Recherche/Fiche document (chips, filtres, sélecteur)

## Suggested Review Order

**Points d'entrée (routes, contrôleur)**

- Quatre routes dédiées `/configuration` + `/tags/*`, enregistrées avant les routes génériques `/documents/*`.
  [`web.php:20`](../../routes/web.php#L20)

- `index()`/`store()`/`update()`/`destroy()` délèguent chacun à une Action unique, jamais d'écriture directe sur `Tag`.
  [`TagController.php:28`](../../app/Http/Controllers/TagController.php#L28)

**Détection de doublon insensible à la casse (backend)**

- `prepareForValidation()` trim le nom avant toute règle — corrige le cas "nom composé d'espaces" et les doublons masqués par un espace de fin.
  [`CreateTagRequest.php:28`](../../app/Http/Requests/CreateTagRequest.php#L28)

- Comparaison faite entièrement en PHP (`Str::lower()`) plutôt qu'en SQL `LOWER()`, pour rester correcte sur les accents quel que soit le driver.
  [`CreateTagRequest.php:63`](../../app/Http/Requests/CreateTagRequest.php#L63)

- Même détection côté renommage, avec exclusion explicite du tag lui-même (`forget($tag->id)`) pour ne jamais se signaler comme son propre doublon.
  [`RenameTagRequest.php:56`](../../app/Http/Requests/RenameTagRequest.php#L56)

**Suppression : comptage puis cascade DB (jamais de fichier disque impliqué)**

- Capture `documents()->count()` avant `delete()` — la ligne pivot est retirée par le `cascadeOnDelete()` déjà en place (migration spec-3-1), jamais un second appel à `SyncDocumentTagsAction`.
  [`DeleteTagAction.php:21`](../../app/Actions/DeleteTagAction.php#L21)

- Le compte capturé est flashé (`tagDeleted`) pour alimenter le message factuel post-suppression.
  [`HandleInertiaRequests.php:90`](../../app/Http/Middleware/HandleInertiaRequests.php#L90)

**Surface Configuration (frontend)**

- Doublon signalé en direct côté client contre la prop `tags` déjà chargée, avant tout aller-retour serveur.
  [`Configuration.vue:33`](../../resources/js/Pages/Documents/Configuration.vue#L33)

- Renommage inline : le focus revient sur le bouton déclencheur après validation ou annulation, même discipline que la boîte de suppression.
  [`Configuration.vue:103`](../../resources/js/Pages/Documents/Configuration.vue#L103)

- Boîte de suppression : piège de focus, Escape, focus par défaut sur "Annuler" jamais sur l'action destructive.
  [`Configuration.vue:224`](../../resources/js/Pages/Documents/Configuration.vue#L224)

**Navigation sidebar route-aware**

- `isConfigActive` s'ajoute à `isSearchActive` pour redéfinir `isLibraryActive` — "Bibliothèque" reste l'état par défaut partout ailleurs.
  [`Sidebar.vue:34`](../../resources/js/Components/Sidebar.vue#L34)

**Tests**

- Matrice I/O complète (création, doublon casse/accents, renommage, suppression avec count, état vide) plus la non-régression du pivot sur suppression d'un tag tiers.
  [`ManageTagsTest.php:1`](../../tests/Feature/ManageTagsTest.php#L1)

- Couverture des callbacks `onSuccess`/`onError` de la suppression et du cas singulier "1 document".
  [`Configuration.spec.js:1`](../../resources/js/Pages/Documents/__tests__/Configuration.spec.js#L1)

