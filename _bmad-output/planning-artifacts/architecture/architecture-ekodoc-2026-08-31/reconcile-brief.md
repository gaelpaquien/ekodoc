---
title: Reconciliation — Brief/Addendum vs Architecture Spine (EkoDoc)
source: briefs/brief-ekodoc-2026-08-31/{brief.md,addendum.md}
target: architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md
generated: 2026-08-31
---

# Reconciliation: brief/addendum → ARCHITECTURE-SPINE.md (EkoDoc)

Scope of this check: (1) did the architecture spine actually address the three technical
pitfalls the addendum flagged for the architecture phase (Office preview conversion,
WYSIWYG→PDF/Word export fidelity, fulltext search/indexing) and the library options it
suggested; (2) does the spine preserve the brief's qualitative intent (the "pont entre wiki
et GED" positioning, "the original must always stay retrievable", the "start small, prove
value" posture) at the architecture layer.

Note on scope inheritance: the architecture spine's direct source is the PRD, not the brief
directly. One deviation between brief and architecture — fulltext search (FR6/FR7) moved
from "hors v1" (brief) into v1 scope — was already identified, justified, and logged as
intentional at the PRD stage (see `prds/prd-ekodoc-2026-08-31/reconcile-brief.md`, "Known,
already-logged deviation"). It is not re-flagged as an architecture-level gap here, but it
does change which addendum pitfalls are now "live" in v1 rather than deferred (see Gap 1
below).

## 1. Pièges techniques — addressed?

| Addendum pitfall | Architecture response | Verdict |
| --- | --- | --- |
| Office preview (`.docx`/`.xlsx`) non trivial; needs a conversion service (LibreOffice headless / Gotenberg / Aspose), not a home-made renderer; PDF.js covers native PDF | AD-10: `ConvertDocumentToPreviewAction` shells out to `soffice --headless --convert-to pdf` via `Illuminate\Process`, cached to `storage/app/private/previews/{id}.pdf`, regenerated only on source change. PDF natif shown directly, no conversion. | **Addressed**, and the choice of direct CLI LibreOffice over the containerized Gotenberg option is a deliberate lean choice consistent with "start small" (no Docker/service infra for a single local user). PDF.js itself is not mentioned — the spine implies native browser PDF display instead, which is a simpler, undocumented substitution for the addendum's suggested library (see Gap 3). |
| WYSIWYG→Word/PDF: rich editors rarely map cleanly to OOXML (styles, headers/footers, TOC); PDF via browser-print/headless-CSS is generally more reliable than DOCX generation | AD-11: PDF export renders the same Blade/HTML the editor shows and converts via `spatie/browsershot` (real headless Chromium) — explicitly ruling out dompdf/wkhtmltopdf to avoid image-position drift (NFR5). AD-12: Word export via `phpoffice/phpword` from HTML, with image-position failure explicitly named a "risque assumé", not a bug to chase — matches the addendum's own caution almost verbatim. | **Addressed thoroughly** — this is the best-reconciled of the three pitfalls; the asymmetry the addendum predicted (PDF more reliable than Word) is preserved and made explicit as a policy (AD-12), not silently hoped away. |
| Fulltext search/indexing needs multi-format text extraction **+ OCR**, plus a unified index (Elasticsearch/OpenSearch or vector store for semantic search) — flagged in the brief as a "later" concern | AD-8: Scout with the `database` driver (LIKE-based, no separate search server) — a deliberately lean choice matching NFR2's modest scale (~350 docs) and avoiding premature infra (Elasticsearch), with the Deferred section explicitly noting Scout's driver can later swap for Meilisearch/a vector store without a data-model rewrite. AD-9: text extraction is best-effort via `smalot/pdfparser` (PDF) and `phpoffice/phpword`/`phpspreadsheet` (Word/Excel) — **no OCR anywhere in the spine.** | **Partially addressed — see Gap 1.** The indexing/engine choice is well-reasoned and appropriately lean. But OCR is not mentioned at all, even though search is now a v1 requirement (FR6/NFR2), not a deferred nice-to-have — the addendum's own pitfall list ties OCR directly to "extraction de texte multi-formats" for search, and that need didn't disappear when search moved into v1. |

## 2. Pistes de librairies — arbitrated as asked?

The addendum explicitly left these "non arbitrées... à confirmer lors du passage à
l'architecture." The spine does arbitrate all of them:

| Addendum piste | Architecture decision |
| --- | --- |
| PDF.js for PDF preview | Not adopted/mentioned; native PDF display implied instead (AD-10). Undocumented substitution — see Gap 3. |
| LibreOffice headless / Gotenberg / docx-preview / sheetjs for Office preview | LibreOffice headless chosen (AD-10), CLI-invoked rather than containerized (Gotenberg) — reasoned, lean choice. |
| wkhtmltopdf / Dompdf / headless-Chromium for PDF export | Headless Chromium via Browsershot chosen (AD-11), with dompdf/wkhtmltopdf explicitly excluded and reasoned against (image fidelity, NFR5). |
| PHPWord for Word export | Adopted (AD-12), with fidelity limits documented as accepted risk rather than a defect. |

All four library decisions are genuine arbitrations with stated rationale (not silent
defaults), which is exactly what the addendum asked the architecture phase to do.

## 3. Qualitative intent from the brief — preserved at the architecture layer?

- **"Pont entre wiki et GED" positioning.** Preserved explicitly: AD-4 (single `documents`
  table, one `source` column instead of two content models) directly cites the brief's
  critique of existing tools — "exactement le piège que le brief reproche aux wikis/GED
  existants." AD-9's text extraction on imported files also makes Office attachments
  genuinely searchable content rather than opaque attachments, which is the addendum's
  specific complaint about wikis (Confluence/Notion treat deposited files as low-value
  embeds). Well preserved.
- **"The original file must always stay retrievable."** Preserved explicitly: AD-7 (files
  live on the private disk, download always streams through an app route, never a public
  URL) and AD-9 (extraction failure never blocks or corrupts the import — "principe du
  brief : l'original doit toujours rester accessible" is quoted near-verbatim in the AD-9
  rationale). One adjacent nuance not a contradiction: the Deferred section accepts "no
  backup strategy" as a risk for solo use — that's a different axis (disaster recovery
  vs. access-path retrievability) and is transparently flagged, not silently dropped, so
  it is not counted as a gap here.
- **"Start small, prove value" / no premature infrastructure.** Generally well preserved:
  no queues (AD-6, synchronous import), no auth/user tables (Deferred), no API layer
  (AD-13), no containerized search server (AD-8 chooses `database` driver over
  Elasticsearch/OpenSearch), no hosting/CI/CD setup (Deferred, explicitly punted to the
  multi-user opening). The Deferred section as a whole reads as a direct implementation of
  the brief's Vision principle ("chaque étape ne se justifie que si l'étape précédente a
  démontré son utilité") — it repeatedly ties future work to a usage-proof trigger rather
  than pre-building it. One tension worth naming: Browsershot (AD-11) pulls in a full
  headless-Chromium runtime and LibreOffice (AD-10) is a sizeable OS-level dependency —
  both are justified by NFR5/FR4 necessity (the addendum itself named them), but neither
  the Stack table nor Deferred acknowledges the resulting runtime footprint (extra
  Chromium/Node toolchain, LibreOffice install) as a "start small" cost worth a sentence of
  awareness for a "petit outil local". Minor, not a contradiction — see Gap 2.

## Gaps found

1. **OCR is absent from the spine even though fulltext search is now v1 scope.** The
   addendum's "Pièges techniques" section ties fulltext search directly to "extraction de
   texte/OCR multi-formats." That pitfall was written when search was still "hors v1," but
   the PRD deliberately moved search into v1 (FR6/FR7, logged deviation). The architecture
   spine inherits FR6/FR7 and defines extraction (AD-9) using text-layer-only tools
   (`pdfparser`, `phpword`, `phpspreadsheet`) with no OCR fallback, and no mention of OCR
   anywhere, including Deferred. If the ~350-document corpus contains scanned/image-based
   PDFs (a real possibility — the PRD's own "Questions ouvertes" already flags uncertainty
   about the corpus's formats), those documents will silently have `extracted_text = NULL`
   and be invisible to the core v1 search feature, undermining NFR2/FR6 for exactly the
   subset of documents most likely to need a search-based recovery path (old, unstructured
   scans). This isn't necessarily wrong to defer, but it should be an explicit, named
   Deferred item (a known limitation of AD-9) rather than an unaddressed silence.

2. **Runtime footprint of Browsershot/LibreOffice not acknowledged as a "start small"
   trade-off.** AD-10 and AD-11 both introduce sizeable non-PHP dependencies (a Chromium
   runtime for Browsershot, a full LibreOffice install for headless conversion) into what
   the brief frames as "un outil local, pour une poignée d'utilisateurs." Both choices are
   defensible and traceable to addendum-flagged necessities (fidelity, Office conversion),
   so this is not a wrong decision — but the Stack table and Deferred section, which
   otherwise carefully name every infra trade-off (no queue, no auth, no API, no backup),
   say nothing about this one. A one-line acknowledgment (e.g., "requires a local
   Chromium + LibreOffice install alongside Herd; verified compatible with the dev
   machine") would keep the spine's own standard of transparency consistent.

3. **PDF.js substitution left undocumented.** The addendum specifically named PDF.js as
   the library to evaluate for native PDF preview. The spine's AD-10 only says "PDF natif :
   jamais de conversion (affiché directement, FR4)" without naming how it's displayed
   (native `<embed>`/browser viewer vs. PDF.js). This is very likely a reasonable and even
   simpler choice (no added dependency), but because the addendum explicitly asked for this
   library decision to be arbitrated at the architecture phase, its absence — even to say
   "rejected, native browser rendering suffices" — is a minor documentation gap, not a
   design flaw.

No gaps found regarding the "pont wiki/GED" positioning, the original-file-retrievability
principle, or the overall anti-over-engineering posture — these are preserved and, in
several places (AD-4, AD-7, AD-9, Deferred), explicitly traced back to the brief's own
language rather than re-derived independently.
