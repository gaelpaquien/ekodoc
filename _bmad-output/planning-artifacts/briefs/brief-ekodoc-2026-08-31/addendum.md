---
title: Addendum — EkoDoc
related_brief: brief.md
updated: 2026-08-31
---

# Addendum

Ce document conserve le détail qui ne trouve pas sa place dans le brief mais reste utile pour la suite (architecture, choix techniques).

## Tour d'horizon des outils comparables

**Catégories existantes**
- **Wikis internes** (Confluence, Notion, Outline, BookStack, Wiki.js, GitBook) : optimisés pour l'authoring collaboratif et la consultation, faibles sur le stockage de fichiers hétérogènes.
- **GED / DMS** (SharePoint, Nuxeo, Alfresco, Google Drive/Workspace, M-Files) : optimisés pour le stockage/versioning/permissions de fichiers déposés, plus faibles comme surface d'authoring native.
- **Ponts entre les deux** : cette combinaison (upload + consultation de fichiers Office hétérogènes ET authoring natif avec export propre) reste rare comme produit unique cohérent. SharePoint/Google Workspace s'en approchent le plus architecturalement, mais aucun ne traite authoring "wiki" et dépôt de fichiers comme un seul et même modèle de contenu.

**Comparables en détail**
- **Confluence** : documentation d'équipe structurée, permissions par espaces. Export PDF correct (avec quirks sur macros/tableaux), export Word limité, souvent besoin d'un add-on marketplace. Les fichiers Office déposés restent des pièces jointes qu'on prévisualise, pas du contenu indexé de premier ordre.
- **Notion** : docs flexibles + base légère. Des agents IA (Notion 3.6, 2026) génèrent désormais PDF/Word/PPT/Excel depuis le contenu du workspace. Les fichiers déposés restent des embeds peu indexés ; la fidélité Word sur du contenu complexe reste imparfaite.
- **Outline / BookStack (OSS auto-hébergés)** : wikis markdown rapides. Export PDF correct, export Word faible ou absent (le markdown ne mappe pas proprement vers `.docx`). Pas de véritable expérience de consultation de PDF/Excel déposés.
- **SharePoint (+ Word/Excel Online)** : l'authoring EST Word/Excel/PowerPoint Online, donc l'export Office est parfait par construction. En contrepartie, l'authoring de type "page wiki" est peu pratique et la recherche/l'organisation à travers des types de contenu hétérogènes se complexifie à l'échelle.
- **GitBook** : publication de docs soignée, bon export PDF, export Word et stockage de fichiers non prioritaires — pas conçu pour du stockage documentaire général.

**Fil conducteur** : les wikis (contenu markdown/rich-text) exportent imparfaitement vers Word/PDF car leur modèle de contenu ne mappe pas 1:1 vers Office ; les GED réussissent l'export Office car ce SONT des outils Office, mais leur couche "wiki" est secondaire.

## Pièges techniques à anticiper (si développement interne)

- **Prévisualisation des fichiers Office** (`.docx`/`.xlsx`/`.pptx`) : rendu fidèle en navigateur non trivial, passe généralement par un service de conversion (LibreOffice headless, Gotenberg, Aspose) plutôt que par un renderer maison. Pour le PDF, des libs comme PDF.js couvrent bien le besoin nativement.
- **Export WYSIWYG → Word/PDF** : les éditeurs riches (ProseMirror/Slate/TipTap) mappent rarement proprement vers l'OOXML (`.docx`) — styles, en-têtes/pieds de page, sommaire. L'export PDF via impression navigateur/CSS headless est généralement plus fiable que la génération DOCX.
- **Recherche/indexation** (hors v1, pour plus tard) : nécessite extraction de texte/OCR multi-formats + un index unifié (Elasticsearch/OpenSearch ou vector store pour de la recherche sémantique).
- **Permissions** (hors v1) : réconcilier ACL fichier/dossier (style GED) et permissions page/espace (style wiki) est une source fréquente de complexité si le nombre d'utilisateurs grandit.

## Pistes de librairies open-source à évaluer (non arbitrées)

À explorer au moment de l'architecture/des choix techniques (stack Laravel) :
- Prévisualisation PDF : PDF.js.
- Prévisualisation Word/Excel : conversion via LibreOffice headless / Gotenberg, ou libs JS dédiées (ex. docx-preview, sheetjs pour Excel).
- Génération/export PDF depuis contenu créé dans l'outil : rendu HTML → PDF (ex. wkhtmltopdf, Dompdf côté PHP/Laravel, ou service headless Chromium).
- Génération/export Word : bibliothèques PHP de génération `.docx` (ex. PHPWord) à valider selon la fidélité de mise en forme attendue.

Ces pistes sont indicatives — à confirmer lors du passage à l'architecture (`bmad-architecture`), pas figées ici.

## Idées futures mentionnées mais explicitement hors v1

- Recherche fulltexte et par tags.
- Agent IA branché en MCP pour discuter naturellement avec la base de connaissance et obtenir des réponses appuyées sur les documents.

Ces idées sont notées pour mémoire ; leur pertinence et leur simplicité de mise en œuvre seront réévaluées une fois la v1 en usage.
