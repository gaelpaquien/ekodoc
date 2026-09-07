---
title: 'Story 2.1 - Créer et enregistrer un document dans l''éditeur WYSIWYG'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-2-context.md']
baseline_commit: 'b47d24eb63bf94098313cd89828d93f913b41d93'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** EkoDoc ne permet aujourd'hui que d'importer des fichiers existants — aucune façon de rédiger un document directement dans l'outil.

**Approach:** Bouton "Créer un document" sur la Bibliothèque ouvrant un Éditeur WYSIWYG (TipTap 3.x, `@tiptap/vue-3`) vide, focus sur le titre. "Enregistrer" révèle le sélecteur catégorie s'il n'est pas déjà renseigné, puis crée un `Document` (`source=created`) via `CreateDocumentAction`, avec `extracted_text` dérivé synchroniquement du HTML (AD-9), et redirige vers sa Fiche document — même flux que l'import (FR10).

## Boundaries & Constraints

**Always:** `CreateDocumentAction` est l'unique point d'écriture d'un document créé. `content_html`/`extracted_text` toujours écrits ensemble. `CategorizeDocumentAction` reste l'unique point d'écriture de `category_id` (AD-16) — jamais écrit directement par la nouvelle Action. Barre d'outils : titres, listes, tableaux (FR8).

**Ask First:** _Aucun._

**Never:** Pas d'insertion d'image (Story 2.2). Pas de ré-édition d'un document déjà créé (Story 2.3) — un second "Enregistrer" dans la même session crée un nouveau document distinct, limite connue de cette story. Pas d'indicateur "modifications non enregistrées"/confirmation de sortie (hors AC de cette story, cf. deferred-work).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Enregistrement, catégorie déjà choisie | Titre + contenu rédigés, catégorie sélectionnée, clic "Enregistrer" | `Document` créé (`source=created`, `content_html`, `extracted_text` dérivé, `extraction_status=Completed`), catégorie assignée, redirection vers la Fiche document | N/A |
| Enregistrement, catégorie non renseignée | Clic "Enregistrer" sans catégorie choisie | Le sélecteur catégorie apparaît (pas de requête envoyée) ; second clic crée le document avec `category_id=null` ("Non classé") | N/A |
| Titre vide | Contenu rédigé, titre laissé vide, "Enregistrer" | Requête rejetée, message d'erreur sous le champ titre | 422, `title` requis |
| Contenu HTML uniquement des balises (aucun texte) | Un tableau vide ou une liste vide sans texte, "Enregistrer" | Document créé, `extracted_text` vaut `null` (texte extrait vide après `strip_tags`) | N/A |
| Document créé retrouvable | Après enregistrement | Apparaît dans la Bibliothèque, filtrable via `type=created` (Story 1.7) et cherchable via son `extracted_text` (Story 1.6) | N/A |

</frozen-after-approval>

## Code Map

- `package.json` -- `@tiptap/vue-3`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/extension-table(-row/-header/-cell)`.
- `database/migrations/{ts}_add_content_html_to_documents_table.php` (nouveau) -- `content_html` longText nullable, après `mime_type`.
- `app/Models/Document.php:17-24` -- `content_html` ajouté à `$fillable`.
- `app/DataTransferObjects/CreateDocumentData.php` (nouveau) -- `{title, contentHtml}`, patron `ImportDocumentData.php`.
- `app/Actions/CreateDocumentAction.php` (nouveau) -- invokable, `Document::create()` (source=Created, content_html, extracted_text via `strip_tags`/`trim`, extraction_status=Completed), patron `ImportDocumentAction`.
- `app/Http/Requests/CreateDocumentRequest.php` (nouveau) -- title/content_html requis, category_id nullable exists, patron `CategorizeDocumentRequest.php`.
- `app/Http/Controllers/DocumentController.php` -- `create()` rend `Documents/Editor.vue` ; `storeCreated()` patron exact de `store()` (transaction Action+catégorisation, redirige `documents.show`).
- `routes/web.php:5-6` -- `GET /documents/create` **avant** `GET /documents/{document}` (sinon collision de model binding) ; `POST /documents/create`.
- `resources/js/Pages/Documents/Editor.vue` (nouveau) -- titre (focus montage), `EditorContent` TipTap (StarterKit + Table), barre d'outils, `CategoryPicker` révélé au 1er clic "Enregistrer" si non renseigné.
- `resources/js/Pages/Documents/Index.vue` -- bouton "Créer un document" à côté d'"Importer".
- `tests/Feature/CreateDocumentTest.php` (nouveau) -- patron `CategorizeDocumentTest.php`, couvre la matrice I/O.

## Tasks & Acceptance

**Execution:**
- [x] `package.json` -- dépendances TipTap -- éditeur WYSIWYG (FR8).
- [x] `database/migrations/..._add_content_html_to_documents_table.php` -- colonne `content_html`.
- [x] `app/Models/Document.php` -- `content_html` fillable.
- [x] `app/DataTransferObjects/CreateDocumentData.php` -- DTO -- frontière d'Action.
- [x] `app/Actions/CreateDocumentAction.php` -- dérive `extracted_text` synchroniquement -- pas de job différé pour un document créé (AD-9).
- [x] `app/Http/Requests/CreateDocumentRequest.php` -- validation titre/contenu requis, catégorie optionnelle.
- [x] `app/Http/Controllers/DocumentController.php` -- `create()` + `storeCreated()` -- patron `store()`.
- [x] `routes/web.php` -- routes `GET/POST /documents/create`.
- [x] `resources/js/Pages/Documents/Editor.vue` -- éditeur + sélecteur catégorie révélé au save (UX-DR10).
- [x] `resources/js/Pages/Documents/Index.vue` -- bouton "Créer un document".
- [x] `tests/Feature/CreateDocumentTest.php` -- garantit `extracted_text` dérivé et classement/recherche identiques à un import.

**Acceptance Criteria:**
- Given la Bibliothèque, when je clique "Créer un document", then l'Éditeur s'ouvre vide, focus sur le titre, avec une barre d'outils (titres, listes, tableaux).
- Given du contenu rédigé, when je clique "Enregistrer", then le sélecteur catégorie apparaît s'il n'est pas déjà renseigné, puis un `Document` (`source=created`) est créé avec `content_html` et `extracted_text` dérivé automatiquement.
- Given le document enregistré, then il possède les mêmes propriétés de classement/recherche qu'un document importé -- apparaît dans la Bibliothèque, filtrable et cherchable immédiatement (FR10).

## Design Notes

Le texte "Enregistré." (voix/ton, EXPERIENCE.md) est interprété comme "pas de confirmation intrusive" plutôt qu'un texte littéral affiché : la redirection vers la Fiche document constitue la confirmation silencieuse, cohérente avec le flux d'Import déjà en place -- aucun mécanisme de flash message n'existe dans l'app (absence déjà notée, deferred-work spec-1-8). Un flash "Enregistré." explicite est journalisé en `deferred-work.md` plutôt qu'implémenté ici.

## Verification

**Commands:**
- `npm run build` -- expected: build Vite réussi (nouvelles dépendances TipTap résolues).
- `herd php artisan test --filter=CreateDocumentTest` -- expected: verts.
- `herd php artisan test` -- expected: suite complète verte, aucune régression Epic 1.

**Manual checks (if no CLI):**
- Depuis la Bibliothèque, cliquer "Créer un document" : l'Éditeur s'ouvre vide, focus sur le titre. Rédiger un titre + contenu (titre, liste, tableau), cliquer "Enregistrer" : le sélecteur catégorie apparaît, choisir une catégorie, cliquer à nouveau "Enregistrer" : redirection vers la Fiche document, contenu visible. Retour Bibliothèque : le document apparaît, filtrable via "Créé" et cherchable par son contenu.

## Suggested Review Order

**Écriture du document créé**

- Point d'entrée : seule Action autorisée à écrire un document créé, `content_html` sanitizé avant persistance puis `extracted_text` dérivé du HTML déjà nettoyé.
  [`CreateDocumentAction.php:42`](../../app/Actions/CreateDocumentAction.php#L42)

- Allowlist de balises TipTap (FR8) : tout ce qui n'y figure pas — `<script>`, attribut `onerror=`, appel API brut — est retiré avant stockage, car le contenu est ensuite rendu sans échappement (`v-html`).
  [`CreateDocumentAction.php:36`](../../app/Actions/CreateDocumentAction.php#L36)

- Reconstruction récursive de l'arbre DOM : supprime tout nœud hors allowlist et tous les attributs des nœuds conservés.
  [`CreateDocumentAction.php:61`](../../app/Actions/CreateDocumentAction.php#L61)

- Dérivation d'`extracted_text` : espace inséré à chaque frontière de balise pour éviter la fusion de mots entre blocs adjacents (`</h1><p>`).
  [`CreateDocumentAction.php:117`](../../app/Actions/CreateDocumentAction.php#L117)

**Validation de la requête**

- `title` plafonné à 255 caractères (colonne `varchar(255)`) pour transformer un dépassement en 422 propre plutôt qu'une erreur SQL brute.
  [`CreateDocumentRequest.php:38`](../../app/Http/Requests/CreateDocumentRequest.php#L38)

- Normalisation `category_id` vide → `null` : un formulaire Inertia envoie toujours une chaîne, jamais `null`, sur le fil.
  [`CreateDocumentRequest.php:25`](../../app/Http/Requests/CreateDocumentRequest.php#L25)

**Contrôleur et routage**

- `storeCreated()` : transaction unique — `CreateDocumentAction` puis, si une catégorie est choisie, `CategorizeDocumentAction` (AD-16, seul point d'écriture de `category_id`).
  [`DocumentController.php:229`](../../app/Http/Controllers/DocumentController.php#L229)

- `create()` : rend l'Éditeur vide.
  [`DocumentController.php:216`](../../app/Http/Controllers/DocumentController.php#L216)

- `show()` expose désormais `content_html` à la Fiche document.
  [`DocumentController.php:252`](../../app/Http/Controllers/DocumentController.php#L252)

- Routes `GET`/`POST /documents/create` déclarées avant `GET /documents/{document}`, pour éviter que le model binding ne capture `create`.
  [`web.php:11`](../../routes/web.php#L11)

**Éditeur WYSIWYG (Vue)**

- Instanciation TipTap : StarterKit + extensions Table, bornée au périmètre FR8 (titres, listes, tableaux).
  [`Editor.vue:26`](../../resources/js/Pages/Documents/Editor.vue#L26)

- Sauvegarde en deux temps (UX-DR10) : le premier clic révèle le sélecteur catégorie sans requête, le suivant soumet.
  [`Editor.vue:67`](../../resources/js/Pages/Documents/Editor.vue#L67)

- Soumission : sérialise le HTML de l'éditeur dans le formulaire Inertia juste avant l'envoi.
  [`Editor.vue:78`](../../resources/js/Pages/Documents/Editor.vue#L78)

**Fiche document (rendu du contenu créé)**

- `isCreated` bascule l'affichage entre le flux fichier (import) et le rendu direct du HTML rédigé.
  [`Show.vue:78`](../../resources/js/Pages/Documents/Show.vue#L78)

- Rendu `v-html` du contenu créé — dépend entièrement de la sanitization côté serveur en amont (`CreateDocumentAction.php:61`).
  [`Show.vue:324`](../../resources/js/Pages/Documents/Show.vue#L324)

- Entrée depuis la Bibliothèque : bouton "Créer un document" à côté d'"Importer".
  [`Index.vue:264`](../../resources/js/Pages/Documents/Index.vue#L264)

**Schéma et modèle**

- Nouvelle colonne `content_html`, nullable — les documents importés ne la renseignent jamais.
  [`2026_09_02_090000_add_content_html_to_documents_table.php:20`](../../database/migrations/2026_09_02_090000_add_content_html_to_documents_table.php#L20)

- `content_html` ajouté au `$fillable` du modèle.
  [`Document.php:22`](../../app/Models/Document.php#L22)

**Tests**

- Couverture de la sanitization : script et attribut d'événement retirés avant écriture.
  [`CreateDocumentTest.php:148`](../../tests/Feature/CreateDocumentTest.php#L148)

- Couverture de la matrice I/O du spec (catégorie choisie/non renseignée, titre/contenu vide, contenu sans texte, retrouvabilité).
  [`CreateDocumentTest.php:18`](../../tests/Feature/CreateDocumentTest.php#L18)
