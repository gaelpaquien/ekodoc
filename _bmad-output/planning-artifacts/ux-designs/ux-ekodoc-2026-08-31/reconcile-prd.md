---
title: Réconciliation PRD → UX (DESIGN.md + EXPERIENCE.md)
input: prd-ekodoc-2026-08-31
target: ux-ekodoc-2026-08-31
created: 2026-08-31
---

# Réconciliation PRD → UX — EkoDoc

Vérification que chaque FR (FR1–FR12) et NFR pertinent (NFR1–NFR5) du PRD a une représentation concrète dans `EXPERIENCE.md` (IA, component pattern, state pattern, ou key flow) — pas seulement une citation en passant.

## Mapping FR/NFR → couverture UX

| Exigence | Couverture EXPERIENCE.md | Statut |
|---|---|---|
| FR1 — Importer un document | IA (Import = modale depuis Bibliothèque) ; Component Patterns « Zone d'import » ; State Patterns « Import : format non supporté » ; Interaction Primitives (glisser-déposer + bouton) | Couvert |
| FR2 — Classer par dossiers/catégories | IA (mentionné dans la ligne Bibliothèque) ; Component Patterns « Carte document » (affichage passif de la catégorie/dossier) | **Partiel — voir Gaps** |
| FR3 — Métadonnées de base (titre, type, catégorie/dossier, date) | Component Patterns « Carte document » ; IA (Fiche document) | Couvert |
| FR4 — Prévisualiser dans le navigateur | Component Patterns « Panneau de prévisualisation » ; State Patterns « Conversion de prévisualisation en cours » ; Key Flow 1 (étape 4 + cas d'échec) | Couvert |
| FR5 — Télécharger l'original en un clic | IA (Fiche document) ; Key Flow 1 (climax) ; DESIGN.md bouton secondaire dédié | Couvert |
| FR6 — Recherche fulltexte | Component Patterns « Barre de recherche » (debounce, live) ; Key Flow 1 (étape 2) ; IA | Couvert |
| FR7 — Filtrer par catégorie/type | Component Patterns « Filtres (chips) » ; Key Flow 1 (étape 3) | Couvert |
| FR8 — Éditeur WYSIWYG | IA (surface Éditeur) ; Component Patterns « Barre d'outils éditeur » ; Key Flow 2 (étape 2) | Couvert |
| FR9 — Insertion d'images inline | Component Patterns « Barre d'outils éditeur » ; Interaction Primitives (bouton + glisser-déposer) ; Accessibility Floor (alt text obligatoire) ; Key Flow 2 (étapes 3–4) | Couvert |
| FR10 — Enregistrer un document créé avec les mêmes propriétés qu'un import (FR2/3/6/7) | Voice and Tone (message « Enregistré. ») ; State Patterns « Éditeur : modifications non enregistrées » | **Partiel — voir Gaps** |
| FR11 — Export PDF fidèle | Component Patterns « Bouton Export » ; State Patterns « Export réussi/échoué » ; Key Flow 2 (climax) | Couvert |
| FR12 — Export Word fidèle | Component Patterns « Bouton Export » ; Key Flow 2 (cas d'échec — mapping HTML → .docx) | Couvert |
| NFR1 — Local Laravel Herd, pas d'infra partagée | Foundation (explicite) | Couvert |
| NFR2 — Recherche < 1s sur ~350 documents | Component Patterns « Barre de recherche » (recherche en direct, debounce) — traduction indirecte d'une NFR de perf backend | Couvert (indirect, adéquat) |
| NFR3 — Pas d'authentification/rôles en v1 | Foundation (mono-surface, aucune surface de login/permissions décrite — cohérent par absence) | Couvert (par absence, cohérent) |
| NFR4 — Formats prioritaires PDF/.docx/.xlsx | Component Patterns « Zone d'import » (formats affichés explicitement) ; State Patterns « Import : format non supporté » | Couvert |
| NFR5 — Fidélité d'export des images inline (critique, pas secondaire) | State Patterns « Export réussi/échoué » (référence explicite à NFR5) ; Key Flow 2 climax + cas d'échec (référence explicite à NFR5) ; Key Flow 2 étape 4 (stabilité de mise en page) | Couvert — voir nuance en Gaps |

## Gaps found

- **FR2 (classement par dossiers/catégories) — pas de parcours d'assignation documenté.** `EXPERIENCE.md` décrit le *filtrage* par catégorie/type (FR7, bien couvert) et l'*affichage* de la catégorie/dossier sur la carte document (FR3), mais aucun component pattern, state pattern ou key flow ne montre comment l'utilisateur **assigne ou modifie** la catégorie/dossier d'un document — ni au moment de l'import (la « Zone d'import » ne mentionne que le drag&drop et les formats acceptés, pas de champ catégorie/dossier), ni depuis la Fiche document. C'est un trou silencieux : FR2 est une exigence de classement actif, pas seulement de filtrage passif.
- **FR10 (mêmes propriétés de classement/recherche pour un document créé) — dépend du même trou que FR2.** Le Key Flow 2 (rédaction d'une documentation illustrée) va directement de la rédaction/insertion d'image à l'export, sans jamais montrer où/comment Camille enregistre le document dans une catégorie ou un dossier avant qu'il devienne cherchable/filtrable comme un document importé. Le mécanisme d'enregistrement lui-même n'a qu'un state pattern (« modifications non enregistrées ») mais pas de flow explicite ni de component pattern dédié.
- **NFR5 (fidélité image inline) — bien traité en State Patterns et Key Flows, plus léger en Component Patterns.** Les deux mentions explicites de NFR5 sont correctement ancrées (pas juste citées en passant) : la ligne State Patterns « Export réussi/échoué » et le Key Flow 2 (climax + échec) portent la charge de la démonstration. En revanche, le component pattern « Barre d'outils éditeur » (qui gère l'insertion d'image, FR8/FR9) ne porte aucune règle comportementale reliant explicitement l'expérience d'édition à la garantie de fidélité (ex. : ancrage visuel de l'image au flux de texte pendant l'édition, aperçu avant export). Ce n'est pas un trou silencieux — l'exigence est bien représentée — mais elle repose entièrement sur les Key Flows plutôt que d'être aussi renforcée au niveau des component patterns, ce qui la rend plus fragile si un mockup futur s'appuie surtout sur cette section.
- **NFR2 (recherche < 1s) — pas d'état de chargement de recherche explicite.** Seule la mécanique de debounce est mentionnée ; aucun state pattern ne couvre un éventuel indicateur de recherche en cours. Risque mineur compte tenu de la cible < 1s, mais à noter si l'implémentation s'écarte de la cible indicative.
