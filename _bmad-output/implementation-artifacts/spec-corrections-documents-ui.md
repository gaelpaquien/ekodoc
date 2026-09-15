---
title: 'Corrections UI : listing documents, focus des champs, import en 2 étapes'
type: 'bugfix'
created: '2026-09-15'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '08eaa0008cb0367f87f2f0eff4cba8fab0729a97'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Trois défauts UI/UX sur le module Documents : (1) le listing affiche une étiquette de type de fichier (PDF/Word/Excel) inutile sur chaque ligne ; (2) certains champs de saisie (inputs, le combobox de tags) cumulent un ring ET une bordure au focus, au lieu d'une seule bordure lime ; (3) l'import d'un document l'enregistre immédiatement et définitivement, sans possibilité de le supprimer pour recommencer avant d'y ajouter des tags.

**Approach:** Retirer `DocumentTypeBadge` du listing (Index.vue) uniquement. Remplacer, sur les champs de saisie concernés, le combo `outline`+`ring` par une bordure `border-primary` au focus. Transformer `Import.vue` en flux à 2 étapes : upload seul (le document existe déjà en base, comme aujourd'hui) puis un écran de revue avec bouton "Supprimer" (repartir de zéro) ou sélection de tags + bouton "Enregistrer" qui finalise et redirige vers la fiche document.

## Boundaries & Constraints

**Always:**
- `ImportDocumentAction`, `SyncDocumentTagsAction`, `DeleteDocumentAction` restent les seuls points d'écriture — seule la cible de redirection du contrôleur change.
- Le focus des boutons et liens (outline+ring) reste inchangé — la correction ne touche que les champs de saisie (`input`, le combobox `TagSelector`).
- `DocumentTypeBadge.vue` reste inchangé et continue d'être utilisé par `Search.vue` et `Show.vue` — seul son usage dans `Index.vue` est retiré.

**Ask First:** Aucune décision bloquante identifiée.

**Never:** Ne pas introduire de statut "brouillon" en base pour le document importé — il reste un `Document` normal dès l'étape 1, comme aujourd'hui ; "Supprimer" en étape 2 utilise le hard-delete existant.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Upload valide | Fichier PDF/Word/Excel déposé ou choisi | Document créé (comme aujourd'hui), page reste sur `/documents/import` en mode revue (nom du fichier, bouton Supprimer, sélecteur de tags, bouton Enregistrer) | N/A |
| Suppression pour recommencer | Étape 2, clic "Supprimer" après confirmation | Document supprimé, page revient à l'écran d'upload initial | Annulation de la confirmation → aucun appel réseau |
| Enregistrement | Étape 2, tags sélectionnés (ou aucun), clic "Enregistrer" | Tags synchronisés, redirection vers `/documents/{id}` | Échec validation tags → message d'erreur affiché, reste en étape 2 |
| Navigation quittée sans Enregistrer/Supprimer | Étape 2 active, utilisateur clique un lien de la sidebar | Confirmation demandée (le document reste importé sans tags si l'utilisateur confirme) | Annulation → reste sur la page |

</frozen-after-approval>

## Code Map

- `resources/js/Pages/Documents/Index.vue:49` -- retirer `<DocumentTypeBadge ... />` du rendu de chaque ligne du listing.
- `resources/js/Pages/Documents/Configuration.vue:269,310` -- inputs `create-tag-name`/`rename-tag-name` : classe focus à corriger.
- `resources/js/Pages/Documents/Search.vue:202` -- input de recherche : classe focus à corriger.
- `resources/js/Pages/Documents/Editor.vue:541,728` -- input titre + `image-alt-input` : classe focus à corriger.
- `resources/js/Components/TagSelector.vue:192` -- input combobox (le seul "select" de l'appli) : classe focus à corriger.
  - Sur ces 6 champs, remplacer `focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background` par `focus-visible:border-primary` (la classe statique `border border-border` reste, seule sa couleur change au focus).
- `resources/js/Pages/Documents/Import.vue` -- réécriture : état `uploadedDocument` alimenté par `watch(() => page.props.flash?.uploadedDocument, ...)` (même pattern que `Editor.vue`'s `uploadedImage`, nécessite `preserveState: true` sur le `form.post`). Étape 1 = dropzone seule (TagSelector retiré d'ici). Étape 2 = résumé du fichier (réutilise `DocumentTypeBadge`) + bouton Supprimer (`window.confirm`, même style que le garde de navigation existant) + `TagSelector` + bouton Enregistrer. Garde de navigation (`router.on('before')`) étendu : avertit aussi si `uploadedDocument` est défini et non sauvegardé.
- `app/Http/Controllers/DocumentController.php`:
  - `store()` (l.203-219) -- retourner `back()->with('uploadedDocument', ['id' => $document->id, 'title' => $document->title, 'mime_type' => $document->mime_type])` au lieu de `to_route('documents.show', $document)`.
  - `destroy()` (l.455-462) -- ajouter `Request $request`, rediriger vers `documents.import` si `$request->query('redirect') === 'import'`, sinon `documents.index` (inchangé).
  - `updateTags()` (l.439-447) -- rediriger vers `documents.show` si `$request->query('redirect') === 'show'`, sinon `back()` (inchangé, toujours utilisé tel quel par `Show.vue`).
- `tests/Feature/ImportDocumentTest.php` -- les assertions `assertRedirect("/documents/{$document->id}")` après `POST /documents` doivent devenir `assertRedirect('/documents/import')` (le flash `uploadedDocument` remplace l'ancienne redirection directe) ; ajouter des cas pour `DELETE /documents/{id}?redirect=import` et `PATCH /documents/{id}/tags?redirect=show`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Pages/Documents/Index.vue` -- retirer l'usage de `DocumentTypeBadge` -- fix #1
- [x] `resources/js/Pages/Documents/Configuration.vue`, `Search.vue`, `Editor.vue`, `resources/js/Components/TagSelector.vue` -- corriger la classe focus des 6 champs listés -- fix #2
- [x] `app/Http/Controllers/DocumentController.php` -- `store()`/`destroy()`/`updateTags()` : redirections contextuelles -- fix #3 (backend)
- [x] `resources/js/Pages/Documents/Import.vue` -- flux à 2 étapes -- fix #3 (frontend)
- [x] `tests/Feature/ImportDocumentTest.php` -- mettre à jour les assertions de redirection, ajouter suppression/enregistrement -- couvre fix #3
- [x] `resources/js/Pages/Documents/__tests__/Import.spec.js` -- couverture de la garde de navigation (ligne "Navigation quittée..." de la matrice) et de l'écran de revue

**Acceptance Criteria:**
- Given le listing des documents, when il s'affiche, then aucune étiquette de type de fichier n'apparaît sur les lignes.
- Given un des 6 champs corrigés, when il reçoit le focus, then seule une bordure lime apparaît (pas de ring, pas d'outline visible en plus).
- Given un fichier valide déposé sur `/documents/import`, when l'upload réussit, then la page reste sur l'import en affichant le fichier importé, un bouton Supprimer et un sélecteur de tags + bouton Enregistrer -- aucune redirection vers la fiche document à ce stade.
- Given l'écran de revue (étape 2), when l'utilisateur clique Supprimer puis confirme, then le document est supprimé et l'écran revient à l'upload initial.
- Given l'écran de revue avec des tags sélectionnés, when l'utilisateur clique Enregistrer, then les tags sont sauvegardés et la page redirige vers `/documents/{id}`.

## Design Notes

Le document existe déjà en base dès l'étape 1 (comportement d'import inchangé) : "Supprimer" en étape 2 est un vrai hard-delete, pas l'annulation d'un brouillon. Le paramètre `?redirect=` sur `destroy()`/`updateTags()` est une convention légère pour distinguer l'appel venant d'`Import.vue` de ceux venant de `Show.vue` -- les Actions métier ne changent pas, seule la redirection HTTP en dépend.

## Verification

**Commands:**
- `php artisan test --filter=ImportDocumentTest` -- expected: tous les tests passent avec les nouvelles assertions.
- `npm run test` -- expected: aucune régression sur les specs Vue existantes (Index, Editor, Search, Configuration).

**Manual checks (if no CLI):**
- Vérifier visuellement le focus lime sans ring sur les 6 champs, en light et dark mode.

## Suggested Review Order

**Flux d'import en 2 étapes**

- Point d'entrée : l'upload flashe le document créé et revient sur la page d'import au lieu d'aller sur la fiche.
  [`DocumentController.php:211`](../../app/Http/Controllers/DocumentController.php#L211)

- Étape 2 pilotée par le flash : bascule l'écran de revue une fois `uploadedDocument` peuplé.
  [`Import.vue:48`](../../resources/js/Pages/Documents/Import.vue#L48)

- Template : dropzone seule (étape 1) vs résumé + tags + Enregistrer (étape 2).
  [`Import.vue:251`](../../resources/js/Pages/Documents/Import.vue#L251)

- "Supprimer" : hard-delete + retour à l'étape 1 via `?redirect=import`.
  [`Import.vue:128`](../../resources/js/Pages/Documents/Import.vue#L128)
  [`DocumentController.php:484`](../../app/Http/Controllers/DocumentController.php#L484)

- "Enregistrer" : sync des tags + redirection vers la fiche via `?redirect=show`.
  [`Import.vue:168`](../../resources/js/Pages/Documents/Import.vue#L168)
  [`DocumentController.php:457`](../../app/Http/Controllers/DocumentController.php#L457)

- Garde de navigation Inertia, étendue pour couvrir l'étape 2 non enregistrée.
  [`Import.vue:199`](../../resources/js/Pages/Documents/Import.vue#L199)

- Garde `beforeunload` (revue de code) : même protection pour un rechargement/fermeture d'onglet natif.
  [`Import.vue:236`](../../resources/js/Pages/Documents/Import.vue#L236)

**Retrait de l'étiquette de type dans le listing**

- `DocumentTypeBadge` retiré de la ligne de document — `Search.vue`/`Show.vue` le gardent.
  [`Index.vue:48`](../../resources/js/Pages/Documents/Index.vue#L48)

**Focus lime sans ring**

- Combobox de tags, seul "select" de l'appli.
  [`TagSelector.vue:192`](../../resources/js/Components/TagSelector.vue#L192)

- Champs de création/renommage de tag.
  [`Configuration.vue:269`](../../resources/js/Pages/Documents/Configuration.vue#L269)
  [`Configuration.vue:310`](../../resources/js/Pages/Documents/Configuration.vue#L310)

- Champ de recherche.
  [`Search.vue:202`](../../resources/js/Pages/Documents/Search.vue#L202)

- Titre et texte alternatif d'image dans l'éditeur.
  [`Editor.vue:541`](../../resources/js/Pages/Documents/Editor.vue#L541)
  [`Editor.vue:728`](../../resources/js/Pages/Documents/Editor.vue#L728)

**Tests**

- Suppression/enregistrement de l'étape 2, côté serveur.
  [`ImportDocumentTest.php:230`](../../tests/Feature/ImportDocumentTest.php#L230)

- Étape 2, garde de navigation et corrections de revue de code, côté client.
  [`Import.spec.js:118`](../../resources/js/Pages/Documents/__tests__/Import.spec.js#L118)

- Listing sans étiquette de type.
  [`Index.spec.js:70`](../../resources/js/Pages/Documents/__tests__/Index.spec.js#L70)
