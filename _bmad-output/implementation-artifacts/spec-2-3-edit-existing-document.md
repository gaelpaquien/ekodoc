---
title: 'Modifier un document créé existant'
type: 'feature'
created: '2026-09-07'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '28a2957197622f48edbc426ea09ccaf75c539e00'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** L'Éditeur WYSIWYG (Stories 2.1/2.2) ne sait que créer un document neuf — aucune route ni prop ne permet de rouvrir un document `source=created` existant pour le corriger, et aucune protection contre la perte de modifications non enregistrées n'existe dans l'application (FR non couvert, UX-DR18/19).

**Approach:** Ajouter un couple de routes/actions `edit`/`update` réutilisant l'Éditeur existant (prop `document` optionnelle qui pré-charge `content_html`/`titre`/`catégorie` dans TipTap avant d'autoriser la saisie), extraire la logique de sanitization/dérivation/relocalisation d'images de `CreateDocumentAction` dans un trait partagé pour l'`UpdateDocumentAction`, et ajouter un suivi d'état "non enregistré" avec confirmation avant de quitter l'Éditeur.

## Boundaries & Constraints

**Always:**
- `edit()`/`update()` n'agissent que sur `source=created` ; sur un document `imported`, réponse `403`.
- `content_html` chargé dans TipTap avant que l'édition soit possible (éditeur `editable:false` jusqu'à ce que le contenu soit monté), avec un indicateur de chargement affiché seulement si le montage n'est pas instantané.
- `CategorizeDocumentAction` reste l'unique point d'écriture de `category_id` (AD-16) ; appelée dès que la valeur soumise diffère de la valeur stockée, y compris pour revenir à `null` ("Non classé") — contrairement à la création qui ne l'appelle que si non-null.
- `extracted_text` re-dérivé à chaque enregistrement via la même logique que la création (tags strippés, vide → `null`).
- La sanitization/dérivation/relocalisation d'images de `CreateDocumentAction.php` (lignes ~110-317) est extraite dans un trait partagé (`app/Actions/Concerns/SanitizesDocumentContent.php`) réutilisé tel quel par `CreateDocumentAction` (comportement inchangé, non-régression) et `UpdateDocumentAction` ; le trait accepte la liste des préfixes de `src` autorisés en paramètre.
- En édition, deux préfixes de `src` sont autorisés par le sanitizer : le dossier final du document (`documents/{id}/images/`, images déjà enregistrées) et le dossier temporaire du brouillon courant (`documents/tmp/{draftToken}/images/`, nouvelles images de cette session) — tout autre `src` est supprimé comme aujourd'hui.
- Déplacement des nouvelles images temporaires + réécriture de `content_html` + mise à jour du `Document` s'exécutent dans une seule `DB::transaction` (échec = rollback complet).
- Le formulaire d'édition indique discrètement un état "non enregistré" (ex. pastille sur le bouton Enregistrer) et bloque toute tentative de quitter l'Éditeur (navigation Inertia ou fermeture d'onglet) tant que la confirmation n'est pas donnée.
- Le bouton "Modifier" sur la Fiche document (`Show.vue`) n'apparaît que si `document.source === 'created'`, même style que les boutons Télécharger/Supprimer existants.

**Ask First:** si le montage du contenu existant dans TipTap s'avère systématiquement instantané (aucun cas réel où l'indicateur de chargement serait visible), demander si l'indicateur doit être conservé tel quel ou simplifié en no-op documenté.

**Never:** pas d'historique de versions ni d'undo inter-session. Pas de résolution de conflit d'édition concurrente (dernier enregistrement gagne, comportement implicite déjà présent partout ailleurs). Pas de nouvelle route d'upload d'image dédiée à l'édition — l'endpoint d'upload existant (`documents.editorImages.store`, brouillon keyé par token) reste inchangé et suffit. Pas de modification du parcours d'édition pour les documents `imported`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Ouverture édition, document créé | `GET /documents/{id}/edit`, `source=created` | Éditeur ouvert, `content_html`/titre/catégorie pré-remplis, édition activée après montage | N/A |
| Ouverture édition, document importé | `GET /documents/{id}/edit`, `source=imported` | Requête refusée | `403` |
| Enregistrement sans changement de catégorie | `PATCH update`, `category_id` identique à l'existant | `content_html`/`extracted_text` mis à jour ; `CategorizeDocumentAction` non appelée | N/A |
| Enregistrement avec changement de catégorie (y compris vers "Non classé") | `PATCH update`, `category_id` différent (y compris `null`) | `CategorizeDocumentAction` appelée avec la nouvelle valeur | N/A |
| Image insérée pendant l'édition | Image+alt via bouton/drop sur un document existant | Fichier relocalisé sous `documents/{id}/images/` au save, `content_html` réécrit | Échec du déplacement → transaction annulée, message explicite |
| `content_html` forgé référençant l'image d'un autre document | `src` pointant vers `documents/{autre_id}/images/...` | Balise supprimée par le sanitizer | N/A |
| Tentative de quitter avec modifications non enregistrées | Navigation Inertia ou fermeture d'onglet, formulaire "dirty" | Confirmation demandée ; navigation bloquée si annulée | N/A |

</frozen-after-approval>

## Code Map

- `app/Actions/Concerns/SanitizesDocumentContent.php` (nouveau) -- trait extrait de `CreateDocumentAction.php` (`sanitizeContentHtml` L110-134, `sanitizeNode`/`isAllowedDraftImageSrc` L136-192, `deriveExtractedText` L311-317, `relocateDraftImages` L222-278) ; méthodes paramétrées par la liste de préfixes de `src` autorisés et le dossier cible.
- `app/Actions/CreateDocumentAction.php` -- refactor pour consommer le trait avec un seul préfixe autorisé (dossier tmp du brouillon) ; comportement inchangé (tests existants doivent rester verts).
- `app/Actions/UpdateDocumentAction.php` (nouveau) -- invokable `__invoke(UpdateDocumentData $data): Document`, utilise le trait (préfixes = dossier final `{id}` + tmp courant), appelle `CategorizeDocumentAction` (`app/Actions/CategorizeDocumentAction.php:24-26`) si `categoryId` diffère de `$data->document->category_id`, le tout dans `DB::transaction`.
- `app/DataTransferObjects/UpdateDocumentData.php` (nouveau) -- readonly `{document: Document, title: string, contentHtml: string, categoryId: ?int, draftToken: ?string}`, patron `CreateDocumentData.php`.
- `app/Http/Requests/UpdateDocumentRequest.php` (nouveau) -- mêmes règles que `CreateDocumentRequest.php` (title, content_html, category_id nullable|exists, draft_token nullable|uuid) ; `authorize()` refuse si `$this->route('document')->source` n'est pas `Created`.
- `app/Http/Controllers/DocumentController.php` -- `edit(Document $document)` (abort 403 si `source` ≠ `Created`, sinon `Inertia::render('Documents/Editor', ['document' => [...]])`, patron `create()` L220-223) ; `update(UpdateDocumentRequest $request, Document $document)` (construit le DTO, appelle l'Action, `to_route('documents.show', $document)`, patron `storeCreated()` L233-255).
- `routes/web.php` -- ajoute `GET /documents/{document}/edit` (`documents.edit`) et `PATCH /documents/{document}` (`documents.update`), à côté des routes existantes (L29 et suivantes) ; pas de conflit d'ordre avec `documents.show` (suffixe distinct sur le même segment lié).
- `resources/js/Pages/Documents/Editor.vue` -- prop `document` optionnelle ; si présente : `useForm` initialisé depuis `document` (title, content_html, category_id), TipTap `content` = `document.content_html`, `editable:false` jusqu'au montage (flag `isLoadingContent`), sélecteur de catégorie visible immédiatement (pas de geste en deux temps réservé à la création) ; `submit()` poste vers `documents.update` (méthode `patch`) si `document` présent, sinon comportement create inchangé (route `/documents/create`) ; suivi `isDirty` (pastille sur le bouton Enregistrer) + garde `router.on('before', ...)` (Inertia) et `window.addEventListener('beforeunload', ...)` demandant confirmation si `isDirty`, levée après un enregistrement réussi.
- `resources/js/Pages/Documents/Show.vue` -- bouton "Modifier" (lien vers `documents.edit`) ajouté dans la zone d'actions (L295-319), visible seulement si `isCreated` (L78), même style que Télécharger/Supprimer.
- `tests/Feature/UpdateDocumentTest.php` (nouveau) -- patron `CreateDocumentTest.php` : édition charge le contenu existant, mise à jour réécrit `content_html`/`extracted_text`, changement de catégorie (y compris vers `null`) appelle `CategorizeDocumentAction`, image insérée en édition se relocalise, forgerie de `src` étrangère supprimée, `403` sur document `imported`.
- `tests/Feature/CreateDocumentTest.php` -- suite existante, exécutée en non-régression après extraction du trait (aucune modification de test attendue si le comportement est identique).

## Tasks & Acceptance

**Execution:**
- [x] `app/Actions/Concerns/SanitizesDocumentContent.php` -- extraire sanitizer/dérivation/relocalisation en trait paramétré -- réutilisation sans duplication entre création et édition.
- [x] `app/Actions/CreateDocumentAction.php` -- consommer le trait -- non-régression du flux de création.
- [x] `app/DataTransferObjects/UpdateDocumentData.php` -- DTO d'édition -- frontière d'Action.
- [x] `app/Http/Requests/UpdateDocumentRequest.php` -- validation + autorisation `source=created` -- ferme l'édition des documents importés.
- [x] `app/Actions/UpdateDocumentAction.php` -- update transactionnel + re-catégorisation conditionnelle -- AD-16, cohérence avec la création.
- [x] `app/Http/Controllers/DocumentController.php` -- `edit()`/`update()` -- expose le flux d'édition côté HTTP.
- [x] `routes/web.php` -- routes `documents.edit`/`documents.update` -- conventions RESTful existantes.
- [x] `resources/js/Pages/Documents/Editor.vue` -- mode édition (chargement, catégorie immédiate, save vers update, suivi dirty + confirmation de sortie) -- UX-DR18/19.
- [x] `resources/js/Pages/Documents/Show.vue` -- bouton "Modifier" conditionnel -- point d'entrée du parcours.
- [x] `tests/Feature/UpdateDocumentTest.php` -- matrice I/O -- couverture du nouveau flux.

**Acceptance Criteria:**
- Given une Fiche document `source=created`, when je clique "Modifier", then l'Éditeur s'ouvre avec `content_html` déjà chargé dans le WYSIWYG avant que je puisse taper, avec un indicateur bref si le chargement est perceptible.
- Given des modifications non enregistrées dans l'Éditeur, when je tente de le quitter (navigation ou fermeture d'onglet), then une confirmation est demandée et la sortie est bloquée si je l'annule.
- Given un document en cours d'édition dont la catégorie change, when j'enregistre, then `CategorizeDocumentAction` est invoquée avec la nouvelle valeur, y compris pour repasser à "Non classé".

## Design Notes

Le trait partagé ne change pas le comportement de la création : les préfixes de `src` autorisés qu'on lui passe pour `CreateDocumentAction` restent strictement identiques à la logique actuelle (un seul préfixe, le dossier tmp du brouillon courant). Pour l'édition, la liste passée au trait s'étend au dossier final déjà existant du document, ce qui permet de conserver les images déjà enregistrées lors d'un nouveau passage du sanitizer sans les traiter comme forgées. Le suivi "dirty" compare l'état courant (titre, HTML de l'éditeur, catégorie) à l'état chargé initialement plutôt que d'utiliser un compteur d'évènements, pour éviter les faux positifs (ex. un clic dans l'éditeur sans modification réelle).

## Verification

**Commands:**
- `npm run build` -- expected: build Vite réussi.
- `php artisan test --filter=UpdateDocumentTest` -- expected: verts.
- `php artisan test --filter=CreateDocumentTest` -- expected: toujours verts après extraction du trait (non-régression).
- `php artisan test` -- expected: suite complète verte.

**Manual checks (if no CLI):**
- La ligne "Tentative de quitter avec modifications non enregistrées" de l'I/O Matrix n'a pas de couverture automatisée : le projet ne dispose d'aucun framework de test JS/Vue (Pest uniquement, décision déjà actée pour ce projet). Comportement (garde Inertia `router.on('before')` + `beforeunload`) vérifié manuellement dans le navigateur plutôt que par un nouvel outillage de test hors périmètre de cette story.

## Suggested Review Order

**Contrat HTTP d'édition**

- Entrée : deux routes RESTful distinctes ajoutées sans conflit d'ordre avec les routes existantes.
  [`web.php:33`](../../routes/web.php#L33)

- `edit()` referme l'accès aux documents importés avant même de rendre l'Éditeur.
  [`DocumentController.php:344`](../../app/Http/Controllers/DocumentController.php#L344)

- `update()` délègue toute la logique métier à l'Action, reste un simple pont HTTP.
  [`DocumentController.php:366`](../../app/Http/Controllers/DocumentController.php#L366)

- Seconde garde `source=created`, indépendante de celle du contrôleur — source de dérive potentielle si l'une évolue sans l'autre.
  [`UpdateDocumentRequest.php:19`](../../app/Http/Requests/UpdateDocumentRequest.php#L19)

**Sanitisation et relocalisation d'images partagées (trait)**

- Le sanitizer accepte désormais une liste de préfixes `src` autorisés au lieu d'un seul, pour coexister avec les images déjà enregistrées.
  [`SanitizesDocumentContent.php:159`](../../app/Actions/Concerns/SanitizesDocumentContent.php#L159)

- Correctif de revue : en cas d'échec de déplacement, seuls les fichiers déjà déplacés par cet appel sont supprimés — plus tout le dossier de destination, qui peut déjà contenir les images sauvegardées du document en édition.
  [`SanitizesDocumentContent.php:215`](../../app/Actions/Concerns/SanitizesDocumentContent.php#L215)

**Écriture transactionnelle et recatégorisation**

- Contrairement à la création, `category_id` est réévalué à chaque sauvegarde, y compris pour revenir à `null`.
  [`UpdateDocumentAction.php:46`](../../app/Actions/UpdateDocumentAction.php#L46)

- DTO frontière de l'Action ; le commentaire sur `draftToken` a été corrigé en revue pour refléter qu'il est en pratique toujours transmis.
  [`UpdateDocumentData.php:7`](../../app/DataTransferObjects/UpdateDocumentData.php#L7)

**Éditeur WYSIWYG en mode édition**

- Un seul composant sert la création et l'édition ; `props.document` bascule tout le comportement (préchargement, catégorie visible d'emblée).
  [`Editor.vue:18`](../../resources/js/Pages/Documents/Editor.vue#L18)

- Contenu chargé dans TipTap avant toute saisie ; `useEditor()` étant synchrone en pratique, ce garde-fou ne s'observe jamais réellement.
  [`Editor.vue:99`](../../resources/js/Pages/Documents/Editor.vue#L99)

- État "non enregistré" comparé à un instantané pris au montage plutôt qu'à un compteur, pour éviter les faux positifs.
  [`Editor.vue:77`](../../resources/js/Pages/Documents/Editor.vue#L77)

- Garde de sortie (Inertia + `beforeunload`) avec drapeau `programmaticNavigation` pour ne jamais bloquer les propres requêtes du composant.
  [`Editor.vue:159`](../../resources/js/Pages/Documents/Editor.vue#L159)

- `submit()` bascule entre `patch`/`post` selon la présence du document — seul point de divergence create/edit à l'envoi.
  [`Editor.vue:427`](../../resources/js/Pages/Documents/Editor.vue#L427)

**Point d'entrée Fiche document**

- Bouton "Modifier" conditionné à `isCreated`, même style que Télécharger/Supprimer comme l'exige la spec.
  [`Show.vue:316`](../../resources/js/Pages/Documents/Show.vue#L316)

**Tests**

- Couvre l'ouverture, le refus sur document importé, la (non-)recatégorisation, la relocalisation d'image et le rejet d'un `src` forgé.
  [`UpdateDocumentTest.php:66`](../../tests/Feature/UpdateDocumentTest.php#L66)
