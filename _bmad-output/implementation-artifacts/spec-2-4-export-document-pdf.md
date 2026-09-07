---
title: 'Exporter un document créé en PDF'
type: 'feature'
created: '2026-09-07'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: 'ba33b7aed7e107c64d7e8852347053ec0594b4d0'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Un document `source=created` ne peut pas encore être exporté en PDF (FR11 non couvert) — aucune route, action ni bouton "Exporter en PDF" n'existe sur la Fiche document.

**Approach:** Ajouter `ExportDocumentToPdfAction`, qui rend une vue Blade du `content_html` du document (mêmes styles visuels que l'éditeur, images résolues en chemins disque) et la convertit via `spatie/browsershot` (Chromium headless réel, AD-11), exposée par un bouton "Exporter en PDF" sur la Fiche document qui télécharge le fichier et affiche un toast ou une erreur explicite (UX-DR11/UX-DR20).

## Boundaries & Constraints

**Always:**
- Export réservé à `source=created` ; sur un document `imported`, réponse `403`.
- `ExportDocumentToPdfAction` rend `resources/views/documents/export-pdf.blade.php` et convertit via `spatie/browsershot` — jamais `dompdf`/`wkhtmltopdf` (AD-11).
- Les `<img src="/documents/{id}/images/{file}">` du `content_html` sont résolus en chemins disque (ou data URI) avant le rendu Blade — jamais via une requête HTTP, l'export doit fonctionner hors contexte web.
- Toute erreur (Browsershot indisponible, timeout, échec de conversion) est capturée : jamais un fichier dégradé ni une exception non catchée — réponse HTTP `422` avec message explicite.
- Succès : le fichier est téléchargé et un toast bref "Export PDF généré." s'affiche ; bouton "Exporter en PDF" toujours visible, style primaire (UX-DR11).
- Le gabarit Blade reprend les règles CSS de `.tiptap-content` déjà définies dans l'éditeur, pour un rendu visuellement cohérent (titres, listes, tableaux, images).
- Le PDF est régénéré à chaque export, jamais mis en cache (contrairement à la prévisualisation Office).

**Ask First:** si Browsershot ne détecte pas correctement le Chrome local déjà installé (`C:\Program Files\Google\Chrome\Application\chrome.exe`) et que la seule solution est de laisser `puppeteer` télécharger son propre Chromium (~300 Mo), demander confirmation avant.

**Never:** pas de moteur PDF alternatif ; pas d'export PDF pour les documents `imported` (ils ont déjà un PDF natif ou passent par la prévisualisation Office existante) ; pas de mise en cache du PDF généré.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Export document créé, sans image | GET export, `content_html` sans `<img>` | PDF téléchargé, toast "Export PDF généré." | N/A |
| Export document créé, avec images inline | GET export, `content_html` avec `<img>` (Story 2.2) | PDF téléchargé, position/rendu des images fidèles (NFR5) | N/A |
| Export d'un document `imported` | GET export, `source=imported` | Requête refusée | `403` |
| Échec de conversion Browsershot | Chromium indisponible/timeout | Aucun fichier livré | `422` + message explicite, bouton reste actif pour réessayer |

</frozen-after-approval>

## Code Map

- `composer.json` -- ajoute `spatie/browsershot:^5.4` -- seule voie d'export PDF acceptée (AD-11).
- `package.json` -- ajoute `puppeteer` (devDependency) -- requis par le script node embarqué de Browsershot ; Chrome système déjà présent, à pointer via un nouvel env `BROWSERSHOT_CHROME_PATH`.
- `config/services.php:50` -- ajoute un bloc `browsershot.chrome_path` (`env('BROWSERSHOT_CHROME_PATH')`) -- même patron que le bloc `libreoffice` existant juste au-dessus.
- `app/DataTransferObjects/ExportDocumentToPdfData.php` (nouveau) -- readonly `{document: Document}`, patron `ConvertDocumentToPreviewData.php`.
- `app/Actions/ExportDocumentToPdfAction.php` (nouveau) -- rend la vue `export-pdf`, résout les `<img src>` en chemins disque, convertit via Browsershot ; jamais d'exception non catchée -- patron try/catch/log/retour explicite de `app/Actions/ConvertDocumentToPreviewAction.php`.
- `resources/views/documents/export-pdf.blade.php` (nouveau) -- HTML minimal + CSS inline reprenant les règles `.tiptap-content` de `resources/js/Pages/Documents/Editor.vue:677-747`.
- `app/Http/Controllers/DocumentController.php` -- `exportPdf(Document $document, ExportDocumentToPdfAction $export)` -- garde `source=Created` (patron `edit()`), réponse binaire en succès ou `abort(422, message)` en échec (patron `previewOfficeDocument()`).
- `routes/web.php:35` -- `GET /documents/{document}/export/pdf` (`documents.export.pdf`) -- aux côtés de `preview`/`download`, même forme de suffixe distinct, pas de conflit d'ordre.
- `resources/js/Pages/Documents/Show.vue:295-331` -- bouton "Exporter en PDF" (`v-if="isCreated"`, style primaire) -- `fetch()` + téléchargement blob côté client + toast/erreur inline (aucun composant toast existant dans le projet, à introduire minimalement ici).
- `tests/Feature/ExportDocumentToPdfTest.php` (nouveau) -- patron `tests/Feature/UpdateDocumentTest.php` -- couvre la matrice I/O ci-dessus, dont le `403` sur `imported`.

## Tasks & Acceptance

**Execution:**
- [x] `composer.json`/`package.json`/`config/services.php` -- installer et brancher Browsershot + Chrome local -- rend AD-11 opérationnel.
- [x] `app/DataTransferObjects/ExportDocumentToPdfData.php`, `app/Actions/ExportDocumentToPdfAction.php` -- rendu Blade + conversion Browsershot + résolution disque des images -- FR11, NFR5.
- [x] `resources/views/documents/export-pdf.blade.php` -- gabarit visuellement fidèle à l'éditeur -- cohérence de rendu.
- [x] `app/Http/Controllers/DocumentController.php`, `routes/web.php` -- `exportPdf()` + route -- expose le flux HTTP, `403` imported, `422` échec.
- [x] `resources/js/Pages/Documents/Show.vue` -- bouton + téléchargement + toast/erreur -- UX-DR11, UX-DR20.
- [x] `tests/Feature/ExportDocumentToPdfTest.php` -- couverture de la matrice I/O -- non-régression.

**Acceptance Criteria:**
- Given un document `source=created` avec images inline, when je clique "Exporter en PDF", then le fichier téléchargé conserve fidèlement la position et le rendu des images (NFR5).
- Given un document `source=imported`, when la route d'export est appelée directement, then la réponse est `403`.
- Given une conversion Browsershot en échec, when j'exporte, then un message d'erreur explicite s'affiche et je peux réessayer, jamais un fichier dégradé livré silencieusement.

## Design Notes

Le bouton déclenche un `fetch()` plutôt qu'un simple `<a href>` : cela permet de distinguer succès/échec côté client (statut HTTP) et d'afficher un toast ou un message d'erreur sans navigation complète — un `<a>` classique masquerait un `422` derrière la page d'erreur par défaut de Laravel. En succès, le blob PDF reçu est transformé en téléchargement via une ancre temporaire (`URL.createObjectURL`).

Les images sont résolues en chemins disque (ou data URI) au moment du rendu Blade plutôt que via la route `documents.images.show` : Browsershot exécute Chromium en dehors de toute requête HTTP applicative, donc un `src` relatif ne se résoudrait pas. Cette conversion est strictement transitoire (jamais persistée), contrairement à l'interdiction du base64 dans `content_html` en base (AD-14), qui elle reste inchangée.

## Verification

**Commands:**
- `php artisan test --filter=ExportDocumentToPdfTest` -- expected: verts.
- `php artisan test` -- expected: suite complète verte.
- `npm run build` -- expected: build Vite réussi.

**Manual checks (if no CLI):**
- Exporter un document créé contenant un tableau et une image insérée ; ouvrir le PDF téléchargé et vérifier visuellement que la position/le rendu de l'image et du tableau correspondent à ce qui est affiché dans l'éditeur/la Fiche document.

## Suggested Review Order

**Génération du PDF (Action + gabarit)**

- Point d'entrée : rend le gabarit Blade puis convertit via Browsershot/Chromium réel, jamais dompdf/wkhtmltopdf (AD-11).
  [`ExportDocumentToPdfAction.php:35`](../../app/Actions/ExportDocumentToPdfAction.php#L35)

- Correctif de revue : une chaîne vide retournée par Browsershot est traitée comme un échec, jamais un PDF dégradé servi en 200.
  [`ExportDocumentToPdfAction.php:57`](../../app/Actions/ExportDocumentToPdfAction.php#L57)

- Résout chaque `<img src>` en `data:` URI lue sur le disque local — Chromium tourne hors contexte HTTP, un chemin d'app ne se résoudrait pas.
  [`ExportDocumentToPdfAction.php:92`](../../app/Actions/ExportDocumentToPdfAction.php#L92)

- Rendue `public` en revue pour être testée directement, indépendamment d'un comptage d'octets sur le PDF final.
  [`ExportDocumentToPdfAction.php:85`](../../app/Actions/ExportDocumentToPdfAction.php#L85)

- Gabarit minimal reprenant les règles `.tiptap-content` de l'éditeur pour un rendu visuellement cohérent.
  [`export-pdf.blade.php:1`](../../resources/views/documents/export-pdf.blade.php#L1)

**Contrat HTTP d'export**

- Garde `source=Created` avant tout traitement, même forme que la garde d'édition existante.
  [`DocumentController.php:462`](../../app/Http/Controllers/DocumentController.php#L462)

- Échec de l'Action → `422` explicite ; succès → réponse binaire `Content-Disposition: attachment`.
  [`DocumentController.php:460`](../../app/Http/Controllers/DocumentController.php#L460)

- Route dédiée, même forme de suffixe distinct que `preview`/`download`, pas de conflit d'ordre.
  [`web.php:39`](../../routes/web.php#L39)

**Bouton Export PDF (Fiche document)**

- `fetch()` plutôt qu'un `<a href>` classique : distingue succès/échec du statut HTTP sans masquer un `422` derrière la page d'erreur par défaut.
  [`Show.vue:108`](../../resources/js/Pages/Documents/Show.vue#L108)

- Correctif de revue : le nom de fichier téléchargé est lu depuis l'en-tête `Content-Disposition` de la réponse plutôt que reconstruit côté client, pour éviter toute divergence avec le nom slugifié côté serveur.
  [`Show.vue:98`](../../resources/js/Pages/Documents/Show.vue#L98)

- Bouton toujours visible/style primaire (UX-DR11), seul `:disabled` change pendant l'export.
  [`Show.vue:422`](../../resources/js/Pages/Documents/Show.vue#L422)

**Installation Browsershot/Chrome local**

- Ajoute `spatie/browsershot`, seule voie d'export PDF acceptée par AD-11.
  [`composer.json:16`](../../composer.json#L16)

- Correctif de revue : persiste `skipDownload` dans la config native de puppeteer (`getConfiguration()`) — `.npmrc` n'est pas lu par son installeur — pour qu'un `npm install` futur ne retélécharge jamais silencieusement son propre Chromium (~300 Mo).
  [`package.json:1`](../../package.json#L1)

- Bloc de config pointant vers le Chrome système déjà installé, même patron que le bloc `libreoffice` existant.
  [`config/services.php:54`](../../config/services.php#L54)

**Tests**

- Correctif de revue : teste directement `resolveImageSources()` contre les octets réels du fichier stocké — un test au niveau PDF ne distinguait pas une image correctement intégrée d'un `src` cassé laissé tel quel.
  [`ExportDocumentToPdfTest.php:94`](../../tests/Feature/ExportDocumentToPdfTest.php#L94)

- Couvre la garde `403` sur un document `imported` et l'échec Browsershot → `422` avec message explicite.
  [`ExportDocumentToPdfTest.php:134`](../../tests/Feature/ExportDocumentToPdfTest.php#L134)
