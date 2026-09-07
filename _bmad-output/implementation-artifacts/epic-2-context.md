# Epic 2 Context: Création et export de documents

<!-- Compiled from planning artifacts. Edit freely. Regenerate with compile-epic-context if planning docs change. -->

## Goal

This epic lets a user draft a brand-new document directly inside EkoDoc, illustrate it with inline images, save it into the same library used for imported files, come back to edit it later, and export it as PDF or Word. It delivers the PRD's second core journey ("create a document for future use or sharing"), completing EkoDoc alongside Epic 1's "find and use an existing document" journey. It builds on the `documents`/`categories` tables and category selector already established by Epic 1 — no new data modeling here.

## Stories

- Story 2.1: Create and save a document in the WYSIWYG editor
- Story 2.2: Insert images inline while drafting
- Story 2.3: Edit an existing created document
- Story 2.4: Export a created document to PDF
- Story 2.5: Export a created document to Word

## Requirements & Constraints

- The editor must support headings, lists, tables, and inline images (no reusable templates/models in v1 — explicit product decision).
- Images must be insertable at the cursor position between existing text blocks while drafting, not only attachable at the end of the document.
- A saved created document must carry the exact same classification and search properties as an imported document — it must be immediately findable via search and filtering after saving.
- Export fidelity does not need to be pixel-perfect overall, but the position and rendering of inline images is a critical fidelity point, not a secondary one — this guarantee is especially strong for PDF; Word fidelity is best-effort (see Technical Decisions).
- Alt text is mandatory at the moment an image is inserted (accessibility floor).
- Editor forms must meet WCAG 2.2 AA: full keyboard navigability, visible focus indicators, tab order matching reading order.
- Voice/tone stays direct and factual, no emoji or "fun" copy, even in save/export confirmations.

## Technical Decisions

- Follow the project-wide Thin Controller → Action → DTO → Eloquent Model paradigm: controllers hold no business logic; each Action is a pure invokable class (`__invoke`) with a single named responsibility and no HTTP/Inertia awareness; every Action boundary takes a readonly DTO, never a raw array or Request.
- Created documents reuse the existing unified `Document` model (`source = created`), the existing `categories` table, and the existing category-selector component — do not introduce parallel modeling for created content. `file_path`/`mime_type` stay null for created documents; `content_html` is populated instead.
- For created documents, `extracted_text` is derived synchronously from `content_html` (tags stripped) on every save, immediately marked complete — unlike imported documents, it is never left null or deferred to a background job.
- `CategorizeDocumentAction` remains the single write path for `category_id`; the editor's save flow must invoke it rather than writing the field directly.
- Inserted images are stored as files (`storage/app/private/documents/{document_id}/images/{uuid}.{ext}`) and served via a dedicated application route — never base64-encoded inline in `content_html`. A dedicated `UploadEditorImageAction` owns this storage step.
- PDF export renders the same Blade view the editor displays and converts it via `spatie/browsershot` (real headless Chromium) — no alternative PDF engine (`dompdf`/`wkhtmltopdf`) is acceptable for this path.
- Word export uses `phpoffice/phpword`'s HTML import. Image positioning fidelity is a documented best-effort, not guaranteed to PDF-level accuracy; a detected positioning failure must surface as an explicit error message offering retry, never a silently degraded file delivered to the user.
- Both export Actions must resolve inline images by their disk file path, not by HTTP URL, so exports keep working outside of a web request context.
- No JSON API layer: editor pages render via Inertia; save and export are mutations that redirect, never `Route::apiResource`/`response()->json()`.
- Naming is English-only throughout code/classes/logs; no magic values — `source`, file types, and statuses must be enums/constants.
- Business failures (save, export) always surface as an explicit Inertia-rendered message, never an uncaught exception.
- Pest test coverage target is 100%, checked manually (`pest --coverage`) before each commit — no CI pipeline in v1.
- Stack pieces specific to this epic: TipTap 3.x (`@tiptap/vue-3`) for the editor, `spatie/browsershot` 5.4 for PDF export, `phpoffice/phpword` 1.4.0 for Word export.

## UX & Interaction Patterns

- Editor toolbar provides text formatting (headings, lists, tables) plus an "Insert image" button that inserts at the current cursor position; drag-and-drop of an image directly into the body text is also accepted.
- The category/folder selector is the same field used in the Import modal and the document sheet; it appears on Save only if not already set, stays optional, and an empty value always means "Non classé" — never a blocking state.
- Saving is silent — a brief "Enregistré." with no intrusive popup, consistent with the tool's direct, factual tone (no "Votre document a été sauvegardé avec succès..." style copy).
- The two export actions are always visible, never tucked in a hidden menu: "Exporter en PDF" uses the primary button style (most frequent target), "Exporter en Word" uses the secondary style.
- When reopening an existing created document, its content must be fully loaded into the WYSIWYG before editing is allowed, with a brief loading indicator only if the load is perceptibly slow.
- Unsaved changes get a discreet indicator (e.g., a dot on the Save button) and a confirmation dialog before the user is allowed to leave the editor.
- Export results always give feedback: a brief success toast ("Export PDF généré.") or an explicit error message on failure — never a silent failure, since export fidelity is treated as a critical requirement.

## Cross-Story Dependencies

- Story 2.2 (inline images) depends on the editor shell delivered by Story 2.1.
- Story 2.3 (editing an existing created document) reuses the same editor and save path as Story 2.1, including the `CategorizeDocumentAction` reuse rule.
- Stories 2.4 and 2.5 (PDF/Word export) depend on the `content_html` produced by Story 2.1 and the inline images stored by Story 2.2 to validate fidelity.
- The whole epic depends on Epic 1's `documents`/`categories` tables, the category-selector component, and the Scout-backed search/filter flow (Epic 1 Stories 1.6/1.7) so that a saved created document appears in library search and filtering immediately, as required by FR10.
