---
title: 'Exporter un document créé en Word'
type: 'feature'
created: '2026-09-07'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '219f5ca159cd3b82b5ab53183693ce9cc8c174b5'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Un document `source=created` ne peut pas encore être exporté en `.docx` (FR12 non couvert) — aucune route, action ni bouton "Exporter en Word" n'existe sur la Fiche document.

**Approach:** Ajouter `ExportDocumentToWordAction`, qui convertit le `content_html` du document en `.docx` via `phpoffice/phpword` (import HTML), résout les images en chemins disque avant conversion, exposée par un bouton "Exporter en Word" (style secondaire) sur la Fiche document qui télécharge le fichier et affiche un toast ou une erreur explicite (UX-DR11/UX-DR20), en miroir de l'export PDF déjà livré (Story 2.4).

## Boundaries & Constraints

**Always:**
- Export réservé à `source=created` ; sur un document `imported`, réponse `403` (même garde que `exportPdf`, `DocumentController.php:462`).
- `ExportDocumentToWordAction` convertit `content_html` en `.docx` via `phpoffice/phpword` (`Html::addHtml()` + `IOFactory::createWriter(..., 'Word2007')`) — jamais un moteur alternatif (AD-12).
- Les `<img src="/documents/{id}/images/{file}">` du `content_html` sont résolus en chemins disque absolus avant l'appel PhpWord — jamais via une requête HTTP, l'export doit fonctionner hors contexte web (même contrainte que l'export PDF).
- Toute erreur (échec PhpWord, exception pendant la génération) est capturée : jamais un fichier dégradé livré ni une exception non catchée — réponse HTTP `422` avec message explicite proposant de réessayer (AD-12, UX-DR20).
- Succès : le fichier `.docx` est téléchargé et un toast bref "Export Word généré." s'affiche ; bouton "Exporter en Word" toujours visible, style secondaire, à côté du bouton PDF (UX-DR11).
- Une image référencée dans `content_html` mais absente du disque ne bloque pas l'export entier : elle est laissée de côté (best-effort documenté, pas garanti au niveau PDF — AD-12).

**Ask First:** aucune décision anticipée nécessitant confirmation humaine identifiée à ce stade ; si PhpWord ne fournit aucun signal exploitable pour distinguer un échec de conversion global d'un simple problème d'image isolée, demander confirmation avant d'ajouter une heuristique de détection fine.

**Never:** pas de moteur d'export Word alternatif ; pas d'export Word pour les documents `imported` (ils gardent leur fichier original) ; pas de mise en cache du `.docx` généré (régénéré à chaque export, comme le PDF).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Export document créé, sans image | GET export, `content_html` sans `<img>` | `.docx` téléchargé, toast "Export Word généré." | N/A |
| Export document créé, avec images inline | GET export, `content_html` avec `<img>` (Story 2.2) | `.docx` téléchargé, images intégrées en best-effort | N/A |
| Export d'un document `imported` | GET export, `source=imported` | Requête refusée | `403` |
| Image référencée introuvable sur disque | `<img src>` pointant vers un fichier supprimé | Export continue sans cette image | best-effort, pas de blocage |
| Échec de conversion PhpWord | Exception pendant `Html::addHtml()`/`save()` | Aucun fichier livré | `422` + message explicite, bouton reste actif pour réessayer |

</frozen-after-approval>

## Code Map

- `app/DataTransferObjects/ExportDocumentToWordData.php` (nouveau) -- readonly `{document: Document}`, même patron que `ExportDocumentToPdfData.php:7-13`.
- `app/Actions/ExportDocumentToWordAction.php` (nouveau) -- `__invoke(ExportDocumentToWordData): ?string`, résout les `<img src>` en chemins disque absolus (`Storage::disk('local')->path()`), construit un `\PhpOffice\PhpWord\PhpWord`, `Html::addHtml($section, $html)`, sauvegarde via `IOFactory::createWriter($phpWord, 'Word2007')` sur un fichier temporaire puis retourne son contenu ; try/catch/log/retour `null` en échec, même patron try/catch que `ExportDocumentToPdfAction.php:39-72`.
- `app/Http/Controllers/DocumentController.php` -- `exportWord(Document $document, ExportDocumentToWordAction $export): HttpResponse` -- garde `source=Created` identique à `exportPdf()` (`DocumentController.php:460-478`), réponse binaire `.docx` (`Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document`) en succès, `abort(422, message)` en échec.
- `routes/web.php:39` -- `GET /documents/{document}/export/word` (`documents.export.word`), juste après la route `documents.export.pdf`, même forme de suffixe distinct.
- `resources/js/Pages/Documents/Show.vue` -- bouton "Exporter en Word" (`v-if="isCreated"`, style secondaire), à côté du bouton PDF existant (lignes 412-426) -- réplique `exportToPdf()` (lignes 108-162 : fetch, blob download, filename via `Content-Disposition`, toast 3s) avec de nouveaux refs `isExportingWord`/`exportWordError`/`showExportWordToast` ; toast markup répliqué sur le patron des lignes 539-551.
- `tests/Feature/ExportDocumentToWordTest.php` (nouveau) -- réutilise les helpers `createDocumentForExport()`/`uploadDraftImageForExport()` de `ExportDocumentToPdfTest.php` (lignes 18-42) -- couvre la matrice I/O ci-dessus, dont le `403` sur `imported` (`Document::factory()->create()`).

## Tasks & Acceptance

**Execution:**
- [x] `app/DataTransferObjects/ExportDocumentToWordData.php` -- créer le DTO -- frontière d'Action propre (paradigme Thin Controller → Action → DTO).
- [x] `app/Actions/ExportDocumentToWordAction.php` -- conversion HTML → `.docx` via PhpWord + résolution disque des images -- FR12, AD-12.
- [x] `app/Http/Controllers/DocumentController.php`, `routes/web.php` -- `exportWord()` + route -- expose le flux HTTP, `403` imported, `422` échec.
- [x] `resources/js/Pages/Documents/Show.vue` -- bouton secondaire + téléchargement + toast/erreur -- UX-DR11, UX-DR20.
- [x] `tests/Feature/ExportDocumentToWordTest.php` -- couverture de la matrice I/O -- non-régression, 100% de couverture Pest.

**Acceptance Criteria:**
- Given un document `source=created` avec images inline, when je clique "Exporter en Word", then un fichier `.docx` valide est téléchargé, images intégrées en best-effort (FR12).
- Given un document `source=imported`, when la route d'export Word est appelée directement, then la réponse est `403`.
- Given un échec de conversion PhpWord, when j'exporte, then un message d'erreur explicite s'affiche et je peux réessayer, jamais un fichier dégradé livré silencieusement (AD-12).

## Design Notes

`Html::addHtml()` de PhpWord charge un `<img src="...">` local directement depuis un chemin disque (contrairement à Browsershot qui nécessitait une `data:` URI car Chromium tourne hors requête HTTP). Il suffit donc de réécrire les `src` relatifs (`/documents/{id}/images/{file}`) en chemins disque absolus avant l'appel — une regex équivalente à `ExportDocumentToPdfAction::resolveImageSources()` peut être adaptée en un point (retourner un chemin disque plutôt qu'une `data:` URI).

## Verification

**Commands:**
- `php artisan test --filter=ExportDocumentToWordTest` -- expected: verts.
- `php artisan test` -- expected: suite complète verte.
- `npm run build` -- expected: build Vite réussi.

**Manual checks (if no CLI):**
- Exporter un document créé contenant un tableau et une image insérée ; ouvrir le `.docx` téléchargé dans Word/LibreOffice et vérifier que le texte, la structure et les images sont présents (fidélité best-effort, pas pixel-perfect, AD-12).

## Suggested Review Order

**Génération du .docx (Action)**

- Point d'entrée : résout les images, neutralise les balises vides incompatibles XML, convertit via PhpWord, jamais un fichier dégradé ni une exception non catchée.
  [`ExportDocumentToWordAction.php:44`](../../app/Actions/ExportDocumentToWordAction.php#L44)

- Correctif de revue : le nettoyage du fichier temporaire est isolé dans son propre try/catch pour ne jamais transformer un succès ou un 422 déjà émis en 500 non géré.
  [`ExportDocumentToWordAction.php:86`](../../app/Actions/ExportDocumentToWordAction.php#L86)

- Résout chaque `<img src>` en chemin disque absolu (contrairement au PDF qui utilise une `data:` URI) — une image introuvable voit sa balise entière supprimée plutôt que laissée cassée.
  [`ExportDocumentToWordAction.php:119`](../../app/Actions/ExportDocumentToWordAction.php#L119)

- Corrige une incompatibilité découverte à l'implémentation : `Html::addHtml()` parse en XML strict, incompatible avec les balises vides HTML5 (`<img>`, `<br>`, `<hr>`) produites par le sanitizer existant.
  [`ExportDocumentToWordAction.php:152`](../../app/Actions/ExportDocumentToWordAction.php#L152)

**Contrat HTTP d'export**

- Garde `source=Created` avant tout traitement, même forme que la garde d'export PDF existante ; `422` explicite en cas d'échec, jamais un fichier dégradé.
  [`DocumentController.php:493`](../../app/Http/Controllers/DocumentController.php#L493)

- Route dédiée, même forme de suffixe distinct que `export/pdf`, pas de conflit d'ordre.
  [`web.php:42`](../../routes/web.php#L42)

**Bouton Export Word (Fiche document)**

- `fetch()` + téléchargement blob, miroir exact d'`exportToPdf()`, avec ses propres refs pour ne jamais bloquer un export PDF en cours.
  [`Show.vue:176`](../../resources/js/Pages/Documents/Show.vue#L176)

- Bouton style secondaire, visible uniquement pour un document créé, désactivé seulement pendant l'export.
  [`Show.vue:502`](../../resources/js/Pages/Documents/Show.vue#L502)

- Correctif de revue : les deux toasts (PDF et Word) partagent désormais un seul conteneur empilé verticalement pour ne jamais se superposer si les deux exports sont déclenchés à quelques secondes d'écart.
  [`Show.vue:643`](../../resources/js/Pages/Documents/Show.vue#L643)

**Tests**

- Correctif de revue : vérifie que l'image insérée est réellement intégrée dans le `.docx` généré (présence sous `word/media/`), pas seulement que la conversion n'a pas échoué.
  [`ExportDocumentToWordTest.php:37`](../../tests/Feature/ExportDocumentToWordTest.php#L37)

- Couvre la garde `403` sur un document `imported` et l'échec PhpWord → `422` avec message explicite et réessai possible.
  [`ExportDocumentToWordTest.php:164`](../../tests/Feature/ExportDocumentToWordTest.php#L164)
