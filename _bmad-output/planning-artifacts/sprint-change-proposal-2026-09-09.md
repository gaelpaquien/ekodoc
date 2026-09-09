---
title: Sprint Change Proposal — Epic 3 (Consolidation v1)
status: approved
created: 2026-09-09
---

# Sprint Change Proposal — EkoDoc

## 1. Issue Summary

Après usage réel de la v1 (Epic 1 + Epic 2, tous deux `done`), Gaël remonte une liste de retours couvrant navigation/design, classement des documents, et fonctionnalités de l'éditeur. Ce n'est pas un bug isolé sur une story en cours : c'est un lot de consolidation post-lancement (v1.1) qui reste dans le scope "usage solo" du PRD — aucune trajectoire post-v1 (ouverture collègues, agent IA) n'est déclenchée.

Point notable découvert en cours d'analyse : le retour initial sur les tags ("catégorie + tags") a été révisé par l'utilisateur en cours de session — décision produit finale : **suppression complète du concept de catégorie**, remplacé uniquement par des tags illimités (jugé redondant). Décision explicite : pas de migration des catégories existantes vers des tags équivalents, on repart de zéro sur le tagging.

Autre décision explicite : la cible WCAG 2.2 AA (`UX-DR23`) est conservée telle quelle (navigation clavier, focus visible, texte alternatif obligatoire restent en vigueur), mais **aucune validation de contraste n'est requise** sur la nouvelle palette de couleurs (lime + gris) — projet interne, contrainte explicitement écartée par l'utilisateur pour ce point précis.

## 2. Impact Analysis

### Epic Impact

- Epic 1 et Epic 2 : `done`, non rouverts en tant que tels — mais du code qu'ils ont livré (classement par catégorie) est **retiré** par ce changement, pas juste étendu.
- Nouvel **Epic 3** créé pour porter l'ensemble de ce lot.
- Aucun epic futur (trajectoire post-v1) impacté ni resséquencé.

### Story Impact

Stories déjà `done` dont le comportement change ou dont du code est retiré :
- Story 1.5 (Classer un document par catégorie) — le mécanisme entier disparaît (table `categories`, `category_id`, `CategorizeDocumentAction`, `CategoryPicker.vue`).
- Story 1.7 (Filtrer par catégorie et type) — filtre catégorie retiré, remplacé par filtre tag.
- Story 1.2 (Parcourir la bibliothèque) — affichage carte + barre de recherche intégrée retirés (page dédiée + listing simple paginé).
- Story 2.1 (Créer et enregistrer) — sélecteur catégorie/dossier à l'enregistrement devient sélecteur de tags.

Aucune de ces stories n'est rouverte individuellement — leur remplacement est porté par les nouvelles stories de l'Epic 3.

### Artifact Conflicts

**PRD** — FR2, FR3, FR7 **remplacées** (pas ajoutées) ; FR13, FR14 nouvelles. Question ouverte sur la granularité de classement retirée (devenue sans objet).

**Architecture** — AD-5 remplacée (catégorie → tags), AD-16 retirée, AD-17 et AD-18 nouvelles (pièces jointes, page Configuration). Voir détail § 4.

**UX** — DESIGN.md (tokens couleur, principe "pas de sidebar") et EXPERIENCE.md (Information Architecture, Component Patterns, Key Flows) nécessitent une révision substantielle — traité comme un work-order pour une session `bmad-ux` dédiée plutôt que des edits mécaniques ici, vu l'ampleur des décisions de design impliquées (remplacement de l'affichage carte, nouveau pattern de sélection de tags, etc.).

### Technical Impact

- Migration DB : `DROP TABLE categories`, `DROP COLUMN documents.category_id` (pas de rétromigration de données, décision explicite).
- Nouvelles tables : `tags`, `document_tag` (pivot), `document_attachments`.
- Code à retirer : `Category.php`, `CategoryController.php`, `Actions/Category/*`, `CategorizeDocumentAction`, `CategoryPicker.vue`, badges/filtres catégorie dans `Index.vue`/`Show.vue`/`ImportModal.vue`, tests Pest associés.
- Code à ajouter : `Tag.php`, `TagController.php`, `Actions/Tag/*` (create/rename/delete), `AttachDocumentFileAction`/`DetachDocumentFileAction`, page Configuration (Inertia), sélecteur de tags (Vue), page Recherche dédiée, sidebar de navigation, nouveaux tokens couleur.
- `DeleteDocumentAction` (AD-15) étendue pour nettoyer aussi les pièces jointes.

## 3. Recommended Approach

**Option 1 (Ajustement direct), élargi** — pas de rollback, pas de révision du MVP (toujours entièrement atteint). Nécessite une mise à jour PRD + Architecture avant découpage en stories, et une session UX dédiée pour le volet design.

- Effort : Moyen (mise à jour de 3 documents de planification + epic de retrait/ajout substantiel)
- Risque : Moyen (retrait de code en production — catégorie — plus que son extension ; nouveau modèle de données bien maîtrisé côté Laravel)
- Aucune alternative (rollback / réduction de MVP) ne s'applique : rien à annuler, le MVP reste acquis.

## 4. Detailed Change Proposals

### PRD (`prd-ekodoc-2026-08-31/prd.md`)

```
OLD FR2: Classer les documents (importés et créés) par dossiers et/ou catégories.
NEW FR2: Classer les documents (importés et créés) par tags illimités, choisis
         dans une liste gérée (pas de texte libre).

OLD FR3: Associer à chaque document des métadonnées de base : titre, type,
         catégorie/dossier, date d'ajout.
NEW FR3: Associer à chaque document des métadonnées de base : titre, type,
         tags, date d'ajout.

OLD FR7: Filtrer les résultats par catégorie/dossier et par type de document
         (PDF, Word, Excel, document créé dans l'outil).
NEW FR7: Filtrer les résultats par tag et par type de document (PDF, Word,
         Excel, document créé dans l'outil).

NEW FR13: Associer un ou plusieurs fichiers (PDF, Word, Excel) à un document
          créé, indépendamment du contenu rédigé dans l'éditeur WYSIWYG (FR8).
NEW FR14: Gérer les tags (créer, renommer, supprimer) depuis une page de
          configuration dédiée.
```

Section "Questions ouvertes" : retirer *"Granularité de classement à trancher..."* (sans objet).

### Architecture (`architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md`)

```
OLD AD-5 — Classement à plat, une catégorie par document [ADOPTED]
Rule: table `categories` (id, name) à plat, sans parent. `documents.category_id`
nullable (NULL = "Non classé"). Pas de relation many-to-many, pas de hiérarchie.

NEW AD-5 — Classement à plat, tags illimités par document [AMENDED 2026-09-09]
Binds: FR2, FR7, FR10
Prevents: une UI qui suppose une hiérarchie de tags pendant qu'un autre module
suppose un tag plat ; un mélange tag/texte libre non contrôlé (doublons du
type "Finance"/"finance"/"Finances").
Rule: table `tags` (id, name) à plat, sans hiérarchie, sans parent. Relation
many-to-many via pivot `document_tag` (document_id, tag_id). Pas de limite de
nombre de tags par document. Tags gérés exclusivement depuis la page
Configuration (FR14) — jamais de création à la volée ailleurs.
Changelog: remplace l'AD-5 v1 (catégorie unique) et retire AD-16
("category_id : un seul point d'écriture"). Table `categories`, colonne
`documents.category_id`, `CategorizeDocumentAction`, `Category`
model/controller sont supprimés — pas dépréciés. Pas de migration des
données existantes (décision explicite du 2026-09-09).

OLD AD-16 — category_id : un seul point d'écriture [ADOPTED]
NEW: [REMOVED 2026-09-09 — voir changelog AD-5]

NEW AD-17 — Pièces jointes sur document créé : disque privé, table dédiée
Binds: FR13
Rule: nouvelle table `document_attachments` (id, document_id, file_path,
original_filename, mime_type, timestamps). Fichiers sur
storage/app/private/documents/{document_id}/attachments/{uuid}.{ext},
jamais public — même discipline qu'AD-7. `AttachDocumentFileAction`/
`DetachDocumentFileAction` comme points d'entrée uniques (AD-2).
`DeleteDocumentAction` (AD-15) est étendue pour nettoyer aussi ces fichiers,
dans le même ordre.

NEW AD-18 — Page Configuration : gestion des tags
Binds: FR14
Rule: `TagController` expose une page Inertia listant les tags avec
create/rename/delete. `DeleteTagAction` détache le tag de tous les documents
(delete des lignes du pivot) sans jamais supprimer les documents eux-mêmes —
même discipline que l'ancien DeleteCategoryAction (désormais retiré).
```

Capability → Architecture Map : retirer les lignes catégorie (FR2/FR10) ; ajouter FR2/FR7/FR10 (Tags), FR13 (Pièces jointes), FR14 (Configuration).

Structural Seed : retirer `Category.php`, `CategoryController.php`, `Actions/Category/` ; ajouter `Tag.php`, `TagController.php`, `Actions/Tag/`, tables `document_tag` et `document_attachments`, dossier `documents/{id}/attachments/`.

### UX (`ux-ekodoc-2026-08-31/DESIGN.md` + `EXPERIENCE.md`) — work-order pour session `bmad-ux` dédiée

**DESIGN.md**
- Tokens couleur : fonds gris clair/gris foncé (jamais totalement blanc/noir) ; accent lime au lieu de bleu. **Aucune validation de contraste WCAG requise** (décision explicite 2026-09-09) — seule la cible WCAG 2.2 AA générale (`UX-DR23`, hors contraste couleur) reste en vigueur.
- Principe *"pas de sidebar de navigation complexe — 3 surfaces"* à amender : 5 surfaces désormais (Bibliothèque, Fiche document, Éditeur, Recherche, Configuration) justifient la sidebar.
- `document-card` : remplacement à explorer. `category-selector` retiré, remplacé par un sélecteur de tags multi-select.

**EXPERIENCE.md**
- Information Architecture : ajouter Recherche (dédiée) et Configuration ; réviser Bibliothèque (listing paginé par 20, sans barre de recherche intégrée).
- Component Patterns : retirer "Sélecteur catégorie/dossier", ajouter "Sélecteur de tags", sidebar, toggle thème, footer "Made with 💔 Claude", upload de pièces jointes.
- Key Flows 1, 2, 3 à réécrire (catégorie → tags ; recherche → page dédiée).
- `UX-DR23` (cible WCAG 2.2 AA) conservée intégralement — navigation clavier, focus visible, texte alternatif obligatoire restent en vigueur.

### Hors artefacts de planification (bug, pas un changement de scope)

Bug TipTap : clics répétés sur "insérer tableau" créent des tableaux imbriqués inexploitables ; aucun moyen de supprimer un tableau. Traité directement comme story de correction dans l'Epic 3, sans impact PRD/Architecture (FR8 le couvre déjà).

## 5. Implementation Handoff

- **PRD + Architecture (Major)** → `bmad-prd` (mode update) puis `bmad-architecture` (mode update), pour formaliser les éditions ci-dessus dans les documents sources.
- **UX (Major)** → `bmad-ux`, session dédiée pour trancher la palette finale, le remplacement de l'affichage carte, et réviser `EXPERIENCE.md` en détail.
- **Epic 3 (Moderate)** → `bmad-create-epics-and-stories`, une fois PRD/Architecture/UX à jour.
- **Sprint tracking** → `bmad-sprint-planning` pour intégrer l'Epic 3 dans `sprint-status.yaml`.

**Succès** : PRD/Architecture/UX cohérents entre eux et avec le code cible, Epic 3 découpé en stories implémentables sans décision non enregistrée, catégorie proprement retirée (DB + code + tests), aucune régression sur Epic 1/Epic 2 hors du périmètre de ce changement.
