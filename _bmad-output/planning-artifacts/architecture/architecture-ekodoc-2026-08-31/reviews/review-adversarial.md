---
title: Adversarial Review — ARCHITECTURE-SPINE.md (2026-09-09 amendment)
reviewer: adversarial-spine-review
target: ../ARCHITECTURE-SPINE.md
date: 2026-09-09
method: >
  Construct pairs of one-level-down units (e.g. two builders each implementing
  a different story) that each obey every cited AD to the letter yet still
  produce incompatible systems. Every pair found is reported as a hole to be
  closed by a new or tightened AD — no fixes are proposed here.
---

# Adversarial Review — EkoDoc Architecture Spine (2026-09-09 amendment)

## Verdict

The 2026-09-09 amendment (AD-5 amended to tags, AD-16 removed, AD-17/AD-18
added) is **not yet safe to build from as-is**. The category→tag removal
itself is clean (no stray `category_id`/`CategorizeDocumentAction` references
survive in the Capability Map, Structural Seed, or other ADs). But the new
surface area — the tag-assignment write path, attachment cleanup ordering,
and attachment structural placement — has real seams where two AD-compliant
builders diverge. Two findings are **critical** and should block story
slicing for Epic 3 until closed.

---

## Finding 1 (CRITICAL) — No named owner for the `document_tag` write path; full-replace vs incremental mutation collide

**ADs involved:** AD-5, AD-2, AD-18. **Capability Map row:** "FR2/FR7/FR10 — Tags & filtrage."

AD-5 and AD-18 fully specify the **tag entity's** lifecycle (create/rename/delete, Configuration-only). Neither AD, nor the Structural Seed's `Actions/Tag/` comment (`CreateTagAction, RenameTagAction, DeleteTagAction`), nor the Capability Map, names an Action that owns **attaching/detaching existing tags to/from a `Document`** — i.e. writes to the `document_tag` pivot itself. AD-2 requires every mutation to live in exactly one named Action, but no Action for this specific mutation exists anywhere in the spine.

Construct two builders, each fully AD-compliant:

- **Builder A**, implementing "create/save a document with tags" (Editor save flow, FR8/FR2): folds tag assignment into the existing `CreateDocumentAction`/`SaveDocumentAction`, calling `$document->tags()->sync($tagIds)` as part of the single document-save transaction. Semantics: **full replace** of the tag set on every save.
- **Builder B**, implementing "add/remove a tag from an already-classified document" (e.g. from `DocumentShow.vue` for an imported document, FR2/FR7): adds a new `Actions/Tag/AttachTagToDocumentAction` / `DetachTagFromDocumentAction` pair — a perfectly AD-2-conformant name, `{Verbe}{Entité}Action`, single public `__invoke`. Semantics: **incremental** `attach()`/`detach()` on the pivot.

Both pass every literal AD-2/AD-5/AD-18 check. But now the `document_tag` pivot has two independent, semantically incompatible writers: a "replace everything" path and an "add/remove one" path. If a user has the Editor open (autosave firing `sync()`) while also using a quick "add tag" affordance elsewhere on the same document, the replace-path autosave can silently discard a tag just attached by the incremental path (classic lost-update race) — and there is no rule anywhere assigning which Action is authoritative for the pivot, or forbidding a second one from existing.

**This is a hole to close**, e.g. an AD stating "exactly one Action (`SyncDocumentTagsAction` or similar) is the sole writer of `document_tag`; every UI touchpoint that changes a document's tags — creation, edit, imported-document classification — calls it with the full desired tag-ID set."

---

## Finding 2 (CRITICAL) — AD-15's Rule text was not amended; attachment cleanup ordering is genuinely ambiguous, and cascade-delete is a live escape hatch

**ADs involved:** AD-15, AD-17.

AD-17's text is the *only* place that extends deletion: "`DeleteDocumentAction` (AD-15) est étendue pour nettoyer aussi ces fichiers, dans le même ordre que le nettoyage existant." **AD-15 itself was not edited** — its own Rule still lists, verbatim, only: Scout unindex → delete original file + images (AD-7/AD-14) → delete preview cache (AD-10) → delete `Document` row. A builder who reads AD-15 as the self-contained source of truth for `DeleteDocumentAction` (which is exactly what AD-15's own text claims to be: "l'unique point d'entrée de suppression") has no textual signal there that attachments exist at all — the extension lives one AD away, referenced only in the reverse direction.

Even granting that a builder does read AD-17, "dans le même ordre que le nettoyage existant" does not say **where** in the sequence attachment-file cleanup slots in, nor whether it must happen **before** the `Document` row delete. Two compliant builders:

- **Builder A** inserts explicit attachment-file deletion as a new step between "delete preview cache" and "delete `Document` row," reading files off `$document->attachments` before the row (and any cascading pivot/FK rows) disappears.
- **Builder B** notices that `document_attachments.document_id` is a foreign key to `documents.id`, defines it with Laravel's idiomatic `->constrained()->cascadeOnDelete()`, and reasons that AD-17's "étendue pour nettoyer aussi ces fichiers" is satisfied structurally: deleting the `Document` row (the last step, unchanged from AD-15) cascades and removes the `document_attachments` **rows**. Builder B never writes code to unlink the **physical files** on `storage/app/private/documents/{id}/attachments/`.

Both implementations look AD-15/AD-17-compliant on a literal reading. Builder B's version permanently orphans attachment files on disk on every document deletion — precisely the class of bug AD-15 exists to prevent for the original file/images/preview cache, but the amendment failed to make equally airtight for attachments.

**This is a hole to close**: AD-15's Rule text itself needs to enumerate the attachment-cleanup step explicitly (not just be extended-by-reference from AD-17), and state unambiguously "before the row/pivot rows are deleted, not relying on FK cascade for file cleanup."

---

## Finding 3 (HIGH) — AD-5's "no free-text / no on-the-fly tag creation" rule has no enforcement point at the UI-component boundary

**ADs involved:** AD-5. **Structural Seed:** `Components/TagSelector.vue`.

AD-5's Prevents/Rule clauses are explicit in prose ("jamais de création à la volée ailleurs — pas de texte libre dans le sélecteur de tags"), but this constraint is never attached to anything with a contract. `TagSelector.vue` is listed once, undifferentiated, in the shared `Components/` list — implying reuse across multiple pages/stories (the Editor save flow per FR8/FR2, and presumably an imported-document classification touchpoint per FR2's "importés et créés" scope, since AD-4 insists both `source` values share one model and the same classification properties).

Two builders, each implementing a different story that consumes `TagSelector.vue`:

- **Builder A** (Editor / created-document save story) wires it as a closed multi-select bound to a pre-fetched tag list, no creation affordance — correct per AD-5.
- **Builder B** (imported-document tagging story, e.g. from `DocumentShow.vue` or an import modal) reuses the same component but, because nothing in the spine specifies `TagSelector.vue`'s prop contract, adds a `creatable`/`allow-create` mode (a default behavior of most tag-selector UI libraries and a natural feature request for "usage solo" convenience) — silently reintroducing free-text tag creation exactly where AD-5 forbids it, without touching any Action-level code (the violation is purely in the Vue layer, invisible to a backend-focused reviewer).

Nothing in AD-5, the Capability Map, or the Structural Seed pins down that **the component itself** must never expose a create-affordance, only that "tags are managed exclusively from Configuration." The rule is airtight for the backend (no `CreateTagAction` call site outside `TagController`) but not for the frontend, where the actual UX violation would surface first.

---

## Finding 4 (HIGH) — FR7 is double-governed by AD-5 and AD-8 with no cross-reference, inviting a second query path for tag filtering

**ADs involved:** AD-5, AD-8. **Capability Map rows:** "FR2/FR7/FR10 — Tags & filtrage" (→ AD-5) and "FR6/FR7 — Recherche & filtres" (→ AD-8, AD-9).

FR7 (filter by tag and by type) appears in **two separate Capability Map rows**, governed by two different ADs that never reference each other. AD-8's entire purpose is to prevent exactly two divergent query paths ("jamais deux chemins de requête codés séparément") for search-with-term vs filters-only — but AD-8 is not listed as governing the tags row, and AD-5 says nothing about how tag-based filtering is executed as a query.

- **Builder A**, working the "Tags" story under AD-5/AD-18, ships tag filtering as its own query surface — e.g. a scope method or a route parameter handled by a code path specific to `Tag`/`document_tag`, built and tested independently of `DocumentController@index`.
- **Builder B**, working the "Search & filters" story under AD-8, builds `DocumentController@index` as the single entry point for term + filters, and only later discovers (or doesn't) that tag filtering was already implemented as a second, parallel path by Builder A.

Both builders can point to their governing AD and claim full compliance; the contradiction only exists in the seam between two Capability Map rows that were never reconciled. This is precisely the two-query-path divergence AD-8 was written to forbid, reachable without either builder violating their own cited AD.

---

## Finding 5 (MEDIUM) — FR13 capability coverage has a structural gap versus every other row

**ADs involved:** AD-17. **Capability Map row:** "FR13 — Pièces jointes."

Every other Capability Map row names at least a controller/method (`DocumentController@store`, `DocumentController@download`, `DocumentController@index`, `TagController`). The FR13 row names only two Actions and a Model — no controller, no route, no Form Request, no DTO. The Structural Seed confirms this isn't an omission of convenience: `Http/Controllers/` lists only `DocumentController.php` and `TagController.php` (no `AttachmentController`), `Http/Requests/` lists only `ImportDocumentRequest.php` and `SaveDocumentRequest.php` (nothing for attachments), and `DataTransferObjects/` lists only `DocumentData.php`/`TagData.php` (no `AttachmentData.php`) — yet AD-3 requires every Action to take a typed DTO.

- **Builder A** adds `attachFile`/`detachFile` methods to `DocumentController`, treating attachments as a sub-concern of the document resource.
- **Builder B**, applying the Consistency Conventions rule "un controller par ressource... méthodes RESTful standard," reads `document_attachments` as its own resource and creates a dedicated `DocumentAttachmentController` with `store`/`destroy`.

Both are defensible readings of the existing conventions; the spine simply never picked one, unlike every other capability it defines.

---

## Finding 6 (MEDIUM) — AD-17 has no stated guard restricting attachments to `source = created` documents

**ADs involved:** AD-17, AD-4, AD-7. FR13's descriptive text ("à un document créé") implies the restriction, but AD-17's Rule itself places no constraint on `AttachDocumentFileAction` beyond the storage path convention. An imported document already has its own `file_path` (AD-4/AD-7); nothing in AD-17 states whether attaching a second file to an *imported* document is disallowed, silently permitted, or an error. A builder could implement the Action generically (no `source` check) since nothing in AD-2/AD-17 forbids it, while UI-layer builders assume (correctly, per FR13's prose, but not per any Rule text) that the affordance only ever appears on created-document pages — leaving the backend permissive where the product intent is restrictive.

---

## Finding 7 (LOW) — No rule for a tag deleted mid-session from a stale selector

**ADs involved:** AD-5, AD-18. Tags can be deleted at any time from Configuration (AD-18), detaching pivot rows. Nothing addresses what happens when a `Document`-save request (Editor autosave, or the pivot-write path from Finding 1) arrives carrying a tag ID that was deleted after the page loaded — FK violation surfaced as an uncaught exception, silent drop of the missing ID, or a validation error are all live, unaddressed choices, and the "jamais d'exception non catchée" convention (Consistency Conventions § État & transverse) doesn't by itself dictate which of the non-exception behaviors to pick.

---

## Non-finding: AD-16 removal hygiene

Checked explicitly per the review brief: the Capability Map, Structural Seed, and every other AD's Rule/Prevents text were searched for `category_id`, `Category`, `CategorizeDocumentAction`, and `DeleteCategoryAction`. The only surviving references are the intentional historical pointers inside AD-5's Changelog and AD-16's own stub, plus one comparative mention inside AD-18 ("même discipline que l'ancien `DeleteCategoryAction` (désormais retiré, voir AD-5)") — all correctly framed as removed, not live. No contradiction found here; this part of the amendment is clean.

---

## Summary Table

| # | Finding | Severity |
| --- | --- | --- |
| 1 | No named owner for `document_tag` writes; full-replace (`sync`) vs incremental (`attach`/`detach`) paths collide | Critical |
| 2 | AD-15 not amended in its own text; attachment-cleanup ordering ambiguous; FK cascade can silently skip file deletion | Critical |
| 3 | AD-5 "no free text" rule unenforced at `TagSelector.vue`'s component boundary, reused across stories with no prop contract | High |
| 4 | FR7 double-governed by AD-5 and AD-8 with no cross-reference — invites a second, forbidden query path | High |
| 5 | FR13 Capability Map/Structural Seed missing controller, route, Request, and DTO — every other row has these | Medium |
| 6 | AD-17 has no rule restricting attachments to `source = created` documents | Medium |
| 7 | No rule for a stale/deleted tag ID arriving in a save request | Low |
