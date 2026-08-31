---
title: EXPERIENCE.md EkoDoc
name: EkoDoc
status: final
sources:
  - ../../briefs/brief-ekodoc-2026-08-31/brief.md
  - ../../prds/prd-ekodoc-2026-08-31/prd.md
created: 2026-08-31
updated: 2026-08-31
---

# EkoDoc — Experience Spine

> Statut au 2026-08-31 : aucune maquette produite (`mockups/`, `wireframes/`, `imports/` vides) — décision explicite du mode rapide, voir `.memlog.md`. Les 3 surfaces sont construites directement depuis les tables ci-dessous. `DESIGN.md` fait foi pour l'identité visuelle ; ce fichier fait foi pour le comportement ; les deux priment en cas de conflit avec une future maquette.

## Foundation

- **Runtime** : application web mono-surface, exécutée en local via Laravel Herd (PRD NFR1), pas de multi-tenant.
- **Périmètre d'appareil** : `[ASSUMPTION]` usage prioritairement desktop/navigateur — les parcours du PRD (recherche, consultation, rédaction de documentation) se prêtent mal à un petit écran ; le layout reste fluide pour ne pas casser sur tablette, mais aucune optimisation mobile n'est prévue en v1.
- **Langue** : `[ASSUMPTION]` interface en français, cohérent avec la langue du PRD/brief et le contexte interne francophone.
- **Thème** : `[ASSUMPTION]` mode clair/sombre disponible dès la v1 (bascule manuelle et respect de la préférence système par défaut).
- **Fondation CSS** : Tailwind CSS, sans librairie de composants tierce (voir `DESIGN.md` pour les tokens visuels).

## Information Architecture

| Surface | Atteinte depuis | Objectif |
|---|---|---|
| Bibliothèque (accueil) | Ouverture de l'app | Liste des documents (importés et créés), recherche fulltexte, filtres catégorie/type (FR2, FR3, FR6, FR7) |
| Fiche document | Clic sur une carte document | Prévisualisation, métadonnées, téléchargement de l'original, accès à l'édition si le document a été créé dans l'outil (FR3, FR4, FR5) |
| Éditeur | Bouton "Créer un document" (Bibliothèque) ou "Modifier" (Fiche document) | Rédaction WYSIWYG, insertion d'images inline, sauvegarde, export PDF/Word (FR8–FR12) |

Import (FR1) n'est pas une surface séparée : c'est une modale ouverte depuis la Bibliothèque (glisser-déposer ou sélection de fichier), qui se referme sur la Fiche document du fichier importé.

**Principe de conception (au-delà du solo v1)** : les libellés de catégorie/dossier et la structure de la Bibliothèque doivent rester lisibles pour quelqu'un sans connaissance institutionnelle du contenu — pas seulement optimisés pour l'usage mental du seul utilisateur actuel. Aucun flow dédié n'est nécessaire en v1 (mono-utilisateur), mais cette contrainte guide les choix de libellés dès maintenant pour éviter de tout renommer lors du passage à plusieurs utilisateurs (PRD § Trajectoire post-v1).

## Voice and Tone

Ton direct, factuel, sans emoji ni ton "fun" — cohérent avec un outil de travail interne (`DESIGN.md` § Brand & Style).

| Do | Don't |
|---|---|
| "3 documents trouvés" | "Youpi, 3 résultats ! 🎉" |
| "Document importé." | "Import réussi avec succès !" |
| "Aucun document ne correspond à votre recherche." | "Oups, rien ici !" |
| "Enregistré." (sauvegarde silencieuse, pas de pop-up intrusive) | "Votre document a été sauvegardé avec succès dans la base de données." |

## Component Patterns

Comportemental — les specs visuelles sont dans `DESIGN.md.Components`.

| Composant | Usage | Règles comportementales |
|---|---|---|
| Carte document | Bibliothèque | Toute la carte cliquable → Fiche document (pas de menu contextuel en v1). Affiche badge type, titre, catégorie/dossier, date (FR3). |
| Barre de recherche | Bibliothèque | Recherche fulltexte en direct (debounce, pas de bouton "Rechercher" séparé) sur le contenu des documents (FR6). |
| Filtres (chips) | Bibliothèque | Multi-sélection possible (catégorie et type combinés, FR7). Filtres actifs visibles et retirables en un clic. |
| Zone d'import | Modale "Importer" | Glisser-déposer ou sélection fichier. Formats acceptés affichés explicitement (PDF, `.docx`, `.xlsx` — NFR4). Erreur de format claire et immédiate, pas de rejet silencieux. |
| Panneau de prévisualisation | Fiche document | PDF affiché nativement dans le navigateur — perçu comme quasi instantané, aucun traitement nécessaire. Word/Excel affichés via la conversion prévue en FR4 — c'est le seul cas où une attente est acceptable, avec état de chargement explicite (voir § State Patterns). |
| Barre d'outils éditeur | Éditeur | Mise en forme (titres, listes, tableaux) et bouton "Insérer une image" qui insère l'image à l'emplacement du curseur, entre deux blocs de texte (FR8, FR9) — pas seulement en pièce jointe finale. `[ASSUMPTION]` Glisser-déposer une image directement dans le corps du texte est accepté en plus du bouton, pour rester naturel à l'usage. |
| Sélecteur catégorie/dossier | Modale Import, Éditeur (à l'enregistrement), Fiche document | Champ optionnel, présent dans les trois contextes : à l'import, à la sauvegarde d'un document créé (FR2, FR10), et sur la Fiche document, où il reste modifiable sans limite de temps. Laissé vide = "Non classé", jamais bloquant. |
| Action Enregistrer (éditeur) | Éditeur | Clic sur "Enregistrer" fait apparaître le sélecteur catégorie/dossier (si pas déjà renseigné), puis intègre le document à la Bibliothèque avec les mêmes propriétés de classement et de recherche qu'un document importé (FR10). |
| Boutons Export (PDF/Word) | Éditeur | Deux actions distinctes et visibles, jamais dans un menu caché : "Exporter en PDF" (style bouton primaire — cible la plus fréquente) et "Exporter en Word" (style bouton secondaire) — voir `DESIGN.md.Components` (FR11, FR12). |

## State Patterns

| État | Surface | Traitement |
|---|---|---|
| Chargement initial | Bibliothèque | `[ASSUMPTION]` Corpus local (~350 documents, SQLite) : chargement quasi instantané — décision explicite de ne prévoir aucun état de chargement visuel (skeleton/spinner), à revoir si le volume réel dépasse largement cette cible. |
| Bibliothèque vide (premier lancement) | Bibliothèque | Message : "Aucun document pour l'instant." et bouton primaire "Importer un document" ou "Créer un document". |
| Aucun résultat de recherche/filtre | Bibliothèque | "Aucun document ne correspond à votre recherche." et suggestion de retirer les filtres actifs. |
| Recherche en cours | Bibliothèque | `[ASSUMPTION]` Pas d'indicateur dédié : la cible NFR2 (<1s sur ~350 documents) rend un état de chargement visuellement inutile ; le debounce suffit. À revoir si la performance réelle s'écarte de la cible. |
| Conversion de prévisualisation en cours (Word/Excel) | Fiche document | Indicateur de chargement explicite dans le panneau de prévisualisation ; le reste de la fiche (métadonnées, téléchargement) reste utilisable pendant ce temps. |
| Fichier source introuvable | Fiche document | Fichier déplacé/supprimé du disque local ou illisible → message explicite dans le panneau de prévisualisation, bouton "Télécharger" désactivé plutôt qu'un échec silencieux. |
| Conversion de prévisualisation échouée | Fiche document | Fichier Word/Excel présent mais non convertible (corrompu, format inattendu) → message clair ("Aperçu indisponible pour ce fichier") dans le panneau de prévisualisation ; le bouton "Télécharger" reste actif — un échec de prévisualisation ne bloque jamais l'accès à l'original. |
| Import : format non supporté | Modale Import | Message d'erreur immédiat nommant les formats acceptés ; le fichier n'est pas envoyé. |
| Chargement d'un document existant | Éditeur | Contenu chargé dans le WYSIWYG avant que l'édition soit possible ; indicateur bref si le chargement prend un temps perceptible (rare vu le volume cible). |
| Éditeur : modifications non enregistrées | Éditeur | Indicateur discret (ex. point sur le bouton "Enregistrer") ; confirmation avant de quitter si non enregistré. |
| Export réussi / échoué | Éditeur | Toast bref "Export PDF généré." ou message d'erreur explicite si l'export échoue (ex. image invalide) — jamais un échec silencieux, la fidélité d'export est une exigence critique (NFR5). |

## Interaction Primitives

- `[ASSUMPTION]` Raccourci `/` pour donner le focus à la barre de recherche depuis la Bibliothèque — confort simple, faible risque, à valider ou écarter en revue.
- Suppression de document : toujours confirmée par une boîte de dialogue (pas d'annulation "undo" prévue en v1).

**Bannis en v1** : navigation par raccourcis clavier complexes (pas de posture "keyboard-first"), drag-to-reorder des documents, actions destructives sans confirmation.

## Accessibility Floor

Comportemental — le contraste visuel est dans `DESIGN.md`.

- `[ASSUMPTION]` Cible WCAG 2.2 AA sur l'ensemble de la surface web, bonne pratique par défaut bien que non explicitement demandée (outil interne, pas de contrainte réglementaire connue).
- Texte alternatif obligatoire à la saisie pour toute image insérée dans l'éditeur (FR9) — utile pour l'accessibilité et prépare une future recherche/IA sur le contenu (Trajectoire post-v1 du PRD).
- Formulaires (import, filtres, éditeur) intégralement navigables au clavier ; focus visible partout (`DESIGN.md` `{colors.primary}` sur l'anneau de focus).
- Ordre de tabulation cohérent avec l'ordre de lecture sur chaque surface.

## Key Flows

### Flow 1 — Importer un document existant (Camille, lundi matin)

1. Depuis la Bibliothèque, Camille clique "Importer" ; la modale d'import s'ouvre.
2. Elle glisse le fichier PDF depuis son explorateur de fichiers dans la zone de dépôt (FR1).
3. Une fois le fichier accepté, le sélecteur catégorie/dossier apparaît ; elle le laisse vide pour l'instant — elle classera plus tard depuis la Fiche document.
4. **Climax** : le fichier est importé, la modale se ferme et Camille atterrit directement sur la Fiche document du fichier importé — les métadonnées (FR3) sont déjà renseignées (titre déduit du nom de fichier, type, date d'ajout), prêtes à être affinées.

Échec : format non supporté (ex. `.pptx`) → message d'erreur immédiat nommant les formats acceptés (voir § State Patterns), le fichier n'est pas envoyé, la modale reste ouverte pour réessayer.

### Flow 2 — Retrouver et partager un document (Camille, 15h un jeudi)

1. Camille ouvre EkoDoc dans son navigateur ; la Bibliothèque affiche la liste des documents récents.
2. Elle tape "procédure export compta" dans la barre de recherche ; les résultats se filtrent en direct sur le contenu fulltexte (FR6).
3. Elle affine avec le filtre de catégorie "Finance" (FR7) — un seul résultat reste.
4. Elle clique sur la carte : la Fiche document s'ouvre, le PDF s'affiche directement dans le panneau de prévisualisation (FR4).
5. **Climax** : elle clique "Télécharger" (FR5), récupère le fichier original en un geste, et l'envoie par email à un collègue — sans avoir eu à fouiller dans un dossier partagé ou demander à quelqu'un où se trouve le document.

Échec : la conversion de prévisualisation échoue pour un fichier Excel → le panneau affiche un message clair ("Aperçu indisponible pour ce fichier") mais le bouton "Télécharger" reste actif — l'échec de prévisualisation ne bloque jamais l'accès au fichier original.

### Flow 3 — Rédiger une documentation illustrée (Camille, mardi matin)

1. Depuis la Bibliothèque, Camille clique "Créer un document" ; l'Éditeur s'ouvre, vide, avec le focus sur le titre.
2. Elle rédige deux paragraphes d'explication, ajoute un titre de section (FR8).
3. Elle clique "Insérer une image" à l'endroit précis où elle veut illustrer une étape, choisit une capture d'écran depuis son poste — l'image s'insère entre les deux blocs de texte, à la position du curseur (FR9).
4. Elle continue à rédiger après l'image ; la mise en page reste stable (l'image ne "saute" pas).
5. Elle clique "Enregistrer" : le sélecteur catégorie/dossier apparaît, elle choisit "Procédures" — le document rejoint la Bibliothèque avec les mêmes propriétés de classement et de recherche qu'un document importé (FR10).
6. **Climax** : elle clique "Exporter en PDF" (FR11) puis ouvre le fichier généré — l'image est exactement à sa place entre les deux paragraphes, comme dans l'éditeur. C'est le point de fidélité que le PRD identifie comme critique (NFR5) : Camille n'a pas besoin de retoucher le PDF après coup.

Échec : l'export Word place l'image à un mauvais endroit (bug de mapping HTML → `.docx`, risque identifié dans l'addendum du PRD) → message d'erreur explicite proposant de réessayer, jamais un fichier silencieusement dégradé livré sans avertissement.
