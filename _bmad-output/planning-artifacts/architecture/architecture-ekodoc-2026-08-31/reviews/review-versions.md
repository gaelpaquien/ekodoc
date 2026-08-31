---
name: 'Review — Version Claims'
type: review
scope: 'ARCHITECTURE-SPINE.md Stack table + .memlog.md version decisions'
reviewed: '2026-08-31'
verdict: some-gaps
---

# Review — Technology Version Claims (ARCHITECTURE-SPINE.md)

Method: read `ARCHITECTURE-SPINE.md` and `.memlog.md` in full, classified every version/technology
claim by whether the memlog documents an actual web verification (vs. a bare assertion), then ran
targeted web searches (dated August 2026) against a sample weighted toward the least-verified and
highest-risk claims: framework majors (PHP, Laravel, Inertia, Vite, Tailwind), the editor (TipTap),
and the document-processing libs (browsershot, phpword, phpspreadsheet, pdfparser, Scout, Pest).

## Verdict: Some gaps

Most pinned/high-specificity version claims (the ones the memlog explicitly marks "vérifié via
recherche web") hold up exactly. However, two claims that read as confidently stated facts in the
Stack table are **stale relative to what shipped by August 2026**, and several "dernier" (latest)
entries have no documented verification trail at all — they may still be correct today because
"latest" self-updates, but the memlog gives no evidence a search was ever run for them.

## Per-technology findings

| # | Technology | Spine claim | Memlog verification marker | Web-check result (Aug 2026) | Verdict |
|---|---|---|---|---|---|
| 1 | PHP | 8.5 | Bundled into line 13's general "vérifié compatible PHP/Laravel via recherche web" | PHP 8.5.0 released 2025-11-20, current stable, active support through 2027. | **Holds.** |
| 2 | Laravel | 13 (exige PHP 8.3+) | Same bundled line 13 marker | Laravel 13 released 2026-03-17, requires PHP 8.3+, PHP 8.2 dropped. Matches exactly. | **Holds.** |
| 3 | Inertia.js | "2.x (adaptateur Laravel + Vue 3)" | Bundled into the same generic line 13 marker — **no Inertia-specific verification is documented** | **Inertia.js v3.0 is the current stable release**, shipped ~March 2026 alongside Laravel 13, with breaking changes (own XHR client replacing Axios, ESM-only output, renamed APIs, new `@inertiajs/vite` plugin, `useHttp`, optimistic updates). v2 is behind by a major version. | **Discrepancy.** This reads as a training-data-era assumption (Inertia 2.x was current pre-2026), not a verified current fact — no search result or memlog line ties "2.x" specifically to a check. Should be corrected to 3.x or explicitly justified if v2 was a deliberate choice (nothing in the memlog suggests it was deliberate). |
| 4 | Vite | "5.x/6.x (intégration officielle Laravel)" | No Vite-specific memlog line at all; bundled at best into line 13 | **Vite 8.0 released 2026-03-12**, now at 8.2.2 (Aug 2026), ships Rolldown as its bundler. Vite 5/6 are two majors behind. | **Discrepancy.** Same pattern as Inertia — asserted, not checked. Lower architectural risk (build tool, largely invisible to app code) but still a wrong current-state claim in a document meant to fix facts. |
| 5 | Tailwind CSS | 4.x | Bundled into line 13 marker, no Tailwind-specific search documented | Tailwind CSS v4 is still the current major (v4.2/4.3 as of Aug 2026); no v5 exists yet. | **Holds**, but by luck of timing rather than a documented check — no line in the memlog shows a Tailwind-specific search. |
| 6 | Vue.js | 3.x | Not mentioned in memlog at all | Not independently re-searched in this pass (low risk: Vue 3 has been the sole current major for years, no signal of a Vue 4). | **Plausible but unverified** — flagged as a documentation gap, not a suspected error. |
| 7 | MySQL | 8.x (via Herd) | Bundled into line 13 marker | Not independently re-searched in this pass (MySQL 8.x has been current/stable for years; low volatility). | **Plausible but unverified** — documentation gap only. |
| 8 | TipTap / @tiptap/vue-3 | "3.x (MIT, vérifié actif)" | Memlog line 14: explicit web verification documented | Current version is 3.29.2, actively published (last release ~1 week before this review). MIT, official Vue 3 integration confirmed. | **Holds**, and correctly documented as verified. |
| 9 | Laravel Scout | "dernier, driver `database`" | Line 23: decision documented (why database driver, not why version), **no version-verification marker** | Latest Laravel Scout is v11.6.1 (2026-08-25); built-in `database` engine confirmed as a real, current, non-external-service option. | **Holds** (claim is non-pinned "latest," so it can't be technically wrong), but no verification trail exists in the memlog — should have one per this project's own stated diligence pattern. |
| 10 | spatie/browsershot | "5.4 (vérifié actif, mai 2026)" | Line 21: explicit web verification documented | Latest is 5.4.0, published 2026-05-26, requires PHP ^8.2. Exact match. | **Holds**, correctly documented as verified. |
| 11 | phpoffice/phpword | "1.4.0 (vérifié actif, LGPL)" | Line 22: explicit web verification documented | v1.4.0 released 2025-06-05, still the latest, LGPL-3.0-only confirmed. Exact match. | **Holds**, correctly documented as verified. |
| 12 | phpoffice/phpspreadsheet | "dernier (même éditeur que phpword)" | Line 25: mentioned only as reuse rationale, **no version-verification marker** | Latest is 5.9.0 (2026-07-12) — a much higher major than phpword's 1.x, which is expected (different versioning schemes) but worth knowing since the spine implies parity/co-maintenance without checking the target version's own health. | **Holds** (non-pinned), but no documented verification — gap. |
| 13 | smalot/pdfparser | "2.12.4 (vérifié — maintenance limitée, voir AD-9)" | Line 25 confirms "maintenance limitée mais acceptable," AD-9 in the spine also states "vérifié 2026-08" | Latest is now v2.12.5 (~2026-08-23), one patch ahead of the pinned 2.12.4. Maintenance status confirmed accurate: "no active development by the author at the moment, PRs welcome," ~30M installs / 212 dependents — i.e., low-velocity but not abandoned, exactly as AD-9 frames it. | **Holds**, with a trivial one-patch drift (expected for any pinned version reviewed after the fact — not a documentation defect, just a note that "2.12.4" will keep aging). |
| 14 | LibreOffice | "dernière stable, invoqué en CLI headless" | Line 24: decision documented (why LibreOffice vs Gotenberg/Docker), **no version-verification marker** | Not independently re-searched in this pass — low risk, since the `soffice --headless --convert-to` CLI contract has been stable across LibreOffice versions for years, and the spine deliberately avoids pinning a version. | **Plausible, unverified** — documentation gap only, low risk given non-pinned wording. |
| 15 | Pest | "dernier (tests, cible 100% couverture)" | Line 17: called "défaut moderne Laravel 12/13," **no version-verification marker** | Latest is Pest 5 (v5.0.1, 2026-07-29), requires PHP 8.4+ (compatible with the project's PHP 8.5), and `pestphp/pest-plugin-laravel` v5.0.1 explicitly requires Laravel ^13.23 — confirms Pest 5 + Laravel 13 + PHP 8.5 combination is real and current. | **Holds** (non-pinned), and turns out well-founded, but no search was documented before this review. |

## Technologies in the spine with no documented verification that should have had one

Ranked by how much a wrong assumption would matter to the build:

1. **Inertia.js** — confirmed wrong claim ("2.x" vs. actual current "3.x"), and it is the one place where getting the major version wrong has real downstream consequences: Inertia v3 removes Axios, changes to ESM-only output, and renames APIs relative to v2. A build-substrate document that silently assumes v2 patterns (forms, `router` usage, Axios-based requests) risks generating code against an API surface that no longer exists in the version a fresh `composer create-project`/`npm install` would actually pull. **This should be corrected in the spine, not just this review.**
2. **Vite** — confirmed wrong claim ("5.x/6.x" vs. actual current "8.x"). Lower risk than Inertia because Vite is mostly invisible to application code and Laravel's official Vite plugin tends to track compatibility, but it is still a stale fact presented as current.
3. **Laravel Scout**, **phpoffice/phpspreadsheet**, **Pest**, **LibreOffice** — all use "dernier"/"latest" phrasing, which is technically self-correcting and did check out in this review, but the memlog shows zero search trail for any of them, unlike TipTap, Browsershot, phpword, and pdfparser which each have an explicit "(vérifié...)" annotation. The project's own convention (visible in AD-9 and the Stack table) is to mark verified items explicitly — these entries break that convention.
4. **Vue.js** and **MySQL** — no verification documented, not re-checked here either (judged low-risk/low-volatility); still worth a one-line confirmation for completeness given the document's stated ambition to fix facts rather than assume them.

## Overall assessment

The spine's diligence pattern (explicit "(vérifié...)" tags with dates) is real and mostly reliable
where it was applied — five separate pinned versions (Laravel/PHP compatibility, TipTap, Browsershot,
phpword, pdfparser) all check out against current sources with no material discrepancy. The failure
mode isn't fabrication; it's that **two framework-level version numbers (Inertia.js, Vite) were
asserted without the same treatment applied to the tools around them**, and both turned out to be
behind by one or more major versions by the time of this review — consistent with them having been
pulled from training-data-era knowledge rather than checked. A second, lower-severity pattern is that
every dependency using "dernier"/"latest" phrasing skipped the verification step entirely (even though
"latest" can't be "wrong," the project's own convention calls for confirming the choice is still sound,
e.g. checking Pest 5's PHP 8.4+ floor against the project's PHP 8.5 before assuming compatibility,
which this review did and confirmed, but the spine's authors did not).
