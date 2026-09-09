---
title: Rubric Review — ARCHITECTURE-SPINE.md (post sprint-change-proposal-2026-09-09)
reviewed: 2026-09-09
target: architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md
inputs:
  - sprint-change-proposal-2026-09-09.md (§4 Architecture)
  - architecture-ekodoc-2026-08-31/.memlog.md (last ~20 entries)
  - prds/prd-ekodoc-2026-08-31/prd.md (current, updated 2026-09-09)
verdict: NOT READY for Epic 3 story breakdown — one critical divergence point (FR13/FR6 attachment search + per-attachment download/preview) is entirely unaddressed by any AD.
---

# Rubric Review — ARCHITECTURE-SPINE.md

## Verdict

The tags/AD-16-removal/AD-18 edits themselves are clean, faithful to the sprint-change-proposal, and internally consistent — but the spine was reconciled against the *proposal's* draft wording for AD-17, not against the PRD's **final, more detailed FR13 text** (which already exists in the current `prd.md`, updated same day). That final FR13 adds two concrete requirements — individual preview/download per attachment, and fulltext indexing of attachment content "au même titre qu'un document importé" (echoed in the updated FR6) — that no AD, no Capability Map row, and no Structural Seed entry currently covers. This is exactly the kind of divergence point two builders would resolve differently. Everything else checked out.

## Rubric walk

| # | Rubric item | Result |
|---|---|---|
| 1 | Fixes real divergence points for the level below, misses none | **FAIL** — see Critical finding (FR13/FR6 attachment search + per-attachment download route) |
| 2 | Every AD's Rule is enforceable and prevents its stated divergence | PASS for AD-5/AD-16/AD-18. **PARTIAL** for AD-17: Binds FR13 in full, but its Prevents/Rule only cover the storage-path half of FR13, not the search-indexing/download half — the AD claims a scope it doesn't fully police |
| 3 | Nothing under Deferred could let two units diverge silently | **FAIL** — the attachment-search-indexing question isn't in Deferred either; it's just absent, i.e. silently dropped rather than explicitly deferred |
| 4 | Named tech is verified-current, no stale category/tag tech claim | PASS — no new library introduced for tags/attachments (plain table + pivot), Stack table untouched and correctly so |
| 5 | No leftover `category`/`Category`/`category_id`/`CategorizeDocumentAction`/`CategoryController`/`CategorySelector` outside AD-5's changelog and AD-16's removal note | **MOSTLY PASS, one leak** — `DeleteCategoryAction` is named in AD-18's Rule (line 155), a third location outside the two permitted ones |
| 6 | Capability Map / Structural Seed match AD text exactly, no orphans, FR13/FR14 present | **MOSTLY PASS** — FR13/FR14 rows exist and are correct as far as they go; FR3 row is stale (see Medium finding); no orphaned category rows remain |
| 7 | Every dimension the altitude owns is still decided/deferred/open | **FAIL** — attachment search-indexing dimension dropped (same as #3) |
| 8 | Sprint-change-proposal wording for AD-5/AD-17/AD-18 faithfully carried | PASS — all three are near-verbatim, no constraint dropped *relative to the proposal's own text*. The gap is that the proposal's own AD-17 draft was already thinner than the PRD's final FR13 by the time the spine was finalized, and nothing reconciled the two |

## Findings

### CRITICAL — AD-17 does not cover FR13's search-indexing and per-attachment access requirements

The current `prd.md` (already updated 2026-09-09, same day as this spine) states:

> **FR13** — ... ajout, prévisualisation/téléchargement individuel et retrait d'une pièce jointe, sans affecter le contenu de l'éditeur. Le contenu de chaque pièce jointe est indexé pour la recherche fulltexte (FR6) au même titre qu'un document importé (FR1).
> **FR6** — Rechercher en fulltexte sur le contenu des documents (importés et créés, **pièces jointes incluses — FR13**) ...

AD-17 (spine lines 145–149) binds FR13 but its Rule only specifies: table `document_attachments` (id, document_id, file_path, original_filename, mime_type, timestamps), private storage path, and `AttachDocumentFileAction`/`DetachDocumentFileAction`. It says nothing about:

- **Extraction/indexing**: no `extracted_text`/`extraction_status` column on `document_attachments`, no job wiring, no statement of whether attachment text is (a) merged into the parent `Document.extracted_text`, (b) indexed as its own Scout-searchable row, or (c) something else. AD-8 ("un unique point d'entrée... le champ indexé est `extracted_text`... pas les colonnes de métadonnées") and AD-9 (extraction pipeline) were not extended to mention attachments at all — their Binds lists (`FR6, FR7, NFR2` and `FR1, FR6, FR10` respectively) don't even include FR13.
- **Per-attachment preview/download**: AD-7 governs downloads but is explicitly bound to FR5 only ("original files"); nothing gives attachments a download/preview route. Structural Seed's `Http/Controllers/` section lists only `DocumentController.php` and `TagController.php` — no attachment route/controller method appears anywhere (Capability Map's FR13 row lists only `AttachDocumentFileAction`/`DetachDocumentFileAction`/`Models/DocumentAttachment`, no read-side access at all).

This is not a hypothetical edge case — it's two concrete, PRD-mandated behaviors with zero architectural decision behind them. Two builders implementing the Epic 3 attachment stories will independently invent: where attachment text lives, whether search results surface "found in an attachment," and how download/preview is routed. That's precisely the class of divergence AD-17 exists to prevent, and its Prevents clause doesn't even claim to address it. It also isn't logged in Deferred, so it isn't a documented open question — it simply isn't there.

**Recommendation**: extend AD-17 (or add AD-19) before Epic 3 story breakdown: decide whether `document_attachments` gets its own `extracted_text`/`extraction_status` (and whether `DocumentAttachment` becomes independently `Searchable`, or its text rolls into the parent `Document`), reuse or fork `ExtractDocumentTextJob` for attachments, and name the controller/route for per-attachment preview/download (likely `DocumentController@downloadAttachment` alongside AD-7's existing pattern). Update AD-8/AD-9 Binds to include FR13, and add the corresponding Capability Map row and Structural Seed entries (Jobs/, Http/Controllers/).

### HIGH — Missing DTO for attachment Actions in Structural Seed

AD-3 mandates every Action receive a typed `readonly` DTO, never an array/`Request`. The `DataTransferObjects/` block in Structural Seed (lines 214–216) lists only `DocumentData.php` and `TagData.php` — no DTO is named for `AttachDocumentFileAction`/`DetachDocumentFileAction`. This is a small, mechanical gap (a level-below builder must guess the DTO's name, e.g. `AttachmentData.php` vs. `DocumentAttachmentData.php`) but it's exactly the kind of naming divergence AD-3/Consistency Conventions exist to close off.

### MEDIUM — Capability Map's FR3 row is stale relative to the new FR3 text

Current PRD FR3: "titre, type, **tags**, date d'ajout." The Capability Map row `FR3 — Métadonnées | Models/Document | AD-4` (line 163) still points only to AD-4 (the single-`documents`-table decision), not to AD-5, even though "tags" — now explicitly part of FR3's metadata list — is governed by AD-5's `tags`/`document_tag` pivot, not by a column on `documents`. A builder consulting only the FR3 row could reasonably (and wrongly) model tags as a column rather than the pivot relation AD-5 mandates.

### MEDIUM — `DeleteCategoryAction` named outside the two permitted history locations

AD-18's Rule (line 155) reads: "...même discipline que l'ancien `DeleteCategoryAction` (désormais retiré, voir AD-5)." The rubric's consistency rule allows leftover mentions of the retired category concept only in AD-5's changelog and AD-16's removal note (both explicitly historical). This third mention is harmless in intent (it's explaining continuity of a deletion-safety discipline) and does cross-reference AD-5, but it technically leaks the retired name into a forward-looking, `[ADOPTED]` AD's Rule text. Consider rephrasing AD-18 to state the discipline standalone ("détache sans jamais supprimer les documents, cohérent avec AD-15") without naming the retired Action.

### LOW — AD-17 doesn't constrain attachment file types

FR13 restricts attachments to "PDF, Word, Excel," matching FR1's import formats. AD-17's Rule stores whatever `mime_type` is given with no validation rule stated (likely intended to live in a Form Request, which the spine wouldn't normally itemize) — but since AD-17's Prevents clause is silent on this dimension too, it's worth a one-line addition (e.g., "mêmes formats acceptés que FR1") so the constraint is traceable to an AD rather than assumed.

## Non-findings (checked, no issue)

- Stack table: no new/changed technology from this change; no stale claims slipped in for tags/attachments.
- AD-5, AD-16, AD-18 text: faithfully carries the sprint-change-proposal's wording, no dropped constraints.
- Structural Seed: `Category.php`, `CategoryController.php`, `Actions/Category/`, `CategoryData.php`, `CategorySelector.vue` all correctly absent; `Tag.php`, `TagController.php`, `Actions/Tag/`, `TagData.php`, `TagSelector.vue`, `Configuration.vue`, `document_tag`, `document_attachments`, and the attachments storage path are all correctly present.
- AD-16: ID correctly retired (marked `[REMOVED 2026-09-09]`, Binds/Prevents/Rule all "—"), not reused.
- No orphaned category rows remain in the Capability Map.
- `.memlog.md` entries are consistent with the spine's final state — no undocumented decision.
