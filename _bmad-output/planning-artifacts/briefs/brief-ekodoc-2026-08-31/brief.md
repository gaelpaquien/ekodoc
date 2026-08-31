---
title: Product Brief EkoDoc
status: draft
created: 2026-08-31
updated: 2026-08-31
---

# Product Brief : EkoDoc

## Résumé exécutif

EkoDoc est une base de connaissance interne qui réunit en un seul endroit les documents existants (PDF, Word, Excel...) et permet d'en créer de nouveaux directement dans l'outil, exportables ensuite au format PDF ou Word. Aujourd'hui, la documentation interne est éparpillée un peu partout — aucun outil ne centralise l'existant ni ne facilite la création de nouveaux contenus. EkoDoc démarre volontairement petit : un outil local, pour une poignée d'utilisateurs, dont la vocation est de prouver son utilité avant d'envisager toute évolution.

## Le problème

Les documents internes sont dispersés (fichiers locaux, échanges, dossiers divers), sans point d'accès unique. Résultat : on ne sait pas toujours où chercher, on retrouve difficilement un document existant, et créer un nouveau document ne s'inscrit dans aucun processus ni aucun endroit de référence. C'est particulièrement sensible pour un nouvel arrivant, qui n'a aucun point d'entrée pour découvrir la documentation de l'équipe.

## La solution

EkoDoc offre deux usages complémentaires dès la première version :

- **Déposer et consulter** les documents existants (PDF, Word, Excel...) : import dans l'outil, prévisualisation rapide dans le navigateur, et téléchargement du fichier original à tout moment.
- **Créer et exporter** des documents directement depuis l'outil, avec export vers PDF et Word pour pouvoir les partager ou les utiliser hors de l'outil.

`[ASSUMPTION]` La v1 tourne en local sur le poste de l'utilisateur via Laravel Herd, sans se préoccuper pour l'instant d'hébergement partagé, d'authentification multi-utilisateurs ou d'accès distant — ces questions seront traitées si et quand l'outil doit grandir au-delà d'un usage local.

## Pourquoi un outil dédié plutôt qu'un existant

Un tour d'horizon rapide des outils du marché (wikis internes comme Confluence, Notion, Outline ; GED comme SharePoint, Nuxeo) montre qu'aucun ne couvre nativement les deux besoins à la fois : les wikis gèrent bien l'authoring mais traitent les fichiers Office déposés comme de simples pièces jointes peu exploitables, et l'export vers Word y est souvent limité ; les GED gèrent très bien les fichiers Office mais leur couche « wiki »/authoring reste secondaire. Vu le périmètre volontairement restreint (2-3 utilisateurs, besoins précis), un petit outil sur-mesure permet de coller exactement à l'usage réel sans le poids ni le coût d'une plateforme généraliste. Détails de ce tour d'horizon en annexe (`addendum.md`).

## À qui ça s'adresse

Utilisateurs initiaux : un petit groupe de 2 à 3 personnes en interne, qui alimentent et consultent la base au quotidien. Cas d'usage clé : un nouvel arrivant doit pouvoir trouver rapidement la documentation pertinente sans avoir à demander où chercher.

## Critères de réussite

- Usage quotidien réel par les 2-3 utilisateurs initiaux (pas juste un dépôt ponctuel de documents qu'on ne rouvre jamais).
- L'outil devient le réflexe pour l'onboarding d'un nouvel arrivant : on le pointe vers EkoDoc plutôt que vers des fichiers épars.
- `[ASSUMPTION]` Ce succès d'usage est le signal qui déclenche la décision de faire évoluer l'outil (nouveaux utilisateurs, nouvelles fonctionnalités) — pas une échéance fixée à l'avance.

## Périmètre

**Dans la v1 :**
- Import de documents existants (PDF, Word, Excel) avec prévisualisation dans le navigateur et téléchargement du fichier original.
- Création de documents directement dans l'outil.
- Export des documents créés vers PDF et Word.
- Organisation minimale des documents (de quoi retrouver un document sans recherche avancée).

**Explicitement hors v1 :**
- Recherche fulltexte et par tags — idée à valider plus tard selon sa pertinence et sa simplicité de mise en œuvre.
- Agent IA (via MCP) permettant d'interroger la base en langage naturel — idée à explorer plus tard, non prioritaire.
- Authentification/permissions avancées, hébergement partagé, accès multi-postes ou distant.

`[ASSUMPTION]` L'usage de librairies ou modules open-source existants est privilégié dès que cela fait gagner du temps sans imposer de contrainte lourde sur la stack (Laravel), en particulier pour la prévisualisation des fichiers Office et la génération d'export PDF/Word.

## Vision

Si l'usage prouve sa valeur, EkoDoc peut évoluer progressivement : recherche fulltexte et par tags pour naviguer dans un volume de documents grandissant, puis un agent IA capable de répondre en s'appuyant sur le contenu de la base (via MCP), et potentiellement l'ouverture à plus d'utilisateurs. Chaque étape ne se justifie que si l'étape précédente a démontré son utilité réelle — pas de sur-ingénierie anticipée.
