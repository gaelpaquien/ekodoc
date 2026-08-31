---
title: Reconciliation — Brief vs PRD (EkoDoc)
source: briefs/brief-ekodoc-2026-08-31/{brief.md,addendum.md}
target: prds/prd-ekodoc-2026-08-31/{prd.md,addendum.md}
generated: 2026-08-31
---

# Reconciliation: brief-ekodoc-2026-08-31 → prd-ekodoc-2026-08-31

Known, already-logged deviation (NOT flagged below): fulltext search + filtering was
explicitly moved from "hors v1" (brief) into v1 scope (PRD FR6/FR7, PRD addendum
`.memlog.md` override 2026-08-31) during PRD discovery. This is intentional and
correctly reflected on both sides.

## Section-by-section mapping

### brief.md

| Brief section | Landed in PRD | Notes |
|---|---|---|
| Résumé exécutif (centralize + create, local tool, small user group, "prove value first") | PRD Contexte | Tone/intent preserved ("volontairement restreint"). PRD narrows "poignée d'utilisateurs" → solo user for v1 (see Utilisateurs), explicitly justified as a deliberate scope decision, not silent. |
| Le problème (dispersed docs; **new-arrival pain point as a driver of the problem**) | PRD Contexte (dispersion only); new-arrival angle moved to Métriques de succès as a forward-looking mention only | Partial demotion — see Gap 3. |
| La solution (deposit/consult + create/export, two complementary usages) | FR1–FR5 (consult/download), FR8–FR12 (create/export) | Fully mapped. |
| La solution — `[ASSUMPTION]` v1 runs local via Laravel Herd, no auth/multi-user/remote access | NFR1, NFR3, PRD Utilisateurs | Preserved, and sharpened (solo user, not just "no auth infra"). |
| Pourquoi un outil dédié (build-vs-buy rationale, competitive tour d'horizon) | Not duplicated in PRD body; PRD Contexte points to brief ("contexte produit complet"); PRD addendum "Reprise du tour d'horizon du brief" explicitly defers to brief+brief-addendum | Explicitly referenced, not silently dropped. Not a gap. |
| À qui ça s'adresse (2–3 users; key use case = new arrival finding docs without asking) | PRD Utilisateurs narrows to solo for v1, defers 2–3-user opening to later; new-arrival use case not restated as a v1 driver | See Gap 3. |
| Critères de réussite (daily usage; onboarding reflex; `[ASSUMPTION]` success signal triggers evolution decision, not a fixed deadline) | PRD Métriques de succès covers daily usage + onboarding reflex + contre-métrique (new, good addition). The "success triggers the *decision* to evolve, not a calendar" framing is only partially echoed | See Gap 2. |
| Périmètre — Dans la v1 (import/preview/download, creation, export, minimal organization) | FR1–FR5, FR8–FR12, FR2/FR3 | Fully mapped. "Sans recherche avancée" superseded by the known FR6/FR7 deviation (not flagged). |
| Périmètre — Hors v1 (fulltext/tags — now in v1 per known deviation; AI agent/MCP; advanced auth/permissions/hosting/remote) | PRD "Hors scope (v1)" retains AI agent, multi-user/roles/permissions, shared hosting/remote/multi-device | Fully mapped except the known deviation. |
| Périmètre — `[ASSUMPTION]` prefer open-source libs/modules when they save time without heavy stack constraints (esp. Office preview, PDF/Word export) | Not restated as a general principle in PRD; PRD addendum discusses concrete library options (Dompdf, Gotenberg, PHPWord) and explicitly defers to brief addendum for "pistes de librairies" | Deferred by reference, not silently dropped. Not a gap. |
| **Vision** (staged evolution: fulltext/tags → AI agent (MCP) → more users; each step only justified once the prior step proves value; explicit anti-over-engineering stance) | No PRD counterpart. PRD "Hors scope (v1)" is a scope boundary list, not a staged roadmap or rationale | **Gap 1 — most material.** |

### addendum.md (brief)

| Addendum section | Landed in PRD | Notes |
|---|---|---|
| Tour d'horizon des outils comparables (Confluence, Notion, SharePoint, Outline/BookStack, GitBook — detailed comparison) | Not duplicated; PRD addendum "Reprise du tour d'horizon du brief" explicitly points back | Deliberate non-duplication, referenced. Not a gap. |
| Pièges techniques — Office preview rendering, WYSIWYG→Word/PDF export mapping | PRD addendum "Fidélité d'export des images inline" section elaborates further (Dompdf/Gotenberg/Chromium, PHPWord) | Enriched, fully mapped. |
| Pièges techniques — Recherche/indexation (was "hors v1, pour plus tard") | PRD addendum has its own "Options de recherche fulltexte (FR6)" section reflecting the v1 move (SQLite FTS5 / Scout+database / Meilisearch) | Correctly updated for the known scope change. |
| Pièges techniques — Permissions (hors v1, ACL/wiki-permission reconciliation complexity) | Not elaborated in PRD (permissions still out of v1 scope) | Consistent with scope; no elaboration needed since out of v1. Not a gap. |
| Pistes de librairies open-source (PDF.js, LibreOffice/Gotenberg, docx-preview/sheetjs, wkhtmltopdf/Dompdf, PHPWord) | PRD addendum reuses PHPWord/Dompdf/Gotenberg in its own export-fidelity section; explicitly defers rest to brief addendum | Fully mapped / referenced. |
| Idées futures hors v1 (fulltext/tags; AI agent MCP) | Fulltext moved into v1 (known deviation); AI agent MCP remains in PRD "Hors scope (v1)" | Fully mapped. |

## Gaps found

1. **Vision / staged-roadmap rationale dropped.** The brief's "Vision" section lays out an explicit sequence (fulltext/tags → AI agent via MCP → more users) with a stated governing principle: each step is justified only once the previous one has proven its value, and there is a deliberate anti-over-engineering stance. The PRD has no equivalent section — "Hors scope (v1)" only lists what's excluded, not the ordering, the "earn-your-way" logic, or the philosophy behind deferring these items. A future reader of the PRD alone would not know *why* these are deferred or in what order they're expected to return. Recommend either a short "Vision / trajectoire post-v1" subsection in the PRD or an explicit addendum note pointing back to the brief's Vision section (the way the competitive tour d'horizon is already handled).

2. **"Success triggers the evolution decision, not a calendar" principle only partially carried forward.** The brief states this as a general assumption governing *all* future evolution (new users AND new features): the trigger is proven usage value, not a fixed timeline. The PRD echoes this only narrowly, in the Utilisateurs section, and only for the specific case of opening access to 2–3 colleagues ("conditionnée à la preuve de valeur et à une décision d'infrastructure"). It does not generalize the principle to feature evolution (e.g., when to revisit the AI-agent idea) or explicitly rule out calendar-driven timelines. Minor — the intent survives in spirit but the explicit rationale is thinned.

3. **New-arrival use case demoted from problem driver to a forward-looking metric footnote.** In the brief, the new-arrival's inability to find documentation is part of the core problem statement (Le problème) and the explicit "cas d'usage clé" in À qui ça s'adresse. In the PRD, this appears only once, briefly, inside Métriques de succès ("y compris dans un contexte d'onboarding futur") — it does not inform the Contexte/problem framing or the Parcours type (both now written strictly from a solo-user PoV, matching the PRD's narrowed v1 scope to mono-utilisateur). This narrowing looks like a deliberate, reasonable consequence of the solo-v1 decision rather than an oversight, but it's worth an explicit confirmation from the PM that de-emphasizing the onboarding angle for v1 design purposes was intentional, since it was one of the brief's two named audience/problem anchors.

No other material qualitative content (tone, rationale, assumptions) appears to have been silently dropped — most brief/addendum material not literally repeated in the PRD is explicitly deferred back to the brief or its addendum (competitive tour d'horizon, library pistes, permissions complexity), which is a deliberate non-duplication pattern rather than a gap.
