# PRD Quality Review — EkoDoc (prd-ekodoc-2026-08-31)

## Overall verdict
This update cleanly executes the sprint-change proposal: the folder/category split is fully gone, terminology is now consistently "tags" across FR2/FR3/FR7/FR14, and two of the three prior review's findings (NFR2's missing bound, the missing Assumptions Index) are genuinely fixed rather than papered over. The new attachments capability (FR13) is the weak spot — it states the "associate" action but leaves consultation, removal, and search/filter applicability of attached files unspecified, which the architecture spine has already had to resolve on its own (a `DetachDocumentFileAction` with no FR behind it). The tags model also lands a real UX trade-off (no free-text tagging) without naming what was given up.

## Decision-readiness — adequate
FR6's fulltext-search rationale and the mono-user deferral remain well-framed, as in the prior review. The tags redesign, however, is a case study in the red flag this dimension warns about: FR2 states "tags illimités, choisis dans une liste gérée (pas de texte libre)" as a flat decision with no trade-off attached. The sprint-change-proposal (`sprint-change-proposal-2026-09-09.md`, AD-5) shows the real rationale existed — "un mélange tag/texte libre non contrôlé (doublons du type Finance/finance/Finances)" — and that tags are managed *exclusively* via the FR14 config page ("jamais de création à la volée ailleurs"), meaning a user must round-trip to Configuration before they can apply a tag they haven't pre-created. None of that friction or rationale made it into the PRD; a reader sees only the constraint, not why it's worth the added workflow step.

### Findings
- **medium** FR2's free-text exclusion has no stated rationale or trade-off (§ Exigences fonctionnelles, FR2) — "pas de texte libre" is asserted without naming what's given up (in-flow tag creation) or why (duplicate/inconsistent tags, per the sprint-change-proposal's own AD-5 rationale, which never made it into the PRD). *Fix:* add a short clause or `[NOTE FOR PM]` to FR2 capturing the rationale, so a reader isn't left to reconstruct it from the architecture doc.
- **low** (carried over, unchanged) Search-engine trade-off still lives only in addendum.md prose ("Options de recherche fulltexte"), not marked as a decision point. *Fix:* unchanged from prior review — a one-line marker at the SQLite/Scout/Meilisearch recommendation would make it scannable.

## Substance over theater — strong
Unchanged from the prior review. No personas, no Vision boilerplate, no differentiation theater. The new FR13/FR14 read as genuine capabilities the sprint-change surfaced (attachments, tag management), not filler. No findings.

## Strategic coherence — strong
The tags redesign tightens rather than dilutes the thesis: "classer par tags illimités" is a more honest fit for a flat, search-first library than the prior dossier/catégorie split ever was, and FR6 (fulltext search) remains the backbone the PRD was already betting on. No findings.

## Done-ness clarity — thin
This dimension moved in both directions. Two prior findings are now genuinely resolved:
- NFR2 now reads "Recherche fulltexte en moins d'1 seconde sur un corpus de l'ordre de 350 documents (cible indicative, pas un SLA)" — a real, testable bound. Fixed.
- FR4's addendum cross-reference now points at `../../briefs/brief-ekodoc-2026-08-31/addendum.md` § *Pièges techniques à anticiper*, and that section exists (verified). Fixed.

But the new attachments FR introduces a gap at least as serious as what it replaced. FR13 says only: "Associer un ou plusieurs fichiers (PDF, Word, Excel) à un document créé, indépendamment du contenu rédigé dans l'éditeur WYSIWYG (FR8)." It states the association action and nothing else — no testable consequence for what happens after. Compare FR10, which explicitly says "FR2, FR3, FR6, FR7 s'appliquent aussi aux documents créés" — FR13 has no equivalent clause. Concretely, unanswered: can an attached file be previewed (FR4) or downloaded (FR5) individually? Can it be removed after attaching? Is it covered by fulltext search (FR6) or filtering (FR7)? The sprint-change-proposal's own architecture section (AD-17) already commits to a `DetachDocumentFileAction` — a capability with no FR behind it, meaning architecture is inventing requirements the PRD didn't state. That's the traceability direction the rubric warns against (downstream should source-extract from the PRD, not the reverse).

Separately, FR14 ("Gérer les tags (créer, renommer, supprimer)") doesn't say what happens to a document's existing tag assignments when a tag in use is deleted. The sprint-change-proposal (AD-18) has already decided this — detach from all documents, never delete the documents — but that decision isn't reflected anywhere in the PRD text itself.

### Findings
- **high** FR13 has no testable consequence beyond "associate" (§ Pièces jointes & configuration, FR13) — consultation (preview/download), removal (detach), and search/filter applicability (FR6/FR7) of attached files are all unstated, yet architecture has already had to invent a detach action to fill the gap. *Fix:* extend FR13 (or add an FR) to state explicitly whether attachments can be previewed/downloaded individually, removed, and whether they're covered by FR6/FR7 — matching the pattern FR10 uses for created documents.
- **medium** FR14 doesn't specify tag-deletion behavior when the tag is in use (§ Pièces jointes & configuration, FR14) — silent cascade-detach vs. blocked deletion is left to the reader's assumption, even though the sprint-change-proposal shows this was already decided (detach from documents, keep documents). *Fix:* add one clause to FR14, e.g. "la suppression d'un tag le détache de tous les documents concernés, sans les supprimer."
- **low** (carried over, unchanged) NFR5's general fidelity bar ("raisonnable") still has no criterion beyond the image carve-out. *Fix:* unchanged from prior review — one sentence defining what "raisonnable" excludes would close this.

## Scope honesty — adequate
The Assumptions Index is now present and roundtrips cleanly: all three inline `[ASSUMPTION]` tags (§ Utilisateurs, FR8, NFR4) are indexed verbatim at the end, and no index entry lacks an inline counterpart. Fixed from the prior review. The "Questions ouvertes" removal is also correctly executed — the classification-granularity question (dossiers vs. catégories) is gone, consistent with the sprint-change-proposal's instruction that it became moot once tags replaced the dual model.

The attachments gap noted under Done-ness has a scope-honesty angle too: whether FR13 attachments fall inside or outside FR6 (search) and FR7 (filter) scope is neither confirmed nor declared a `[NON-GOAL for MVP]` — it's a silent omission rather than an honest exclusion, and at this point in the document's life (heading into architecture, which has already guessed an answer) that's worth closing explicitly rather than leaving implicit.

### Findings
- **medium** Attachments' search/filter scope is an unmarked omission, not a stated non-goal (§ Pièces jointes & configuration, FR13; also § Hors scope) — if attachments are intentionally out of FR6/FR7 scope for v1, that should be a `[NON-GOAL for MVP]`; if they're meant to be included, FR6/FR7 should say so. *Fix:* pick one and state it — either extend FR6/FR7's wording or add a non-goal bullet.

## Downstream usability — adequate
The tags redesign is a net improvement here. The prior review's glossary-drift note ("dossiers et/ou catégories" vs. "catégorie/dossier" used loosely across FR2/FR3/FR7) is now fully resolved — "tags" is used identically across FR2, FR3, FR7, and FR14 with no synonym drift. FR/NFR IDs remain contiguous and unique (FR1–FR14, NFR1–NFR5), and the FR4 cross-reference now resolves (see Done-ness). The one open item is the FR13/FR14 traceability gap already noted — architecture has decisions (AD-17 detach, AD-18 cascade-delete) that don't trace back to any FR text, which is exactly the kind of drift this dimension exists to catch. Since the next hop is architecture/UX rather than direct story generation, the bar is a notch lighter, but this PRD is chain-top per the brief, so it still matters.

## Shape fit — strong
Unchanged in verdict. The new FR14 "page de configuration dédiée" is proportionate rather than over-formalized — a single-operator tool with a controlled tag vocabulary genuinely needs one place to manage that vocabulary, and the PRD doesn't manufacture UJs or personas to justify it. No findings.

## Mechanical notes
- **Assumptions Index roundtrip: clean (fixed).** All three inline `[ASSUMPTION]` tags (§ Utilisateurs, FR8, NFR4) are indexed at the end; every index entry has an inline counterpart. This was broken in the 2026-08-31 review and is now resolved.
- **Glossary drift: resolved.** The "dossiers et/ou catégories" vs. "catégorie/dossier" inconsistency flagged previously no longer exists — the category concept was removed entirely rather than patched, and "tags" is used identically across FR2, FR3, FR7, FR14.
- **ID continuity: clean.** FR1–FR14 and NFR1–NFR5 are contiguous and unique; FR10's cross-references to FR2/FR3/FR6/FR7 and FR11/FR12/NFR5's references to FR9 all still resolve. FR13/FR14 slot in without gaps.
- **PRD ↔ Architecture traceability: drifted.** The sprint-change-proposal's architecture section (AD-5, AD-17, AD-18) contains product decisions — no inline tag creation ("jamais de création à la volée ailleurs"), tag-deletion cascade behavior, attachment detach capability — that aren't reflected in the PRD's FR text. Architecture is currently the source of truth for these behaviors instead of the PRD; worth folding back before story creation so the PRD stays authoritative.
- **UJ protagonist naming: N/A by design.** Unchanged — no UJs, correctly so for a single-operator tool.
- **Required sections for stakes: present.** The new "Pièces jointes & configuration" section is proportionate and correctly scoped; Contexte, Parcours type, Utilisateurs, FR, NFR, Métriques de succès, Hors scope, Questions ouvertes, and Index des assumptions are all present.
