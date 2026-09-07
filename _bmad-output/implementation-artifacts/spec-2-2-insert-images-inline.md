---
title: 'Insérer des images à la volée pendant la rédaction'
type: 'feature'
created: '2026-09-07'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: 'f9c8eb9664e6484a87e5a7baa592ac2563b139b1'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** L'éditeur WYSIWYG (Story 2.1) ne permet que du texte formaté — aucune image ne peut être insérée pendant la rédaction (FR9), alors qu'un document tout juste ouvert n'a pas encore d'`id` (aucun `Document` n'existe avant "Enregistrer"), ce qui empêche d'appliquer directement la convention de stockage `documents/{document_id}/images/{uuid}.{ext}` (AD-14).

**Approach:** Ajouter l'extension TipTap Image, un bouton "Insérer une image" et le glisser-déposer (UX-DR8), tous deux capturant un texte alternatif obligatoire (UX-DR24) puis passant par un nouvel `UploadEditorImageAction` qui stocke le fichier dans une zone temporaire keyée par un token généré côté client (`documents/tmp/{token}/images/{uuid}.{ext}`). Au clic "Enregistrer", `CreateDocumentAction` déplace ces fichiers vers le dossier final du document et réécrit les URLs dans `content_html`.

## Boundaries & Constraints

**Always:**
- Chaque image passe par `UploadEditorImageAction` ; jamais de base64 inline dans `content_html` (AD-14).
- Texte alternatif obligatoire, validé côté serveur (`required`), pas seulement côté client (UX-DR24).
- Le bouton toolbar et le glisser-déposer partagent le même chemin de code (upload + capture d'alt) ; jamais d'aperçu blob/base64 injecté directement dans le document TipTap.
- Communication upload → éditeur via redirection Inertia (`return back()`) + prop `flash` partagée, jamais `response()->json()` (AD-13).
- Le sanitizer de `CreateDocumentAction` autorise `<img>` mais ne conserve que `src`/`alt` ; `src` doit correspondre au format interne attendu (route d'images de l'app), sinon la balise est supprimée comme tout autre contenu non conforme.
- `Document::create()` + déplacement disque (`tmp/{token}` → `{document_id}`) + réécriture de `content_html` s'exécutent dans une même `DB::transaction` (échec de déplacement = rollback complet, jamais un document sauvegardé avec une image cassée).
- Segments de route `{token}`/`{filename}` contraints (UUID, pas de `/` ni `..`) contre le path traversal.

**Ask First:** si l'extension TipTap Image entre en conflit avec une fonctionnalité de `StarterKit` déjà utilisée (Story 2.1) au point de devoir en désactiver une, arrêter et demander avant de toucher au comportement existant.

**Never:** pas de nettoyage automatique des dossiers `tmp/{token}` abandonnés (drafts jamais enregistrés) dans cette story — à journaliser en dette technique. Pas de redimensionnement/compression d'image. Pas d'upload multi-images en un geste. Pas de modification de l'alt après insertion. Le chargement d'un document créé existant dans l'éditeur (Story 2.3) reste hors périmètre — cette story ne couvre que le brouillon d'un document neuf, sans `id`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Insertion réussie en brouillon | Image + alt via bouton ou drop, document jamais enregistré | Fichier stocké sous `tmp/{token}/images/{uuid}.ext` ; `<img>` inséré au curseur, visible immédiatement dans l'éditeur | N/A |
| Enregistrement avec image insérée | Clic "Enregistrer" avec `content_html` contenant un `<img>` temporaire | Fichiers déplacés vers `documents/{id}/images/` ; `content_html` réécrit avec les URLs finales ; image visible sur la Fiche document | Échec du déplacement → transaction annulée, message d'erreur explicite, aucun `Document` créé |
| Alt manquant | Fichier image sans texte alternatif | Requête rejetée, aucun fichier stocké | Erreur de validation sur `alt` |
| Fichier non-image | `.pdf`/`.docx` envoyé à l'endpoint d'upload | Requête rejetée, aucun fichier stocké | Erreur de validation sur `image` |
| Enregistrement sans image | Document sauvegardé sans jamais avoir inséré d'image (comportement Story 2.1 inchangé) | Aucun dossier `tmp/{token}` à déplacer — no-op silencieux | N/A |
| `content_html` forgé (requête hors éditeur) | `<img src="https://evil.example/x">` ou `onerror=` injecté directement dans la requête | Balise/attribut non conforme supprimé par le sanitizer, comme aujourd'hui pour les autres balises | N/A |

</frozen-after-approval>

## Code Map

- `package.json` -- ajoute `@tiptap/extension-image`.
- `app/DataTransferObjects/UploadEditorImageData.php` (nouveau) -- `{draftToken, image: UploadedFile, alt}`, patron `ImportDocumentData.php`.
- `app/Http/Requests/UploadEditorImageRequest.php` (nouveau) -- `draft_token` requis uuid, `image` requis `File::image()->max(5*1024)`, `alt` requis string max:255, patron `ImportDocumentRequest.php`.
- `app/Actions/UploadEditorImageAction.php` (nouveau) -- invokable, stocke sous `storage/app/private/documents/tmp/{token}/images/{uuid}.{ext}`, retourne url/alt/filename.
- `app/Actions/CreateDocumentAction.php` -- `ALLOWED_TAGS` += `img` avec allowlist d'attributs dédiée (`src`,`alt`) validant le format de `src` ; nouvelle étape post-`Document::create()` : déplacement `tmp/{token}` → `{id}` + réécriture `content_html`, le tout dans `DB::transaction()` (aligné sur le patron `ImportDocumentAction`).
- `app/DataTransferObjects/CreateDocumentData.php` -- ajoute `draftToken` (nullable).
- `app/Http/Requests/CreateDocumentRequest.php` -- ajoute règle `draft_token` nullable|uuid.
- `app/Http/Controllers/DocumentController.php` -- `storeEditorImage()` (POST, appelle l'Action, `return back()->with('uploadedImage', ...)`), `serveDraftImage($token, $filename)` (GET, stream depuis `tmp/`), `serveDocumentImage(Document $document, $filename)` (GET, stream depuis le dossier final), patron `preview()`/`download()`.
- `app/Http/Middleware/HandleInertiaRequests.php` -- ajoute `'flash' => fn () => ['uploadedImage' => session('uploadedImage')]` au `share()`.
- `routes/web.php` -- `POST /documents/create/images`, `GET /documents/editor-images/tmp/{token}/{filename}`, `GET /documents/{document}/images/{filename}`.
- `resources/js/Pages/Documents/Editor.vue` -- extension `Image` TipTap ; `draftToken` généré côté client (`crypto.randomUUID()`) ; bouton "Insérer une image" + zone de drop, capture d'alt inline avant upload ; `watch` sur `usePage().props.flash.uploadedImage` pour insérer au curseur ; `draft_token` inclus dans le `useForm` d'enregistrement.
- `tests/Feature/UploadEditorImageTest.php` (nouveau) -- couvre la matrice I/O upload/serve.
- `tests/Feature/CreateDocumentTest.php` -- ajoute le cas déplacement + réécriture au save.

## Tasks & Acceptance

**Execution:**
- [x] `package.json` -- `@tiptap/extension-image` -- support image dans le document TipTap (FR9).
- [x] `app/DataTransferObjects/UploadEditorImageData.php` -- DTO -- frontière d'Action.
- [x] `app/Http/Requests/UploadEditorImageRequest.php` -- validation fichier/alt -- UX-DR24.
- [x] `app/Actions/UploadEditorImageAction.php` -- stockage zone temporaire -- AD-14 sans `document_id` encore disponible.
- [x] `app/Actions/CreateDocumentAction.php` -- déplacement + réécriture transactionnels, sanitizer étendu à `img` -- clôt le brouillon sans casser AD-14/sécurité XSS.
- [x] `app/Http/Controllers/DocumentController.php` -- 3 nouvelles routes/méthodes -- upload, service temporaire, service final.
- [x] `app/Http/Middleware/HandleInertiaRequests.php` -- prop `flash` -- retour d'upload sans `response()->json()` (AD-13).
- [x] `routes/web.php` -- 3 routes -- contraintes anti path-traversal.
- [x] `resources/js/Pages/Documents/Editor.vue` -- bouton + drop + alt + insertion réactive.
- [x] `tests/Feature/UploadEditorImageTest.php` -- matrice I/O.
- [x] `tests/Feature/CreateDocumentTest.php` -- cas déplacement/réécriture.

**Acceptance Criteria:**
- Given l'éditeur ouvert sur un document neuf (non enregistré), when j'insère une image via le bouton ou un glisser-déposer avec un texte alternatif, then elle apparaît immédiatement à la position du curseur, entre deux blocs de texte.
- Given une image insérée en brouillon, when je clique "Enregistrer", then l'image est visible et fonctionnelle sur la Fiche document, servie par une route applicative dédiée, jamais en base64.
- Given l'insertion d'une image sans texte alternatif, when je tente de l'insérer, then l'insertion est refusée tant que l'alt n'est pas renseigné.

## Design Notes

Le token de brouillon est généré côté client (`crypto.randomUUID()`) à l'ouverture de l'éditeur pour un document neuf — aucun aller-retour serveur nécessaire avant la première image. Le retour d'upload passe par `return back()` (redirection Inertia standard, cohérente avec le reste de l'app) plutôt qu'un JSON ad hoc : la nouvelle prop partagée `flash.uploadedImage` est lue côté client via un `watch`, ce qui préserve l'état local du composant (`preserveState`) et donc le contenu en cours de frappe. La réécriture de `content_html` au save est un simple remplacement de préfixe (`/documents/editor-images/tmp/{token}/` → `/documents/{id}/images/`), déterministe car les deux formats d'URL sont entièrement maîtrisés par cette story.

## Verification

**Commands:**
- `npm run build` -- expected: build Vite réussi (nouvelle dépendance `@tiptap/extension-image` résolue).
- `php artisan test --filter=UploadEditorImageTest` -- expected: verts.
- `php artisan test --filter=CreateDocumentTest` -- expected: verts, y compris le nouveau cas déplacement/réécriture.
- `php artisan test` -- expected: suite complète verte, aucune régression Epic 1/Story 2.1.

## Suggested Review Order

**Zone temporaire, sanitizer, et clôture transactionnelle du brouillon**

- Point d'entrée : le brouillon se ferme en une transaction qui crée le `Document` puis relocalise ses images.
  [`CreateDocumentAction.php:75`](../../app/Actions/CreateDocumentAction.php#L75)

- Le sanitizer n'autorise `<img>` que si son `src` correspond exactement au brouillon courant (AD-14 sans `document_id`).
  [`CreateDocumentAction.php:183`](../../app/Actions/CreateDocumentAction.php#L183)

- Seuls les fichiers encore référencés dans `content_html` sont déplacés -- une image insérée puis retirée reste en `tmp/`.
  [`CreateDocumentAction.php:291`](../../app/Actions/CreateDocumentAction.php#L291)

- Le déplacement + réécriture de préfixe se fait après coup ; un échec fait échouer toute la transaction.
  [`CreateDocumentAction.php:222`](../../app/Actions/CreateDocumentAction.php#L222)

- Stockage initial de l'image sous `documents/tmp/{token}/images/{uuid}.ext` -- l'extension vient uniquement du contenu validé, jamais du nom client.
  [`UploadEditorImageAction.php:29`](../../app/Actions/UploadEditorImageAction.php#L29)

- Le fichier doit être une image raster réelle (pas de SVG) -- ferme le risque de script embarqué servi tel quel.
  [`UploadEditorImageRequest.php:21`](../../app/Http/Requests/UploadEditorImageRequest.php#L21)

**Communication upload → éditeur sans API JSON (AD-13)**

- L'upload redirige simplement (`back()`) et ne renvoie jamais de JSON -- conforme à AD-13.
  [`DocumentController.php:267`](../../app/Http/Controllers/DocumentController.php#L267)

- Le `flash.uploadedImage` partagé porte le `draftToken` -- seul canal de retour vers l'éditeur.
  [`HandleInertiaRequests.php:61`](../../app/Http/Middleware/HandleInertiaRequests.php#L61)

- L'éditeur ignore un flash dont le `draftToken` ne correspond pas au sien -- ferme le mélange entre onglets/brouillons concurrents.
  [`Editor.vue:261`](../../resources/js/Pages/Documents/Editor.vue#L261)

**Service des images (brouillon et final)**

- Sert une image de brouillon depuis sa zone temporaire, avant tout enregistrement.
  [`DocumentController.php:287`](../../app/Http/Controllers/DocumentController.php#L287)

- Sert une image finale depuis le dossier définitif du document une fois déplacée.
  [`DocumentController.php:303`](../../app/Http/Controllers/DocumentController.php#L303)

- `{token}`/`{filename}` contraints au format UUID -- ferme le path traversal à la racine.
  [`web.php:13`](../../routes/web.php#L13)

**Éditeur : insertion (bouton + glisser-déposer) et alt obligatoire**

- Le token de brouillon est généré côté client, sans aller-retour serveur, dès l'ouverture.
  [`Editor.vue:20`](../../resources/js/Pages/Documents/Editor.vue#L20)

- Le dépôt d'un fichier ouvre toujours le dialogue d'alt -- même un fichier non-image, pour laisser la validation serveur produire un message explicite.
  [`Editor.vue:144`](../../resources/js/Pages/Documents/Editor.vue#L144)

- L'upload effectif se déclenche seulement après saisie d'un texte alternatif non vide.
  [`Editor.vue:165`](../../resources/js/Pages/Documents/Editor.vue#L165)

**Tests**

- Couvre la matrice I/O de l'upload, y compris le flash vérifié via une vraie réponse Inertia.
  [`UploadEditorImageTest.php:40`](../../tests/Feature/UploadEditorImageTest.php#L40)

- Couvre le déplacement/réécriture au save, le no-op sans image, et les deux forgeries de `content_html`.
  [`CreateDocumentTest.php:188`](../../tests/Feature/CreateDocumentTest.php#L188)
