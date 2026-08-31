---
title: DESIGN.md EkoDoc
status: final
created: 2026-08-31
updated: 2026-08-31
name: EkoDoc
description: Base de connaissance interne, usage solo/local (Laravel Herd). Fondation Tailwind CSS from scratch, pas de librairie de composants tierce.
colors:
  # [ASSUMPTION] Palette neutre + un seul accent (bleu sobre). Discipline "un accent, pas deux" pour rester pro et laisser le contenu (les documents) etre la star.
  background: '#FFFFFF'
  background-dark: '#12151A'
  surface: '#F7F8FA'
  surface-dark: '#1A1E24'
  surface-alt: '#EEF0F3'
  surface-alt-dark: '#232830'
  border: '#E2E5EA'
  border-dark: '#2B3038'
  foreground: '#1A1D22'
  foreground-dark: '#E7E9EC'
  muted: '#6B7280'
  muted-dark: '#9AA1AC'
  primary: '#2C5AA0'
  primary-foreground: '#FFFFFF'
  primary-dark: '#6C9BD9'
  primary-foreground-dark: '#0B1420'
  destructive: '#B3261E'
  destructive-foreground: '#FFFFFF'
  destructive-dark: '#F2B8B5'
  destructive-foreground-dark: '#3A0906'
typography:
  # [ASSUMPTION] Inter (ou pile system-ui en repli) — sans-serif neutre, priorite lisibilite pour un outil ou le corps de texte des documents est le contenu principal.
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
  # [ASSUMPTION] Arrondi modere — ni pointu (trop "outil brut"), ni tres arrondi (trop "consumer").
  sm: 4px
  md: 8px
  lg: 12px
spacing:
  # Echelle Tailwind par defaut (base 4px), inheritee telle quelle.
  unit: 4px
  gutter: 24px
  content-max-width: 960px
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
  document-card:
    background: '{colors.surface}'
    border: '{colors.border}'
    radius: '{rounded.md}'
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
  editor-toolbar:
    background: '{colors.surface-alt}'
    border-bottom: '{colors.border}'
  import-zone:
    background: '{colors.surface}'
    border: '{colors.border}'
    border-style: dashed
    radius: '{rounded.md}'
  preview-panel:
    background: '{colors.background}'
    border: '{colors.border}'
    radius: '{rounded.md}'
  category-selector:
    background: '{colors.background}'
    border: '{colors.border}'
    radius: '{rounded.sm}'
---

## Brand & Style

EkoDoc est un outil interne, pas un produit vitrine : le principe directeur est que **le contenu (les documents) doit être la star, l'interface doit s'effacer**. Pas de flourish décoratif, pas de dégradé, pas d'illustration de marque — une seule couleur d'accent utilisée avec parcimonie pour les actions et les états actifs, et des neutres partout ailleurs. `[ASSUMPTION]` Ce choix traduit la demande "sobre/pro/simple" de l'utilisateur en discipline concrète : un accent, pas deux ; du texte et des icônes plutôt que des badges de couleur pour distinguer les types de fichiers.

Ce fichier fixe l'identité visuelle (tokens, couleurs, typographie, composants) ; `EXPERIENCE.md` fixe le comportement (parcours, états, interactions) et référence ces tokens par leur nom. Les deux font foi en cas de conflit avec une maquette.

Mode sombre supporté dès la v1 (voir `EXPERIENCE.md` § Foundation) — chaque token de couleur a sa paire clair/sombre listée ci-dessus.

## Colors

- **Bleu primaire (`#2C5AA0` clair / `#6C9BD9` sombre)** — seul accent du système. Utilisé sur les boutons d'action principale (importer, créer, exporter), les liens actifs, l'état "sélectionné" dans la bibliothèque. `[ASSUMPTION]` Teinte non validée par l'utilisateur — à ajuster librement en revue, aucune contrainte de charte n'a été exprimée.
- **Neutres (`background`, `surface`, `surface-alt`, `border`, `foreground`, `muted`)** portent tout le reste : fond de page, cartes de documents, barres d'outils, texte secondaire. Pas de deuxième couleur d'accent — les types de fichiers (PDF/Word/Excel/créé) se distinguent par icône + libellé, pas par teinte, pour ne pas fragmenter la palette.
- **Destructive (`#B3261E` clair / `#F2B8B5` sombre)** réservé aux actions de suppression et aux messages d'erreur bloquants.

Règles détaillées en § Do's and Don'ts.

## Typography

Une seule famille (Inter, `[ASSUMPTION]`) du titre au corps de texte — pas de moment "display" séparé, cohérent avec la posture sobre. `heading-lg` pour les titres de page (nom du document en fiche, titre de section bibliothèque), `heading-md` pour les sous-sections, `body` pour le contenu courant et le texte des documents créés dans l'éditeur, `body-sm` pour les métadonnées secondaires (date, taille), `label` pour les étiquettes de filtre et de champ de formulaire (majuscule, tracking léger).

## Layout & Spacing

Échelle Tailwind par défaut (base 4px). Largeur de contenu maximale `960px` (`content-max-width`) pour la lecture de documents et l'éditeur — assez large pour un tableau ou une image, pas assez pour perdre l'œil sur un écran large. Layout mono-colonne pour la fiche document et l'éditeur ; la bibliothèque utilise une liste/grille de cartes avec une barre de filtres en tête de page (pas de sidebar de navigation complexe — l'outil n'a que 3 surfaces, voir `EXPERIENCE.md`).

## Elevation & Depth

Minimaliste : la plupart des surfaces sont plates, séparées par des bordures 1px (`{colors.border}`) plutôt que des ombres. Une légère élévation (ombre douce, faible opacité) est réservée aux éléments qui flottent au-dessus du contenu : menu déroulant, modale d'import, notification toast. Pas d'ombre décorative sur les cartes de documents au repos.

## Shapes

Arrondi modéré et cohérent : `rounded.sm` (4px) pour les champs de saisie et badges, `rounded.md` (8px) pour les boutons et cartes, `rounded.lg` (12px) pour les modales. Ni anguleux (trop "outil brut de décoffrage"), ni très arrondi (trop "grand public") — vise un entre-deux professionnel.

## Components

- **Bouton primaire** — fond `{colors.primary}`, texte `{colors.primary-foreground}`, `{rounded.md}`. Réservé à une action principale par écran (Importer, Créer un document, Exporter).
- **Bouton secondaire** — fond `{colors.surface-alt}`, bordure `{colors.border}`, texte `{colors.foreground}`. Actions secondaires (Annuler, Filtrer, Télécharger l'original).
- **Carte document** — fond `{colors.surface}`, bordure `{colors.border}`, `{rounded.md}`. Affiche titre, badge de type, catégorie/dossier, date d'ajout (FR3). Toute la carte est cliquable vers la fiche document.
- **Badge type de fichier** — fond `{colors.surface-alt}`, texte `{colors.muted}`, `{rounded.sm}`, icône + libellé (PDF / Word / Excel / Créé) — pas de couleur dédiée par type, voir § Colors.
- **Barre de recherche** — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.md}`, icône loupe à gauche. Toujours visible en haut de la bibliothèque (FR6).
- **Filtres (chips)** — style bouton secondaire à l'état inactif, fond `{colors.primary}`/texte clair à l'état actif (FR7).
- **Barre d'outils éditeur** — fond `{colors.surface-alt}`, bordure basse `{colors.border}` ; regroupe mise en forme de texte, insertion de tableau, insertion d'image (FR8/FR9).
- **Zone d'import** — fond `{colors.surface}`, bordure pointillée `{colors.border}`, `{rounded.md}`. Le pointillé la distingue visuellement d'une carte document au repos et signale une zone de dépôt actif (FR1).
- **Panneau de prévisualisation** — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.md}`, sans ombre. Cadre neutre autour du PDF natif ou du contenu converti — le document affiché doit rester la seule chose qui attire l'œil (FR4).
- **Sélecteur catégorie/dossier** — fond `{colors.background}`, bordure `{colors.border}`, `{rounded.sm}`. Champ simple, réutilisé à l'identique dans la modale d'import, à l'enregistrement d'un document créé et depuis la Fiche document (FR2, FR10).
- **Boutons Export** — réutilise les tokens existants, pas un nouveau composant : "Exporter en PDF" utilise `button-primary`, "Exporter en Word" utilise `button-secondary` — le PDF est la cible la plus fréquente pour un usage de documentation interne, le Word reste une option secondaire (FR11, FR12).

## Do's and Don'ts

| Do | Don't |
|---|---|
| Un seul accent (`primary`), utilisé pour l'action principale et les états actifs | Ajouter une deuxième couleur d'accent pour "égayer" l'interface |
| Distinguer les types de fichiers par icône + libellé | Coder les types de fichiers par couleur (fragmente la palette) |
| Bordures fines plutôt qu'ombres pour séparer le contenu au repos | Ombres décoratives sur les cartes de documents |
| Une seule famille de police (Inter) à toutes les tailles | Ajouter une police "display" pour un effet de marque |
| Layout mono-colonne, largeur de lecture contenue (960px) | Tableaux/listes en pleine largeur d'écran large |
