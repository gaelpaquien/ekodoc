# PRD Quality Review — EkoDoc (prd-ekodoc-2026-08-31)

## Overall verdict
This PRD is well-calibrated to its stakes: a solo, local, ~350-document tool with a single builder-operator, correctly shaped as a lean capability spec rather than a padded template exercise. Strategic coherence and honesty about scope are the strongest points — the counter-metric, the explicit non-pixel-perfect trade-off, and the "Hors scope" list all do real work. The main risks are downstream: a couple of NFRs lean on unbounded adjectives ("rapide," "raisonnable"), one FR's cross-reference doesn't actually resolve to the detail it promises, and the three inline `[ASSUMPTION]` tags have no index to roundtrip against.

## Decision-readiness — adequate
Decisions are stated as decisions, not hedged as considerations: fulltext search is explicitly pulled into v1 scope with a stated reason ("priorité v1 confirmée pour rester exploitable au-delà de quelques dizaines de documents," FR6), and the mono-user scope is framed as a deliberate deferral, not an oversight ("évolution ultérieure, conditionnée à la preuve de valeur et à une décision d'infrastructure," § Utilisateurs). NFR5 names an actual trade-off with what's given up: "sans viser une fidélité pixel-perfect — à l'exception des images inline (FR9)." The three Open Questions are genuinely open (legacy formats, classification granularity, search engine) — none are rhetorical.

No `[NOTE FOR PM]` callouts appear anywhere in the PRD. Given this is a solo tool where the PM and the builder are the same person, this is a defensible economy rather than a dodge — but it means the one real tension worth flagging (Meilisearch vs. SQLite FTS5 trades local simplicity against search relevance, addendum.md § Options de recherche fulltexte) lives only in the addendum's prose, not as a marked decision point.

### Findings
- **low** Search-engine trade-off not marked as a decision point (addendum.md, "Options de recherche fulltexte") — the addendum lays out three options and a leaning ("SQLite FTS5 ou Scout/database semblent les mieux alignés") but this is prose, not a flagged tension a future reader would immediately spot as unresolved. *Fix:* a one-line `[NOTE FOR PM]` or equivalent marker at the recommendation would make it scannable.

## Substance over theater — strong
No personas, no Vision-statement boilerplate, no differentiation section — all appropriately absent for a single-operator internal tool where that apparatus would be theater. The "Contexte" section stands in for Vision and is specific to this problem, not swappable into another PRD ("corpus d'environ 350 documents existants," "Laravel Herd"). NFRs are grounded in real numbers and named exclusions (NFR1: local-only via Herd; NFR4: `.doc`/`.xls` explicitly deprioritized) rather than copied "must be scalable/secure" language. No findings.

## Strategic coherence — strong
The thesis is legible: consolidate scattered documents and make retrieval + creation reflexive for one user, prove value before any multi-user investment. Feature sequencing follows from it — fulltext search was promoted into v1 specifically because the corpus is large enough that browsing breaks down (FR6 rationale). Success Metrics validate the thesis rather than measuring activity: "Usage quotidien réel constaté" and "devient le réflexe" target actual reuse, and the PRD names an explicit counter-metric — "le nombre de documents importés seul n'est pas un signal de succès" (§ Métriques de succès) — which is exactly the kind of honesty the rubric rewards and that most PRDs skip. No findings.

## Done-ness clarity — adequate
Most FRs are testable: format lists (FR1, NFR4), the WYSIWYG editor's supported block types (FR8: "titres, listes, tableaux, images"), and the inline-image fidelity requirement (FR9, FR11, FR12, reinforced by NFR5's carve-out) all give a builder a clear bar to hit. The addendum adds concrete implementation guidance for the riskiest item (image position through PDF/Word export) and calls out testing it early — that's the PRD doing its job.

Two NFRs rely on unbounded adjectives the rubric flags by name:
- NFR2 — "Reste rapide à l'usage (recherche, consultation)" has no number attached (no target response time), even though it's the one NFR most likely to actually get violated at 350 documents with a naive setup.
- NFR5 — "La fidélité d'export... doit rester raisonnable... sans viser une fidélité pixel-perfect" for the non-image content has no bound at all beyond "raisonnable." The image exception is well specified; the general case isn't.

### Findings
- **medium** NFR2 has no measurable performance bound (§ Exigences non fonctionnelles, NFR2) — "rapide" isn't verifiable. *Fix:* add a concrete target, e.g. "recherche fulltexte < 1s sur ~350 documents," even a rough one, so architecture has something to design against.
- **low** NFR5's general fidelity bar ("raisonnable") has no criterion beyond the image carve-out — acceptable at this stakes level since it's explicitly de-prioritized relative to the image exception, but worth one clarifying phrase (e.g., "mise en page globale, pas la fidélité typographique fine"). *Fix:* one sentence defining what "raisonnable" excludes.
- **medium** FR4's cross-reference doesn't resolve where it points — "Word/Excel via une conversion, voir addendum.md" (FR4) — but addendum.md contains no section on preview conversion; the only related content is a pointer back to the brief ("pièges de prévisualisation Office," addendum.md § Reprise du tour d'horizon du brief). *Fix:* point FR4 directly at the brief, or add a short preview-conversion subsection to the addendum matching FR9/FR11's pattern.

## Scope honesty — adequate
"Hors scope (v1)" does real work with four concrete exclusions (AI/MCP agent, multi-user, shared hosting, templates), not a token gesture. The three inline `[ASSUMPTION]` tags (§ Utilisateurs: no roles/accounts; FR8: no templates; NFR4: legacy formats not prioritized) mark genuine inferences rather than confirmed decisions. Open-items density (3 Open Questions + 3 Assumptions across 12 FRs and 5 NFRs) is proportionate to the stakes — not padded, not suspiciously thin for a PRD headed into architecture next.

### Findings
- **medium** No Assumptions Index — the three `[ASSUMPTION]` tags are inline only; nothing at the end of the PRD collects them for a roundtrip check. *Fix:* add a short "Assumptions" list at the end (even three bullets) so a reader validating the PRD later doesn't have to re-scan the whole document to find every tag. (Also noted under Mechanical notes.)

## Downstream usability — adequate
No formal Glossary, but the PRD is small enough (12 FRs, 5 NFRs) that terminology stays legible without one — "dossiers," "catégories," and "documents créés" are used consistently enough not to create ambiguity, though see the mechanical note on "dossiers et/ou catégories" below. FR/NFR IDs are contiguous and unique (FR1–FR12, NFR1–NFR5) with no gaps or duplicates. Cross-references mostly resolve: FR10's references to FR2/FR3/FR6/FR7, and FR11/FR12/NFR5's references to FR9, all point to real requirements. The one exception is FR4's dangling addendum reference (see Done-ness). Since the immediate next steps are UX and architecture rather than story generation, the bar here is lighter than a chain-top PRD would need — the PRD clears it.

## Shape fit — strong
This is the PRD's best-calibrated dimension. It correctly skips personas and named-protagonist UJs — a single implicit user (the builder) makes that apparatus overhead, and the rubric calls this out by name ("Internal tool, single-operator role → capability spec shape; UJs may be overhead"). In their place, "Parcours type" gives two lightweight usage flows without manufacturing a persona to hang them on. Success Metrics are operational ("usage quotidien réel constaté") rather than forced into user-satisfaction language. No over-formalization, no under-formalization. No findings.

## Mechanical notes
- **Assumptions Index roundtrip: broken.** Three `[ASSUMPTION]` tags appear inline (§ Utilisateurs, FR8, NFR4) but there is no Assumptions Index section anywhere in the PRD to roundtrip against. Low cost to fix given only three items exist.
- **Glossary drift: minor.** "Classer... par dossiers et/ou catégories" (FR2) vs. "catégorie/dossier" (FR3, FR7) vs. "dossiers hiérarchiques, catégories à plat" (Questions ouvertes) — the terms are used somewhat interchangeably. Not contradictory, and the granularity question is already correctly flagged as open, but a future Glossary entry should pin down whether "dossier" and "catégorie" are the same concept or two axes.
- **ID continuity: clean.** FR1–FR12 and NFR1–NFR5 are contiguous, unique, and every cross-reference among them resolves (FR10→FR2/FR3/FR6/FR7; FR11/FR12/NFR5→FR9). No gaps or duplicates.
- **UJ protagonist naming: N/A by design.** No UJs are present; per Shape fit, this is the correct choice for a single-operator tool rather than an omission.
- **Required sections for stakes: present.** Contexte, Parcours type, Utilisateurs, FR, NFR, Métriques de succès, Hors scope, Questions ouvertes are all there and proportionate to a solo/internal-tool PRD. The addendum appropriately carries technical-how detail (export implementation options, search engine trade-offs) out of the main body.
