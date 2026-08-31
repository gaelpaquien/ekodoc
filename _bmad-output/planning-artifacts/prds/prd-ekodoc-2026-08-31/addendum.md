---
title: Addendum — PRD EkoDoc
related_prd: prd.md
updated: 2026-08-31
---

# Addendum

Détail technique-how et rationale qui n'a pas sa place dans le corps du PRD, à trancher en architecture.

## Fidélité d'export des images inline (FR9, FR11, FR12)

Point remonté explicitement par l'utilisateur : les images insérées à la volée entre deux blocs de texte doivent rester à leur place dans les exports PDF/Word — c'est un usage clé pour de la documentation illustrée, pas un détail cosmétique.

Implication technique à considérer en architecture :
- Pour l'**export PDF**, une approche « rendu HTML → PDF » (ex. Dompdf côté Laravel, ou un moteur headless comme Gotenberg/Chromium) gère nativement le positionnement des images inline, contrairement à une génération PDF bas niveau construite bloc par bloc.
- Pour l'**export Word (`.docx`)**, des bibliothèques comme PHPWord supportent l'insertion d'images à une position donnée dans le document — à valider concrètement avec le contenu produit par l'éditeur WYSIWYG choisi (le mapping HTML de l'éditeur → structure PHPWord doit préserver l'ordre texte/image).
- Recommandation de vigilance : tester ce chemin (upload image → sauvegarde → export PDF et Word) tôt dans l'implémentation, avant de construire le reste de l'éditeur autour — c'est le risque de fidélité le plus concret identifié à ce stade.

## Options de recherche fulltexte (FR6)

La recherche fulltexte passe en scope v1 (voir `.memlog.md`, override du 2026-08-31). Options envisageables dans un contexte Laravel, corpus ~350 documents, usage local mono-utilisateur — à arbitrer en architecture, aucune n'est tranchée ici :
- **SQLite FTS5** : simple à mettre en place en local (pas de service externe), largement suffisant à cette échelle.
- **Laravel Scout + driver database** : intégration Laravel idiomatique, reste léger.
- **Meilisearch (via Scout)** : plus de fonctionnalités (tolérance aux fautes, pertinence) mais ajoute un service à faire tourner en local — à peser vu la contrainte de rester simple (Herd, pas d'infra additionnelle).

À ce stade, la contrainte dominante est de **ne pas ajouter de service lourd** pour un usage solo local — SQLite FTS5 ou Scout/database semblent les mieux alignés, Meilisearch reste une option si le besoin de pertinence de recherche se révèle insuffisant.

## Reprise du tour d'horizon du brief

Le brief (`../../briefs/brief-ekodoc-2026-08-31/brief.md` et son addendum) reste la référence pour : comparables du marché (Confluence, Notion, SharePoint...), pièges de prévisualisation Office, et pistes de librairies pour la prévisualisation PDF/Word/Excel. Non dupliqué ici.
