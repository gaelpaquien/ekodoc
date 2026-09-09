---
title: DESIGN.md EkoDoc
status: final
created: 2026-08-31
updated: 2026-09-09
name: EkoDoc
description: Base de connaissance interne, usage solo/local (Laravel Herd). Fondation Tailwind CSS from scratch, pas de librairie de composants tierce.
colors:
  # Palette v2 (2026-09-09) : gris chaud (beige/brun) + accent lime neon, choisie parmi 4 variations rendues en atelier (voir mockups/color-themes.html). Remplace la palette bleu sobre v1 — decision produit du sprint-change-proposal-2026-09-09.
  background: '#F5F3F0'
  background-dark: '#1B1815'
  surface: '#EFEBE6'
  surface-dark: '#211D19'
  surface-alt: '#EAE6E1'
  surface-alt-dark: '#24201B'
  border: '#DBD5CD'
  border-dark: '#362F27'
  foreground: '#221F1A'
  foreground-dark: '#ECE7E0'
  muted: '#7A7266'
  muted-dark: '#A69C8D'
  primary: '#C6FF00'
  primary-foreground: '#12130A'
  primary-dark: '#C6FF00'
  primary-foreground-dark: '#12130A'
  destructive: '#B3261E'
  destructive-foreground: '#FFFFFF'
  destructive-dark: '#F2B8B5'
  destructive-foreground-dark: '#3A0906'
typography:
  # Inchange depuis v1 (non concerne par le work-order tags/pieces jointes/Configuration).
  heading-lg:
    fontFamily: 'Inter'
    fontSize: 28px
    fontWeight: '600'
    lineHeight: '1.25'
  heading-md:
    fontFamily: 'Inter'
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.3'
  body:
    fontFamily: 'Inter'
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  body-sm:
    fontFamily: 'Inter'
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label:
    fontFamily: 'Inter'
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.4'
    letterSpacing: 0.04em
rounded:
  sm: 4px
  md: 8px
  lg: 12px
spacing:
  unit: 4px
  gutter: 24px
  content-max-width: 960px
  sidebar-width: 220px
components:
  button-primary:
    background: '{colors.primary}'
    foreground: '{colors.primary-foreground}'
    radius: '{rounded.md}'
  button-secondary:
    background: '{colors.surface-alt}'
    foreground: '{colors.foreground}'
    border: '{colors.border}'
    radius: '{rounded.md}'
  sidebar:
    background: '{colors.surface-alt}'
    border-right: '{colors.border}'
    width: '{spacing.sidebar-width}'
    nav-item-foreground: '{colors.foreground}'
    nav-item-active-background: '{colors.primary}'
    nav-item-active-foreground: '{colors.primary-foreground}'
    radius: '{rounded.md}'
  theme-toggle:
    foreground: '{colors.muted}'
    active-foreground: '{colors.foreground}'
    radius: '{rounded.sm}'
  footer:
    foreground: '{colors.muted}'
    typography: '{typography.body-sm}'
  document-row:
    background: transparent
    hover-background: '{colors.surface}'
    border-bottom: '{colors.border}'
    radius: '{rounded.sm}'
  filetype-badge:
    background: '{colors.surface-alt}'
    foreground: '{colors.muted}'
    radius: '{rounded.sm}'
  search-bar:
    background: '{colors.background}'
    border: '{colors.border}'
    radius: '{rounded.md}'
  filter-chip:
    background: '{colors.surface-alt}'
    foreground: '{colors.foreground}'
    radius: '{rounded.md}'
    active-background: '{colors.primary}'
    active-foreground: '{colors.primary-foreground}'
  tag-chip:
    background: '{colors.surface-alt}'
    foreground: '{colors.foreground}'
    radius: '{rounded.lg}'
    removable-icon-foreground: '{colors.muted}'
  tag-selector:
    background: '{colors.background}'
    border: '{colors.border}'
    radius: '{rounded.sm}'
    suggestion-list-background: '{colors.surface}'
    suggestion-match-foreground: '{colors.primary}'
  editor-toolbar:
    background: '{colors.surface-alt}'
    border-bottom: '{colors.border}'
  attachment-panel:
    background: '{colors.surface}'
    border: '{colors.border}'
    radius: '{rounded.md}'
  import-zone:
    background: '{colors.surface}'
    border: '{colors.border}'
    border-style: dashed
    radius: '{rounded.md}'
  preview-panel:
    background: '{colors.background}'
    border: '{colors.border}'
    radius: '{rounded.md}'
---

## Brand & Style

EkoDoc reste un outil interne, pas un produit vitrine : **le contenu (les documents) doit être la star, l'interface doit s'effacer**. Ce principe ne change pas — ce qui change, c'est le tempérament de l'unique accent qui perce ce calme : un lime néon franchement saturé (`#C6FF00`), choisi délibérément énergique plutôt que sobre, mais gouverné par la même discipline qu'avant — **un accent, pas deux**, réservé aux actions principales et aux états actifs. Le reste de l'interface reste des neutres chauds (beige/brun) plats, sans dégradé ni fioriture décorative. Le contraste entre le calme des neutres et l'énergie ponctuelle du lime est volontaire : il signale sans jamais dominer.

Ce fichier fixe l'identité visuelle (tokens, couleurs, typographie, composants) ; `EXPERIENCE.md` fixe le comportement (parcours, états, interactions) et référence ces tokens par leur nom. Les deux font foi en cas de conflit avec une maquette.

Mode sombre supporté (voir `EXPERIENCE.md` § Foundation) — chaque token de couleur a sa paire clair/sombre listée ci-dessus. Aucune validation de contraste WCAG n'est requise sur cette palette (décision produit explicite du 2026-09-09, projet interne) — la cible WCAG 2.2 AA générale (`EXPERIENCE.md` § Accessibility Floor) reste en vigueur sur tout le reste (clavier, focus, texte alternatif).

## Colors

- **Lime néon (`#C6FF00`, identique clair/sombre)** — seul accent du système. Texte/icône sur accent en quasi-noir (`#12130A`) pour rester lisible dans les deux thèmes. Utilisé sur les boutons d'action principale (importer, créer, exporter), l'item de navigation actif dans la sidebar, l'état "sélectionné"/actif d'un filtre. `[DECISION]` Registre "vif/néon" choisi explicitement par l'utilisateur ; température de gris "chaude" choisie parmi 4 variations rendues en atelier (voir [`mockups/color-themes.html`](mockups/color-themes.html)), après délégation du choix à l'agent UX.
- **Neutres chauds (`background`, `surface`, `surface-alt`, `border`, `foreground`, `muted`)** — famille beige/brun, jamais blanc ou noir pur même aux extrêmes (`background`/`background-dark`). Portent tout le reste : fond de page, sidebar, lignes de documents, barres d'outils, texte secondaire. Jamais de deuxième couleur d'accent — les types de fichiers se distinguent par icône + libellé (badge neutre), pas par teinte.
- **Destructive (`#B3261E` clair / `#F2B8B5` sombre)** — inchangé depuis v1, réservé aux actions de suppression et messages d'erreur bloquants. Le rouge reste lisible sur les neutres chauds sans ambiguïté avec le lime (aucune confusion action/danger possible).

Règles détaillées en § Do's and Don'ts.

## Typography

Inchangé depuis v1 — Inter du titre au corps de texte.

## Layout & Spacing

Échelle Tailwind par défaut (base 4px), inchangée. Largeur de contenu maximale `960px` (`content-max-width`) pour la lecture de documents et l'éditeur, mesurée dans la zone de contenu principale (hors sidebar).

**Changement structurel** (justification complète en `EXPERIENCE.md` § Information Architecture) : sidebar fixe, toujours visible, largeur `{spacing.sidebar-width}` (220px), sur toutes les surfaces sans exception. Bibliothèque et Fiche document restent mono-colonne dans leur zone de contenu ; l'Éditeur ajoute un panneau latéral rétractable pour les pièces jointes (voir § Components, `attachment-panel`).

## Elevation & Depth

Minimaliste, inchangé : la plupart des surfaces sont plates, séparées par des bordures 1px (`{colors.border}`) plutôt que des ombres — y compris la sidebar, qui se distingue du contenu par une bordure droite, jamais une ombre. Une légère élévation reste réservée aux éléments qui flottent au-dessus du contenu : menu déroulant (dont les suggestions du sélecteur de tags), modale d'import, notification toast.

## Shapes

Inchangé : `rounded.sm` (4px) pour les champs de saisie et badges, `rounded.md` (8px) pour les boutons et lignes de document au survol, `rounded.lg` (12px) pour les modales et les chips de tag (rendu quasi-pilule sur leur hauteur réduite).

## Components

- **Bouton primaire** — fond `{colors.primary}` (lime), texte `{colors.primary-foreground}` (quasi-noir), `{rounded.md}`. Réservé à une action principale par écran (Importer, Créer un document, Exporter).
- **Bouton secondaire** — fond `{colors.surface-alt}`, bordure `{colors.border}`, texte `{colors.foreground}`. Actions secondaires (Annuler, Télécharger l'original).
- **Sidebar** — fond `{colors.surface-alt}`, bordure droite `{colors.border}`, largeur `{spacing.sidebar-width}`. Fixe, toujours visible sur les 5 surfaces. Items : Bibliothèque, Recherche, Configuration (les 3 racines) ; item actif en fond `{colors.primary}` / texte `{colors.primary-foreground}`. Pied de sidebar : toggle thème clair/sombre + texte du footer (voir ci-dessous). Référence visuelle : [`mockups/key-bibliotheque.html`](mockups/key-bibliotheque.html) (présente sur les 4 maquettes d'écrans).
- **Toggle thème** — icône simple (soleil/lune), texte `{colors.muted}` au repos, `{colors.foreground}` à l'état actif/survolé. En pied de sidebar.
- **Footer** — texte littéral **"Made with 💔 Claude"**, `{typography.body-sm}`, `{colors.muted}`. En pied de sidebar, sur toutes les surfaces.
- **Ligne de document** *(remplace la Carte document v1)* — pas de fond au repos, `{colors.surface}` au survol, séparateur `{colors.border}` en bas de ligne, `{rounded.sm}` au survol uniquement. Affiche badge type, titre, chips de tags, date d'ajout (FR3). Toute la ligne est cliquable et mène à la fiche document. Référence visuelle : [`mockups/key-bibliotheque.html`](mockups/key-bibliotheque.html).
- **Badge type de fichier** — fond `{colors.surface-alt}`, texte `{colors.muted}`, `{rounded.sm}`, icône + libellé (PDF / Word / Excel / Créé).
- **Barre de recherche** — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.md}`, icône loupe à gauche. Sur la surface Recherche dédiée (plus sur la Bibliothèque, voir `EXPERIENCE.md`).
- **Filtres (chips)** — style bouton secondaire à l'état inactif, fond `{colors.primary}`/texte `{colors.primary-foreground}` à l'état actif. Sur la Bibliothèque (tag + type) et la Recherche (tag).
- **Chip de tag** *(nouveau, distinct des filtres)* — fond `{colors.surface-alt}`, texte `{colors.foreground}`, `{rounded.lg}` (quasi-pilule). Affiche un tag sur une ligne de document/Fiche document, ou dans le sélecteur de tags avec icône de retrait.
- **Sélecteur de tags** *(remplace le Sélecteur catégorie/dossier v1)* — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.sm}`. Champ texte qui filtre en direct la liste gérée de tags existants ; suggestions affichées sur fond `{colors.surface}`, portion correspondante du texte tapé en `{colors.primary}`. Aucune création de tag depuis ce champ (liste gérée uniquement, voir FR2/FR14).
- **Barre d'outils éditeur** — fond `{colors.surface-alt}`, bordure basse `{colors.border}` ; regroupe mise en forme de texte, insertion de tableau, insertion d'image (FR8/FR9).
- **Panneau de pièces jointes** *(nouveau)* — fond `{colors.surface}`, bordure `{colors.border}`, `{rounded.md}`. Panneau latéral rétractable dans l'Éditeur, visuellement distinct du corps WYSIWYG. Liste les pièces jointes (badge type + nom + action retirer), zone d'ajout en pied de panneau. Référence visuelle : [`mockups/key-editeur.html`](mockups/key-editeur.html).
- **Zone d'import** — fond `{colors.surface}`, bordure pointillée `{colors.border}`, `{rounded.md}`. Réutilisée à l'identique pour l'ajout de pièces jointes.
- **Panneau de prévisualisation** — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.md}`, sans ombre. Cadre neutre autour du PDF natif ou du contenu converti.
- **Boutons Export** — réutilisent les tokens existants, pas un nouveau composant : "Exporter en PDF" utilise `button-primary`, "Exporter en Word" utilise `button-secondary`.

## Do's and Don'ts

| Do | Don't |
|---|---|
| Un seul accent (`primary`, lime), utilisé pour l'action principale, la navigation active et les états actifs | Ajouter une deuxième couleur d'accent, même pour distinguer les tags entre eux |
| Distinguer les types de fichiers par icône + libellé | Coder les types de fichiers par couleur (fragmente la palette) |
| Neutres chauds partout, jamais de blanc ou noir pur même aux extrêmes de la palette | Revenir à un fond `#FFFFFF`/`#000000` pur "pour plus de contraste" |
| Bordures fines plutôt qu'ombres pour séparer le contenu au repos (y compris la sidebar) | Ombres décoratives sur la sidebar ou les lignes de documents |
| Une seule famille de police (Inter) à toutes les tailles | Ajouter une police "display" pour un effet de marque |
| Layout mono-colonne, largeur de lecture contenue (960px), sidebar fixe à côté | Sidebar repliable/masquable qui change de largeur selon la surface |
