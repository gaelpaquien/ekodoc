# Input Reconciliation — sprint-change-proposal-2026-09-09.md vs PRD

Scope reminder: the proposal's "4. Detailed Change Proposals > PRD" subsection is the only part that assigns PRD changes. Architecture and UX changes (AD-5/16/17/18, DESIGN.md, EXPERIENCE.md) are explicitly out of scope for this document and are not evaluated here except to confirm they weren't mistakenly needed in the PRD.

## Applied correctly

- **FR2** — PRD text is a verbatim match to the proposal's NEW FR2: "Classer les documents (importés et créés) par tags illimités, choisis dans une liste gérée (pas de texte libre)." (prd.md line 30)
- **FR3** — Verbatim match to NEW FR3: "...titre, type, tags, date d'ajout." (prd.md line 31)
- **FR7** — Verbatim match to NEW FR7: "Filtrer les résultats par tag et par type de document (PDF, Word, Excel, document créé dans l'outil)." (prd.md line 41)
- **FR13** (new) — Verbatim match, added under a new "Pièces jointes & configuration" subsection (prd.md line 53).
- **FR14** (new) — Verbatim match, same subsection (prd.md line 54).
- **Open Questions** — "Granularité de classement à trancher..." bullet is gone; "Questions ouvertes" now contains only the two unrelated items (formats hérités, moteur fulltexte). Confirmed removed as instructed.
- **FR10 cross-reference** — FR10 still references "(FR2, FR3, FR6, FR7 s'appliquent aussi aux documents créés)"; since FR2/FR3/FR7 were edited in place (not renumbered), this cross-reference remains valid without needing its own edit.
- **Front-matter** — `updated: 2026-09-09` on prd.md reflects the change date.
- **.memlog.md** — has explicit "(change)" entries logging the FR2/FR3/FR7 replacements and their source (sprint-change-proposal-2026-09-09), consistent with the applied edits.

## Gaps (in input, not reflected in PRD/addendum)

- None found. Everything the proposal's "PRD" subsection specified (FR2/FR3/FR7 replacement text, FR13/FR14 additions, open-question removal) was applied correctly and completely.
- Items from the Issue Summary / Impact Analysis that are *not* PRD gaps, confirmed intentionally out of scope for this document per the proposal's own "Artifact Conflicts" section:
  - WCAG contrast validation decision ("aucune validation de contraste n'est requise") — scoped to DESIGN.md/UX-DR23, not a PRD NFR (PRD has no accessibility NFR to begin with, so there's nothing stale to reconcile here).
  - 5-surface navigation model (vs. old "3 surfaces, pas de sidebar") — scoped to EXPERIENCE.md/DESIGN.md (Information Architecture), not a PRD FR.
  - Bug TipTap (nested tables) — proposal explicitly states "sans impact PRD/Architecture (FR8 le couvre déjà)."
  - Architecture changes (AD-5, AD-16 removal, AD-17, AD-18) — scoped to ARCHITECTURE-SPINE.md, separate workflow step.

## Stale references found

- **None inside prd.md or addendum.md.** A full-text search for "dossier"/"catégorie"/"categorie" (case-insensitive) across the PRD folder turns up matches only in two files outside the scope of this update:
  - `review-rubric.md` (line 39, 46) — a pre-existing review artifact written against the *old* FR2/FR3/FR7 text (mentions "Glossary drift" between "dossiers et/ou catégories" and "catégorie/dossier"). This is now stale relative to the current PRD but is a review artifact, not the PRD itself — flagged for awareness, not a PRD/addendum defect.
  - `.memlog.md` (lines 6, 16-18) — a historical change log; references to "dossiers/categories" there are appropriately preserved as history (it also logs the FR2/FR3/FR7 replacement itself), not something to edit.
- `addendum.md` contains zero occurrences of "catégorie"/"dossier" — it never referenced the old classification concept (its content is about export fidelity for inline images and fulltext-search engine options), so no cleanup is needed there for the tags-only decision.

## Recommendation

- No PRD or addendum edits are needed as a follow-up to this reconciliation — the sprint-change-proposal's PRD subsection was applied fully and accurately.
- Optional, low-priority housekeeping (not required by the change proposal, not blocking): consider refreshing or archiving `review-rubric.md` since its "Glossary drift" finding refers to language the PRD no longer contains (post-change, PRD consistently uses "tags" only). No action needed on `.memlog.md` — its historical entries are correctly append-only.
- No addendum.md changes needed; it remains consistent with the tags-only PRD as written.
