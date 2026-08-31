# Adversarial Review — ARCHITECTURE-SPINE.md (EkoDoc)

**Lens:** Construct pairs of sibling units (Action/Action, Action/Controller, Vue/Vue, Model/Action…) that each satisfy every applicable AD to the letter, yet build incompatibly with each other. Each finding is a hole in the spine, not a code bug — the fix is a new or tightened AD.

**Source reviewed:** `_bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md` (2026-08-31 draft)

**Total findings: 14**

---

## F1 — `DocumentData` has no canonical shape, so Import and Create/Update populate it differently

**Units:** `ImportDocumentAction(DocumentData $data)` vs. an editor-save path (`SaveDocumentRequest` → some Action) that also builds `DocumentData`.

**Both obey the letter of the spine:** AD-3 only requires "a typed readonly DTO, never an array or Request." AD-4 only requires that the `Document` model itself has nullable side-specific columns.

**Divergence:** Nothing in the spine fixes `DocumentData`'s property list. Builder A (import path) reasonably defines it with `file_path`, `mime_type`, `original_filename`, `extracted_text`, and no `content_html`. Builder B (WYSIWYG create/save path) reasonably defines it with `content_html`, `title`, and no `file_path`/`mime_type`. Since the structural seed names exactly **one** `DocumentData.php` for both Controllers, the two builders either (a) each redeclare an incompatible constructor signature for the same class name — a merge collision — or (b) silently agree to make every property nullable/optional, which defeats AD-3's stated purpose (an implicit, framework-free contract) by turning the DTO into the very ambiguous bag AD-3 was written to prevent.

**AD fix:** Tighten AD-3 (or add AD-3a) to require one DTO per Action, not per entity: `ImportDocumentData`, `CreateDocumentData`, `CategorizeDocumentData`, etc., each with only the fields that Action needs. Ban the "one shared `{Entity}Data`" pattern implied by the structural seed's single `DocumentData.php` line.

---

## F2 — Category creation has two possible owners

**Units:** `CategorizeDocumentAction` (assigns a category while importing/editing a document) vs. `CreateCategoryAction` / `CategoryController@store`.

**Both obey the letter of the spine:** AD-5 only constrains the *shape* of `categories` (flat, nullable FK). AD-2 only requires each Action be single-purpose and named `{Verbe}{Entité}Action`.

**Divergence:** The UX almost certainly wants "type a new category name inline while categorizing a document" (a free-text `CategorySelector.vue`, per the structural seed). Builder A implements this as `CategorizeDocumentAction` doing a `firstOrCreate` on `categories.name` — technically still "one operation," still correctly named. Builder B, working from `Actions/Category/CreateCategoryAction` in the seed, assumes that action is the *only* path that ever inserts a row into `categories`, and writes `CategoryController@index`/dedupe logic on that assumption (e.g., a "manage categories" admin list that expects every category to have been created through `CreateCategoryAction`, perhaps with an audit log or normalization step it applies at creation time). If Builder A's inline upsert bypasses that normalization, categories accumulate through two divergent creation paths, one of which silently skips whatever `CreateCategoryAction` adds later (dedupe by trimmed/case-folded name, slug generation, etc.).

**AD fix:** New AD: "Category creation has exactly one entry point, `CreateCategoryAction`. Any other Action that needs a category to exist (e.g. `CategorizeDocumentAction`) must call `CreateCategoryAction` internally rather than touching the `categories` table directly — never a local `firstOrCreate`."

---

## F3 — No Action owns "edit an existing document's metadata/content," so two Actions race on the same columns

**Units:** `CategorizeDocumentAction` (per Capability Map, governs FR2/FR10) vs. whatever Action backs `SaveDocumentRequest` (listed in the structural seed's `Http/Requests/`, but with **no corresponding Action** listed under `Actions/Document/`).

**Both obey the letter of the spine:** AD-2 says each Action does one named thing; nothing forbids two different Actions from each legitimately touching the `documents` row for their own concern.

**Divergence:** `SaveDocumentRequest` implies an "edit/save document" flow (title, `content_html`, and plausibly `category_id` if the editor also lets you reclassify a document while editing it — the UX seed lists `CategorySelector.vue` as a shared component available on multiple pages, not just import). If both a `SaveDocumentAction` (invented by Builder B to back `SaveDocumentRequest`) and `CategorizeDocumentAction` (Builder A) can each independently persist `category_id`, you get two mutation paths for one column with no defined precedence — e.g., a save that races an in-flight categorize call, or a `SaveDocumentAction` that resets `category_id` to whatever stale value the edit form loaded, clobbering a categorization made in another tab/request.

**AD fix:** Add the missing Action to the structural seed explicitly (`UpdateDocumentAction`) and add an AD stating: "Each mutable concern of `Document` has exactly one owning Action: content/title via `UpdateDocumentAction`, `category_id` via `CategorizeDocumentAction` only. `UpdateDocumentAction` must not accept or write `category_id`."

---

## F4 — AD-6's own ordering is self-contradictory, and two builders resolve it in incompatible ways

**Units:** an `ImportDocumentAction` implementation that calls `$document->searchable()` explicitly after `Document::create()`, vs. one that relies on Scout's automatic model-event syncing (the `Searchable` trait auto-indexes on `saved`/`created` events).

**Both obey the letter of the spine:** AD-6's rule text is: "stockage du fichier → extraction de texte → indexation Scout → **création du `Document`**" — indexing is listed *before* model creation, which is impossible (Scout indexes an existing Eloquent record; there is nothing to index before the row exists). Both builders "fix" this contradiction differently while claiming AD-6 compliance: Builder A reorders in code (create → then explicit `->searchable()`), Builder B assumes AD-6's intent was "just let Scout's automatic observer handle it" and never calls anything explicit, relying on the trait.

**Divergence:** If both patterns end up in the codebase (e.g., Builder A's explicit call in `ImportDocumentAction`, Builder B's implicit reliance in a later `CreateDocumentAction` or `UpdateDocumentAction`), you get one Action that double-indexes (auto event + explicit call) and another that silently depends on the trait's default queue-vs-sync config — which matters because AD-6 also demands indexing complete *synchronously within the same HTTP request*, and Scout's default `queue` config for the `Searchable` trait would violate that unless explicitly set to sync everywhere. Nothing in the spine pins `ScoutServiceProvider`/`config/scout.php`'s queue setting.

**AD fix:** Rewrite AD-6's rule with the correct, non-contradictory order ("stockage → extraction → création du `Document` → indexation Scout") and add: "Scout indexing is always synchronous (`config('scout.queue') = false` / no `ShouldQueue`), and is triggered exclusively via the automatic `Searchable` model-event sync — no Action ever calls `->searchable()` manually." This closes both the ordering bug and the double-index race.

---

## F5 — Export failure handling: two Actions, two incompatible error-propagation shapes

**Units:** `ExportDocumentToPdfAction` (AD-11, Browsershot) vs. `ExportDocumentToWordAction` (AD-12, PhpWord).

**Both obey the letter of the spine:** Consistency Conventions only says "jamais d'exception non catchée remontée à l'utilisateur — toujours un message explicite côté Inertia." Both a thrown-exception approach and a return-value approach satisfy that sentence.

**Divergence:** Builder A implements `ExportDocumentToPdfAction` to throw a domain exception (`PdfExportFailedException`) on a Browsershot/Chromium failure, caught by a global `Handler.php` that converts it to an Inertia flash error. Builder B implements `ExportDocumentToWordAction` — reading AD-12's "risque assumé, message d'erreur explicite" as "the Action itself must decide the message" — to return a value object (`ExportResult{success: bool, message: ?string}`) that `DocumentController@exportWord` inspects and flashes manually. Both display an explicit message to the user, satisfying the sentence literally. But: (1) the two Controller methods now have structurally different bodies (one is a thin try/nothing pass-through, the other has an `if` on a business-meaning field, arguably violating AD-1's "no business condition in a controller" for the Word path); (2) a shared Vue error-toast/banner component reading `page.props.errors.export` (thrown-exception convention) gets nothing for the Word path if `ExportResult` was flashed under a different prop key.

**AD fix:** New AD: "All Action-level business failures (extraction, conversion, export) are reported via a single mechanism: a typed domain exception extending `App\Exceptions\BusinessException`, caught by one Inertia-aware handler that always flashes to `errors.<action_key>`. No Action returns a `success`/`message` result object; Controllers never branch on Action return values for error handling."

---

## F6 — Non-CRUD document operations tempt one builder into a business-branching controller

**Units:** `DocumentController` extended with a single `export(Document $document, string $format)` method vs. a hypothetical separate `DocumentExportController` with `toPdf`/`toWord` methods.

**Both obey the letter of the spine:** Consistency Conventions only mandates "un controller par ressource... méthodes RESTful standard," which is a naming/shape convention, not a hard ban on extra methods, and the Capability Map already shows `DocumentController@download` as a precedent for non-RESTful additions to the resource controller.

**Divergence:** Builder A, trying to stay within "one controller per resource," adds `DocumentController@export($format)` and branches internally (`if ($format === 'pdf') ExportDocumentToPdfAction::class else ExportDocumentToWordAction::class`) — this is a business decision (which conversion pipeline to run) living in a controller, a direct AD-1 violation, but AD-1's text ("aucune condition métier... sur un type") is worded around *content* logic, not *routing/dispatch* logic, so a literal-minded builder could argue it's just "picking a route handler," not "business logic." Builder B, working from the Capability Map row that lists `Actions/Document/ExportDocumentToPdfAction` and `ExportDocumentToWordAction` as siblings with independent AD numbers (AD-11 vs AD-12), infers two independent endpoints (`POST /documents/{document}/export/pdf`, `POST /documents/{document}/export/word`) and two controller methods, never a shared dispatcher. When routes/tests/UI links are written against one assumption and the Action registration against the other, either a route is missing or a controller method silently short-circuits AD-1.

**AD fix:** Add explicit routing rule to AD-1: "One HTTP route (and one Controller method) per Action, with no branching between Actions inside a Controller method based on a request parameter (format, type, mode…). Format-specific operations (`export/pdf`, `export/word`) always get separate routes/methods, never a shared dispatcher method."

---

## F7 — Shared Inertia `categories` prop has two incompatible consumer-side shapes

**Units:** `CategorySelector.vue` (used on import/editor forms) vs. `FilterChips.vue` (used on the Library index).

**Both obey the letter of the spine:** Nothing in the spine defines Inertia shared-prop contracts; it only names the components in the structural seed.

**Divergence:** `FilterChips.vue`'s natural implementation needs "how many documents in each category" to render useful filter counts (a common pattern for chip filters), so its author has `DocumentController@index` pass `categories` as `[{id, name, documents_count}]` (via `withCount('documents')`). `CategorySelector.vue`'s natural implementation (a plain `<select>`/combobox for choosing one category while importing) needs only `[{id, name}]`, and since import/editor pages don't need the categorized-library count, its author has whichever controller renders those pages pass a lean array. If both pages actually share one global Inertia prop (e.g. defined once in `HandleInertiaRequests::share()` so every page gets `categories` for free — a very natural DRY move given both components are cross-page), one of the two components ends up reading a field (`documents_count`) that the other code path never populates, either showing "undefined" badges or forcing an unnecessary `withCount` query on pages that never render it.

**AD fix:** New AD: "Every cross-page shared Inertia prop (categories, current document, flash errors) has one canonical shape, declared once as a `{Entity}Data`-shaped array in `HandleInertiaRequests::share()`, documented in the spine's Capability/Data map. Components never assume enrichment fields (counts, aggregates) beyond that canonical shape; a component needing more fetches its own page-scoped prop instead of mutating the shared one."

---

## F8 — `DocumentSource` (and sibling enums) have no fixed namespace or backing type

**Units:** an enum authored while building `ImportDocumentAction` vs. one authored while building `Document.php`/migrations.

**Both obey the letter of the spine:** Consistency Conventions only says "constantes/enums nommés pour `source` (`DocumentSource::Imported`/`Created`)" — it names the enum and its cases, not its location or backing type.

**Divergence:** Builder A (working top-down from Actions) creates `App\Actions\Document\DocumentSource` as a plain (non-backed) PHP enum, since it's only ever compared in-memory within Actions. Builder B (working bottom-up from the migration/model) creates `App\Models\DocumentSource` as a `string`-backed enum (`'imported'`, `'created'`) because it must round-trip through a MySQL `enum`/`varchar` column and Eloquent casts. Both satisfy the convention's literal text. When merged: two classes named `DocumentSource` in different namespaces, only one of which is actually `Document`-castable, and code that does `Document::where('source', DocumentSource::Imported)` breaks depending on which one it imported (non-backed enum has no scalar value to compare against a DB column; backed enum does). Same risk applies to the "types de fichiers, statuts" enums the same sentence calls for, which are never named or located anywhere in the spine.

**AD fix:** New AD: "All domain enums live in `app/Enums/`, are `string`-backed (never bare enums) so they cast cleanly through Eloquent's `casts()`, and are named/enumerated explicitly in the spine's Consistency table (not left to 'et statuts' as a catch-all). `DocumentSource` is the only status/type enum for v1 unless a new AD adds one."

---

## F9 — AD-10's "regenerate only if source changed" has no field to check, so two implementations diverge

**Units:** `ConvertDocumentToPreviewAction` vs. a hypothetical `Document::hasFreshPreview()` helper (or a cleanup/cron unit) that needs to answer the same question independently.

**Both obey the letter of the spine:** AD-10 states the *policy* ("régénéré seulement si le fichier source change") but not the *mechanism*.

**Divergence:** Builder A implements the check inside `ConvertDocumentToPreviewAction` itself by comparing filesystem mtimes: `Storage::lastModified($originalPath) > Storage::lastModified($previewPath)`. Builder B, needing the same "is the preview stale?" answer elsewhere (e.g., `PreviewPanel.vue`'s parent page deciding whether to show a "generating…" state, or a future disk-cleanup command), adds a `documents.preview_generated_at` timestamp column and compares it to `documents.updated_at` — a column that doesn't exist anywhere in AD-10, AD-4, or the structural seed's migrations. Now there are two sources of truth for preview freshness that can disagree (e.g., a file overwritten directly on disk without touching `updated_at` looks stale to Builder A's check but fresh to Builder B's).

**AD fix:** Tighten AD-10: "Preview staleness is determined exclusively by comparing `Storage::lastModified()` of the source file against the cached preview file's mtime — no additional staleness column is ever added to `documents`. Any code needing to know preview freshness calls a single `Document::previewIsStale(): bool` method that wraps this filesystem check; it is never reimplemented."

---

## F10 — No AD settles soft-delete vs. hard-delete, so `DeleteDocumentAction` and the Scout/search path disagree

**Units:** `DeleteDocumentAction` vs. `Document::search()` (AD-8) / any query built on the `Searchable` trait's default index-sync behavior.

**Both obey the letter of the spine:** AD-7 only names the storage path to remove; AD-8 only says fulltext search must go through Scout. Neither states whether `documents` rows are ever soft-deleted.

**Divergence:** Builder A implements `DeleteDocumentAction` as a hard delete (`$document->delete()` with no `SoftDeletes` trait), reasoning that a mono-user local tool has no "trash/restore" requirement anywhere in the FR list, and doesn't worry about stale index entries since deleting the row also fires the `deleted` model event Scout listens to. Builder B, defensively following "erreurs métier ne doivent jamais planter silencieusement" and wanting an undo safety net for the only human using the tool, adds `SoftDeletes` to `Document` and writes `DeleteDocumentAction` to just set `deleted_at`. Under Builder B's version, Scout's default behavior differs (a soft-deleted, `Searchable` model is *not* automatically removed from the index unless `search.soft_delete` config is explicitly enabled), so soft-deleted documents remain fully searchable and clickable in `Library.vue` results unless every query elsewhere (index listing, category counts, `FilterChips.vue` badge counts) remembers to add `withoutTrashed()` — which nothing in the spine mandates.

**AD fix:** New AD: "`Document` deletion is a hard delete; `SoftDeletes` is never used on `Document` in v1 (no restore/undo requirement exists). `DeleteDocumentAction` is the only place a `documents` row is destroyed, and it must also remove the row's Scout index entry and its cached preview file (see F11) in the same call."

---

## F11 — Category deletion has no owning Action and an unspecified business rule

**Units:** `CategoryController@destroy` (implied by the RESTful-methods convention) vs. a hypothetical `DeleteCategoryAction` a second builder adds for consistency with the Document domain.

**Both obey the letter of the spine:** The structural seed's `Actions/Category/` list contains only `CreateCategoryAction` — no delete action is named anywhere, and Consistency Conventions mandates `destroy` as one of the RESTful methods every controller must expose.

**Divergence:** Builder A, following the seed literally (no `DeleteCategoryAction` exists to call), writes the deletion logic directly in `CategoryController@destroy` — including the business decision of what happens to documents currently pointing at that category (null out `category_id`, per AD-5's "NULL = non classé, jamais bloquant," or block the deletion if any document references it). That decision, made inline in a controller, violates AD-1's "no business condition in a controller." Builder B, applying AD-2's naming convention by analogy, creates `DeleteCategoryAction` and makes the *opposite* business call (blocks deletion with a validation error if `documents_count > 0`, reasoning that silent reclassification to "Non classé" is data loss). Neither behavior is specified by the spine, so the two are simply different, incompatible products depending on who happened to write the delete path first.

**AD fix:** Add `DeleteCategoryAction` to the structural seed explicitly, and add a rule to AD-5: "Deleting a category always sets `documents.category_id` to `NULL` for every affected document (never blocks on `documents_count`), performed by `DeleteCategoryAction` — `CategoryController@destroy` never deletes a `Category` row directly."

---

## F12 — AD-7's path template doesn't say whether filenames are sanitized, so writer and reader disagree

**Units:** `ImportDocumentAction` (writes to `storage/app/private/documents/{document_id}/{filename}`) vs. `DocumentController@download` / `ConvertDocumentToPreviewAction` (both must reconstruct that same path to read the file back).

**Both obey the letter of the spine:** AD-7 gives the path template verbatim with a literal `{filename}` placeholder and says nothing about sanitization.

**Divergence:** Builder A implements storage using the client's original filename as uploaded (`Rapport annuel (v2) — été.pdf`), since AD-7's template implies "the filename" is just... the filename, and MySQL/the filesystem on Windows-via-Herd will accept it. Builder B, implementing `ConvertDocumentToPreviewAction`'s shell-out to `soffice --headless --convert-to pdf` (AD-10), reasonably slugifies/ASCII-sanitizes the filename before building the CLI argument (spaces, parentheses, and em-dashes are common sources of shell-quoting bugs), producing `rapport-annuel-v2-ete.pdf` as the expected source path — which never exists on disk because Builder A never sanitized on write. The download route, written by a third path, might pick a third convention (store `filename` in the DB column and always use *that* string verbatim, sidestepping sanitization) — three implicit conventions for one placeholder.

**AD fix:** Tighten AD-7: "`{filename}` is always the slugified, ASCII-only, extension-preserved form of the original upload name, computed once in `ImportDocumentAction` and persisted verbatim in `documents.file_path`; every other unit (preview conversion, download) reads `documents.file_path` directly and never reconstructs the path from `documents.id` + a recomputed filename."

---

## F13 — AD-8 doesn't say how fulltext search and category/source filters compose, so combined queries diverge

**Units:** the code path triggered by `SearchBar.vue` (a search term present) vs. the code path triggered by `FilterChips.vue` alone (category/source filter, no search term) inside `DocumentController@index`.

**Both obey the letter of the spine:** AD-8 only forbids ad hoc `LIKE` for *fulltext* search; it says nothing about plain attribute filtering, which isn't fulltext at all.

**Divergence:** Builder A, handling the "search term present" case, naturally writes `Document::search($term)->where('category_id', $categoryId)->query(fn($q) => $q->with('category'))->paginate()`, using Scout's `database`-driver query hook. Builder B, handling "no search term, category filter only" (a very common state — e.g., landing on the Library page with a category selected and empty search box), sees no fulltext need and writes plain `Document::query()->where('category_id', $categoryId)->with('category')->paginate()`. These two paths, both individually AD-8-compliant, differ in pagination page-size defaults if not both configured identically, in whether `with('category')` is remembered on both branches, and in sort order (Scout's `database` driver ranks by match relevance by default; plain Eloquent needs an explicit `orderBy`) — so the same category filter produces differently-ordered results depending on whether a search box happens to be empty, which reads as a bug to the sole user even though each branch individually followed AD-8 to the letter.

**AD fix:** New AD: "`DocumentController@index` always builds its query through `Document::search($term ?? '')`, even with an empty term (Scout's `database` driver treats an empty search as `LIKE '%%'`/match-all), so filters, eager loads, sorting, and pagination pass through exactly one code path regardless of whether a search term is present."

---

## F14 — Date formatting is required in French but has no shared implementation, so components format inconsistently

**Units:** `DocumentCard.vue` (Library grid) vs. `DocumentShow.vue` (document detail page).

**Both obey the letter of the spine:** Consistency Conventions only says "formatage en français côté Vue" — it names the language, not the format or the mechanism.

**Divergence:** Builder A, writing the compact `DocumentCard.vue`, uses `new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(...)` inline, producing `31 août 2026`. Builder B, writing `DocumentShow.vue`'s "imported on / last modified" fields, reaches for a small helper it writes locally (`formatDate(d) => d.split('-').reverse().join('/')`), producing `31/08/2026`. Both are "French," both satisfy the convention's literal text, but the same underlying `documents.updated_at` timestamp now renders in two different formats depending on which page you're looking at — and if the sole user later asks for a third format (relative time, "il y a 2 jours"), there's no single place to change it.

**AD fix:** New AD: "All timestamp display goes through one shared composable, `resources/js/composables/useFrenchDate.ts`, exporting a single `formatDate(iso: string, style?: 'short'|'medium'|'long')` function. No component calls `Intl.DateTimeFormat` or hand-rolls date string manipulation directly."

---

## Summary Table

| # | Units in conflict | Divergence in one line |
| --- | --- | --- |
| F1 | `ImportDocumentAction` vs. editor-save Action | Both build `DocumentData` but need incompatible field sets — no per-Action DTO required |
| F2 | `CategorizeDocumentAction` vs. `CreateCategoryAction` | Two possible creators of a `categories` row (inline upsert vs. dedicated Action) |
| F3 | `CategorizeDocumentAction` vs. missing `UpdateDocumentAction` | Two Actions can both write `category_id`, no ownership rule, no such Action even listed |
| F4 | Explicit `->searchable()` vs. automatic Scout event sync | AD-6's stated order (index → then create) is impossible; builders resolve it two different ways, risking double-index or async leakage |
| F5 | `ExportDocumentToPdfAction` (throws) vs. `ExportDocumentToWordAction` (returns result object) | Two incompatible error-propagation contracts, one of which pushes a business `if` back into the controller |
| F6 | Polymorphic `DocumentController@export($format)` vs. separate export controller | Format-branching in a controller quietly violates AD-1 while looking like routing, not logic |
| F7 | `CategorySelector.vue` vs. `FilterChips.vue` | Same shared `categories` prop, one expects `documents_count`, the other doesn't produce it |
| F8 | Enum authored top-down (Actions) vs. bottom-up (Model/migration) | Two `DocumentSource` classes, different namespace and backing type, only one DB-castable |
| F9 | mtime-based staleness check vs. a `preview_generated_at` column | AD-10's "only if source changed" has two disagreeing implementations of "changed" |
| F10 | Hard-delete `DeleteDocumentAction` vs. `SoftDeletes` + Scout | Soft-deleted docs stay searchable/visible unless every query remembers `withoutTrashed()` — nothing mandates it |
| F11 | Delete logic inline in `CategoryController@destroy` vs. a new `DeleteCategoryAction` | No Action is named for category deletion, and the null-out-vs-block business rule is undecided |
| F12 | Raw filename on write vs. sanitized filename on read (preview/download) | AD-7's `{filename}` placeholder has no sanitization rule, so writer and readers can disagree on the actual path |
| F13 | Search-term index path (`Document::search()`) vs. filter-only path (`Document::query()->where()`) | Same "index" feature silently forks into two query builders with different sort/eager-load/pagination defaults |
| F14 | `DocumentCard.vue` vs. `DocumentShow.vue` date formatting | "French formatting" has no shared implementation, so the same timestamp renders differently on two pages |
