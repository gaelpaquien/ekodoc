# Epic 1 Context: Bibliothèque de documents — importer, classer, retrouver

<!-- Compiled from planning artifacts. Edit freely. Regenerate with compile-epic-context if planning docs change. -->

## Goal

This epic delivers the core document library: import existing PDF/Word/Excel files, browse them, assign a flat category, preview and download the originals, and find them again via fulltext search or combined category/type filters. It's a standalone value ("find and use an existing document") and also the technical foundation everything else builds on: it's where the Laravel project itself gets scaffolded from a blank slate, and where the `documents`/`categories` schema, private file storage, and search infrastructure that Epic 2 (creation/export) will reuse get established.

## Stories

- Story 1.1: Import an existing document (also the project scaffolding story)
- Story 1.2: Browse the document library
- Story 1.3: Preview a document in the browser
- Story 1.4: Download the original file
- Story 1.5: Categorize a document
- Story 1.6: Search documents by content (fulltext)
- Story 1.7: Filter documents by category and type
- Story 1.8: Delete a document

## Requirements & Constraints

- Priority import formats: PDF, `.docx`, `.xlsx`. Legacy `.doc`/`.xls` are not a v1 priority. Unsupported formats must produce an immediate, explicit error naming accepted formats — never a silent rejection.
- Every document carries: title, type, category, date added. Category is optional and never blocks any action; empty = "Uncategorized".
- PDF preview must work natively (no conversion); Word/Excel via conversion. Download must always remain available independent of preview success/failure.
- Fulltext search covers document content, targets under ~1 second for a corpus of roughly 350 documents (indicative, not a hard SLA). Category and type filters must combine (multi-select) with search through the same path, not diverging logic.
- Runs entirely locally via Laravel Herd; no shared infrastructure, no authentication/roles/multi-user concerns in v1.
- Success is measured by recurring real usage (search/consult), not by import volume alone.

## Technical Decisions

- **Fresh scaffold, no starter kit.** Story 1.1 also bootstraps the project: a clean Laravel 13 install manually wired with Inertia.js 3.0 + Vue 3 + Vite 8.x + Tailwind CSS 4.x. No Breeze/Jetstream or auth scaffolding. Also install: PHP 8.5, MySQL 8.x (Herd), Laravel Scout (`database` driver), `smalot/pdfparser` 2.12.5, `phpoffice/phpword` 1.4.0, `phpoffice/phpspreadsheet`, LibreOffice (headless CLI), Pest 5.x.
- **Paradigm (all code, all epics):** Thin Controller → Action → DTO → Eloquent Model. Controllers hold no business logic — validate via Form Request, build a DTO, call exactly one Action, return an Inertia response. Actions are pure invokable classes (`{Verb}{Entity}Action`, single `__invoke(DTO $data)`), framework-agnostic. DTOs are native `readonly` classes at every Action boundary — never a raw array/`Request`. No repository layer.
- **Unified data model.** One `documents` table with discriminator `source` (`imported`|`created`) — not separate tables. Side-specific columns (`file_path`, `mime_type` vs. `content_html`, the latter used by Epic 2) stay nullable.
- **Flat categorization.** `categories` is `(id, name)` only, no hierarchy. `documents.category_id` nullable (`NULL` = "Uncategorized", never blocking). `CategorizeDocumentAction` is the single write point for `category_id`.
- **Synchronous import, queued extraction (AD-6 amended 2026-09-01).** `ImportDocumentAction` stores the file and creates the `Document` (`extraction_status = pending`) synchronously, then dispatches `ExtractDocumentTextJob` (queue `database` driver) instead of extracting inline — a large/complex real document can take longer to parse than any HTTP/proxy timeout should allow. `extraction_status` moves `pending` → `processing` → `completed`/`failed`; a document is always visible/consultable in the library regardless of its extraction status. A worker (`php artisan queue:work` or `queue:listen`) must be running for extraction to actually happen — nothing processes it automatically under Herd. Scout indexing fires via the `Searchable` trait's `saved` event, never called manually. A shared Inertia prop (`pendingExtractions`, set in `HandleInertiaRequests`) feeds a small dismissible bottom-of-screen panel (`ExtractionTasksPanel.vue`) listing documents still `pending`/`processing`, polled client-side while non-empty.
- **Private file storage.** Originals live at `storage/app/private/documents/{document_id}/{filename}`, never `public`. Download and preview always stream through an app route, checking file existence/readability first (explicit error state, never an uncaught exception).
- **Unified search/filter path.** `Document` uses `Laravel\Scout\Searchable`, indexed on `extracted_text` (not metadata). `DocumentController@index` builds results through one query entry point applying the same sort/pagination whether or not a search term is present — never two divergent code paths.
- **Best-effort text extraction.** Runs in an isolated block inside `ImportDocumentAction` (`smalot/pdfparser` for PDF; `phpoffice/phpword`/`phpspreadsheet` for Word/Excel). Failure (e.g., scanned PDF, no OCR in v1) logs a warning and leaves `extracted_text = NULL` without blocking the import.
- **Office preview via on-demand, cached conversion.** `ConvertDocumentToPreviewAction` shells out to `soffice --headless --convert-to pdf`, only for imported Word/Excel. Cached at `storage/app/private/previews/{document_id}.pdf`, invalidated only by delete/re-import. Native PDFs render via the browser's built-in viewer — no PDF.js.
- **No JSON API.** All routes render Inertia pages or redirect after mutations. No `Route::apiResource`, no `response()->json()`.
- **Permanent deletion.** No `SoftDeletes`. `DeleteDocumentAction` is the sole entry point, cleaning up in order: Scout index → original file (+ images) → preview cache → `Document` row.
- **Conventions:** English-only naming (classes, code, logs) though the UI is French; enums/constants instead of magic values for `source`, file types, statuses. One controller per resource (`DocumentController`, `CategoryController`), standard RESTful actions. Eloquent `timestamps` (UTC in DB, French formatting in Vue), auto-increment IDs (no UUIDs). Business errors always surface as an explicit Inertia message, never an uncaught exception; standard `stack` log channel. 100% Pest coverage checked manually before each commit — no CI in v1.

## UX & Interaction Patterns

- Neutral palette + a single accent color for primary actions/active states; file types distinguished by icon + label, never by color.
- Light/dark mode from v1: manual toggle, defaults to system preference.
- Document card: entire card clickable to Document Detail (no context menu); shows type badge, title, category, date added.
- Search bar: live fulltext filtering with debounce, no separate "Search" button; `/` focuses it from the Library.
- Filter chips: multi-select combining category and type; active filters visible and removable in one click; search and filters are one continuous flow.
- Import zone: drag-and-drop or file picker, dashed border, accepted formats shown explicitly. Import is a modal opened from the Library (not a separate page), closing onto the imported document's Detail page.
- Preview panel: native PDF feels instant with no processing indicator; Word/Excel conversion shows an explicit loading state while the rest of the Detail page stays usable.
- Missing/unreadable source file: explicit message in the preview panel, Download disabled — never a silent failure.
- Failed preview conversion: "Preview unavailable for this file" message, but Download stays enabled — preview failure must never block access to the original.
- Category/folder selector: one reusable optional field, present identically in the Import modal and Document Detail (also reused later by the Editor in Epic 2); editable any time; labels should stay understandable without institutional knowledge of the content.
- Empty library: "No documents yet." message with a primary "Import" or "Create a document" button.
- No search/filter results: explicit message plus a suggestion to clear active filters.
- Deletion always requires a confirmation dialog (no undo in v1).
- Accessibility target: WCAG 2.2 AA; forms fully keyboard-navigable with visible focus and reading-order tab order.
- Voice/tone: direct and factual, no emoji or "fun" tone.

## Cross-Story Dependencies

- Story 1.1 scaffolds the Laravel project and the base `documents` table — every other story in this epic depends on it.
- Story 1.5 introduces `categories`/`category_id`; Story 1.2's card display and Story 1.7's filtering depend on it.
- Stories 1.6 (search) and 1.7 (filters) must share the single query entry point — build them so neither forks the query logic the other relies on.
- Stories 1.3 (preview) and 1.4 (download) both depend on the private-disk storage/streaming route from Story 1.1; 1.4 must keep working regardless of 1.3's preview state.
- Story 1.8 (delete) depends on Scout indexing (1.6), preview caching (1.3), and file storage (1.1), since `DeleteDocumentAction` cleans up all of them in sequence.
- Epic 2 (creation/export) reuses the `documents`/`categories` schema and `CategorizeDocumentAction` established here — no duplicate data model.
