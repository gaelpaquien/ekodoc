---
title: 'Associer des pièces jointes à un document créé'
type: 'feature'
created: '2026-09-10'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: 'f2de9e754033c93f023e2732d0e83578b3ee4c63'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Un document rédigé dans l'Éditeur ne peut fournir sa source originale (PDF/Word/Excel) qu'en la fusionnant dans le texte — aucun moyen d'attacher un fichier indépendamment du `content_html` (epic-3-context, FR13).

**Approche :** Table `document_attachments` + `AttachDocumentFileAction`/`DetachDocumentFileAction` (immédiats, document déjà enregistré) ; panneau latéral rétractable dans `Editor.vue` réutilisant le drop-zone d'`ImportModal.vue` ; liste lecture-seule dans `Show.vue` ; `ExtractDocumentTextJob` généralisé à `Document|DocumentAttachment` ; suppression étendue à `DeleteDocumentAction`.

## Boundaries & Constraints

**Always:** un document pas encore enregistré (`props.document === null`) peut recevoir des pièces jointes via zone temporaire (`documents/tmp/{draftToken}/attachments/{uuid}.ext`, réutilise le `draftToken` déjà généré pour les images) ; relocalisées vers `documents/{id}/attachments/` et leurs lignes créées par `CreateDocumentAction` au premier enregistrement, jamais avant ; un document déjà enregistré attache/détache immédiatement (`AttachDocumentFileAction`/`DetachDocumentFileAction`) ; nom de fichier stocké toujours un UUID régénéré serveur, jamais le nom client ; suppression complète = index → fichier original/images → pièces jointes (fichier puis ligne) → cache preview → ligne document, jamais `cascadeOnDelete()` nu pour les fichiers ; le driver Scout `database` interroge directement les colonnes nommées par les clés de `toSearchableArray()` (`WHERE <colonne> LIKE`, pas de valeur calculée en mémoire — `vendor/laravel/scout/src/Engines/DatabaseEngine.php:280-316`) → le texte agrégé des pièces jointes vit dans une vraie colonne `documents.attachments_extracted_text`, tenue à jour par `Document::syncAttachmentsExtractedText()`.

**Ask First:** aucune décision bloquante identifiée — HALT si ambiguïté en cours d'implémentation.

**Never:** ne pas faire confiance au `mime_type` client pour la relocalisation brouillon — redérivé via `Storage::mimeType()` ; pas de conversion Office→PDF pour l'aperçu d'une pièce jointe (stream direct, docx/xlsx inclus) ; ajout/retrait ne touche jamais `content_html` ni `isDirty` ; pas d'ajout/retrait depuis `Show.vue` (lecture seule, UX-DR16) ; un fichier brouillon retiré avant enregistrement reste dans `tmp/{token}/attachments/` (écart de nettoyage déjà accepté pour les images).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Ajout immédiat | Drop `.docx` valide, document déjà enregistré | Ligne créée (`pending`→`completed`), panneau recharge via `back()` | N/A |
| Format non supporté | Drop `.png` | Message nommant PDF/.docx/.xlsx, aucun envoi réseau | Validation client avant `form.post` |
| Ajout sur brouillon jamais enregistré | Drop, `props.document === null` | Stocké sous `tmp/{draftToken}/attachments/`, ajouté à la liste locale | N/A |
| Enregistrement avec pièces jointes en attente | "Enregistrer" avec 2 pièces jointes locales | Relocalisation + 2 lignes créées, jobs dispatchés après commit | Échec relocalisation → rollback |
| Retrait | "Retirer" sur doc existant | Fichier + ligne supprimés, réindexage parent | N/A |
| Aucune pièce jointe | Panneau/liste vide | "Aucune pièce jointe." toujours affiché | N/A |
| Suppression document parent | `DeleteDocumentAction` avec pièces jointes | Fichier puis ligne supprimés, avant cache preview | N/A |

</frozen-after-approval>

## Code Map

- `database/migrations/{ts}_create_document_attachments_table.php` (nouveau) -- `document_id` FK `cascadeOnDelete()` (ligne seulement), `file_path`/`mime_type`/`original_filename` string non-nullable, `extracted_text` longText nullable, `extraction_status` enum `ExtractionStatus::cases()` défaut `pending`, timestamps. Style: `database/migrations/2026_08_31_152623_create_documents_table.php:15-23`.
- `database/migrations/{ts}_add_attachments_extracted_text_to_documents_table.php` (nouveau) -- `longText('attachments_extracted_text')->nullable()->after('extracted_text')`.
- `app/Models/DocumentAttachment.php` (nouveau) -- fillable (`document_id, file_path, original_filename, mime_type, extracted_text, extraction_status`), cast `extraction_status`, `belongsTo(Document::class)`.
- `app/Models/Document.php` -- `attachments(): HasMany` (mirroir `tags()` L41-44) ; `toSearchableArray()` L52-57 ajoute `attachments_extracted_text` ; `syncAttachmentsExtractedText(): void` = reload `attachments`, `forceFill(['attachments_extracted_text' => implode/pluck/filter])->save()`.
- `app/Jobs/ExtractDocumentTextJob.php` -- constructeur `Document|DocumentAttachment $target` (`$this->document`→`$this->target` partout, L34/40/50-64/67-79/91-98) ; `extractText()`/`formatFromMimeType()`/`extractFromWord()`/`extractFromSpreadsheet()` L107-162 inchangées ; si `$target instanceof DocumentAttachment`, appeler `$target->document->syncAttachmentsExtractedText()` après chaque `save()`.
- `app/Actions/ImportDocumentAction.php:62-86` -- mirroir pour `AttachDocumentFileAction::storeFile()` (UUID régénéré au lieu du nom client).
- `app/Actions/AttachDocumentFileAction.php` (nouveau) -- `DB::transaction()` crée la ligne (`pending`) + stocke sous `documents/{document_id}/attachments/{uuid}.{ext}`, dispatch `ExtractDocumentTextJob` après commit.
- `app/Actions/DetachDocumentFileAction.php` (nouveau) -- delete fichier, `$attachment->delete()`, `$attachment->document->syncAttachmentsExtractedText()`.
- `app/Actions/UploadEditorImageAction.php` (72 lignes) -- mirroir pour `UploadDraftAttachmentAction.php` (nouveau) : stocke sous `documents/tmp/{draftToken}/attachments/{uuid}.ext`, retourne `{filename, original_filename, mime_type, draftToken}`, aucune ligne DB, aucun job.
- `app/Actions/CreateDocumentAction.php:41-71` -- `relocateDraftAttachments(?draftToken, $document, $keptDraftAttachments)` dans la même transaction : `move()` chaque fichier gardé vers `documents/{id}/attachments/{filename}`, MIME redérivé (`Storage::mimeType()`), crée la ligne (`pending`) ; retourne les lignes créées pour dispatch post-commit ; échec `move()` → rollback (mirroir `relocateDraftImages`, `Concerns/SanitizesDocumentContent.php:232-262`).
- `app/DataTransferObjects/CreateDocumentData.php:5-18` -- ajouter `array $draftAttachments = []`.
- `app/DataTransferObjects/AttachDocumentFileData.php`, `DetachDocumentFileData.php`, `UploadDraftAttachmentData.php` (nouveaux) -- mirroir `ImportDocumentData.php`/`DeleteDocumentData.php`.
- `app/Http/Requests/ImportDocumentRequest.php:39-42,55-66` -- règles/messages à dupliquer dans `AttachDocumentFileRequest.php` (nouveau, `file` seul) et `UploadDraftAttachmentRequest.php` (nouveau, mirroir `UploadEditorImageRequest` : `draft_token` uuid + `file`).
- `app/Http/Requests/CreateDocumentRequest.php:42-58` -- ajouter `draft_attachments` (`array`, `.filename`/`.original_filename` requis), normaliser `null`→`[]`.
- `app/Http/Controllers/DocumentController.php:261-270` -- mirroir pour `storeEditorAttachment()`, `back()->with('uploadedAttachment', ...)`.
- `app/Http/Controllers/DocumentController.php:231-247` (`storeCreated`) -- passer `draftAttachments`, dispatch job par ligne relocalisée après commit.
- `app/Http/Controllers/DocumentController.php` `show()`/`edit()` -- `->loadMissing('attachments')`.
- `app/Http/Controllers/DocumentAttachmentController.php` (nouveau) -- `store`→`back()` ; `destroy` (`abort_unless($attachment->document_id === $document->id, 404)`, pas de scoping implicite vérifié dans ce repo) → `back()` ; `preview`/`download` -- même garde + `Storage::disk('local')->response()/->download()` (mirroir `DocumentController::preview()` L412-421/`download()` L428-433, sans conversion Office).
- `app/Actions/DeleteDocumentAction.php:42-53` -- entre L49/L50 : `foreach attachments { delete file; delete row; }` (fichiers déjà supprimés par `deleteDirectory` L49, la boucle retire les lignes).
- `routes/web.php:23-27` -- ajouter `POST /documents/create/attachments`→`documents.editorAttachments.store` (avant `{document}` générique L28). Après L45 : `POST/GET/GET/DELETE /documents/{document}/attachments[/{attachment}[/preview|download]]`→`documents.attachments.{store,preview,download,destroy}` (avant L46 `destroy`).
- `resources/js/Components/ImportModal.vue:15-17,33-69` -- extraire constantes/validation/drag-state dans `resources/js/Composables/useFileDropZone.js` (nouveau), réutilisé ici et dans `AttachmentsPanel.vue`.
- `resources/js/Components/AttachmentsPanel.vue` (nouveau) -- panneau rétractable (tokens v2), consomme `useFileDropZone`, props `attachments` + `mode: 'immediate'|'draft'` ; `immediate` : `useForm().post('/documents/{id}/attachments', {forceFormData:true})`/`router.delete(...)` ; `draft` : POST `/documents/create/attachments`, flash `uploadedAttachment` (mirroir `uploadedImage` L401-410), retrait = splice local ; "Aucune pièce jointe." toujours affiché si vide.
- `resources/js/Pages/Documents/Editor.vue:33,440-472` -- monter `AttachmentsPanel` (`draft` si `!props.document`, sinon `immediate`) ; ajouter `draft_attachments` au payload `storeCreated` ; exclu d'`isDirty` L91-101.
- `resources/js/Pages/Documents/Show.vue:430-450` -- entrée "Pièces jointes" (nom + liens preview/download, mirroir `downloadUrl` L72-73), lecture seule.
- `database/factories/DocumentAttachmentFactory.php` (nouveau) -- mirroir `DocumentFactory.php`.
- `tests/Feature/AttachDocumentFileTest.php`, `DetachDocumentFileTest.php`, `DeleteDocumentTest.php`/`CreateDocumentTest.php` (étendre) -- conventions `ImportDocumentTest.php`/`DeleteDocumentTest.php` (`Storage::fake('local')`, `assertExists`/`assertDirectoryEmpty`).
- `resources/js/Components/__tests__/AttachmentsPanel.spec.js` (nouveau) -- conventions `TagSelector.spec.js`/`Index.spec.js`.

## Tasks & Acceptance

**Execution:**
- [x] Migrations `document_attachments` + `attachments_extracted_text` -- fondation
- [x] `DocumentAttachment.php`, `Document.php` -- relation, cast, `toSearchableArray()`, `syncAttachmentsExtractedText()`
- [x] `ExtractDocumentTextJob.php` -- généraliser `Document|DocumentAttachment`, resync parent
- [x] DTOs (`AttachDocumentFileData`, `DetachDocumentFileData`, `UploadDraftAttachmentData`, `CreateDocumentData`)
- [x] Actions (`AttachDocumentFileAction`, `DetachDocumentFileAction`, `UploadDraftAttachmentAction`)
- [x] `CreateDocumentAction.php` -- `relocateDraftAttachments()`
- [x] `DeleteDocumentAction.php` -- boucle suppression
- [x] Requests (`AttachDocumentFileRequest`, `UploadDraftAttachmentRequest`, `CreateDocumentRequest` étendu)
- [x] `DocumentAttachmentController.php` (nouveau), `DocumentController.php` (`storeEditorAttachment`, `storeCreated`, `show`, `edit`)
- [x] `routes/web.php` -- routes attachments (immédiat + brouillon)
- [x] `useFileDropZone.js` -- extraction depuis `ImportModal.vue`
- [x] `ImportModal.vue` -- consommer le composable (pas de régression)
- [x] `AttachmentsPanel.vue` -- panneau Éditeur, modes `immediate`/`draft`
- [x] `Editor.vue` -- montage panneau, `draft_attachments`, exclusion `isDirty`
- [x] `Show.vue` -- liste lecture-seule
- [x] `DocumentAttachmentFactory.php`
- [x] Tests Feature (`AttachDocumentFileTest`, `DetachDocumentFileTest`, extension `DeleteDocumentTest`/`CreateDocumentTest`) -- matrice I/O
- [x] `AttachmentsPanel.spec.js` -- deux modes + état vide

**Acceptance Criteria:**
- Given un document créé déjà enregistré ouvert dans l'Éditeur, when j'y attache un PDF valide, then la ligne existe (`pending`→`completed`) et devient cherchable en fulltexte sans entrée Scout séparée
- Given un brouillon jamais enregistré avec 2 pièces jointes ajoutées, when je clique "Enregistrer", then les 2 fichiers sont relocalisés et leurs lignes créées dans la même transaction, extraction démarrée après commit
- Given un document avec pièces jointes, when il est supprimé, then chaque fichier et chaque ligne disparaissent, jamais via `cascadeOnDelete()` nu sur les fichiers
- Given la Fiche document d'un document créé avec pièces jointes, when je la consulte, then la liste est lecture seule avec preview/téléchargement individuel

## Design Notes

Flux brouillon = mirroir du flux `draftToken` des images (`SanitizesDocumentContent::relocateDraftImages()`), mais une pièce jointe n'étant référencée nulle part dans `content_html`, le client doit transmettre explicitement au "Enregistrer" la liste des pièces jointes brouillon à conserver (`draft_attachments`) — le serveur ne peut pas la déduire d'un texte.

Driver Scout `database` : interroge directement les colonnes nommées par `toSearchableArray()`, n'exécute jamais cette méthode pour agréger en mémoire à la recherche. D'où la colonne réelle `attachments_extracted_text`, tenue à jour côté écriture.

## Verification

**Commands:**
- `php artisan test --filter=Document` -- expected: tests existants + nouveaux passent, aucune régression import/suppression/recherche
- `npm run test -- AttachmentsPanel` -- expected: tests du nouveau composant passent
- `npm run build` -- expected: build Vite sans erreur

**Manual checks (if no CLI):**
- "Aucune pièce jointe." affiché sur Éditeur et Fiche document sans pièce jointe
- Panneau (ouverture/fermeture, ajout, retrait) entièrement navigable au clavier, focus visible

## Suggested Review Order

**Schéma & modèles**

- Point d'entrée : nouvelle table, colonnes non-nullables (contrairement à `documents.file_path`).
  [`create_document_attachments_table.php:15`](../../database/migrations/2026_09_10_130000_create_document_attachments_table.php#L15)

- Colonne agrégat réelle, seule interrogeable par le driver Scout `database`.
  [`add_attachments_extracted_text_to_documents_table.php:20`](../../database/migrations/2026_09_10_130100_add_attachments_extracted_text_to_documents_table.php#L20)

- Relation `attachments()` + recalcul de l'agrégat, tenu à jour côté écriture.
  [`Document.php:53`](../../app/Models/Document.php#L53)
  [`Document.php:87`](../../app/Models/Document.php#L87)

**Écriture immédiate (document déjà enregistré)**

- Généralisation du job d'extraction à `Document|DocumentAttachment`, resync du parent après extraction.
  [`ExtractDocumentTextJob.php:45`](../../app/Jobs/ExtractDocumentTextJob.php#L45)

- Stockage avant création de ligne (ordre inversé vs `ImportDocumentAction`, `file_path` non-nullable) ; nettoyage si l'insertion échoue après coup (correctif revue).
  [`AttachDocumentFileAction.php:36`](../../app/Actions/AttachDocumentFileAction.php#L36)

- Suppression fichier + ligne + resync dans une transaction (correctif revue : état incohérent si le resync échoue seul).
  [`DetachDocumentFileAction.php:32`](../../app/Actions/DetachDocumentFileAction.php#L32)

**Flux brouillon (document jamais enregistré)**

- Relocalisation post-commit : déplace, redérive le MIME serveur (jamais celui du client), crée les lignes.
  [`CreateDocumentAction.php:114`](../../app/Actions/CreateDocumentAction.php#L114)

**Suppression complète**

- Boucle pièces jointes insérée avant le cache preview — fichiers déjà supprimés par `deleteDirectory`, la boucle retire les lignes.
  [`DeleteDocumentAction.php:60`](../../app/Actions/DeleteDocumentAction.php#L60)

**Contrôleurs & routes**

- Garde de propriété explicite (`document_id` vs `attachment->document_id`) + existence disque (correctif revue) sur chaque action.
  [`DocumentAttachmentController.php:58`](../../app/Http/Controllers/DocumentAttachmentController.php#L58)
  [`DocumentAttachmentController.php:79`](../../app/Http/Controllers/DocumentAttachmentController.php#L79)

- Routes immédiates + route brouillon, ordonnées avant les routes génériques `{document}`.
  [`web.php:32`](../../routes/web.php#L32)
  [`web.php:54`](../../routes/web.php#L54)

**Interface Éditeur**

- Panneau rétractable, deux modes (`immediate`/`draft`), réutilise le drop-zone partagé.
  [`AttachmentsPanel.vue:32`](../../resources/js/Components/AttachmentsPanel.vue#L32)

- Resynchronisation du panneau après un attach/detach immédiat — correctif du finding le plus critique de la revue (le panneau ne se rafraîchissait jamais sans ce watcher).
  [`Editor.vue:66`](../../resources/js/Pages/Documents/Editor.vue#L66)

- Garde-fou pendant un upload brouillon en cours au moment d'"Enregistrer" (correctif revue).
  [`Editor.vue:475`](../../resources/js/Pages/Documents/Editor.vue#L475)

**Fiche document**

- Liste lecture seule, preview/téléchargement individuel, aucune action d'ajout/retrait.
  [`Show.vue:459`](../../resources/js/Pages/Documents/Show.vue#L459)

**Peripherals**

- Composable extrait d'`ImportModal.vue`, partagé avec le panneau — pas de régression du comportement d'import.
  [`useFileDropZone.js:19`](../../resources/js/Composables/useFileDropZone.js#L19)

- Factory reconstruit `file_path` à partir du `document_id` réel (correctif revue).
  [`DocumentAttachmentFactory.php`](../../database/factories/DocumentAttachmentFactory.php#L1)

- Tests Feature couvrant la matrice I/O (attach, format rejeté, brouillon, retrait, suppression, gardes croisées).
  [`AttachDocumentFileTest.php`](../../tests/Feature/AttachDocumentFileTest.php#L1)
  [`DetachDocumentFileTest.php`](../../tests/Feature/DetachDocumentFileTest.php#L1)
