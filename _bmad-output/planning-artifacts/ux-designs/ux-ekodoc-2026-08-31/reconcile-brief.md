---
title: Reconciliation Note — Brief vs UX Spines (EkoDoc)
scope: brief-ekodoc-2026-08-31 (+ addendum) vs DESIGN.md / EXPERIENCE.md (ux-ekodoc-2026-08-31)
note: >
  PRD (prd-ekodoc-2026-08-31, status final) sits between the brief and these UX spines and
  already reconciled/narrowed several items (v1 = solo user, fulltext search pulled into v1
  scope, staged post-v1 roadmap). Those deliberate PRD decisions are NOT re-flagged here.
  This note focuses on qualitative/tone/feel/visual-identity signal from the brief that a
  PRD wouldn't carry, checking whether the UX spines picked it up.
updated: 2026-08-31
---

# Reconciliation — Brief → UX Spines

## Résumé exécutif / Le problème → Foundation & Brand and Style

Brief's framing ("documents épars, aucun point d'accès unique", "démarre volontairement
petit... prouver son utilité") maps to `DESIGN.md` § Brand & Style ("le contenu doit être
la star, l'interface doit s'effacer") and `EXPERIENCE.md` § Foundation (mono-surface,
local, pas de multi-tenant). The "small, unglamorous, prove-value-first" spirit of the
brief is faithfully translated into the minimalist/no-flourish design direction. Mapped
cleanly, no gap.

## La solution (deux usages complémentaires) → Information Architecture

Brief's "déposer/consulter" + "créer/exporter" as two equally-weighted usages maps to
`EXPERIENCE.md` § Information Architecture (Bibliothèque unifies imported + created
documents in one list/filter set) and `DESIGN.md` § Components (button-primary reserved
per-screen for Importer / Créer / Exporter — no usage privileged over the other). Also
answers the addendum's observation that wikis and GED rarely treat authoring and file
deposit as "un seul et même modèle de contenu" — the unified Bibliothèque does exactly
that. Mapped cleanly, no gap.

## "Téléchargement du fichier original à tout moment" → Key Flow 1 / State Patterns

Brief's emphasis that the original file must always be retrievable is explicitly
preserved: `EXPERIENCE.md` Flow 1 failure case states preview failure "ne bloque jamais
l'accès au fichier original", and the State Patterns table treats download as always
available even mid-conversion. Mapped cleanly, no gap.

## Pourquoi un outil dédié (comparatif marché) → Do's and Don'ts / Colors

The addendum's competitive teardown (wikis fragment file content, GED under-serves
authoring) doesn't carry direct tone content, but its implicit lesson — don't let the
tool look/feel like either a heavy GED or a decorative wiki — shows up in `DESIGN.md`'s
explicit Don'ts (no gradients, no decorative color-coding by file type, one accent only).
Mapped, no gap.

## Voice and tone

Brief doesn't specify a voice/tone directly; `EXPERIENCE.md` § Voice and Tone (direct,
factual, no emoji) was derived from live elicitation with the user (per `.memlog.md`:
"sobre/pro/simple" recorded as a decision), not silently invented from the brief. No
reconciliation issue — the source of this content is legitimate, just not the brief text
itself.

## Critères de réussite / À qui ça s'adresse ("nouvel arrivant") → Key Flows

This is the one area with real signal loss — see Gaps found below.

## Périmètre / Vision (fulltext search, IA agent, multi-user)

Handled at the PRD layer (search pulled into v1, IA agent and multi-user pushed to a
staged post-v1 roadmap in PRD § Trajectoire post-v1). UX spines correctly reflect the
PRD's v1 boundary (mono-utilisateur, FR6 fulltext live in Bibliothèque). Not re-flagged
per instructions — this is an intentional, explicit PRD decision, not a UX drop.

## Addendum (pièges techniques, librairies OSS)

Purely architecture/technical content, not UX-owned. Correctly absent from DESIGN.md /
EXPERIENCE.md (both note "aucun mockup" / assumptions still open, deferred to
architecture). No gap — out of scope for a UX spine.

---

## Gaps found

- **Newcomer/outsider legibility of the library is the brief's headline differentiator and survives into the PRD's success metric, but the UX spine never addresses it as a design constraint.** The brief calls the "nouvel arrivant" scenario a "cas d'usage clé" twice (problem statement + success criteria: "un nouvel arrivant doit pouvoir trouver rapidement la documentation pertinente sans avoir à demander où chercher"). The PRD narrows v1 to solo use (legitimately, per its own explicit `[ASSUMPTION]`), but it does *not* drop the underlying intent — its own Métriques de succès explicitly keep it: "EkoDoc devient le réflexe... y compris dans un contexte d'onboarding futur." That's a forward-looking UX/IA concern (categories/folders and labels should be legible to someone with no institutional knowledge, not just optimized for the current solo user's own mental model), which is exactly the kind of thing a UX spine — not a PRD — should own. Neither `DESIGN.md` nor `EXPERIENCE.md` mentions this as a principle for FR2/FR3 classification or for the Bibliothèque's information architecture; both Key Flows are written for an already-oriented user (Camille) retrieving/creating, with no flow or note addressing first-time/outsider legibility. Worth an explicit line (e.g., in § Information Architecture or as a design principle) even though no dedicated newcomer flow is warranted in v1.

- **The brief's "prévisualisation rapide dans le navigateur" (quick preview) implies an experience-quality target that neither layer restates explicitly.** The PRD's only preview-adjacent NFR (NFR2) targets fulltext search speed, not preview; `EXPERIENCE.md` § State Patterns only specifies behavior for the *slow* path (conversion loading indicator) but never states the target feel for the common/native PDF path (instant, no perceptible wait) as a design intent. This is minor — it doesn't block anything — but it's worth a one-line addition (e.g., "l'aperçu PDF natif doit être perçu comme instantané ; la conversion Office reste le seul cas où une attente est acceptable") so the immediacy implied by the brief's "rapide" isn't lost by omission.
