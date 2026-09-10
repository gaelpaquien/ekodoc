# Epic 3 Context: Tags illimités, pièces jointes et configuration

<!-- Compiled from planning artifacts. Edit freely. Regenerate with compile-epic-context if planning docs change. -->

## Goal

This is a post-launch consolidation epic (v1.1) that replaces the single-category classification shipped in Epic 1 with unlimited, freely-combinable tags; lets a user attach one or more source files to a created document independently of its authored WYSIWYG content; gives the user a dedicated Configuration page to manage the tag vocabulary (create/rename/delete); and introduces a fixed sidebar plus a new visual identity across all 5 surfaces (Library, Document detail, Editor, Search, Configuration), with Search becoming its own surface separate from Library. It also fixes a known editor defect (nested TipTap tables with no deletion path) as a self-contained bug fix, with no FR/architecture impact. The epic is complete in itself and does not reopen Epic 1 or Epic 2.

## Stories

- Story 3.1: Classer et retrouver ses documents par tags illimités
- Story 3.2: Nouvelle identité visuelle et navigation par sidebar
- Story 3.3: Associer des pièces jointes à un document créé
- Story 3.4: Rechercher un document sur une surface dédiée
- Story 3.5: Gérer les tags depuis une page de configuration
- Story 3.6: Corriger l'insertion de tableaux imbriqués dans l'éditeur

## Requirements & Constraints

- Documents (imported and created) are classified by unlimited tags chosen from a managed list — never free text, to avoid label duplicates (e.g. "Finance"/"finance"/"Finances").
- Document metadata (title, type, tags, date added) applies identically to imported and created documents.
- Fulltext search covers document content plus attachment content for created documents; results must return in well under a second on a corpus of roughly 350 documents (indicative target, not a hard SLA).
- Library results and search results can be filtered by tag; the Library additionally filters by file type (PDF/Word/Excel/created), multi-selectable and combinable.
- A created document can have one or more file attachments (PDF/`.docx`/`.xlsx`, same formats as import) managed independently of the editor's WYSIWYG content: add, individually preview/download, and remove, none of which touch `content_html`.
- Tags are managed exclusively from the Configuration page: create, rename, delete. Deleting a tag detaches it from every document it's on; it must never delete the documents themselves.
- No accepted format outside PDF/`.docx`/`.xlsx` for either import or attachments; unsupported formats fail fast with an explicit message, never silently.

## Technical Decisions

- **Categories are removed, not migrated.** The `categories` table, `documents.category_id` column, `CategorizeDocumentAction`, the `Category` model/controller, and `CategoryPicker.vue` (plus any category display/filter in Index/Show/ImportModal) are deleted outright. No data migration to tags — tagging starts from zero. This is an explicit product decision, not an oversight.
- **Tag data model:** flat `tags` table (id, name), no hierarchy, many-to-many via `document_tag` pivot, no per-document tag limit. `SyncDocumentTagsAction` is the single Action allowed to write `document_tag`, always via full-replacement `sync()` (never incremental `attach()`/`detach()`), invoked from every context that assigns tags (import, editor save, document detail edit). No other Action touches this pivot. `TagSelector.vue` is one shared component (Tailwind-only, no third-party multiselect lib) used everywhere tags are picked; it only ever offers existing tags loaded from the `tags` table — no free text, no create-on-the-fly, in any screen.
- **Attachments data model:** new `document_attachments` table (id, document_id, file_path, original_filename, mime_type, `extracted_text`, `extraction_status`, timestamps) — same extraction columns/failure semantics as `documents`. Files live on the private disk at `storage/app/private/documents/{document_id}/attachments/{uuid}.{ext}`, never public, same discipline as original files. `AttachDocumentFileAction` stores the file, creates the row (`extraction_status = pending`), and dispatches the existing `ExtractDocumentTextJob` generalized to target either a `Document` or a `DocumentAttachment` — never a second extraction pipeline. Extracted attachment text is aggregated into the parent `Document::toSearchableArray()` (no separate Scout entry per attachment), keeping one search query path. Preview/download go through `DocumentAttachmentController@preview`/`@download`, a dedicated controlled route with the same existence/readability checks as original-file downloads. `DetachDocumentFileAction` removes the file and row, then re-triggers Scout indexing of the parent document via the `Searchable` trait's `saved` event.
- **Deletion ordering (extends the existing full-cleanup discipline):** deleting a document must also delete each attachment's disk file and then its row, in the established cleanup order (index removal → original file/images → attachments → preview cache → document row) — never a bare `cascadeOnDelete()`, which would delete rows but orphan attachment files on disk. The `document_tag` pivot itself is cleaned by a standard `cascadeOnDelete()` (no disk files involved there).
- **Tag configuration:** a single controller/page exposes create/rename/delete for tags. Deleting a tag detaches it from all documents (removes `document_tag` rows) and never deletes documents — the same "detach, never cascade" discipline that replaced the removed category mechanism.
- **Conventions to follow (inherited, still binding for this epic's new Actions):** each Action is a single named `{Verb}{Entity}Action` with one `__invoke(DTO $data)` method, no HTTP/Inertia knowledge; text extraction for both imports and attachments runs through the same queued job with best-effort failure (never blocks the parent create/attach operation, never a silently degraded result); search/filtering — including the new tag filter — must stay on the one unified query path already used for fulltext + filters, never a separate `whereHas('tags')` branch.

## UX & Interaction Patterns

- **Visual identity v2**: warm neutral beige/brown backgrounds (never pure white/black) with a single neon-lime accent (`#C6FF00`), each color with a light/dark pair. No WCAG color-contrast validation is required on this palette (explicit product call for this internal tool); general WCAG 2.2 AA otherwise still applies (keyboard, focus, alt text).
- **Sidebar**: fixed, always visible, never collapsible/hideable, on all 5 surfaces. Lists the three navigation roots — Library, Search, Configuration (Document detail and Editor stay reached only by contextual click, not from the sidebar) — plus a light/dark theme toggle and a literal footer "Made with 💔 Claude" at the bottom. Active item highlighted with the lime background. Fully keyboard-navigable, focus visible, tab order matching reading order.
- **Document row** (`document-row`) replaces the v1 document card in the Library: no resting background, hover uses the surface color, whole row clickable to Document detail, shows file-type badge + title + tag chips + date added. Library becomes a plain paginated listing (20/page) with no embedded search bar.
- **Tag selector** (`TagSelector.vue`) appears in the Import modal, Editor save flow, and Document detail: a live-filtering field over the managed tag list; click/Enter adds a chip; never accepts free text or creates tags; leaving it empty is never blocking. Visually distinct from tag chips shown read-only on rows/detail, which are in turn visually distinct from filter chips.
- **Filters**: on the Library, tag + type filters are combinable chips, always visible and removable while active. On Search, only a tag filter is offered — no type filter there, that stays Library-specific.
- **Search is a dedicated surface** reached from the sidebar: empty/neutral initial state with focus already in the search field, live-filtered results with debounce (no submit button) over document + attachment content, and the `/` keyboard shortcut now focuses this surface's field (moved off the Library, which no longer has a search bar).
- **Attachments panel**: a retractable side panel in the Editor, visually distinct from the WYSIWYG body, reusing the same drop-zone pattern as import; lists attachments with a remove action; shows "Aucune pièce jointe." plus an add action when empty (never hidden even when empty). On Document detail, attachments show read-only with individual preview/download — add/remove is Editor-only.
- **Empty/error states to preserve**: "Aucun document ne correspond à ces filtres." (Library, filters active) and "Aucun document ne correspond à votre recherche." (Search) each suggest clearing the active filter; unsupported-format drops fail immediately naming accepted formats, on both the import modal and the attachments panel.
- **Configuration page**: lists all tags; create flags case-insensitive duplicates before submission; delete always confirms via a dialog naming how many documents are affected, then a factual post-delete message ("Tag supprimé — détaché de N documents."); empty state is "Aucun tag pour l'instant." with a prominent "Créer un tag" action. Fully keyboard-navigable, focus visible; tag labels should stay readable to someone without institutional knowledge of the content (not just optimized for the current single user).

## Cross-Story Dependencies

- Story 3.1 (tags model + removal of categories) is a prerequisite for 3.2's document-row (which displays tag chips) and for the tag filter shown on both Library and Search.
- Story 3.2 (sidebar) is a prerequisite for 3.4 (adds the "Recherche" nav item and surface) and 3.5 (adds the "Configuration" nav item and surface) — both stories assume the sidebar already exists.
- Story 3.3 (attachments) depends on the shared extraction job generalized in the same story but is otherwise independent of the tags work in 3.1/3.5; it does depend on the deletion-cleanup path being extended so `DeleteDocumentAction` also removes attachment files/rows.
- Story 3.1 and 3.3 both touch the Editor's save flow (tag selector on save; attachments panel alongside the WYSIWYG body) and the Document detail page (tag editing; read-only attachment list) — coordinate changes to those two surfaces across both stories.
- Story 3.4 removes the Library's embedded search bar, which Story 3.2's document-row and Library filtering must account for (Library keeps only tag/type filters, no search).
- Story 3.6 (nested-table editor fix) is independent of the rest of the epic and has no data-model or FR impact.
- This epic depends on Epic 1's Story 1.5 (category classification) and Epic 2's Story 2.1 (editor save) as the prior implementations being replaced/extended, but does not otherwise reopen either epic.
