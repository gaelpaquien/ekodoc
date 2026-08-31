---
title: PRD EkoDoc
status: final
created: 2026-08-31
updated: 2026-08-31
---

# PRD : EkoDoc

## Contexte

Les documents internes sont aujourd'hui éparpillés, sans point d'accès unique. EkoDoc centralise l'existant (PDF, Word, Excel...) et permet d'en créer de nouveaux directement dans l'outil, exportables ensuite en PDF/Word. Périmètre de départ volontairement restreint : usage **solo**, en local (Laravel Herd), sur un corpus d'environ **350 documents** existants à terme. Voir le brief associé (`../../briefs/brief-ekodoc-2026-08-31/brief.md`) pour le contexte produit complet.

## Parcours type

Deux usages concrets couvrent la v1 :

- **Retrouver et exploiter un document existant** : l'utilisateur ouvre EkoDoc, recherche un document (plutôt que de fouiller manuellement parmi ~350 fichiers), le consulte directement dans le navigateur ou télécharge l'original pour l'envoyer à un collègue.
- **Créer un document pour usage futur ou partage** : l'utilisateur rédige un nouveau document dans l'outil, qui devient disponible pour consultation/recherche future ou pour export et partage.

## Utilisateurs

**V1 : mono-utilisateur.** EkoDoc est utilisé par une seule personne pour l'instant. `[ASSUMPTION]` Pas de gestion de comptes, rôles ou permissions en v1 — un seul utilisateur implicite. L'ouverture à 2-3 collègues (cible du brief) est une évolution ultérieure, conditionnée à la preuve de valeur et à une décision d'infrastructure (accès partagé).

## Exigences fonctionnelles

### Bibliothèque de documents (import & organisation)

- **FR1** — Importer un document existant (PDF, Word `.docx`, Excel `.xlsx`) dans la base.
- **FR2** — Classer les documents (importés et créés) par dossiers et/ou catégories.
- **FR3** — Associer à chaque document des métadonnées de base : titre, type, catégorie/dossier, date d'ajout.

### Consultation & téléchargement

- **FR4** — Prévisualiser un document dans le navigateur (PDF nativement ; Word/Excel via une conversion, voir l'addendum du brief : `../../briefs/brief-ekodoc-2026-08-31/addendum.md` § Pièges techniques à anticiper).
- **FR5** — Télécharger le fichier original en un clic.

### Recherche & filtrage

- **FR6** — Rechercher en fulltexte sur le contenu des documents (importés et créés) — priorité v1 confirmée pour rester exploitable au-delà de quelques dizaines de documents.
- **FR7** — Filtrer les résultats par catégorie/dossier et par type de document (PDF, Word, Excel, document créé dans l'outil).

### Création & export

- **FR8** — Créer un document via un éditeur WYSIWYG (titres, listes, tableaux, images). `[ASSUMPTION]` Pas de système de modèles/templates réutilisables en v1.
- **FR9** — Insérer des images à la volée entre des blocs de texte pendant la rédaction (pas seulement en pièce jointe en fin de document) — usage clé pour de la documentation illustrée.
- **FR10** — Enregistrer un document créé dans la base, avec les mêmes propriétés de classement/recherche qu'un document importé (FR2, FR3, FR6, FR7 s'appliquent aussi aux documents créés).
- **FR11** — Exporter un document créé vers PDF, en conservant fidèlement la position et le rendu des images insérées (FR9).
- **FR12** — Exporter un document créé vers Word (`.docx`), en conservant fidèlement la position et le rendu des images insérées (FR9).

## Exigences non fonctionnelles

- **NFR1** — Fonctionne en local via Laravel Herd, sans dépendance à une infrastructure serveur partagée pour la v1.
- **NFR2** — Recherche fulltexte en moins d'1 seconde sur un corpus de l'ordre de 350 documents (cible indicative, pas un SLA) ; pas d'exigence de performance à plus grande échelle pour la v1.
- **NFR3** — Aucune authentification ni gestion de rôles requise pour la v1 (cohérent avec l'usage mono-utilisateur).
- **NFR4** — Formats supportés en priorité : PDF, `.docx`, `.xlsx`. `[ASSUMPTION]` Les formats hérités (`.doc`, `.xls`) ne sont pas prioritaires sauf besoin identifié en cours de route.
- **NFR5** — La fidélité d'export (WYSIWYG → PDF/Word) doit rester raisonnable pour un usage interne, sans viser une fidélité pixel-perfect — **à l'exception des images inline (FR9)**, dont la position et le rendu dans le texte sont un point de fidélité critique, pas secondaire (voir pièges connus en `addendum.md`).

## Métriques de succès

- Usage quotidien réel constaté (l'outil est effectivement rouvert et utilisé, pas seulement alimenté une fois).
- EkoDoc devient le réflexe pour retrouver ou partager un document, y compris dans un contexte d'onboarding futur.
- **Contre-métrique** : le nombre de documents importés seul n'est pas un signal de succès — un corpus alimenté mais jamais rouvert pour une recherche/consultation indique un échec, pas une réussite.

## Hors scope (v1)

- Agent IA (via MCP) pour interroger la base en langage naturel.
- Gestion multi-utilisateurs, rôles et permissions.
- Hébergement partagé, accès distant ou multi-poste.
- Modèles/templates de documents réutilisables.

## Trajectoire post-v1

Reprise du principe posé dans le brief : chaque étape suivante ne se justifie que si l'étape précédente a prouvé sa valeur réelle — le déclencheur est l'usage constaté, jamais une échéance calendaire fixée à l'avance. Ce principe s'applique aussi bien à l'ouverture à de nouveaux utilisateurs qu'à l'ajout de nouvelles fonctionnalités (y compris l'agent IA listé en hors-scope). Ordre pressenti, sans engagement de calendrier :

1. **V1 (ce PRD)** — usage solo, recherche/filtrage, import/consultation, création/export.
2. **Ouverture à 2-3 collègues** — une fois l'usage quotidien solo prouvé ; déclenche les décisions d'infrastructure/accès (NFR1, NFR3 à revoir).
3. **Agent IA (MCP)** — une fois la base assez riche et utilisée pour qu'une interrogation en langage naturel apporte une valeur réelle par rapport à la recherche fulltexte (FR6) déjà en place.

Pas de sur-ingénierie anticipée : rien de ce qui précède n'est pré-construit "au cas où" dans la v1.

## Questions ouvertes

- Faut-il supporter les formats hérités `.doc`/`.xls`, ou ~350 documents sont-ils déjà en formats modernes `.docx`/`.xlsx` ? — à vérifier en amont de l'architecture.
- Granularité de classement à trancher : dossiers hiérarchiques, catégories à plat, ou les deux (FR2) — laissé ouvert pour la phase architecture/UX.
- Choix technique du moteur de recherche fulltexte (FR6) — plusieurs options possibles selon la stack Laravel, détaillées en `addendum.md`, à arbitrer en architecture.

## Index des assumptions

- `[ASSUMPTION]` § Utilisateurs — pas de gestion de comptes, rôles ou permissions en v1 (un seul utilisateur implicite).
- `[ASSUMPTION]` FR8 — pas de système de modèles/templates réutilisables en v1.
- `[ASSUMPTION]` NFR4 — les formats hérités (`.doc`, `.xls`) ne sont pas prioritaires sauf besoin identifié en cours de route.
