---
title: 'Page dédiée pour l''import de document (remplace la popup)'
type: 'feature'
created: '2026-09-14'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: 'facf63ed635f1b9a47031c518c9a7bd9e336bee0'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** "Importer un document" ouvre aujourd'hui `ImportModal.vue`, une popup superposée à la page courante — moins visible et incohérente avec "Créer un document", qui navigue vers `/documents/create`.

**Approach:** Remplacer la popup par une page dédiée `/documents/import` (`Documents/Import.vue`), reprenant le même contenu (dropzone, `TagSelector`) que la modale actuelle, intégrée dans `AppLayout`. Le bouton de la sidebar devient un `Link` vers cette page, à l'image de "Créer un document". `ImportModal.vue` est supprimé.

## Boundaries & Constraints

**Always:**
- Nouvelle route `GET /documents/import` (nommée `documents.import`), enregistrée avant `GET /documents/{document}` — même précaution que `/documents/create`.
- Le POST reste inchangé : `POST /documents` (`documents.store`, `ImportDocumentAction` + `SyncDocumentTagsAction`), redirection vers la Fiche document en cas de succès.
- La page reprend telle quelle la logique de `ImportModal.vue` : `useFileDropZone` (mêmes extensions/taille), `TagSelector`, mêmes messages d'erreur, mêmes contrôles désactivés pendant `form.processing`.
- La sidebar traite "Importer un document" comme "Créer un document" : un `Link` avec son propre état actif (`isImportActive`, `page.component === 'Documents/Import'`) — plus de `ref` d'état modal ni de gestion clavier Echap/focus-trap (obsolète hors modale).
- Code/classes/logs en anglais, UI en français.

**Ask First:** _Aucune._

**Never:**
- Ne pas dupliquer le formulaire d'import ailleurs (une seule page, plus de popup).
- Ne pas changer le comportement du POST `/documents`, `ImportDocumentAction` ou `SyncDocumentTagsAction`.
- Ne pas ajouter de lien "retour" dans la page (navigation exclusive à la sidebar, cohérent avec le retrait déjà fait des liens "Retour" dans Editor/Show).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Accès direct à la page | `GET /documents/import` | Page `Documents/Import` rendue, dropzone visible | N/A |
| Import valide depuis la page | fichier PDF/.docx/.xlsx déposé | `POST /documents`, redirection vers la Fiche document (inchangé) | N/A |
| Format non supporté | fichier `.pptx` déposé | Aucun envoi serveur, message d'erreur inline, page reste affichée | Validation client (`useFileDropZone`) |
| Navigation sidebar | clic sur "Importer un document" | Navigation Inertia vers `/documents/import`, item actif dans la sidebar | N/A |

</frozen-after-approval>

## Code Map

- `routes/web.php:16` -- ajouter `Route::get('/documents/import', ...)->name('documents.import')` juste avant `documents.create` (même précaution d'ordre que `/documents/create` face à `/documents/{document}`)
- `app/Http/Controllers/DocumentController.php:249` (`create()`) -- ajouter une méthode `import()` miroir : `return Inertia::render('Documents/Import')`
- `resources/js/Pages/Documents/Import.vue` -- nouvelle page ; reprend le template/script de `ImportModal.vue` (dropzone, `useFileDropZone`, `TagSelector`, `useForm` postant sur `/documents`) sans le wrapper modal (`role=dialog`, focus trap, Echap) ; enveloppée dans `AppLayout`
- `resources/js/Components/ImportModal.vue` -- supprimer (plus aucun appelant après migration)
- `resources/js/Components/Sidebar.vue:64,106-117,175` -- remplacer le `<button>`/`isImportModalOpen`/`<ImportModal>` par un `<Link href="/documents/import">` + `isImportActive` (miroir `isCreateActive:50`)
- `resources/js/Components/__tests__/Sidebar.spec.js` -- retirer le mock `ImportModal.vue` (ligne 31) ; adapter les tests "renders...Recherche" (bouton→lien), "opens the import modal" (devient un test de navigation `href`), "never renders...active" (devient un test d'état actif, miroir de celui de "Créer un document", ligne 102)
- `resources/js/Layouts/__tests__/AppLayout.spec.js:32,43` -- retirer le stub `ImportModal: true`, devenu inutile
- `tests/Feature/ImportDocumentTest.php` -- ajouter un test `GET /documents/import` (miroir `CreateDocumentTest.php:201`)

## Tasks & Acceptance

**Execution:**
- [x] `routes/web.php` -- ajouter la route `documents.import` -- point d'entrée de la nouvelle page
- [x] `app/Http/Controllers/DocumentController.php` -- ajouter `import()` -- rend `Documents/Import`
- [x] `resources/js/Pages/Documents/Import.vue` -- créer la page (reprend le contenu de la modale) -- remplace la popup
- [x] `resources/js/Components/ImportModal.vue` -- supprimer -- devenu inutilisé
- [x] `resources/js/Components/Sidebar.vue` -- bouton → `Link` + état actif -- cohérence avec "Créer un document"
- [x] `resources/js/Components/__tests__/Sidebar.spec.js` -- mettre à jour les tests concernés -- suite verte
- [x] `resources/js/Layouts/__tests__/AppLayout.spec.js` -- retirer le stub obsolète -- suite verte
- [x] `tests/Feature/ImportDocumentTest.php` -- test `GET /documents/import` -- couvre la nouvelle route

**Acceptance Criteria:**
- Given l'utilisateur clique sur "Importer un document" dans la sidebar, when la navigation aboutit, then il atterrit sur `/documents/import` (page complète, plus de popup superposée).
- Given l'utilisateur est sur `/documents/import`, when il importe un fichier valide, then il est redirigé vers la Fiche document (comportement `POST /documents` inchangé).
- Given l'utilisateur est sur `/documents/import`, when il regarde la sidebar, then "Importer un document" est visuellement actif, au même titre que "Créer un document" sur `/documents/create`.
- Given `ImportModal.vue` est supprimé, when la suite de tests s'exécute, then aucun test ne le référence plus (mocks/stubs retirés).

## Spec Change Log

<!-- Append-only, peuplé par step-04 lors des boucles de revue. -->

## Design Notes

`Documents/Import.vue` reprend intégralement le markup/la logique de `ImportModal.vue` (dropzone, `TagSelector`, `useForm`) mais sans les préoccupations propres à une modale : pas de `role="dialog"`/`aria-modal`, pas de focus trap, pas de gestion `Escape`, pas de restauration de focus au déclencheur — une page Inertia gère déjà cela nativement.

## Verification

**Commands:**
- `npm run test -- Sidebar` -- expected: tests mis à jour passent
- `npm run test -- AppLayout` -- expected: passe sans le stub `ImportModal`
- `php artisan test --filter=ImportDocumentTest` -- expected: passe, y compris le nouveau test `GET /documents/import`
- `npm run build` -- expected: build Vite sans erreur (plus de référence à `ImportModal.vue`)

**Manual checks (if no CLI):**
- Ouvrir l'app en local, cliquer "Importer un document" depuis chacune des 5 surfaces, vérifier l'arrivée sur une page (pas une popup) et l'état actif dans la sidebar.

## Suggested Review Order

**Routage & Controller**

- Point d'entrée : nouvelle route enregistrée avant `GET /documents/{document}`, même précaution que `/documents/create`.
  [`web.php:28`](../../routes/web.php#L28)

- `import()` miroir de `create()` : bare `Inertia::render()`, aucune logique métier dans le controller.
  [`DocumentController.php:256`](../../app/Http/Controllers/DocumentController.php#L256)

**Nouvelle page d'import**

- Reprend le dropzone/`useFileDropZone`/`TagSelector` de l'ancienne modale, sans wrapper `role="dialog"`.
  [`Import.vue:41`](../../resources/js/Pages/Documents/Import.vue#L41)

- POST inchangé vers `/documents` (`documents.store`), redirection vers la Fiche document toujours gérée serveur.
  [`Import.vue:54`](../../resources/js/Pages/Documents/Import.vue#L54)

- Garde de navigation ajoutée en revue (patch) : une page, contrairement à l'ancienne modale plein écran, n'empêche plus physiquement de cliquer ailleurs pendant un import en cours — ce `router.on('before', ...)` restaure cette protection (même patron déjà utilisé dans `Editor.vue`).
  [`Import.vue:75`](../../resources/js/Pages/Documents/Import.vue#L75)

**Sidebar : bouton devenu lien**

- `isImportActive` mirroring `isCreateActive` : la sidebar traite désormais "Importer un document" comme une vraie page.
  [`Sidebar.vue:55`](../../resources/js/Components/Sidebar.vue#L55)

- Bouton `@click` remplacé par un `Link` Inertia classique.
  [`Sidebar.vue:111`](../../resources/js/Components/Sidebar.vue#L111)

**Suppression de la popup**

- `resources/js/Components/ImportModal.vue` supprimé (207 lignes) — plus aucun appelant après la migration ; fichier absent du disque, non cliquable.

**Nettoyage des références obsolètes (patch, step-04)**

- Commentaire d'accessibilité de la boîte de suppression ne pointait plus vers un fichier existant — réécrit en description autonome du pattern.
  [`Show.vue:239`](../../resources/js/Pages/Documents/Show.vue#L239)

**Tests**

- Nouveau test Feature de la route dédiée.
  [`ImportDocumentTest.php:145`](../../tests/Feature/ImportDocumentTest.php#L145)

- Navigation Sidebar (lien, pas de modale) et état actif miroir de "Créer un document".
  [`Sidebar.spec.js:74`](../../resources/js/Components/__tests__/Sidebar.spec.js#L74)
  [`Sidebar.spec.js:114`](../../resources/js/Components/__tests__/Sidebar.spec.js#L114)

- Stub `ImportModal` devenu inutile, retiré des deux tests `AppLayout`.
  [`AppLayout.spec.js:13`](../../resources/js/Layouts/__tests__/AppLayout.spec.js#L13)
