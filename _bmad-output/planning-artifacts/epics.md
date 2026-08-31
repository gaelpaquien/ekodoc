---
stepsCompleted: [step-01, step-02, step-03]
inputDocuments:
  - _bmad-output/planning-artifacts/prds/prd-ekodoc-2026-08-31/prd.md
  - _bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md
  - _bmad-output/planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/DESIGN.md
  - _bmad-output/planning-artifacts/ux-designs/ux-ekodoc-2026-08-31/EXPERIENCE.md
---

# EkoDoc - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for EkoDoc, decomposing the requirements from the PRD, UX Design (DESIGN.md/EXPERIENCE.md), and Architecture Spine into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: Importer un document existant (PDF, Word `.docx`, Excel `.xlsx`) dans la base.
FR2: Classer les documents (importés et créés) par dossiers et/ou catégories.
FR3: Associer à chaque document des métadonnées de base : titre, type, catégorie/dossier, date d'ajout.
FR4: Prévisualiser un document dans le navigateur (PDF nativement ; Word/Excel via une conversion).
FR5: Télécharger le fichier original en un clic.
FR6: Rechercher en fulltexte sur le contenu des documents (importés et créés).
FR7: Filtrer les résultats par catégorie/dossier et par type de document (PDF, Word, Excel, document créé).
FR8: Créer un document via un éditeur WYSIWYG (titres, listes, tableaux, images).
FR9: Insérer des images à la volée entre des blocs de texte pendant la rédaction (pas seulement en pièce jointe finale).
FR10: Enregistrer un document créé dans la base, avec les mêmes propriétés de classement/recherche qu'un document importé (FR2, FR3, FR6, FR7 s'appliquent aussi aux documents créés).
FR11: Exporter un document créé vers PDF, en conservant fidèlement la position et le rendu des images insérées (FR9).
FR12: Exporter un document créé vers Word (`.docx`), en conservant fidèlement la position et le rendu des images insérées (FR9).

### NonFunctional Requirements

NFR1: Fonctionne en local via Laravel Herd, sans dépendance à une infrastructure serveur partagée pour la v1.
NFR2: Recherche fulltexte en moins d'1 seconde sur un corpus de l'ordre de 350 documents (cible indicative, pas un SLA).
NFR3: Aucune authentification ni gestion de rôles requise pour la v1 (usage mono-utilisateur).
NFR4: Formats supportés en priorité : PDF, `.docx`, `.xlsx` (formats hérités `.doc`/`.xls` non prioritaires en v1).
NFR5: La fidélité d'export (WYSIWYG → PDF/Word) doit rester raisonnable sans viser le pixel-perfect — à l'exception des images inline (FR9), dont la position/rendu est un point de fidélité critique (particulièrement garanti pour le PDF, cf. AD-11/AD-12).

### Additional Requirements

**Aucun starter/scaffolding pré-construit.** Installation Laravel 13 fraîche, configurée manuellement avec Inertia.js 3.0 + Vue 3 + Vite 8.x + Tailwind CSS 4.x (pas de Breeze/Jetstream, aucun scaffolding d'authentification — cohérent avec NFR3). Ceci est le point de départ de l'Epic 1 / Story 1.

- Paradigme obligatoire sur tout le code : Thin Controller → Action → DTO → Eloquent Model (AD-1 à AD-3). Controllers sans logique métier ; Actions PHP pures invokables (`__invoke`), une opération métier nommée par Action ; DTO `readonly` PHP natif en frontière de chaque Action (jamais un array/Request direct) ; pas de repository.
- Modèle de données unifié : une seule table `documents` avec colonne discriminante `source` (`imported`|`created`) — pas deux tables séparées (AD-4).
- Classement à plat : table `categories` (id, name) sans hiérarchie ; `documents.category_id` nullable, `NULL` = "Non classé", jamais bloquant (AD-5). `CategorizeDocumentAction` est l'unique point d'écriture de `category_id` (AD-16).
- Traitement d'import synchrone dans la même requête HTTP : stockage → extraction de texte → création du `Document` avec `extracted_text` renseigné ; aucune file d'attente en v1 (AD-6).
- Fichiers originaux stockés sur disque privé Laravel (`storage/app/private/documents/{document_id}/{filename}`), jamais `public` ; téléchargement toujours via route applicative streamée (AD-7).
- Recherche et filtrage unifiés via Laravel Scout (driver `database`) sur le champ `extracted_text`, un seul point d'entrée de requête (recherche + filtres), jamais deux chemins divergents (AD-8).
- Extraction de texte best-effort à l'import (`smalot/pdfparser` pour PDF, `phpoffice/phpword`/`phpoffice/phpspreadsheet` pour Word/Excel) : un échec n'interrompt jamais l'import, seulement `extracted_text = NULL` (pas d'OCR en v1). Pour les documents créés, `extracted_text` est dérivé automatiquement de `content_html` à chaque sauvegarde (AD-9).
- Prévisualisation Office (Word/Excel importés) via conversion à la demande `soffice --headless --convert-to pdf` (LibreOffice CLI), résultat mis en cache sur `storage/app/private/previews/{document_id}.pdf`, invalidé uniquement par suppression/ré-import. PDF natif et documents créés : rendu direct, jamais de conversion, pas de PDF.js (AD-10).
- Export PDF via rendu HTML réel : `spatie/browsershot` (Chromium headless) sur la même vue Blade que l'éditeur affiche — jamais de génération PDF alternative type dompdf/wkhtmltopdf (AD-11).
- Export Word via `phpoffice/phpword` (import HTML) — fidélité best-effort documentée, pas garantie au niveau du PDF ; un échec de positionnement est un message d'erreur explicite, jamais un fichier dégradé livré silencieusement (AD-12).
- Aucune couche API JSON : toutes les routes rendent des pages Inertia ou redirigent après mutation ; pas de `Route::apiResource` ni `response()->json()` (AD-13).
- Images insérées dans l'éditeur stockées en fichiers (`storage/app/private/documents/{document_id}/images/{uuid}.{ext}`) servies via route dédiée, jamais en base64 inline dans `content_html` ; texte alternatif obligatoire capturé à l'insertion (AD-14).
- Suppression définitive uniquement (pas de `SoftDeletes`) ; `DeleteDocumentAction` unique point d'entrée, nettoie dans l'ordre : index Scout → fichier original + images → cache de prévisualisation → ligne `Document` (AD-15).
- Naming anglais uniquement partout (code, classes, logs) ; pas de valeurs magiques (enums/constantes pour `source`, types de fichiers, statuts).
- Couverture de tests Pest à 100 %, vérifiée manuellement (`pest --coverage`) avant chaque commit — pas de pipeline CI en v1.
- Dates en `timestamps` Eloquent standard (UTC en base, formatage français côté Vue) ; IDs entiers auto-incrémentés (pas d'UUID).
- Erreurs métier (extraction, conversion, export) toujours renvoyées comme message explicite côté Inertia, jamais une exception non catchée ; logs applicatifs Laravel standard (canal `stack`), pas de service externe.
- Un controller par ressource (`DocumentController`, `CategoryController`), méthodes RESTful standard (`index`, `store`, `show`, `update`, `destroy`).
- Stack complète à installer/configurer : PHP 8.5, Laravel 13, MySQL 8.x (via Herd), Inertia.js 3.0, Vue 3, Vite 8.x, Tailwind CSS 4.x, TipTap 3.x (`@tiptap/vue-3`), Laravel Scout, spatie/browsershot 5.4, phpoffice/phpword 1.4.0, phpoffice/phpspreadsheet, smalot/pdfparser 2.12.5, LibreOffice (headless CLI), Pest 5.x — environnement Laravel Herd (édition gratuite), local uniquement.

### UX Design Requirements

UX-DR1: Système de tokens de design (couleurs, typographie, arrondis, espacements) implémenté selon `DESIGN.md` — chaque couleur a une paire clair/sombre ; discipline "un seul accent" (pas de code couleur par type de fichier).
UX-DR2: Mode clair/sombre disponible dès la v1, bascule manuelle et respect de la préférence système par défaut.
UX-DR3: Composant Carte document — toute la carte cliquable vers la Fiche document (pas de menu contextuel en v1), affiche badge de type, titre, catégorie/dossier, date d'ajout.
UX-DR4: Composant Barre de recherche — recherche fulltexte en direct avec debounce, pas de bouton "Rechercher" séparé.
UX-DR5: Composant Filtres (chips) — multi-sélection combinée catégorie + type, filtres actifs visibles et retirables en un clic.
UX-DR6: Composant Zone d'import — glisser-déposer ou sélection de fichier, bordure pointillée, formats acceptés affichés explicitement, erreur de format claire et immédiate (pas de rejet silencieux).
UX-DR7: Composant Panneau de prévisualisation — PDF natif perçu comme quasi instantané (aucun traitement) ; Word/Excel via conversion avec état de chargement explicite.
UX-DR8: Composant Barre d'outils éditeur — mise en forme (titres, listes, tableaux) + bouton "Insérer une image" qui insère à la position du curseur entre deux blocs de texte ; glisser-déposer d'image dans le corps du texte également accepté.
UX-DR9: Composant Sélecteur catégorie/dossier — champ optionnel présent dans 3 contextes identiques (modale Import, sauvegarde Éditeur, Fiche document), modifiable sans limite de temps, vide = "Non classé" jamais bloquant.
UX-DR10: Action Enregistrer (éditeur) — déclenche le sélecteur catégorie/dossier si non renseigné, puis intègre le document à la Bibliothèque avec les mêmes propriétés de classement/recherche qu'un document importé.
UX-DR11: Boutons Export — deux actions distinctes toujours visibles, jamais dans un menu caché : "Exporter en PDF" (style bouton primaire) et "Exporter en Word" (style bouton secondaire).
UX-DR12: État Bibliothèque vide (premier lancement) — message "Aucun document pour l'instant." + bouton primaire "Importer" ou "Créer un document".
UX-DR13: État Aucun résultat de recherche/filtre — message explicite + suggestion de retirer les filtres actifs.
UX-DR14: État Conversion de prévisualisation en cours (Word/Excel) — indicateur de chargement explicite dans le panneau ; reste de la fiche document utilisable pendant ce temps.
UX-DR15: État Fichier source introuvable — message explicite dans le panneau de prévisualisation, bouton "Télécharger" désactivé (jamais un échec silencieux).
UX-DR16: État Conversion de prévisualisation échouée — message clair "Aperçu indisponible pour ce fichier", bouton "Télécharger" reste actif.
UX-DR17: État Import format non supporté — message d'erreur immédiat nommant les formats acceptés, fichier non envoyé, modale reste ouverte.
UX-DR18: État Chargement d'un document existant dans l'Éditeur — contenu chargé dans le WYSIWYG avant que l'édition soit possible, indicateur bref si perceptible.
UX-DR19: État Modifications non enregistrées (Éditeur) — indicateur discret + confirmation avant de quitter si non enregistré.
UX-DR20: État Export réussi/échoué — toast bref en succès, message d'erreur explicite en échec, jamais un échec silencieux.
UX-DR21: Interaction — suppression de document toujours confirmée par boîte de dialogue (pas d'undo en v1).
UX-DR22: Interaction — raccourci `/` donne le focus à la barre de recherche depuis la Bibliothèque.
UX-DR23: Accessibilité — cible WCAG 2.2 AA sur l'ensemble de la surface web.
UX-DR24: Accessibilité — texte alternatif obligatoire à la saisie pour toute image insérée dans l'éditeur.
UX-DR25: Accessibilité — formulaires (import, filtres, éditeur) intégralement navigables au clavier, focus visible partout, ordre de tabulation cohérent avec l'ordre de lecture.
UX-DR26: IA — les libellés de catégorie/dossier et la structure de la Bibliothèque doivent rester lisibles pour quelqu'un sans connaissance institutionnelle du contenu (principe de conception dès la v1, même sans flow dédié).
UX-DR27: IA — l'import n'est pas une surface séparée : c'est une modale ouverte depuis la Bibliothèque qui se referme sur la Fiche document du fichier importé.
UX-DR28: Voix et ton — ton direct, factuel, sans emoji ni ton "fun" (cohérent avec un outil de travail interne) ; sauvegardes silencieuses sans pop-up intrusive.

### FR Coverage Map

FR1: Epic 1 - Import de documents existants
FR2: Epic 1 - Classification par catégorie
FR3: Epic 1 - Métadonnées de base
FR4: Epic 1 - Prévisualisation navigateur
FR5: Epic 1 - Téléchargement de l'original
FR6: Epic 1 - Recherche fulltexte
FR7: Epic 1 - Filtrage par catégorie/type
FR8: Epic 2 - Éditeur WYSIWYG
FR9: Epic 2 - Images inline
FR10: Epic 2 - Enregistrement classé/cherchable
FR11: Epic 2 - Export PDF
FR12: Epic 2 - Export Word

## Epic List

### Epic 1: Bibliothèque de documents — importer, classer, retrouver
Un utilisateur peut importer ses documents existants (PDF/Word/Excel), les classer par catégorie, les prévisualiser/télécharger, et les retrouver par recherche fulltexte ou filtres.
**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7

### Epic 2: Création et export de documents
Un utilisateur peut rédiger un nouveau document dans un éditeur WYSIWYG, y insérer des images à la volée, l'enregistrer dans la bibliothèque avec les mêmes propriétés de classement/recherche qu'un import, puis l'exporter en PDF ou Word.
**FRs covered:** FR8, FR9, FR10, FR11, FR12

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
**Then** un message d'erreur explicite propose de réessayer, jamais un fichier dégradé livré silencieusement (UX-DR20, cohérent avec Flow 3 de l'EXPERIENCE.md)
