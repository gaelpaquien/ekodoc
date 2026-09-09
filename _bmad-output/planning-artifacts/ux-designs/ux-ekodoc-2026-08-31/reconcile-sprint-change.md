# Input Reconciliation — sprint-change-proposal-2026-09-09.md (§4, UX work-order) vs DESIGN.md / EXPERIENCE.md

Scope: the proposal's "4. Detailed Change Proposals > UX" subsection is the work-order this session executed. Checked line by line against the redrafted spines.

## Applied correctly

**DESIGN.md**
- Tokens couleur : fonds jamais blanc/noir pur (background clair `#F5F3F0`, sombre `#1B1815`) — appliqué, choisi parmi 4 variations en atelier (`.working/color-themes-1.html`, variation 3 retenue).
- Accent lime au lieu de bleu — appliqué (`#C6FF00`, registre "vif/néon" choisi explicitement par l'utilisateur).
- Aucune validation de contraste WCAG requise — noté explicitement en frontmatter et en § Brand & Style / § Colors, avec renvoi vers l'exigence WCAG 2.2 AA générale qui, elle, reste en vigueur.
- Principe "pas de sidebar, 3 surfaces" amendé → 5 surfaces, sidebar fixe toujours visible — appliqué en § Layout & Spacing + nouveau composant `sidebar`.
- `document-card` remplacé — appliqué (`document-row`, listing table dense).
- `category-selector` retiré, remplacé par sélecteur de tags multi-select — appliqué (`tag-selector` + `tag-chip` distincts).

**EXPERIENCE.md**
- IA : Recherche et Configuration ajoutées comme surfaces ; Bibliothèque révisée (listing paginé 20/page, barre de recherche retirée) — appliqué.
- Component Patterns : "Sélecteur catégorie/dossier" retiré, "Sélecteur de tags" ajouté, Sidebar ajoutée, Toggle thème ajouté, Footer ajouté (texte littéral "Made with 💔 Claude", confirmé avec l'utilisateur), upload de pièces jointes ajouté (panneau latéral Éditeur + consultation en lecture seule sur Fiche document) — appliqué.
- Key Flows 1, 2, 3 réécrits (catégorie → tags dans les libellés ; Flow 2 réécrit autour de la surface Recherche dédiée) — appliqué.
- `UX-DR23` (WCAG 2.2 AA hors contraste couleur) conservée intégralement — appliqué, § Accessibility Floor inchangée sur le fond, seule la note de contraste couleur est ajoutée.

## Au-delà du work-order (décisions complémentaires, pas des gaps)

- **Flow 4 ajouté** (pièces jointes + nettoyage de tags en Configuration) — le work-order ne demandait que la réécriture des Flows 1-3, mais FR13 (pièces jointes) et FR14/Configuration n'avaient aucun parcours narratif propre. Ajouté pour fermer la couverture IA (chaque surface a un flow qui y mène) — pas une demande explicite du work-order, à confirmer en revue si jugé superflu.
- **Filtre type absent de la surface Recherche** — le work-order ne précise pas la composition exacte des filtres de la surface Recherche ; tranché en atelier avec l'utilisateur : Recherche = fulltexte + tag uniquement, le filtre type reste propre à la Bibliothèque. Décision loggée en `.memlog.md`, pas dans le work-order lui-même.
- **Confirmation à la suppression de tag** — non spécifié dans le work-order ; tranché en atelier (confirmation requise, cohérent avec la suppression de document déjà en place).

## Incohérence détectée (hors périmètre de cette session, à signaler en aval)

- **`ARCHITECTURE-SPINE.md` § Structural Seed** liste encore `Components/DocumentCard.vue` dans `resources/js/Components/` — nom hérité de l'ancien pattern carte de bibliothèque. Cette session renomme le concept en "Ligne de document" (`document-row` dans `DESIGN.md`), ce qui rend `DocumentCard.vue` obsolète comme nom de fichier. La liste des `Pages/` (`Library.vue`, `DocumentShow.vue`, `Editor.vue`, `Search.vue`, `Configuration.vue`) et le reste des `Components/` (`SearchBar.vue`, `FilterChips.vue`, `TagSelector.vue`, `ImportZone.vue`, `PreviewPanel.vue`) sont en revanche déjà cohérents avec cette révision UX. **Action recommandée** : lors du prochain passage `bmad-architecture` (mode update) ou au découpage des stories Epic 3, renommer `DocumentCard.vue` → `DocumentRow.vue` (ou nom équivalent) dans le Structural Seed pour rester aligné avec `DESIGN.md.Components.document-row`.

## Recommandation

- Aucun gap : tous les points du work-order UX sont traités dans `DESIGN.md`/`EXPERIENCE.md`.
- Un point de suivi hors périmètre (nom de composant Vue obsolète) signalé ci-dessus pour la prochaine session `bmad-architecture` ou `bmad-create-epics-and-stories`.
