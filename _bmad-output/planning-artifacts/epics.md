---
stepsCompleted: [step-01, step-02, step-03]
inputDocuments:
  - _bmad-output/planning-artifacts/prds/prd-ekodoc-2026-08-31/prd.md
  - _bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md
  - _bmad-output/planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/DESIGN.md
  - _bmad-output/planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-09-09.md
---

# EkoDoc - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for EkoDoc, decomposing the requirements from the PRD, UX Design (DESIGN.md/EXPERIENCE.md), and Architecture Spine into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: Importer un document existant (PDF, Word `.docx`, Excel `.xlsx`) dans la base.
FR2: Classer les documents (importés et créés) par tags illimités, choisis dans une liste gérée (pas de texte libre).
FR3: Associer à chaque document des métadonnées de base : titre, type, tags, date d'ajout.
FR4: Prévisualiser un document dans le navigateur (PDF nativement ; Word/Excel via une conversion).
FR5: Télécharger le fichier original en un clic.
FR6: Rechercher en fulltexte sur le contenu des documents (importés et créés, pièces jointes incluses — FR13).
FR7: Filtrer les résultats par tag et par type de document (PDF, Word, Excel, document créé dans l'outil).
FR8: Créer un document via un éditeur WYSIWYG (titres, listes, tableaux, images).
FR9: Insérer des images à la volée entre des blocs de texte pendant la rédaction (pas seulement en pièce jointe finale).
FR10: Enregistrer un document créé dans la base, avec les mêmes propriétés de classement/recherche qu'un document importé (FR2, FR3, FR6, FR7 s'appliquent aussi aux documents créés).
FR11: Exporter un document créé vers PDF, en conservant fidèlement la position et le rendu des images insérées (FR9).
FR12: Exporter un document créé vers Word (`.docx`), en conservant fidèlement la position et le rendu des images insérées (FR9).
FR13: Associer un ou plusieurs fichiers (PDF, Word, Excel) à un document créé, indépendamment du contenu rédigé dans l'éditeur WYSIWYG (FR8) : ajout, prévisualisation/téléchargement individuel et retrait, sans affecter le contenu de l'éditeur. Le contenu de chaque pièce jointe est indexé pour la recherche fulltexte (FR6) au même titre qu'un document importé (FR1).
FR14: Gérer les tags (créer, renommer, supprimer) depuis une page de configuration dédiée. La suppression d'un tag le détache de tous les documents associés, sans jamais supprimer les documents eux-mêmes.

### NonFunctional Requirements

NFR1: Fonctionne en local via Laravel Herd, sans dépendance à une infrastructure serveur partagée pour la v1.
NFR2: Recherche fulltexte en moins d'1 seconde sur un corpus de l'ordre de 350 documents (cible indicative, pas un SLA) ; pas d'exigence de performance à plus grande échelle pour la v1.
NFR3: Aucune authentification ni gestion de rôles requise pour la v1 (usage mono-utilisateur).
NFR4: Formats supportés en priorité : PDF, `.docx`, `.xlsx` (formats hérités `.doc`/`.xls` non prioritaires en v1 sauf besoin identifié en cours de route).
NFR5: La fidélité d'export (WYSIWYG → PDF/Word) doit rester raisonnable sans viser le pixel-perfect — à l'exception des images inline (FR9), dont la position/rendu est un point de fidélité critique (particulièrement garanti pour le PDF, cf. AD-11/AD-12).

### Additional Requirements

**Aucun starter/scaffolding pré-construit.** Installation Laravel 13 fraîche, configurée manuellement avec Inertia.js 3.0 + Vue 3 + Vite 8.x + Tailwind CSS 4.x (pas de Breeze/Jetstream, aucun scaffolding d'authentification — cohérent avec NFR3). Ceci était le point de départ de l'Epic 1 / Story 1 (déjà livré).

- Paradigme obligatoire sur tout le code : Thin Controller → Action → DTO → Eloquent Model (AD-1 à AD-3). Controllers sans logique métier ; Actions PHP pures invokables (`__invoke`), une opération métier nommée par Action ; DTO `readonly` PHP natif en frontière de chaque Action (jamais un array/Request direct) ; pas de repository.
- Modèle de données unifié : une seule table `documents` avec colonne discriminante `source` (`imported`|`created`) — pas deux tables séparées (AD-4).
- **Classement à plat par tags illimités `[AMENDED 2026-09-09]`** : table `tags` (id, name) sans hiérarchie ; relation many-to-many via pivot `document_tag` (document_id, tag_id), pas de limite de tags par document (AD-5). `SyncDocumentTagsAction` est l'unique Action qui écrit le pivot, en remplacement complet (`sync()`, jamais `attach()`/`detach()` incrémental), invoquée depuis Éditeur (à l'enregistrement) et Fiche document. `TagSelector.vue` est un composant unique et partagé (jamais réimplémenté par écran), sans librairie tierce, et n'expose aucun mode "créable" — tags gérés exclusivement depuis la page Configuration (FR14, AD-18). **Remplace** l'ancien mécanisme catégorie unique (table `categories`, `documents.category_id`, `CategorizeDocumentAction`, modèle/controller `Category`) — supprimés, pas dépréciés. Aucune migration des données existantes vers des tags équivalents (décision produit explicite, 2026-09-09).
- **Traitement d'import : stockage synchrone, extraction de texte en file d'attente `[AMENDED 2026-09-01]`** : `ImportDocumentAction` stocke le fichier et crée le `Document` (`extraction_status = pending`) dans la même requête HTTP/transaction ; l'extraction de texte est ensuite dispatchée comme job en file d'attente (`ExtractDocumentTextJob`, driver `database`) et s'exécute hors requête HTTP — un worker (`queue:work`/`queue:listen`) doit tourner. `extraction_status` transite `pending` → `processing` → `completed`/`failed` ; le document reste consultable/téléchargeable/visible quel que soit son statut d'extraction (AD-6). L'indexation Scout est toujours automatique via l'événement `saved` du Model, jamais un appel manuel.
- Fichiers originaux stockés sur disque privé Laravel (`storage/app/private/documents/{document_id}/{filename}`), jamais `public` ; téléchargement toujours via route applicative streamée (AD-7).
- Recherche et filtrage unifiés via Laravel Scout (driver `database`) sur le champ `extracted_text`, un seul point d'entrée de requête (recherche + filtres, y compris le filtre tag — jamais une requête `whereHas('tags')` séparée), jamais deux chemins divergents (AD-8).
- Extraction de texte best-effort (`smalot/pdfparser` pour PDF, `phpoffice/phpword`/`phpoffice/phpspreadsheet` pour Word/Excel) via `ExtractDocumentTextJob` : un échec n'interrompt jamais l'import/l'attachement, seulement `extraction_status = failed` et `extracted_text = NULL` (pas d'OCR en v1). Pour les documents créés, `extracted_text` est dérivé automatiquement et de façon synchrone de `content_html` à chaque sauvegarde (`extraction_status = completed` immédiatement) — jamais mis en file d'attente pour un document créé (AD-9).
- Prévisualisation Office (Word/Excel importés) via conversion à la demande `soffice --headless --convert-to pdf` (LibreOffice CLI), résultat mis en cache sur `storage/app/private/previews/{document_id}.pdf`, invalidé uniquement par suppression/ré-import. PDF natif et documents créés : rendu direct, jamais de conversion, pas de PDF.js (AD-10).
- Export PDF via rendu HTML réel : `spatie/browsershot` (Chromium headless) sur la même vue Blade que l'éditeur affiche — jamais de génération PDF alternative type dompdf/wkhtmltopdf (AD-11).
- Export Word via `phpoffice/phpword` (import HTML) — fidélité best-effort documentée, pas garantie au niveau du PDF ; un échec de positionnement est un message d'erreur explicite, jamais un fichier dégradé livré silencieusement (AD-12).
- Aucune couche API JSON : toutes les routes rendent des pages Inertia ou redirigent après mutation ; pas de `Route::apiResource` ni `response()->json()` (AD-13).
- Images insérées dans l'éditeur stockées en fichiers (`storage/app/private/documents/{document_id}/images/{uuid}.{ext}`) servies via route dédiée, jamais en base64 inline dans `content_html` ; texte alternatif obligatoire capturé à l'insertion (AD-14).
- **Suppression définitive et nettoyage complet `[EXTENDED 2026-09-09]`** (pas de `SoftDeletes`) ; `DeleteDocumentAction` unique point d'entrée, nettoie dans l'ordre : index Scout → fichier original + images → chaque fichier disque de `document_attachments` puis leurs lignes (jamais une simple `cascadeOnDelete()` qui laisserait les fichiers orphelins) → cache de prévisualisation → ligne `Document` (le pivot `document_tag` est nettoyé par `cascadeOnDelete()` standard, aucun fichier disque associé) (AD-15).
- **Pièces jointes sur document créé `[NOUVEAU 2026-09-09]`** : nouvelle table `document_attachments` (id, document_id, file_path, original_filename, mime_type, `extracted_text`, `extraction_status`, timestamps) — mêmes types acceptés que FR1, mêmes règles de stockage (`storage/app/private/documents/{document_id}/attachments/{uuid}.{ext}`, jamais public, AD-7) et de pipeline d'extraction (même `ExtractDocumentTextJob` généralisé, jamais un second pipeline dupliqué). `AttachDocumentFileAction`/`DetachDocumentFileAction` points d'entrée uniques ; contenu extrait agrégé par `Document::toSearchableArray()` (pas de ligne Scout séparée par pièce jointe). Prévisualisation/téléchargement individuel via `DocumentAttachmentController@preview`/`@download`, même discipline qu'AD-7 (AD-17).
- **Page Configuration : gestion des tags `[NOUVEAU 2026-09-09]`** : `TagController` expose une page Inertia listant les tags avec create/rename/delete. `DeleteTagAction` détache le tag de tous les documents (suppression des lignes du pivot `document_tag`) sans jamais supprimer les documents eux-mêmes (AD-18).
- Naming anglais uniquement partout (code, classes, logs) ; pas de valeurs magiques (enums/constantes pour `source`, types de fichiers, statuts).
- Couverture de tests Pest à 100 %, vérifiée manuellement (`pest --coverage`) avant chaque commit — pas de pipeline CI en v1.
- Dates en `timestamps` Eloquent standard (UTC en base, formatage français côté Vue) ; IDs entiers auto-incrémentés (pas d'UUID).
- Erreurs métier (extraction, conversion, export) toujours renvoyées comme message explicite côté Inertia, jamais une exception non catchée ; logs applicatifs Laravel standard (canal `stack`), pas de service externe.
- Un controller par ressource (`DocumentController`, `DocumentAttachmentController`, `TagController`), méthodes RESTful standard (`index`, `store`, `show`, `update`, `destroy`).
- Structural Seed (Vue) : `resources/js/Pages/` = `Library.vue`, `DocumentShow.vue`, `Editor.vue`, `Search.vue`, `Configuration.vue` ; `resources/js/Components/` = `DocumentRow.vue` (renommé depuis `DocumentCard.vue` v1), `SearchBar.vue`, `FilterChips.vue`, `TagSelector.vue`, `ImportZone.vue`, `PreviewPanel.vue`, sidebar de navigation.
- Stack complète à installer/configurer : PHP 8.5, Laravel 13, MySQL 8.x (via Herd), Inertia.js 3.0, Vue 3, Vite 8.x, Tailwind CSS 4.x, TipTap 3.x (`@tiptap/vue-3`), Laravel Scout, spatie/browsershot 5.4, phpoffice/phpword 1.4.0, phpoffice/phpspreadsheet, smalot/pdfparser 2.12.5, LibreOffice (headless CLI), Pest 5.x — environnement Laravel Herd (édition gratuite), local uniquement.

### UX Design Requirements

UX-DR1: Système de tokens de design (couleurs, typographie, arrondis, espacements) implémenté selon `DESIGN.md` — palette v2 `[AMENDED 2026-09-09]` (neutres chauds beige/brun, jamais blanc/noir pur ; accent unique lime néon `#C6FF00`) ; chaque couleur a une paire clair/sombre ; discipline "un seul accent". Aucune validation de contraste WCAG requise sur cette palette (décision produit explicite, projet interne).
UX-DR2: Mode clair/sombre disponible dès la v1, bascule manuelle via toggle en pied de sidebar, respect de la préférence système par défaut.
UX-DR3: Composant Sidebar de navigation `[NOUVEAU 2026-09-09]` — fixe, toujours visible sur les 5 surfaces (jamais repliable/masquée) ; 3 racines (Bibliothèque, Recherche, Configuration), item actif en fond lime/texte quasi-noir.
UX-DR4: Composant Footer `[NOUVEAU 2026-09-09]` — texte littéral "Made with 💔 Claude", en pied de sidebar sur toutes les surfaces (exception assumée au ton direct sans emoji).
UX-DR5: Composant Ligne de document *(remplace la Carte document v1)* — toute la ligne cliquable vers la Fiche document (pas de menu contextuel en v1), affiche badge de type, titre, chips de tags, date d'ajout ; listing paginé 20/page sur la Bibliothèque.
UX-DR6: Surface Bibliothèque révisée `[AMENDED 2026-09-09]` — plus de barre de recherche intégrée (déplacée vers la surface Recherche dédiée) ; filtres (chips) tag + type en multi-sélection combinée, filtres actifs visibles et retirables en un clic.
UX-DR7: Surface Recherche dédiée `[NOUVEAU 2026-09-09]` — barre de recherche fulltexte en direct avec debounce (pas de bouton "Rechercher" séparé), porte sur le contenu des documents ET de leurs pièces jointes (FR6, FR13) ; filtre tag uniquement (pas de filtre type, propre à la Bibliothèque).
UX-DR8: Composant Zone d'import — glisser-déposer ou sélection de fichier, bordure pointillée, formats acceptés affichés explicitement, erreur de format claire et immédiate (pas de rejet silencieux) ; réutilisée à l'identique pour l'ajout de pièces jointes.
UX-DR9: Composant Panneau de prévisualisation — PDF natif perçu comme quasi instantané (aucun traitement) ; Word/Excel via conversion avec état de chargement explicite.
UX-DR10: Composant Barre d'outils éditeur — mise en forme (titres, listes, tableaux) + bouton "Insérer une image" qui insère à la position du curseur entre deux blocs de texte ; glisser-déposer d'image dans le corps du texte également accepté.
UX-DR11: Composant Sélecteur de tags *(remplace le Sélecteur catégorie/dossier v1)* — champ qui filtre en direct la liste gérée de tags existants (FR2), clic/Entrée ajoute une chip ; aucune création de tag depuis ce champ (exclusivement en Configuration, FR14). Présent dans 3 contextes identiques (modale Import, sauvegarde Éditeur, Fiche document) ; vide = aucun tag, jamais bloquant.
UX-DR12: Composant Chip de tag `[NOUVEAU 2026-09-09]` — affiche un tag sur une ligne de document/Fiche document, ou dans le sélecteur avec icône de retrait ; visuellement distinct des filtres (chips).
UX-DR13: Action Enregistrer (éditeur) — déclenche le sélecteur de tags si non renseigné, puis intègre le document à la Bibliothèque avec les mêmes propriétés de classement/recherche qu'un document importé.
UX-DR14: Boutons Export — deux actions distinctes toujours visibles, jamais dans un menu caché : "Exporter en PDF" (style bouton primaire) et "Exporter en Word" (style bouton secondaire).
UX-DR15: Composant Panneau de pièces jointes `[NOUVEAU 2026-09-09]` (Éditeur) — panneau latéral rétractable, visuellement distinct du corps WYSIWYG ; liste les pièces jointes (badge type + nom + action retirer), zone d'ajout en pied de panneau ; ajouter/retirer un fichier n'affecte jamais le contenu rédigé (FR13).
UX-DR16: Pièces jointes en consultation `[NOUVEAU 2026-09-09]` (Fiche document) — liste en lecture seule avec prévisualisation/téléchargement individuel ; ajout/retrait reste réservé au panneau de l'Éditeur.
UX-DR17: Surface Configuration `[NOUVEAU 2026-09-09]` — liste des tags existants, actions créer/renommer/supprimer inline (FR14) ; création signale un doublon (insensible à la casse) avant validation.
UX-DR18: État Bibliothèque vide (premier lancement) — message "Aucun document pour l'instant." + bouton primaire "Importer" ou "Créer un document".
UX-DR19: État Aucun résultat de filtre (Bibliothèque) — "Aucun document ne correspond à ces filtres." + suggestion de retirer les filtres actifs.
UX-DR20: État Recherche `[NOUVEAU 2026-09-09]` — état initial neutre (aucune recherche saisie, focus direct sur le champ) ; aucun résultat → "Aucun document ne correspond à votre recherche." + suggestion de retirer le filtre tag actif s'il y en a un.
UX-DR21: État Dernière page atteinte (Bibliothèque) — pagination désactive "Suivant" sans le masquer, jamais un saut silencieux.
UX-DR22: État Conversion de prévisualisation en cours (Word/Excel) — indicateur de chargement explicite dans le panneau ; reste de la fiche document utilisable pendant ce temps.
UX-DR23: État Fichier source introuvable — message explicite dans le panneau de prévisualisation, bouton "Télécharger" désactivé (jamais un échec silencieux).
UX-DR24: État Conversion de prévisualisation échouée — message clair "Aperçu indisponible pour ce fichier", bouton "Télécharger" reste actif.
UX-DR25: État Import/pièce jointe format non supporté — message d'erreur immédiat nommant les formats acceptés, fichier non envoyé (modale reste ouverte pour l'import).
UX-DR26: État Aucune pièce jointe `[NOUVEAU 2026-09-09]` (Éditeur, Fiche document) — "Aucune pièce jointe." + action d'ajout, jamais masqué même vide.
UX-DR27: État Chargement d'un document existant dans l'Éditeur — contenu chargé dans le WYSIWYG avant que l'édition soit possible, indicateur bref si perceptible.
UX-DR28: État Modifications non enregistrées (Éditeur) — indicateur discret + confirmation avant de quitter si non enregistré.
UX-DR29: État Export réussi/échoué — toast bref en succès, message d'erreur explicite en échec, jamais un échec silencieux.
UX-DR30: État Configuration aucun tag créé `[NOUVEAU 2026-09-09]` — "Aucun tag pour l'instant." + action "Créer un tag" mise en avant.
UX-DR31: État Suppression de tag confirmée `[NOUVEAU 2026-09-09]` — boîte de dialogue nommant le nombre de documents concernés ; message factuel après suppression ("Tag supprimé — détaché de N documents.").
UX-DR32: Interaction — suppression de document toujours confirmée par boîte de dialogue (pas d'undo en v1).
UX-DR33: Interaction — suppression de tag `[NOUVEAU 2026-09-09]` toujours confirmée par boîte de dialogue nommant le nombre de documents concernés — même discipline que la suppression de document.
UX-DR34: Interaction — raccourci `/` donne le focus à la barre de recherche depuis la surface Recherche `[AMENDED 2026-09-09]` (déplacé depuis la Bibliothèque, la barre n'y est plus intégrée).
UX-DR35: Accessibilité — cible WCAG 2.2 AA sur l'ensemble de la surface web, hors contraste couleur (aucune validation de contraste requise sur la palette v2, décision produit explicite du 2026-09-09) — navigation clavier, focus visible, texte alternatif obligatoire restent intégralement en vigueur.
UX-DR36: Accessibilité — texte alternatif obligatoire à la saisie pour toute image insérée dans l'éditeur.
UX-DR37: Accessibilité — formulaires (import, filtres, sélecteur de tags, éditeur, Configuration) intégralement navigables au clavier, focus visible partout, ordre de tabulation cohérent avec l'ordre de lecture.
UX-DR38: Accessibilité — sidebar `[NOUVEAU 2026-09-09]` navigable au clavier comme tout autre élément de navigation ; ordre de tabulation cohérent avec l'ordre de lecture sur chaque surface.
UX-DR39: IA — les libellés de tags et la structure de la Bibliothèque doivent rester lisibles pour quelqu'un sans connaissance institutionnelle du contenu ; la page Configuration (FR14) rend ce principe concret dès la v1 (renommer/fusionner des tags mal nommés est une action directe).
UX-DR40: IA — l'import n'est pas une surface séparée : c'est une modale ouverte depuis la Bibliothèque qui se referme sur la Fiche document du fichier importé.
UX-DR41: Voix et ton — ton direct, factuel, sans emoji ni ton "fun" (cohérent avec un outil de travail interne, exception assumée pour le footer UX-DR4) ; sauvegardes silencieuses sans pop-up intrusive ; suppression de tag confirmée par un message factuel nommant le nombre de documents concernés.

### FR Coverage Map

FR1: Epic 1 - Import de documents existants
FR2: Epic 3 - Tags illimités `[AMENDED 2026-09-09, remplace le mécanisme catégorie livré par l'Epic 1]`
FR3: Epic 1 - Métadonnées de base (titre/type/date) + Epic 3 - Tags `[AMENDED 2026-09-09]`
FR4: Epic 1 - Prévisualisation navigateur
FR5: Epic 1 - Téléchargement de l'original
FR6: Epic 1 - Recherche fulltexte + Epic 3 - Indexation des pièces jointes `[AMENDED 2026-09-09]`
FR7: Epic 3 - Filtrage par tag/type `[AMENDED 2026-09-09, remplace le filtre catégorie livré par l'Epic 1]`
FR8: Epic 2 - Éditeur WYSIWYG
FR9: Epic 2 - Images inline
FR10: Epic 2 - Enregistrement classé/cherchable + Epic 3 - Tags `[AMENDED 2026-09-09]`
FR11: Epic 2 - Export PDF
FR12: Epic 2 - Export Word
FR13: Epic 3 - Pièces jointes sur document créé `[NOUVEAU 2026-09-09]`
FR14: Epic 3 - Configuration des tags `[NOUVEAU 2026-09-09]`

## Epic List

### Epic 1: Bibliothèque de documents — importer, classer, retrouver
Un utilisateur peut importer ses documents existants (PDF/Word/Excel), les classer par catégorie, les prévisualiser/télécharger, et les retrouver par recherche fulltexte ou filtres.
**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7

### Epic 2: Création et export de documents
Un utilisateur peut rédiger un nouveau document dans un éditeur WYSIWYG, y insérer des images à la volée, l'enregistrer dans la bibliothèque avec les mêmes propriétés de classement/recherche qu'un import, puis l'exporter en PDF ou Word.
**FRs covered:** FR8, FR9, FR10, FR11, FR12

### Epic 3: Tags illimités, pièces jointes et configuration `[NOUVEAU 2026-09-09 — sprint-change-proposal]`
Un utilisateur classe et retrouve ses documents par tags illimités (remplace le classement par catégorie de l'Epic 1, retiré et non migré), associe une ou plusieurs pièces jointes à un document créé indépendamment du contenu rédigé, gère ses tags depuis une page de configuration dédiée, et navigue entre 5 surfaces (Bibliothèque, Fiche document, Éditeur, Recherche, Configuration) via une sidebar fixe — la surface Recherche devient dédiée, distincte de la Bibliothèque. Corrige aussi un défaut connu de l'éditeur (tableaux TipTap imbriqués, aucun moyen de suppression), sans impact FR/Architecture (FR8 le couvre déjà).
**FRs covered:** FR2, FR3 (tags), FR6 (indexation pièces jointes), FR7, FR10 (tags), FR13, FR14

## Epic 1: Bibliothèque de documents — importer, classer, retrouver

Un utilisateur peut importer ses documents existants (PDF/Word/Excel), les classer par catégorie, les prévisualiser/télécharger, et les retrouver par recherche fulltexte ou filtres — la valeur "Retrouver et exploiter un document existant" du PRD, complète en elle-même.

### Story 1.1: Importer un document existant

As a utilisateur solo,
I want importer un fichier PDF/Word/Excel existant,
So that il rejoint ma base de documents sans ressaisie.

**Acceptance Criteria:**

**Given** l'application EkoDoc n'existe pas encore
**When** le projet est initialisé
**Then** un scaffold Laravel 13 + Inertia.js 3.0 + Vue 3 + Vite 8 + Tailwind CSS 4 est en place (pas de Breeze/Jetstream, cohérent avec NFR3), avec la table `documents` (id, title, source, file_path, mime_type, timestamps)

**Given** la Bibliothèque
**When** l'utilisateur clique "Importer" et glisse-dépose ou sélectionne un fichier PDF/`.docx`/`.xlsx`
**Then** le fichier est stocké sur le disque privé (`storage/app/private/documents/{id}/{filename}`, jamais `public`, AD-7) et un `Document` (`source=imported`) est créé avec titre déduit du nom de fichier, type et date d'ajout (FR3)

**Given** un fichier dans un format non supporté (ex. `.pptx`)
**When** il est déposé dans la zone d'import
**Then** un message d'erreur immédiat nomme les formats acceptés (NFR4), le fichier n'est pas envoyé, la modale reste ouverte (UX-DR17)

**Given** l'import réussi
**When** la modale se ferme
**Then** l'utilisateur atterrit directement sur la Fiche document du fichier importé (UX-DR27) — pas de page d'import séparée
**And** l'extraction de texte (`smalot/pdfparser`/`phpoffice/phpword`/`phpoffice/phpspreadsheet`) tourne dans le même traitement synchrone (AD-6) ; un échec d'extraction n'interrompt pas l'import, `extracted_text` reste `NULL` (AD-9)
**And** le formulaire d'import est intégralement navigable au clavier, focus visible (UX-DR25)

### Story 1.2: Parcourir la bibliothèque de documents

As a utilisateur,
I want voir la liste de tous mes documents dès l'ouverture d'EkoDoc,
So that je retrouve visuellement ce que j'ai déjà importé.

**Acceptance Criteria:**

**Given** au moins un document existe
**When** j'ouvre EkoDoc
**Then** la Bibliothèque affiche une carte par document (badge type, titre, catégorie/dossier, date — UX-DR3), toute la carte cliquable vers la Fiche document

**Given** aucun document n'existe encore
**When** j'ouvre EkoDoc
**Then** le message "Aucun document pour l'instant." s'affiche avec un bouton primaire "Importer un document" ou "Créer un document" (UX-DR12)
**And** un bouton de bascule clair/sombre est disponible dans l'interface, respectant la préférence système par défaut (UX-DR2)
**And** les tokens de design (`DESIGN.md`) sont appliqués : neutres + accent unique, pas de couleur par type de fichier (UX-DR1)

### Story 1.3: Prévisualiser un document dans le navigateur

As a utilisateur,
I want voir le contenu d'un document sans le télécharger,
So that je vérifie rapidement s'il correspond à ce que je cherche.

**Acceptance Criteria:**

**Given** un document `source=imported` de type PDF
**When** j'ouvre sa Fiche document
**Then** le PDF s'affiche nativement dans le panneau de prévisualisation, perçu comme instantané, sans conversion (AD-10, UX-DR7)

**Given** un document Word/Excel importé
**When** j'ouvre sa Fiche document
**Then** une conversion à la demande via LibreOffice headless (`soffice --headless --convert-to pdf`) génère un PDF mis en cache (`storage/app/private/previews/{id}.pdf`), avec un indicateur de chargement explicite pendant la conversion (UX-DR14) ; le reste de la fiche (métadonnées, téléchargement) reste utilisable pendant ce temps

**Given** le fichier source a été déplacé/supprimé du disque ou est illisible
**When** j'ouvre sa Fiche document
**Then** un message explicite s'affiche dans le panneau et le bouton "Télécharger" est désactivé (jamais un échec silencieux, UX-DR15, AD-7)

**Given** un fichier Word/Excel corrompu ou non convertible
**When** la conversion échoue
**Then** le message "Aperçu indisponible pour ce fichier" s'affiche mais le bouton "Télécharger" reste actif (UX-DR16)

### Story 1.4: Télécharger le fichier original

As a utilisateur,
I want télécharger le fichier original d'un document en un clic,
So that je peux le transmettre tel quel à un collègue.

**Acceptance Criteria:**

**Given** une Fiche document avec un fichier source lisible
**When** je clique "Télécharger"
**Then** le fichier original est streamé via une route applicative (`Storage::download()`), jamais un lien direct vers le disque `public` (AD-7, FR5)
**And** le téléchargement reste disponible même si la prévisualisation échoue ou est en cours de conversion (cohérent avec Story 1.3)

### Story 1.5: Classer un document par catégorie

As a utilisateur,
I want assigner une catégorie à un document,
So that je peux structurer ma bibliothèque au fur et à mesure.

**Acceptance Criteria:**

**Given** la table `categories` (id, name, à plat, sans hiérarchie — AD-5) n'existe pas encore
**When** cette story est implémentée
**Then** elle est créée avec `documents.category_id` nullable

**Given** la modale d'import (Story 1.1) ou la Fiche document
**When** j'ouvre le sélecteur catégorie/dossier
**Then** je peux choisir une catégorie existante, en créer une nouvelle, ou laisser vide ("Non classé", jamais bloquant — UX-DR9, FR2)
**And** `CategorizeDocumentAction` est l'unique point d'écriture de `category_id`, invoqué depuis ces contextes (AD-16)
**And** les libellés de catégorie restent lisibles pour quelqu'un sans connaissance institutionnelle du contenu — pas de jargon interne opaque (UX-DR26, principe de conception)

### Story 1.6: Rechercher un document par son contenu

As a utilisateur,
I want taper un mot-clé et retrouver les documents qui le contiennent,
So that je n'ai pas à fouiller manuellement parmi ~350 fichiers.

**Acceptance Criteria:**

**Given** Laravel Scout (driver `database`) configuré sur le modèle `Document` (indexé sur `extracted_text`)
**When** je tape un terme dans la barre de recherche de la Bibliothèque
**Then** les résultats se filtrent en direct avec un debounce, sans bouton "Rechercher" séparé (UX-DR4, FR6)
**And** la recherche répond en moins d'1 seconde sur un corpus de l'ordre de 350 documents (NFR2), sans indicateur de chargement dédié (le debounce suffit)

**Given** aucun document ne correspond au terme recherché
**When** les résultats s'affichent
**Then** le message "Aucun document ne correspond à votre recherche." apparaît avec suggestion de retirer les filtres actifs (UX-DR13)
**And** le raccourci `/` donne le focus à la barre de recherche depuis la Bibliothèque (UX-DR22)

### Story 1.7: Filtrer les documents par catégorie et par type

As a utilisateur,
I want combiner un filtre de catégorie et un filtre de type de fichier,
So that j'affine ma recherche sans avoir à taper un mot-clé précis.

**Acceptance Criteria:**

**Given** la Bibliothèque affichée
**When** je sélectionne une ou plusieurs catégories et/ou types (PDF, Word, Excel, document créé)
**Then** la liste se filtre en combinant les critères (multi-sélection, FR7, UX-DR5)
**And** les filtres actifs sont visibles et retirables en un clic
**And** recherche (Story 1.6) et filtres passent par un unique point d'entrée de requête, jamais deux chemins divergents (AD-8)

### Story 1.8: Supprimer un document

As a utilisateur,
I want supprimer définitivement un document devenu inutile,
So that ma bibliothèque reste pertinente.

**Acceptance Criteria:**

**Given** une Fiche document
**When** je clique "Supprimer"
**Then** une boîte de dialogue de confirmation apparaît avant toute suppression (pas d'undo en v1, UX-DR21)

**Given** la suppression confirmée
**When** `DeleteDocumentAction` s'exécute
**Then** il retire dans l'ordre : l'entrée de l'index Scout → le fichier original et ses images associées → le cache de prévisualisation s'il existe → la ligne `Document` (AD-15, suppression définitive, pas de `SoftDeletes`)

## Epic 2: Création et export de documents

Un utilisateur peut rédiger un nouveau document dans un éditeur WYSIWYG, y insérer des images à la volée, l'enregistrer dans la bibliothèque avec les mêmes propriétés de classement/recherche qu'un import, puis l'exporter en PDF ou Word — la valeur "Créer un document pour usage futur ou partage" du PRD. S'appuie sur la table `documents`/`categories` et le sélecteur catégorie posés par l'Epic 1, sans duplication de modèle.

### Story 2.1: Créer et enregistrer un document dans l'éditeur WYSIWYG

As a utilisateur,
I want rédiger un document directement dans EkoDoc et l'enregistrer,
So that il devient disponible pour consultation/recherche future comme un document importé.

**Acceptance Criteria:**

**Given** la Bibliothèque
**When** je clique "Créer un document"
**Then** l'Éditeur s'ouvre vide (TipTap 3.x, `@tiptap/vue-3`), focus sur le titre, avec une barre d'outils de mise en forme (titres, listes, tableaux — FR8)

**Given** du contenu rédigé
**When** je clique "Enregistrer"
**Then** le sélecteur catégorie/dossier apparaît s'il n'est pas déjà renseigné (UX-DR10), puis un `Document` (`source=created`) est créé avec `content_html` et `extracted_text` dérivé automatiquement du HTML (texte brut, balises retirées — AD-9)
**And** le document enregistré possède les mêmes propriétés de classement et de recherche qu'un document importé — il apparaît dans la Bibliothèque, est filtrable (Story 1.7) et cherchable (Story 1.6) immédiatement (FR10)
**And** la sauvegarde reste silencieuse, sans pop-up intrusive ("Enregistré.", cohérent avec le ton direct — UX-DR28)

### Story 2.2: Insérer des images à la volée pendant la rédaction

As a utilisateur,
I want insérer une image entre deux blocs de texte à l'endroit exact où j'en ai besoin,
So that ma documentation reste illustrée et lisible sans réorganisation après coup.

**Acceptance Criteria:**

**Given** l'Éditeur ouvert (Story 2.1)
**When** je clique "Insérer une image" ou glisse-dépose une image dans le corps du texte
**Then** l'image s'insère à la position du curseur, entre deux blocs de texte — pas seulement en pièce jointe finale (FR9, UX-DR8)
**And** `UploadEditorImageAction` stocke le fichier sur `storage/app/private/documents/{document_id}/images/{uuid}.{ext}`, servi via une route applicative dédiée, jamais encodé en base64 inline dans `content_html` (AD-14)

**Given** l'insertion d'une image
**When** elle est ajoutée
**Then** un texte alternatif est obligatoire à la saisie (UX-DR24, accessibilité)
**And** la mise en page reste stable après insertion — le texte suivant ne "saute" pas

### Story 2.3: Modifier un document créé existant

As a utilisateur,
I want rouvrir un document que j'ai créé pour le corriger ou le compléter,
So that je n'ai pas à repartir de zéro pour une mise à jour.

**Acceptance Criteria:**

**Given** une Fiche document `source=created`
**When** je clique "Modifier"
**Then** l'Éditeur s'ouvre avec le `content_html` existant chargé dans le WYSIWYG avant que l'édition soit possible, avec un indicateur bref si le chargement est perceptible (UX-DR18)

**Given** des modifications non enregistrées
**When** je tente de quitter l'Éditeur
**Then** un indicateur discret signale l'état non sauvegardé et une confirmation est demandée avant de quitter (UX-DR19)
**And** l'enregistrement d'une modification met à jour `content_html` et re-dérive `extracted_text`, réutilisant `CategorizeDocumentAction` si la catégorie change (AD-16)

### Story 2.4: Exporter un document créé en PDF

As a utilisateur,
I want exporter mon document rédigé en PDF,
So that je peux le partager en conservant fidèlement la mise en page et les images.

**Acceptance Criteria:**

**Given** un document `source=created` (avec ou sans images inline, Story 2.2)
**When** je clique "Exporter en PDF" (bouton primaire — UX-DR11)
**Then** `ExportDocumentToPdfAction` rend la même vue Blade que l'éditeur affiche et la convertit via `spatie/browsershot` (Chromium headless réel), jamais `dompdf`/`wkhtmltopdf` (AD-11, FR11)
**And** la position et le rendu des images insérées sont fidèlement conservés — point de fidélité critique, pas secondaire (NFR5)

**Given** l'export réussi
**When** le PDF est généré
**Then** un toast bref "Export PDF généré." s'affiche (UX-DR20)

**Given** un échec d'export (ex. image invalide)
**When** la génération échoue
**Then** un message d'erreur explicite s'affiche, jamais un échec silencieux

### Story 2.5: Exporter un document créé en Word

As a utilisateur,
I want exporter mon document rédigé en `.docx`,
So that je peux le modifier davantage dans un traitement de texte classique si besoin.

**Acceptance Criteria:**

**Given** un document `source=created`
**When** je clique "Exporter en Word" (bouton secondaire — UX-DR11)
**Then** `ExportDocumentToWordAction` utilise `phpoffice/phpword` (import HTML) pour générer le `.docx` (FR12, AD-12)
**And** la fidélité de positionnement des images est un best-effort documenté, pas garanti au niveau du PDF (AD-12) — ce n'est pas un défaut à corriger en priorité

**Given** un échec de positionnement d'image détecté à l'export
**When** le fichier est généré
**Then** un message d'erreur explicite s'affiche, jamais un fichier dégradé livré silencieusement

## Epic 3: Tags illimités, pièces jointes et configuration

Un utilisateur classe et retrouve ses documents par tags illimités (remplace le classement par catégorie de l'Epic 1, retiré et non migré), associe une ou plusieurs pièces jointes à un document créé indépendamment du contenu rédigé, gère ses tags depuis une page de configuration dédiée, et navigue entre 5 surfaces via une sidebar fixe — le lot de consolidation post-lancement v1.1 du sprint-change-proposal, complet en lui-même sans rouvrir l'Epic 1/Epic 2.

### Story 3.1: Classer et retrouver ses documents par tags illimités

As a utilisateur,
I want assigner un ou plusieurs tags à un document (importé ou créé) depuis une liste gérée,
So that je peux structurer et retrouver ma bibliothèque sans les limites d'une catégorie unique.

**Acceptance Criteria:**

**Given** la table `categories` et la colonne `documents.category_id` existent encore (héritage Epic 1)
**When** cette story est implémentée
**Then** elles sont supprimées (`DROP TABLE categories`, `DROP COLUMN documents.category_id`) sans migration des données existantes vers des tags équivalents (décision produit explicite, AD-5) ; `CategorizeDocumentAction`, le modèle/controller `Category` et `CategoryPicker.vue` sont retirés du code, ainsi que tout affichage/filtre de catégorie dans `Index.vue`/`Show.vue`/`ImportModal.vue`

**Given** cette même implémentation
**When** les tables sont créées
**Then** la table `tags` (id, name, à plat, sans hiérarchie) et le pivot `document_tag` (document_id, tag_id) existent, sans limite de nombre de tags par document (AD-5)

**Given** la modale d'import (Story 1.1), la sauvegarde dans l'Éditeur (Story 2.1), ou la Fiche document
**When** j'ouvre le sélecteur de tags
**Then** `TagSelector.vue` (composant unique et partagé) filtre en direct la liste gérée de tags existants ; clic/Entrée ajoute une chip ; aucune saisie libre n'est acceptée, aucune création de tag n'est possible depuis ce champ (UX-DR11, FR2)
**And** laisser le champ vide n'est jamais bloquant — le document reste enregistrable/importable sans tag

**Given** un ou plusieurs tags sélectionnés
**When** j'enregistre (import, sauvegarde éditeur, ou modification depuis la Fiche document)
**Then** `SyncDocumentTagsAction` écrit le pivot `document_tag` en remplacement complet (`sync()`, jamais `attach()`/`detach()` incrémental) ; aucune autre Action n'écrit ce pivot (AD-5, FR3, FR10)

**Given** un document avec des tags assignés
**When** il est affiché sur la Bibliothèque ou la Fiche document
**Then** ses tags s'affichent en chips (`tag-chip`, UX-DR12), visuellement distincts des filtres

**Given** la Bibliothèque affichée
**When** je sélectionne un ou plusieurs tags dans les filtres (chips)
**Then** la liste se filtre en combinant tag(s) et type de fichier (multi-sélection, FR7), les filtres actifs restent visibles et retirables en un clic, réutilisant le point d'entrée de requête unique (AD-8)

**Given** aucun document ne correspond aux tags/type sélectionnés
**When** les résultats s'affichent
**Then** le message "Aucun document ne correspond à ces filtres." apparaît avec suggestion de retirer les filtres actifs (UX-DR19)

**Given** le sélecteur de tags ou les filtres tag
**When** je les utilise au clavier
**Then** ils restent intégralement navigables au clavier, focus visible (UX-DR37)

### Story 3.2: Nouvelle identité visuelle et navigation par sidebar

As a utilisateur,
I want naviguer entre les surfaces d'EkoDoc depuis une sidebar fixe, avec la nouvelle identité visuelle,
So that je retrouve mes repères dans une interface qui reflète le passage à un modèle de classement plus riche (tags, pièces jointes, configuration).

**Acceptance Criteria:**

**Given** l'application EkoDoc existante (palette bleu sobre v1)
**When** cette story est implémentée
**Then** les tokens de couleur `DESIGN.md` v2 sont appliqués partout : neutres chauds beige/brun (jamais blanc/noir pur) et accent unique lime néon `#C6FF00`, chaque couleur avec sa paire clair/sombre (UX-DR1) — aucune validation de contraste WCAG n'est requise sur cette palette (décision produit explicite, projet interne)

**Given** n'importe quelle surface de l'application
**When** elle s'affiche
**Then** une sidebar fixe, toujours visible, jamais repliable ni masquée, liste au minimum l'item "Bibliothèque" (actif en fond lime), avec un toggle thème clair/sombre et le footer "Made with 💔 Claude" en pied de sidebar (UX-DR2, UX-DR3, UX-DR4)

**Given** la Bibliothèque
**When** elle affiche la liste des documents
**Then** chaque document apparaît en Ligne de document (`document-row`, remplace la Carte document v1) — pas de fond au repos, survol `{colors.surface}`, toute la ligne cliquable vers la Fiche document, badge type + titre + chips de tags + date (UX-DR5)

**Given** la sidebar affichée
**When** je la parcours au clavier
**Then** elle est intégralement navigable, focus visible, ordre de tabulation cohérent avec l'ordre de lecture (UX-DR38)

### Story 3.3: Associer des pièces jointes à un document créé

As a utilisateur,
I want attacher un ou plusieurs fichiers (PDF/Word/Excel) à un document que j'ai rédigé, indépendamment du contenu de l'éditeur,
So that je peux fournir la source originale sans la fusionner dans le texte rédigé.

**Acceptance Criteria:**

**Given** la table `document_attachments` n'existe pas encore
**When** cette story est implémentée
**Then** elle est créée (id, document_id, file_path, original_filename, mime_type, `extracted_text`, `extraction_status`, timestamps — AD-17)

**Given** l'Éditeur ouvert sur un document créé
**When** j'ouvre le panneau de pièces jointes (panneau latéral rétractable, distinct du corps WYSIWYG)
**Then** je peux glisser-déposer ou sélectionner un fichier PDF/`.docx`/`.xlsx` (mêmes formats que FR1, NFR4) ; `AttachDocumentFileAction` stocke le fichier (`storage/app/private/documents/{document_id}/attachments/{uuid}.{ext}`, jamais public, AD-7/AD-17) et crée la ligne `document_attachments` (`extraction_status = pending`), sans jamais affecter le `content_html` de l'éditeur (UX-DR8, UX-DR15, FR13)

**Given** un fichier dans un format non supporté déposé dans le panneau
**When** il est rejeté
**Then** un message d'erreur immédiat nomme les formats acceptés, le fichier n'est pas envoyé (UX-DR25)

**Given** une pièce jointe ajoutée
**When** l'extraction de texte tourne (`ExtractDocumentTextJob`, même pipeline que l'import, AD-6/AD-9/AD-17)
**Then** son contenu extrait est agrégé dans `Document::toSearchableArray()` du document parent — pas de ligne Scout séparée — et devient cherchable en fulltexte au même titre qu'un document importé (FR6, FR13), un échec d'extraction laissant `extraction_status = failed` sans jamais bloquer l'attachement

**Given** une pièce jointe listée dans le panneau
**When** je clique "Retirer"
**Then** `DetachDocumentFileAction` supprime le fichier disque et la ligne `document_attachments`, puis déclenche le ré-indexage Scout du document parent

**Given** la Fiche document d'un document créé avec des pièces jointes
**When** je la consulte
**Then** la liste des pièces jointes s'affiche en lecture seule, avec prévisualisation/téléchargement individuel via `DocumentAttachmentController@preview`/`@download` (même discipline qu'AD-7) ; l'ajout/retrait reste réservé au panneau de l'Éditeur (UX-DR16)

**Given** aucune pièce jointe sur un document
**When** le panneau ou la liste s'affiche
**Then** "Aucune pièce jointe." apparaît avec l'action d'ajout, jamais masqué même vide (UX-DR26)

**Given** la suppression complète d'un document via `DeleteDocumentAction`
**When** il possède des pièces jointes
**Then** chaque fichier disque de `document_attachments` est supprimé puis sa ligne, dans l'ordre déjà défini par AD-15 étendue — jamais une simple `cascadeOnDelete()` qui laisserait les fichiers orphelins

### Story 3.4: Rechercher un document sur une surface dédiée

As a utilisateur,
I want rechercher en fulltexte depuis une surface Recherche dédiée,
So that je distingue clairement parcourir ma bibliothèque et chercher un document précis.

**Acceptance Criteria:**

**Given** la sidebar (Story 3.2)
**When** cette story est implémentée
**Then** un item "Recherche" est ajouté à la sidebar, menant à une nouvelle surface dédiée (`Search.vue`) ; la barre de recherche est retirée de la Bibliothèque, qui devient un listing paginé (20/page) sans recherche intégrée (UX-DR6, UX-DR7)

**Given** la surface Recherche ouverte
**When** aucun terme n'est encore saisi
**Then** le focus est directement sur le champ de recherche, aucun résultat n'est affiché (état initial neutre, UX-DR20)

**Given** la surface Recherche
**When** je tape un terme
**Then** les résultats se filtrent en direct avec un debounce sur le contenu des documents et de leurs pièces jointes (FR6, FR13 — pièces jointes indexées depuis la Story 3.3), sans bouton "Rechercher" séparé, et répondent en moins d'1 seconde sur un corpus de l'ordre de 350 documents (NFR2)
**And** je peux affiner avec un filtre tag (pas de filtre type sur cette surface, propre à la Bibliothèque)

**Given** aucun document ne correspond au terme recherché
**When** les résultats s'affichent
**Then** le message "Aucun document ne correspond à votre recherche." apparaît avec suggestion de retirer le filtre tag actif s'il y en a un (UX-DR20)

**Given** n'importe quelle surface
**When** j'appuie sur `/`
**Then** le focus va directement au champ de recherche de la surface Recherche (déplacé depuis la Bibliothèque, UX-DR34)

### Story 3.5: Gérer les tags depuis une page de configuration

As a utilisateur,
I want créer, renommer et supprimer mes tags depuis une page dédiée,
So that je garde un classement propre sans doublons ni tags obsolètes.

**Acceptance Criteria:**

**Given** la sidebar (Story 3.2)
**When** cette story est implémentée
**Then** un item "Configuration" est ajouté à la sidebar, menant à une nouvelle surface (`Configuration.vue`) listant tous les tags existants (UX-DR17, FR14)

**Given** la page Configuration
**When** je saisis le nom d'un nouveau tag
**Then** `CreateTagAction` le crée si le nom (insensible à la casse) n'existe pas déjà ; en cas de doublon (ex. "finance" vs "Finance" existant), le champ signale le doublon avant validation (UX-DR17)

**Given** un tag existant
**When** je clique "Renommer" et saisis un nouveau nom
**Then** `RenameTagAction` met à jour le tag, reflété immédiatement partout où il est affiché (chips, filtres, sélecteur)

**Given** un tag existant utilisé par N documents
**When** je clique "Supprimer"
**Then** une boîte de dialogue de confirmation s'ouvre, nommant le nombre de documents concernés (UX-DR31, UX-DR33)

**Given** la suppression confirmée
**When** `DeleteTagAction` s'exécute
**Then** il détache le tag de tous les documents (suppression des lignes du pivot `document_tag`) sans jamais supprimer les documents eux-mêmes (AD-18, FR14) ; un message factuel confirme "Tag supprimé — détaché de N documents." (UX-DR31, ton direct sans emoji)

**Given** aucun tag n'existe encore
**When** j'ouvre la page Configuration
**Then** "Aucun tag pour l'instant." s'affiche avec l'action "Créer un tag" mise en avant (UX-DR30)

**Given** la page Configuration
**When** je la parcours au clavier
**Then** elle reste intégralement navigable, focus visible, cohérente avec le principe de lisibilité des libellés de tags pour quelqu'un sans connaissance institutionnelle du contenu (UX-DR37, UX-DR39)

### Story 3.6: Corriger l'insertion de tableaux imbriqués dans l'éditeur

As a utilisateur,
I want insérer un tableau dans l'éditeur sans risquer des tableaux imbriqués inexploitables, et pouvoir le supprimer,
So that je peux rédiger une documentation structurée sans devoir recommencer le document.

**Acceptance Criteria:**

**Given** le curseur positionné à l'intérieur d'un tableau déjà inséré dans l'Éditeur (TipTap, FR8)
**When** je clique à nouveau sur "Insérer un tableau"
**Then** aucun tableau imbriqué n'est créé — l'action est soit désactivée soit remplacée par une action cohérente (ex. ajouter une ligne/colonne), jamais un tableau dans une cellule de tableau

**Given** un tableau déjà présent dans le document
**When** je souhaite le supprimer
**Then** une action "Supprimer le tableau" est disponible depuis la barre d'outils ou un contrôle contextuel du tableau, et le retire proprement du contenu sans laisser de structure orpheline

**Given** un document existant qui contient déjà un tableau imbriqué (créé avant ce correctif)
**When** il est rouvert dans l'Éditeur
**Then** son contenu reste chargeable sans erreur (pas de correction rétroactive automatique requise, correctif préventif uniquement)
**Then** un message d'erreur explicite propose de réessayer, jamais un fichier dégradé livré silencieusement (UX-DR20, cohérent avec Flow 3 de l'EXPERIENCE.md)
