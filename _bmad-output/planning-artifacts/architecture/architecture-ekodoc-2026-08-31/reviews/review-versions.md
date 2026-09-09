---
name: 'Review — Version Claims'
type: review
scope: 'ARCHITECTURE-SPINE.md Stack table + AD-5/AD-16/AD-17/AD-18 (2026-09-09 tags/attachments/Configuration update)'
reviewed: '2026-09-09'
verdict: pass
---

# Review — Technology Version Claims & New-Mechanism Sanity Check (2026-09-09 update)

Method: read `ARCHITECTURE-SPINE.md` (current, post-2026-09-09 amendment) and `.memlog.md` in full to
establish what was already verified and corrected in prior review passes (notably the 2026-09-01ish
"Correction post-verification" line fixing Inertia.js and Vite, and the earlier per-library
"(vérifié...)" annotations for TipTap/Browsershot/phpword/pdfparser/Pest). Ran fresh, dated (2026-09)
web searches against every Stack-table entry named in the task, rather than trusting the prior
review's dates, since the prior `reviews/review-versions.md` on disk was itself stale (dated
2026-08-31, predating the Inertia/Vite corrections). Also reasoned through whether the three new
mechanisms introduced by this update (tags many-to-many, attachments table, Configuration CRUD page)
are stock Laravel/Inertia/Vue patterns or hide an undecided library need.

## Verdict: Pass

No committed decision in this update rests on an unverified or incorrect version claim, and no new
mechanism introduced by AD-5 (amended)/AD-17/AD-18 requires a library decision that was skipped. The
previously-flagged Inertia.js/Vite corrections are confirmed durable — they hold up against current
(September 2026) sources, not just the sources available at the time of the prior correction.

## 1. Stack table version re-verification (fresh web checks, 2026-09)

| Technology | Spine claim | Fresh web-check result | Verdict |
|---|---|---|---|
| Laravel | 13, requires PHP 8.3+ | Laravel 13 released 2026-03-17, minimum PHP 8.3 confirmed (PHP 8.2 dropped). Matches exactly. | **Holds.** |
| Inertia.js | 3.0 (vérifié — actuel août 2026 ; Axios removed, ESM-only, renamed APIs) | Confirmed: Inertia v3.0 ships its own XHR client (Axios now optional/peer, not required), ships ESM-only, renamed APIs, adds `useHttp`/optimistic updates. This is the current major as of Sept 2026 — no v4 exists. The spine's own description of the breaking changes matches official release notes point-for-point. | **Holds, and description is accurate**, not just the version number. |
| Vite | 8.x | Vite 8.0 released 2026-03-12 (Rolldown-based bundler), still current major as of Sept 2026 (no v9). | **Holds.** |
| Laravel Scout | "dernier, driver `database`" | Latest Scout is v11.6.1 (2026-08-25). Confirmed against the official Laravel 13.x Scout docs page directly: the built-in engine is invoked via `SCOUT_DRIVER=database` — the exact config value the spine uses. (One web-search summary suggested a `mysql` driver name; checked against the primary docs source and that was not correct — `database` is the real value, matching the spine.) | **Holds.** |
| Pest | 5.x (exige PHP 8.4+) | Pest 5 (v5.0.1+, announced Laracon US 2026) requires PHP 8.4+ and PHPUnit 13; compatible with the project's PHP 8.5. | **Holds.** |
| phpoffice/phpword | 1.4.0 (LGPL) | v1.4.0 released 2025-06-05, still latest on Packagist, LGPL-3.0-only confirmed, includes PHP 8.4 support. | **Holds.** |
| smalot/pdfparser | 2.12.5 | v2.12.5 released 2026-04-17/21, latest on Packagist; includes a PHP 8.5 compatibility fix (chr() deprecation). "Maintenance limitée" framing in AD-9 remains accurate — low-velocity, not abandoned. | **Holds**, and the version is now current (the prior review's own reviewed copy still showed 2.12.4 as pinned; that has since been corrected in the spine per memlog line 40, and 2.12.5 is confirmed still the latest as of this check). |
| spatie/browsershot | 5.4 | v5.4.0 released 2026-05-26, requires PHP 8.2+, still latest. | **Holds.** |

No discrepancies found. The two corrections recorded in `.memlog.md` (Inertia 2.x→3.0, Vite 5.x/6.x→8.x,
pdfparser 2.12.4→2.12.5) are not re-litigated in depth here per the task instructions, beyond
confirming they still match current reality rather than having been superseded again since.

## 2. New mechanisms (AD-5 amended, AD-17, AD-18): do they need an undecided library?

- **AD-5 — `tags` table + `document_tag` pivot (many-to-many).** Stock Eloquent `belongsToMany`
  relationship over a conventional pivot table. No package involved (not even a "taggable" package
  like `spatie/laravel-tags` — the spine deliberately hand-rolls this, consistent with AD-2/AD-3's
  zero-dependency Action/DTO stance). Nothing to verify here; this is framework-native.
- **AD-17 — `document_attachments` table, private-disk folder.** Identical shape to the already-adopted
  AD-7 (original files) and AD-14 (editor images) convention: a dedicated Eloquent model, a
  `storage/app/private/...` path, an Action as the single write entry point. No new storage
  mechanism, no new disk driver, no library. Nothing to verify.
- **AD-18 — `TagController` + Configuration Inertia page (CRUD).** A standard resource controller
  returning `Inertia::render()`, matching AD-13 (no JSON API layer) and the existing
  Controller→Action→DTO→Model paradigm. No new package.
- **The one place a library decision could plausibly be needed and wasn't explicitly named:** a
  multi-select "tag picker" widget in `TagSelector.vue` (referenced in the Structural Seed) is a
  materially different UI control than the prior single-select `CategorySelector.vue` it replaces —
  it needs multi-value selection, likely search/filter across (per FR14) an unbounded tag list, and
  keyboard/ARIA handling. A web check confirms this is a well-known category with dedicated Vue
  libraries (e.g. Vue-Multiselect, headless combobox primitives) that projects commonly reach for
  precisely to avoid hand-rolling this accessibly. **However**, this is not actually a gap in this
  update: the project already carries a standing, explicit constraint (EXPERIENCE.md, restated in the
  spine's `sources`/inherited constraints) of "Tailwind CSS sans librairie de composants tierce, pas de
  framework frontend imposé." That constraint already applied to the single-select `CategorySelector.vue`
  and, by the same logic, extends unchanged to the new multi-select `TagSelector.vue` — no new
  third-party dependency is implied by AD-5, only a larger hand-built component than before. This is
  worth calling out as an **implementation-effort note, not a research gap**: nothing was skipped that
  needed a version/library check, but a builder should not casually reach for `vue-multiselect` or
  similar under the assumption it's an obvious fit — that would violate the standing no-component-library
  constraint and would need its own explicit exception if ever proposed.

**Severity: low.** No action required in the spine text; flagging only so the constraint's reach is
explicit rather than assumed.

## 3. Abandoned / renamed / superseded check across the full spine

Checked every named technology in the Stack table plus TipTap and LibreOffice (both named elsewhere in
the spine) against current status:

- TipTap / `@tiptap/vue-3` — actively published (latest patch within the last two weeks of this
  review's search), MIT, official Vue 3 support confirmed. Not abandoned or renamed.
- Vue.js 3, Tailwind CSS 4, MySQL 8.x, LibreOffice, phpoffice/phpspreadsheet, PHP 8.5 — no signal of
  abandonment, rename, or a superseding major version as of Sept 2026 (not exhaustively re-searched
  individually in this pass since none is touched by the 2026-09-09 change and none was flagged as
  suspicious previously; no new information contradicts their prior "holds" status).

No named technology in the spine has become abandoned, renamed, or superseded.

## Overall assessment

This update is clean from a verification standpoint. It introduces zero new third-party dependencies,
and the three new/amended ADs (AD-5, AD-17, AD-18) are unremarkable applications of patterns already
adopted elsewhere in the same spine (AD-2/AD-3 zero-dependency Actions, AD-7/AD-14 private-disk
convention, AD-13 no-API-layer, the pre-existing no-component-library UI constraint). The Stack
table's existing version claims were re-checked against fresh sources rather than assumed correct
because a prior correction existed, and all hold. The single item worth a builder's attention — that
the new multi-select tag picker inherits the existing "no component library" constraint rather than
justifying a new one — is a low-severity clarity note, not a defect.
