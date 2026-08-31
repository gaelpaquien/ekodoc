---
title: Review — ARCHITECTURE-SPINE.md (rubric pass)
reviewed: '_bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md'
reviewer: rubric checklist (good-spine)
date: 2026-08-31
---

# Review — EkoDoc Architecture Spine

## Verdict

Solid, mostly enforceable spine that correctly resolves the PRD's three open questions (classification model, search engine, format scope), but it leaves one PRD-critical content-model decision — how inline editor images are stored and referenced across import/export — completely undecided, and it doesn't govern deletion cleanup across the three subsystems (disk, search index, preview cache) it created.

## Findings by checklist item

### 1. Fixes the real divergence points for the level below

- **[CRITICAL] No AD governs where/how inline editor images (FR9) are stored or referenced.** AD-7 covers only the original imported file; AD-4/AD-11/AD-12 assume `content_html` exists but never say how images inserted via the TipTap toolbar (or drag-drop) end up represented inside it. Two builders could independently and both "correctly" choose: (a) base64 data URIs embedded directly in `content_html`, or (b) separate files under a disk path + an `<img src>` pointing at an app route. These are not interchangeable: they have different storage-growth, backup, and — critically — export-fidelity consequences for Browsershot (AD-11, needs to resolve the image at render time) and PHPWord (AD-12, needs the raw bytes to embed in OOXML). NFR5 explicitly calls image position/rendering fidelity "critique, pas secondaire," and Flow 3's climax in EXPERIENCE.md is built entirely around this working. This is the single most load-bearing content-model decision FR9/NFR5 depend on, and it is silent. The Structural Seed also has no controller/route for image upload, confirming the gap.
  - *Fix:* Add an AD (e.g. "AD-14 — Images éditeur : fichier privé + route de service, jamais base64") that picks one storage strategy, states the path convention (reusing the AD-7 pattern: private disk + streaming route, or the DB-embedding tradeoff explicitly), and states how AD-11/AD-12 resolve that reference at export time.

- **[HIGH] `DeleteDocumentAction` is named in the Structural Seed but has no governing AD.** Deleting a `Document` touches three subsystems this spine itself created: the file on private disk (AD-7), the Scout index entry (AD-8), and the cached preview PDF (AD-10). Nothing states that delete must cascade to all three. Two builders could diverge: one deletes only the DB row (leaving orphaned files and a stale, dead search hit for FR6), another cleans up everything. In a tool with no admin/cleanup surface, an orphan-accumulating implementation is a real long-term defect, and a stale search result returning a 404 on click is a visible regression against FR6/FR7.
  - *Fix:* Add a short Rule (either as a note under AD-7/AD-8/AD-10's "Rule" text, or its own AD) stating `DeleteDocumentAction` must remove the disk file, the cached preview if present, and the Scout index entry (via model deletion, which Scout syncs automatically) in one transaction/operation.

- **[MEDIUM] FR4 preview rendering for `source = created` documents is not mapped.** The Capability → Architecture Map ties FR4 solely to `ConvertDocumentToPreviewAction` (AD-10), which is explicitly scoped to `source = imported` + Word/Excel mime types. But FR4 and the Fiche document surface (EXPERIENCE.md) apply to both imported and created documents. What renders in the preview panel for a created document is presumably just the stored `content_html`, but that's an inference, not a stated decision — and it interacts with the same image-reference question raised above (finding 1).
  - *Fix:* Add one line to AD-10 or the Capability map clarifying that `source = created` documents render their stored `content_html` directly in the Fiche document preview panel (no conversion step), closing the FR4 mapping for both branches of `source`.

### 2. Every AD's Rule is enforceable and actually prevents its stated divergence

- No findings for AD-1 through AD-9, AD-11, AD-12, AD-13 — each names a concrete, checkable constraint (single table, no queue, disk visibility, search entry point, naming pattern, library choice) that two independent builders would satisfy identically.
- **[LOW] AD-10's cache-invalidation trigger is underspecified and possibly speculative.** "Régénéré seulement si le fichier source change" doesn't say what change-detection mechanism to use (mtime, hash, version column), and no FR in the PRD allows replacing an already-imported file post-import — so the condition may never actually fire in v1. As written, if this scenario is ever hit, two builders would diverge on the detection mechanism.
  - *Fix:* Either state the mechanism explicitly (e.g. compare `filemtime()` of the source against the preview's, or drop the "regenerate on change" clause entirely and say "generated once, cached forever — regeneration policy is Deferred until an update-file flow exists").

### 3. Nothing under "Deferred" could let two units diverge in a way that matters before it's revisited

- No findings. Backup, legacy formats, auth/multi-user, semantic search, and deployment-beyond-local are all genuinely inert in v1 (no code path exists yet for any of them to diverge on), and each carries a concrete trigger condition for revisiting.

### 4. Named tech is verified-current

- No findings. PHP 8.5 / Laravel 13 / Inertia 2.x / Tailwind 4.x / TipTap 3.x are all consistent with a mid/late-2026 timeline, and the two lower-confidence dependencies (`spatie/browsershot` 5.4, `smalot/pdfparser` 2.12.4) are both marked with an explicit verification date and, for pdfparser, an explicit maintenance-risk callout with a designed-in fallback (AD-9). This is good practice, not a gap.

### 5. Every dimension the altitude owns is decided, deferred, or an open question

- **[MEDIUM] Testing enforcement is stated as an absolute but has no enforcement mechanism, and this tension is internal to the document.** Consistency Conventions states "100% de couverture de tests (Pest)" as a flat rule, while the Deferred section explicitly states no CI/CD is defined for v1 ("Aucune configuration de ... CI/CD ... n'est définie"). Without CI, "100% coverage" is not an invariant — it's an aspiration that will erode silently the first time a builder skips a test under time pressure, with nothing in the architecture to catch it.
  - *Fix:* Either soften the convention ("tests required for all Actions; coverage is not gated" ) or add a one-line Deferred/Consistency note that coverage is self-enforced only, pending any future CI.
- Operational/environmental envelope (deployment, infra, ops) is explicitly and adequately addressed: Deferred states v1 is single-machine Herd-only with no staging/CI/hosting, consistent with NFR1. This is a correct, non-silent decision for this dimension — no finding.

### 6. No bloat/overspecification

- No findings beyond the AD-10 cache-trigger note already logged in item 2. The rest of the spine stays at invariant altitude (naming patterns, storage boundaries, library choices) without dictating method bodies, variable names, or other code-level "seed" detail dressed up as architecture.

### 7. Section shape matches template order

- No findings. Order is Design Paradigm → Invariants & Rules → Consistency Conventions → Stack → Structural Seed → Capability → Architecture Map → Deferred, matching the template (no "Inherited Invariants" section is needed since there is no parent-level architecture above this one to inherit from).

### 8. Diagrams are valid and non-empty

- No findings on validity — the single `graph LR` (Controller → DTO/Action → Model → Controller) is syntactically valid and does carry real structure (the paradigm's data/control flow), not decoration.
- **[LOW, optional]** It is the only diagram in the document. The import → extract → index → preview → export pipeline spans five ADs (AD-6, AD-9, AD-10, AD-11, AD-12) with different synchronous/on-demand/cached behaviors per stage; a short sequence or flow diagram for that pipeline would make the lifecycle easier to hold in one view. Not required by the checklist (one valid, structural diagram already satisfies it), but worth considering given the pipeline's complexity relative to the rest of the spine.

## Summary table

| # | Finding | Severity |
| --- | --- | --- |
| 1 | Editor inline-image storage/reference model undecided | Critical |
| 2 | `DeleteDocumentAction` cleanup across disk/index/preview cache ungoverned | High |
| 3 | FR4 preview rendering for `source = created` not mapped | Medium |
| 4 | "100% test coverage" stated with no enforcement mechanism (CI deferred) | Medium |
| 5 | AD-10 cache-invalidation trigger underspecified / possibly speculative | Low |
| 6 | Single diagram; a pipeline diagram for the multi-AD import/export flow would help | Low (optional) |
