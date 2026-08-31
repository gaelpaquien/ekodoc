---
title: Reconciliation — EXPERIENCE.md / DESIGN.md vs ARCHITECTURE-SPINE.md
name: EkoDoc
status: draft
created: 2026-08-31
sources:
  - ux-designs/ux-ekodoc-2026-08-31/DESIGN.md
  - ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md
  - architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md
---

# Reconciliation — UX spines vs Architecture Spine

Method: every row of EXPERIENCE.md's Component Patterns, State Patterns, and Key Flows was checked
against the architecture spine's invariants (AD-1..AD-13), Capability → Architecture Map, and
Structural Seed, to see whether a concrete mechanism exists to produce the specified behavior.

## What checks out

- **Unified Bibliothèque (imported + created in one model).** AD-4 (single `documents` table,
  `source` enum `imported`/`created`, side-specific columns nullable) directly implements this.
  Matches EXPERIENCE.md's Information Architecture row and Flow 3 step 5 ("le document rejoint la
  Bibliothèque avec les mêmes propriétés... qu'un document importé").
- **Sélecteur catégorie/dossier — optional, "Non classé" default, flat.** AD-5 (`categories` flat
  table, `documents.category_id` nullable = "Non classé", no hierarchy, no many-to-many) matches
  the Component Pattern row exactly. `Actions/Category/CreateCategoryAction` and
  `Actions/Document/CategorizeDocumentAction` exist in the Structural Seed. Editing the category
  "sans limite de temps" from the Fiche document is covered implicitly by the standard RESTful
  `DocumentController@update` — no dedicated invariant calls this out, but no new mechanism is
  needed either.
- **Import flow (Flow 1) happy path.** AD-6 (synchronous import pipeline: store → extract →
  index → create) + AD-7 (private disk, streamed download) + AD-9 (extraction best-effort, never
  blocks import) together implement "fichier importé → Fiche document avec métadonnées déjà
  renseignées" and the "format non supporté" failure state (Form Request validation, file never
  sent).
- **Retrieve/download (Flow 2) happy path.** AD-7's streamed download route + AD-8's Scout search
  + native PDF preview (AD-10 explicitly skips conversion for PDFs) implement steps 1–5.
- **Export PDF fidelity (Flow 3 climax, NFR5).** AD-11 (Browsershot renders the *same* HTML the
  editor shows, no separate layout recomputation) is a direct, well-targeted answer to the
  image-position-fidelity requirement.
- **Export Word "best-effort" failure framing.** AD-12 explicitly cites the exact EXPERIENCE.md
  Flow 3 failure case (image mis-positioned) and commits to "explicit error, never a silently
  degraded file" — matches the State Pattern row for "Export réussi/échoué" and Voice and Tone's
  "jamais un échec silencieux" principle.
- **"Jamais un échec silencieux" as a cross-cutting rule.** The Consistency Conventions table's
  "État & transverse" row ("Erreurs métier... jamais d'exception non catchée... toujours un
  message explicite côté Inertia") is a reasonable general backing for most State Pattern rows
  (format non supporté, export échoué, extraction échouée).

## Gaps found

### 1. No mechanism makes created documents (`source = created`) fulltext-searchable

AD-8 is explicit: *"Le champ indexé est `extracted_text` (voir AD-9), pas les colonnes de
métadonnées."* AD-9's extraction pipeline (`smalot/pdfparser`, `phpoffice/phpword`,
`phpoffice/phpspreadsheet`) only runs inside `ImportDocumentAction`, against an uploaded file. A
document created in the editor has no uploaded file — its content lives in `content_html`
(per AD-4). No invariant, Action, or DTO in the spine populates `extracted_text` from
`content_html` for `source = created` documents (e.g., a strip-tags step run by
`CreateDocumentAction` on save, or a Scout `toSearchableArray()` override that falls back to
`content_html`).

This directly contradicts an explicit, repeated EXPERIENCE.md requirement: the Component Pattern
row "Action Enregistrer (éditeur)" and Flow 3 step 5 both state the saved document must have "les
mêmes propriétés de classement **et de recherche**" as an imported document (FR10). As specified,
AD-8 + AD-9 together mean created documents would silently drop out of FR6/FR7 fulltext search —
the opposite of unified search that AD-4 was written to guarantee. This is a hole between two
invariants that individually look correct but don't compose for the `created` half of AD-4's own
unified model.

### 2. No storage/serving mechanism for images inserted inline in the WYSIWYG editor (FR9)

The Capability Map's row for FR8/FR9 says only: `resources/js/Pages/Editor.vue (TipTap)`, governed
by `Stack`. TipTap is a client-side rendering library; nothing in the spine specifies:
- where an inserted image's bytes are stored server-side (no `images` table, no storage path
  pattern alongside `storage/app/private/documents/{id}/{filename}` and
  `storage/app/private/previews/{id}.pdf` in the Structural Seed),
- what URL/route the `<img src>` in `content_html` points to once persisted (AD-7's rule that
  originals are never served via a "lien direct" would, if applied consistently, also apply to
  editor images — but no route for it exists, unlike the explicit download route for originals),
- how the mandatory alt-text ("Texte alternatif obligatoire à la saisie", Accessibility Floor) is
  captured and persisted per image,
- how `spatie/browsershot` (AD-11, headless Chromium) and `phpoffice/phpword` (AD-12) — both
  consuming the same `content_html` — resolve those image references to actual bytes when
  rendering the PDF/Word export. If images are behind an auth-gated private route, Chromium
  headless needs a way to fetch them (local file path substitution, signed URL, or similar) that
  is not addressed anywhere.

This is a concrete behavior EXPERIENCE.md requires (FR9: insert image via button or drag-and-drop
at cursor position, image persists between paragraphs, survives PDF export intact) for which the
architecture spine currently has zero backend mechanism — not an implementation detail left open,
but a missing invariant class entirely (no Model, no Action, no DTO, no storage path, no route).

### 3. Conversion-in-progress state (AD-10) conflicts with "rest of the page stays usable"

EXPERIENCE.md's State Pattern row "Conversion de prévisualisation en cours (Word/Excel)" requires:
an explicit loading indicator *in the preview panel only*, while "le reste de la fiche (métadonnées,
téléchargement) reste utilisable pendant ce temps" — i.e., the Fiche document page itself must
render immediately, with the preview loading asynchronously afterward.

AD-10 only says `ConvertDocumentToPreviewAction` "invoque `soffice --headless --convert-to pdf`...
mis en cache... régénéré seulement si le fichier source change" — it does not say *when* this runs
relative to the page request. Two readings are both consistent with AD-10's text but only one is
consistent with EXPERIENCE.md:
- If invoked inline during `DocumentController@show` (mirroring AD-6's "in the same HTTP request"
  pattern used for import), the whole Inertia page — including metadata — blocks until LibreOffice
  finishes, contradicting "reste utilisable pendant ce temps."
- If invoked asynchronously after the page has rendered, the frontend needs a way to poll or fetch
  conversion status/result — but AD-13 forbids a JSON API ("aucun `Route::apiResource` ni retour
  `response()->json()`... l'app n'expose aucune API publique"), and the stack table has no
  queue/broadcasting mechanism (consistent with AD-6's explicit "aucun job en file d'attente en
  v1" for import, but never stated for preview conversion). The only Inertia-compatible pattern
  for this exists nowhere in the spine (e.g., a dedicated non-Inertia route that streams the
  cached PDF once ready, polled from the client, plus a status value the Document Show page must
  expose).

Related: AD-10 also has no explicit failure-mode contract (unlike AD-9's precise "échec... laisse
`extracted_text` à `NULL`, n'interrompt jamais l'import" wording). There is no defined sentinel to
distinguish "not yet converted," "conversion in progress," and "conversion failed" — needed to
render the distinct State Pattern rows "Conversion... en cours" vs. "Conversion... échouée" vs. a
prior successful cached conversion.

### 4. "Fichier source introuvable" — no explicit invariant (minor)

The State Pattern row requires detecting a moved/deleted/unreadable source file and disabling
the download button rather than failing silently. Nothing in the spine assigns this check to any
Action or route (e.g., `Storage::exists()` before streaming in the download route, or before
attempting AD-10's conversion). This is likely implementable as a small addition inside AD-7's
existing download route and AD-10's conversion Action without a new architectural concept — flagged
as a documentation gap rather than a missing mechanism, but worth an explicit rule so two future
implementations of "download" and "preview" don't diverge on how they handle a missing file (the
exact kind of divergence AD-7 itself was written to prevent for public vs. private access).

## Not checked further (out of scope for this reconciliation)

- Voice and Tone, Accessibility Floor's WCAG/tab-order requirements, and most State Pattern rows
  that are pure frontend/Vue behavior (search debounce, unsaved-changes indicator, empty states,
  confirmation dialogs) do not require backend mechanisms beyond what's already in the spine, and
  were confirmed compatible without further mechanism (Inertia props + client-side state suffice).
