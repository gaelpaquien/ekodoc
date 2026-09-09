---
title: EXPERIENCE.md EkoDoc
name: EkoDoc
status: final
sources:
  - ../../briefs/brief-ekodoc-2026-08-31/brief.md
  - ../../prds/prd-ekodoc-2026-08-31/prd.md
  - ../../sprint-change-proposal-2026-09-09.md
created: 2026-08-31
updated: 2026-09-09
---

# EkoDoc — Experience Spine

> Statut au 2026-09-09 : révision majeure suite au `sprint-change-proposal-2026-09-09` (retrait du classement par catégorie, tags illimités, pièces jointes sur document créé, page Configuration). Session `bmad-ux` dédiée en mode coaching — palette et layout tranchés avec l'utilisateur (voir `.memlog.md`). Quatre surfaces sur cinq ont une maquette visuelle de référence dans `mockups/` (Bibliothèque, Éditeur, Recherche, Configuration) ; Fiche document et la modale d'import restent construites depuis les tables ci-dessous seules (décision explicite, voir `.memlog.md`). `DESIGN.md` fait foi pour l'identité visuelle ; ce fichier fait foi pour le comportement ; les deux priment en cas de conflit avec une maquette.

## Foundation

- **Runtime** : application web mono-surface, exécutée en local via Laravel Herd (PRD NFR1), pas de multi-tenant.
- **Périmètre d'appareil** : `[ASSUMPTION]` usage prioritairement desktop/navigateur — le layout reste fluide pour ne pas casser sur tablette, mais aucune optimisation mobile n'est prévue en v1. La sidebar fixe (voir § Information Architecture) renforce cette hypothèse desktop : elle n'a pas de pattern de repli mobile défini.
- **Langue** : `[ASSUMPTION]` interface en français, cohérente avec la langue du PRD/brief et le contexte interne francophone.
- **Thème** : mode clair/sombre disponible, bascule manuelle via le toggle en pied de sidebar (`DESIGN.md.Components.theme-toggle`) et respect de la préférence système par défaut.
- **Fondation CSS** : Tailwind CSS, sans librairie de composants tierce (voir `DESIGN.md` pour les tokens visuels).

## Information Architecture

| Surface | Atteinte depuis | Objectif |
|---|---|---|
| Bibliothèque (accueil) | Ouverture de l'app, ou clic "Bibliothèque" dans la sidebar | Listing paginé (20/page) de tous les documents (importés et créés), filtres tag + type (FR2, FR3, FR7). Plus de barre de recherche intégrée — la recherche fulltexte vit sur la surface Recherche. Maquette : [`mockups/key-bibliotheque.html`](mockups/key-bibliotheque.html). |
| Recherche | Clic "Recherche" dans la sidebar | Recherche fulltexte sur le contenu des documents, pièces jointes incluses (FR6, FR13), affinée par un filtre tag (pas de filtre type ici — le type reste un filtre de navigation propre à la Bibliothèque). Maquette : [`mockups/key-recherche.html`](mockups/key-recherche.html). |
| Fiche document | Clic sur une ligne de document (Bibliothèque ou résultat de Recherche) | Prévisualisation, métadonnées, tags (modifiables), pièces jointes (consultation/téléchargement si document créé, FR13), téléchargement de l'original, accès à l'édition si le document a été créé dans l'outil (FR3, FR4, FR5). Construite depuis les tables seules — pas de maquette (décision explicite, voir `.memlog.md`). |
| Éditeur | Bouton "Créer un document" (Bibliothèque) ou "Modifier" (Fiche document) | Rédaction WYSIWYG, insertion d'images inline, panneau latéral de pièces jointes (FR13), sauvegarde, export PDF/Word (FR8–FR12). Maquette : [`mockups/key-editeur.html`](mockups/key-editeur.html). |
| Configuration | Clic "Configuration" dans la sidebar | Gestion des tags : créer, renommer, supprimer (FR14). Maquette : [`mockups/key-configuration.html`](mockups/key-configuration.html). |

Import (FR1) n'est pas une surface séparée : c'est une modale ouverte depuis la Bibliothèque (glisser-déposer ou sélection de fichier), qui se referme sur la Fiche document du fichier importé.

Sidebar fixe, toujours visible sur les 5 surfaces (`DESIGN.md.Components.sidebar`) — porte les 3 racines de navigation (Bibliothèque, Recherche, Configuration) ; Fiche document et Éditeur restent atteints par clic contextuel, pas depuis la sidebar directement.

**Principe de conception (au-delà du solo v1)** : les libellés de tags et la structure de la Bibliothèque doivent rester lisibles pour quelqu'un sans connaissance institutionnelle du contenu — pas seulement optimisés pour l'usage mental du seul utilisateur actuel. La page Configuration (gestion des tags, FR14) rend ce principe concret dès la v1 : renommer/fusionner des tags mal nommés devient une action directe, pas un renoncement à tout renommer plus tard à l'ouverture multi-utilisateurs (PRD § Trajectoire post-v1).

## Voice and Tone

Ton direct, factuel, sans emoji ni ton "fun" — cohérent avec un outil de travail interne (`DESIGN.md` § Brand & Style). Seule exception assumée et volontaire : le footer "Made with 💔 Claude" (clin d'œil personnel, hors discours fonctionnel).

| Do | Don't |
|---|---|
| "3 documents trouvés" | "Youpi, 3 résultats ! 🎉" |
| "Document importé." | "Import réussi avec succès !" |
| "Aucun document ne correspond à votre recherche." | "Oups, rien ici !" |
| "Enregistré." (sauvegarde silencieuse, pas de pop-up intrusive) | "Votre document a été sauvegardé avec succès dans la base de données." |
| "Tag supprimé — détaché de 4 documents." (confirmation factuelle après suppression) | "Le tag a été supprimé avec succès !" |

## Component Patterns

Comportemental — les specs visuelles sont dans `DESIGN.md.Components`.

| Composant | Usage | Règles comportementales |
|---|---|---|
| Sidebar | Toutes surfaces | Fixe, toujours visible, jamais repliable ni masquée. Item actif surligné (fond lime). Toggle thème + footer en pied de sidebar. |
| Ligne de document | Bibliothèque, résultats Recherche | Toute la ligne cliquable → Fiche document (pas de menu contextuel en v1). Affiche badge type, titre, chips de tags, date (FR3). Paginé par 20 sur la Bibliothèque. |
| Barre de recherche | Recherche | Recherche fulltexte en direct (debounce, pas de bouton "Rechercher" séparé) sur le contenu des documents et de leurs pièces jointes (FR6, FR13). |
| Filtres (chips) | Bibliothèque (tag + type), Recherche (tag uniquement) | Multi-sélection possible. Filtres actifs visibles et retirables en un clic. |
| Sélecteur de tags | Modale Import, Éditeur (à l'enregistrement), Fiche document | Champ optionnel qui filtre en direct la liste gérée de tags (FR2) ; clic/Entrée ajoute une chip. Aucune création de tag depuis ce champ — la création se fait exclusivement en Configuration (FR14). Présent dans les trois contextes, comme l'ancien sélecteur catégorie ; laissé vide = aucun tag, jamais bloquant. |
| Zone d'import | Modale "Importer", Panneau pièces jointes (Éditeur) | Glisser-déposer ou sélection fichier. Formats acceptés affichés explicitement (PDF, `.docx`, `.xlsx` — NFR4). Erreur de format claire et immédiate, pas de rejet silencieux. |
| Panneau de pièces jointes | Éditeur | Panneau latéral rétractable, distinct du corps WYSIWYG (FR13) — ajouter/retirer un fichier n'affecte jamais le contenu rédigé. Chaque pièce jointe reste indexée pour la recherche fulltexte (FR6) au même titre qu'un document importé. |
| Pièces jointes (consultation) | Fiche document | Liste des pièces jointes d'un document créé, avec prévisualisation/téléchargement individuel — lecture seule ; ajout/retrait reste réservé au panneau de l'Éditeur. |
| Panneau de prévisualisation | Fiche document | PDF affiché nativement dans le navigateur — perçu comme quasi instantané. Word/Excel affichés via la conversion prévue en FR4, avec état de chargement explicite (voir § State Patterns). |
| Barre d'outils éditeur | Éditeur | Mise en forme (titres, listes, tableaux) et bouton "Insérer une image" qui insère l'image à l'emplacement du curseur (FR8, FR9). `[ASSUMPTION]` Glisser-déposer une image directement dans le corps du texte est accepté en plus du bouton. |
| Action Enregistrer (éditeur) | Éditeur | Clic sur "Enregistrer" fait apparaître le sélecteur de tags (si pas déjà renseigné), puis intègre le document à la Bibliothèque avec les mêmes propriétés de classement et de recherche qu'un document importé (FR10). |
| Boutons Export (PDF/Word) | Éditeur | Deux actions distinctes et visibles : "Exporter en PDF" (bouton primaire) et "Exporter en Word" (bouton secondaire) — voir `DESIGN.md.Components` (FR11, FR12). |
| Gestion des tags | Configuration | Liste des tags existants, actions créer/renommer/supprimer inline (FR14). Suppression toujours confirmée par une boîte de dialogue nommant le nombre de documents concernés (voir § Interaction Primitives) — détache le tag des documents, ne supprime jamais les documents eux-mêmes. |

## State Patterns

| État | Surface | Traitement |
|---|---|---|
| Chargement initial | Bibliothèque | `[ASSUMPTION]` Corpus local (~350 documents, SQLite) : chargement quasi instantané — aucun état de chargement visuel (skeleton/spinner), à revoir si le volume réel dépasse largement cette cible. |
| Bibliothèque vide (premier lancement) | Bibliothèque | Message : "Aucun document pour l'instant." et bouton primaire "Importer un document" ou "Créer un document". |
| Aucun résultat de filtre | Bibliothèque | "Aucun document ne correspond à ces filtres." et suggestion de retirer les filtres actifs. |
| Aucune recherche saisie | Recherche | État initial neutre : pas de résultats affichés, focus direct sur le champ de recherche (voir § Interaction Primitives). |
| Aucun résultat de recherche | Recherche | "Aucun document ne correspond à votre recherche." et suggestion de retirer le filtre tag actif s'il y en a un. |
| Recherche en cours | Recherche | `[ASSUMPTION]` Pas d'indicateur dédié : la cible NFR2 (<1s sur ~350 documents) rend un état de chargement visuellement inutile ; le debounce suffit. |
| Dernière page atteinte | Bibliothèque | Pagination désactive "Suivant" sans le masquer — état visuel clairement inactif, pas de saut silencieux. |
| Conversion de prévisualisation en cours (Word/Excel) | Fiche document | Indicateur de chargement explicite dans le panneau de prévisualisation ; le reste de la fiche reste utilisable pendant ce temps. |
| Fichier source introuvable | Fiche document | Fichier déplacé/supprimé du disque local ou illisible → message explicite dans le panneau de prévisualisation, bouton "Télécharger" désactivé plutôt qu'un échec silencieux. |
| Conversion de prévisualisation échouée | Fiche document | Message clair ("Aperçu indisponible pour ce fichier") dans le panneau ; le bouton "Télécharger" reste actif. |
| Import/pièce jointe : format non supporté | Modale Import, Panneau pièces jointes | Message d'erreur immédiat nommant les formats acceptés ; le fichier n'est pas envoyé. |
| Aucune pièce jointe | Éditeur, Fiche document | Panneau de pièces jointes affiche "Aucune pièce jointe." et l'action d'ajout, jamais masqué même vide. |
| Chargement d'un document existant | Éditeur | Contenu chargé dans le WYSIWYG avant que l'édition soit possible ; indicateur bref si le chargement prend un temps perceptible. |
| Éditeur : modifications non enregistrées | Éditeur | Indicateur discret (point sur le bouton "Enregistrer") ; confirmation avant de quitter si non enregistré. |
| Export réussi / échoué | Éditeur | Toast bref "Export PDF généré." ou message d'erreur explicite si l'export échoue — jamais un échec silencieux (NFR5). |
| Configuration : aucun tag créé | Configuration | "Aucun tag pour l'instant." et action "Créer un tag" mise en avant. |
| Suppression de tag confirmée | Configuration | Boîte de dialogue (voir § Interaction Primitives) ; message de retour factuel après suppression (voir § Voice and Tone). |

## Interaction Primitives

- `[ASSUMPTION]` Raccourci `/` pour donner le focus à la barre de recherche depuis la surface Recherche (déplacé depuis la Bibliothèque en v1, la barre n'y est plus intégrée).
- Suppression de document : toujours confirmée par une boîte de dialogue (pas d'annulation "undo" prévue en v1).
- Suppression de tag (Configuration) : toujours confirmée par une boîte de dialogue nommant le nombre de documents concernés — même discipline que la suppression de document, cohérente avec le fait qu'un tag peut être partagé par de nombreux documents.

**Bannis en v1** : navigation par raccourcis clavier complexes (pas de posture "keyboard-first"), drag-to-reorder des documents ou des tags, actions destructives sans confirmation, sidebar repliable/masquable.

## Accessibility Floor

Comportemental — le contraste visuel est dans `DESIGN.md` (aucune validation de contraste WCAG requise sur cette palette, décision produit explicite du 2026-09-09).

- `[ASSUMPTION]` Cible WCAG 2.2 AA sur l'ensemble de la surface web (hors contraste couleur, voir ci-dessus) — bonne pratique par défaut, conservée intégralement malgré le changement de palette.
- Texte alternatif obligatoire à la saisie pour toute image insérée dans l'éditeur (FR9).
- Formulaires (import, filtres, sélecteur de tags, éditeur, Configuration) intégralement navigables au clavier ; focus visible partout (`DESIGN.md` `{colors.primary}` sur l'anneau de focus).
- Sidebar navigable au clavier comme tout autre élément de navigation ; ordre de tabulation cohérent avec l'ordre de lecture sur chaque surface.

## Key Flows

### Flow 1 — Importer un document existant (Camille, lundi matin)

1. Depuis la Bibliothèque, Camille clique "Importer" ; la modale d'import s'ouvre.
2. Elle glisse le fichier PDF depuis son explorateur de fichiers dans la zone de dépôt (FR1).
3. Une fois le fichier accepté, le sélecteur de tags apparaît ; elle le laisse vide pour l'instant — elle classera plus tard depuis la Fiche document.
4. **Climax** : le fichier est importé, la modale se ferme et Camille atterrit directement sur la Fiche document du fichier importé — les métadonnées (FR3) sont déjà renseignées (titre déduit du nom de fichier, type, date d'ajout), prêtes à être affinées.

Échec : format non supporté (ex. `.pptx`) → message d'erreur immédiat nommant les formats acceptés (voir § State Patterns), le fichier n'est pas envoyé, la modale reste ouverte pour réessayer.

### Flow 2 — Retrouver et partager un document (Camille, 15h un jeudi)

1. Camille ouvre EkoDoc dans son navigateur ; la Bibliothèque affiche le listing paginé des documents.
2. Elle clique "Recherche" dans la sidebar — la surface Recherche s'ouvre, focus déjà sur le champ (raccourci `/` disponible).
3. Elle tape "procédure export compta" ; les résultats se filtrent en direct sur le contenu fulltexte, pièces jointes incluses (FR6, FR13).
4. Elle affine avec le filtre de tag "Finance" (FR7) — un seul résultat reste.
5. Elle clique sur la ligne : la Fiche document s'ouvre, le PDF s'affiche directement dans le panneau de prévisualisation (FR4).
6. **Climax** : elle clique "Télécharger" (FR5), récupère le fichier original en un geste, et l'envoie par email à un collègue — sans avoir eu à fouiller dans un dossier partagé ou demander à quelqu'un où se trouve le document.

Échec : la conversion de prévisualisation échoue pour un fichier Excel → le panneau affiche "Aperçu indisponible pour ce fichier" mais le bouton "Télécharger" reste actif — l'échec de prévisualisation ne bloque jamais l'accès au fichier original.

### Flow 3 — Rédiger une documentation illustrée (Camille, mardi matin)

1. Depuis la Bibliothèque, Camille clique "Créer un document" ; l'Éditeur s'ouvre, vide, avec le focus sur le titre.
2. Elle rédige deux paragraphes d'explication, ajoute un titre de section (FR8).
3. Elle clique "Insérer une image" à l'endroit précis où elle veut illustrer une étape — l'image s'insère entre les deux blocs de texte, à la position du curseur (FR9).
4. Elle continue à rédiger après l'image ; la mise en page reste stable.
5. Elle clique "Enregistrer" : le sélecteur de tags apparaît, elle tape "proc", la suggestion "Procédures" apparaît et elle la sélectionne — le document rejoint la Bibliothèque avec les mêmes propriétés de classement et de recherche qu'un document importé (FR10).
6. **Climax** : elle clique "Exporter en PDF" (FR11) puis ouvre le fichier généré — l'image est exactement à sa place entre les deux paragraphes, comme dans l'éditeur (NFR5).

Échec : l'export Word place l'image à un mauvais endroit (bug de mapping HTML → `.docx`) → message d'erreur explicite proposant de réessayer, jamais un fichier silencieusement dégradé livré sans avertissement.

### Flow 4 — Compléter un document et nettoyer les tags (Camille, vendredi après-midi)

1. Camille rouvre en édition une procédure qu'elle a rédigée la veille ; elle veut y attacher le tableur Excel original dont elle s'est servie, sans le fusionner dans le texte rédigé.
2. Elle ouvre le panneau de pièces jointes dans l'Éditeur, glisse le fichier `.xlsx` dans la zone de dépôt (FR13) — le fichier apparaît dans la liste du panneau, distinct du corps du texte.
3. Elle enregistre : le tableur reste attaché, indexé pour la recherche fulltexte au même titre qu'un document importé.
4. Plus tard, en parcourant la Configuration, elle remarque deux tags redondants : "Finance" et "Finances". Elle clique "Supprimer" sur "Finances" (le moins utilisé) — une boîte de dialogue s'ouvre, nommant les 3 documents où il est utilisé.
5. **Climax** : elle confirme ; le tag disparaît, les 3 documents concernés restent intacts (juste détachés de ce tag), et un message factuel confirme "Tag supprimé — détaché de 3 documents." — elle a nettoyé son classement sans risque pour ses documents.

Échec : elle tente de créer un tag "finance" (minuscule) en doublon de "Finance" existant → le champ de création en Configuration signale le doublon avant validation, pas après coup.
