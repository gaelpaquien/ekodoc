# Spine Pair Review — EkoDoc

## Overall verdict

DESIGN.md and EXPERIENCE.md are well-calibrated to the stakes: a solo-use, local, ~350-document internal tool drafted in fast-path mode. Token discipline is clean (every color has a light/dark hex pair, every `{path.to.token}` reference resolves, section order is canonical), there is no bloat, and the two Key Flows map cleanly onto the PRD's two named "Parcours type." The real gaps cluster around two things already half-flagged by the workflow's own `reconcile-prd.md`: the FR2/FR10 document-classification-on-save mechanic has no walked flow anywhere, and a few components with full behavioral specs (import dropzone, preview panel) never got a matching visual row in DESIGN.md. Nothing here is critical or blocking; these are the kind of gaps a one-hour revision pass closes before build.

## 1. Flow coverage — adequate with gaps

Checked: PRD "Parcours type" names exactly two usages (retrieve/consult existing document; create/export a new document). EXPERIENCE.md's two Key Flows map 1:1 onto these, both with a named protagonist (Camille), numbered steps, a bolded **Climax**, and a failure path. Also walked FR1–FR12 individually to see which are demonstrated end-to-end in a flow versus only touched via IA/Component/State rows.

### Findings
- **medium** FR2 (classer par dossiers/catégories) and FR10 (enregistrer un document créé avec les mêmes propriétés de classement) have no flow, component pattern, or state pattern showing the user actually *assigning* a category/folder — neither at import time (the "Zone d'import" row only covers drag-drop and format validation) nor from the Fiche document, nor during Key Flow 2's creation walkthrough (which jumps straight from writing/inserting an image to exporting, with no save-and-classify step). This is the same gap the workflow's own `reconcile-prd.md` already flags as "Partiel." (EXPERIENCE.md — Component Patterns, State Patterns, Key Flow 2). *Fix:* add a component pattern row (or a Key Flow 2 step) showing where/how category or folder gets set — e.g. an inline field in the Zone d'import, or a category picker in the Éditeur's save action.
- **medium** FR1 (Importer) has no Key Flow narrative — it is fully covered by IA, one Component Patterns row ("Zone d'import"), and one State Patterns row (format non supporté), but never gets a protagonist-driven walkthrough with a climax the way the other two PRD usages do. Given import/"déposer" is one of the brief's two headline usages, its absence as a narrated flow is a real gap, not just a stylistic choice. (EXPERIENCE.md — Key Flows section only has Flow 1 "Retrouver et partager" and Flow 2 "Rédiger"). *Fix:* either add a short Flow 3 (import → land on Fiche document) or explicitly note in Key Flows why import doesn't warrant its own narrative (e.g. "trivial, single modal, no branching worth narrating").
- **low** FR12 (Export Word) only appears in Key Flow 2's *failure* path (the HTML→.docx image-mapping bug), never in a successful step — the flow's climax is exclusively the PDF export. Adequately covered elsewhere (Component Patterns "Bouton Export" cites both FR11/FR12), so this is a completeness nit, not a real narrative gap. (EXPERIENCE.md — Key Flow 2). *Fix:* none required; optionally add one sentence showing the Word export succeeding too, for symmetry.

## 2. Token completeness — pass

Checked: every frontmatter token in DESIGN.md's `colors`, `typography`, `rounded`, `spacing`, and `components` blocks, plus every `{path.to.token}` reference in the prose of both files.

### Findings
- **low** The prose "Components" section in DESIGN.md lists 7 components (Bouton primaire, Bouton secondaire, Carte document, Badge type de fichier, Barre de recherche, Filtres (chips), Barre d'outils éditeur), but the frontmatter `components:` map only registers 5 of them — "Filtres (chips)" and "Barre d'outils éditeur" have no machine-readable token entry, even though both are given real visual rules in prose (fond `{colors.primary}`/texte clair actif; fond `{colors.surface-alt}`, bordure basse `{colors.border}`). (DESIGN.md frontmatter vs. body Components section). *Fix:* add `filter-chip` and `editor-toolbar` entries to the frontmatter `components` map for consistency with the other five.
- No color is missing a hex value or a light/dark pair; all typography roles have at least fontFamily/fontSize/fontWeight/lineHeight; all `rounded`/`spacing` tokens used in prose (4px/8px/12px, 960px) match frontmatter values exactly; every `{colors.*}` / `{rounded.*}` reference inside the frontmatter `components` map resolves to a declared token.

## 3. Component coverage — gaps found

Checked: every component name in DESIGN.md.Components and EXPERIENCE.md.Component Patterns, cross-referenced for a matching row (with real rules, not a one-word description) on both sides.

### Findings
- **medium** "Zone d'import" and "Panneau de prévisualisation" each have a full behavioral row in EXPERIENCE.md.Component Patterns (drag-drop, format validation, loading state / native PDF vs. converted Office rendering) but **no corresponding visual spec row in DESIGN.md.Components** and no frontmatter component token — no background, border, radius, or dropzone-affordance styling is specified anywhere for either. This is the most concrete "downstream build risk" in the pair: an implementer has behavior but no look for two of the more visually distinct surfaces (a file drop target, a document preview frame). (EXPERIENCE.md Component Patterns rows 4–5; absent from DESIGN.md Components). *Fix:* add two rows to DESIGN.md.Components (or fold them into existing tokens explicitly, e.g. "Zone d'import reuses `document-card` styling with a dashed `{colors.border}`").
- **low** "Bouton Export" (EXPERIENCE.md Component Patterns) has no exact-name counterpart in DESIGN.md.Components — the closest match is "Bouton primaire," but it's not stated whether the two side-by-side export buttons are both primary-variant, or one primary/one secondary. (See also §7 naming.) *Fix:* state explicitly in either file which button variant(s) the two export actions use.
- **low** "Bouton primaire," "Bouton secondaire," and "Badge type de fichier" have DESIGN.md visual rows but no dedicated EXPERIENCE.md.Component Patterns row. Given stakes (generic buttons, a non-interactive display badge), this is defensible — there's no meaningful behavior beyond "click triggers the labeled action" / "displays icon + label" — but it is a literal coverage gap per the rubric's symmetric-rows rule. *Fix:* none required given genericity; optional one-liner rows would close the gap cheaply if a stricter pass is wanted later.

## 4. State coverage — gaps found

Checked: each IA surface (Bibliothèque, Fiche document, Éditeur, plus the Import modal) against the state set appropriate for a local, single-user, no-auth app (empty, cold-load, loading/in-progress, error, unsaved-changes; offline and permission-denied correctly treated as not applicable — no penalty for their absence, see Mechanical notes).

### Findings
- **medium** Bibliothèque has no cold-load / initial-loading state (e.g., a skeleton or spinner while the document list loads on app open) — only the *empty* state (zero documents) and the *no-results* state (search/filter) are covered. This is the very first frame a user sees on every session; its absence is a silent gap even in a fast local app. (EXPERIENCE.md — State Patterns table). *Fix:* add a row, even a minimal one ("near-instant on local SQLite for ~350 docs; no loading treatment needed" is an acceptable explicit answer too).
- **low** Fiche document has no state for a missing or corrupted underlying file (moved/deleted from local disk, unreadable) — plausible in a local-filesystem-backed tool over time, and NFR5/fidelity concerns suggest the team is already thinking about file-integrity edge cases. *Fix:* one row: "fichier source introuvable → message explicite, téléchargement désactivé."
- **low** Éditeur has no explicit loading state for opening an *existing* document for editing (as opposed to the "Créer un document" empty-editor case, which Key Flow 2 covers implicitly) — content has to load into the WYSIWYG before it's editable. Related: `reconcile-prd.md`'s own gap note that NFR2 (search < 1s) has no "recherche en cours" indicator in State Patterns, only the debounce mechanic. *Fix:* add a brief loading-state row for editor content load, and decide explicitly whether search needs a spinner given the <1s target.

## 5. Visual reference coverage — clean

Checked `.working/`, `mockups/`, `wireframes/`, and `imports/` under `ux-ekodoc-2026-08-31/`. None of these directories exist — the only files present are `DESIGN.md`, `EXPERIENCE.md`, `.memlog.md`, and `reconcile-prd.md`. This matches EXPERIENCE.md's own IA section, which states explicitly that no mockup has been produced yet ("mode rapide"). Nothing is orphaned; nothing to reconcile.

### Findings
- None.

## 6. Bloat & overspecification — clean

Checked for pixel specs where tokens already cover it, source (PRD/brief/persona) restatement, prose where a table would work, and sections no downstream consumer would read.

### Findings
- None. Every pixel/dimension value used in prose (4px/8px/12px corners, 960px content width) traces back to a declared frontmatter token rather than being restated as a bare number. FR/NFR references are citations (`FR6`, `NFR5`), not restatements of their text. No persona document beyond the lightweight generic protagonist (Camille) used consistently across both Key Flows, which is appropriate for this stakes level. No section reads as filler.

## 7. Inheritance discipline — mostly clean, one naming gap

Checked: EXPERIENCE.md `sources` frontmatter resolution, verbatim FR/NFR usage, component-name identity across both files, and EXPERIENCE.md's one `{colors.primary}` token reference.

### Findings
- **low** "Bouton Export" (EXPERIENCE.md) does not use a name that appears anywhere in DESIGN.md — it's presumably an instance of "Bouton primaire" (or a mix of primary/secondary), but the two files never state the link explicitly, which breaks the "component names identical across all sections" rule literally even though the intent is inferable. (Cross-ref with §3.) *Fix:* rename to match a DESIGN.md component name, or add a one-line cross-reference.
- No other findings: both `sources` paths (`../../briefs/brief-ekodoc-2026-08-31/brief.md`, `../../prds/prd-ekodoc-2026-08-31/prd.md`) resolve correctly from the file's location; FR1 through FR12 are all cited with their verbatim PRD numbers (including FR10 via the "FR8–FR12" IA range) with no renamed or invented requirement labels; "Carte document," "Barre de recherche," and "Filtres (chips)" and "Barre d'outils éditeur" are spelled identically in both files; the sole `{colors.primary}` reference in EXPERIENCE.md (Accessibility Floor, focus ring) resolves to a token DESIGN.md actually defines.

## 8. Shape fit — pass

Checked: DESIGN.md section order against the canonical order (Brand & Style → Colors → Typography → Layout & Spacing → Elevation & Depth → Shapes → Components → Do's and Don'ts); EXPERIENCE.md required defaults (Foundation, IA, Voice and Tone, Component Patterns, State Patterns, Interaction Primitives, Accessibility Floor, Key Flows) presence and order; whether any dropped defaults are defensible given stakes.

### Findings
- None. DESIGN.md's eight sections appear in exactly the canonical order. EXPERIENCE.md's eight required sections are all present in the expected order. EXPERIENCE.md omits the (non-required, optional) "Responsive & Platform" and "Inspiration & Anti-patterns" sections that appear in the shadcn example — both omissions are defensible given a single-surface, desktop-only, no-mobile-optimization, single-tool-not-a-platform scope; Foundation already states the no-mobile-optimization posture in one sentence, which is proportionate.

## Mechanical notes

- Frontmatter completeness: DESIGN.md's `name`, `description`, `colors`, `typography`, `rounded`, `spacing`, `components` are all present and well-formed; EXPERIENCE.md's `title`, `name`, `status`, `sources`, `created`, `updated` are all present.
- Cross-refs: EXPERIENCE.md's two `sources` paths both resolve to real files at the expected locations. No broken links found.
- Naming inconsistencies: see §3/§7 — "Bouton Export" has no exact DESIGN.md counterpart name; "Filtres (chips)" and "Barre d'outils éditeur" are named in DESIGN.md prose but absent from its frontmatter `components` map; "Zone d'import" and "Panneau de prévisualisation" exist only in EXPERIENCE.md with no DESIGN.md counterpart at all.
- Appropriate, non-penalized omissions given stakes: no offline state (local single-machine app, not meaningfully "online/offline"), no permission-denied state (NFR3 — no auth in v1), no persona document beyond the lightweight generic protagonist, no multi-platform/responsive matrix.
- `reconcile-prd.md` (present alongside these artifacts) independently identifies the FR2/FR10 classification gap and the NFR2 search-loading-indicator gap found in this review — corroborating rather than contradicting evidence, not a separate source of truth.
