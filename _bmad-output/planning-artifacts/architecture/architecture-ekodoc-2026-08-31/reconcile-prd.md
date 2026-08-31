---
title: Réconciliation PRD ↔ Architecture Spine — EkoDoc
status: draft
created: 2026-08-31
sources:
  - '../../prds/prd-ekodoc-2026-08-31/prd.md'
  - './ARCHITECTURE-SPINE.md'
---

# Réconciliation PRD ↔ Architecture Spine — EkoDoc

Vérification que chaque FR/NFR du PRD a un point d'ancrage concret dans la spine (AD, Consistency Convention, Stack, Structural Seed, ou ligne de la Capability → Architecture Map), et que les trois questions ouvertes du PRD ont réellement été tranchées.

## Table de couverture

| Exigence | Couverture (spine) | Statut |
| --- | --- | --- |
| **FR1** — Importer PDF/Word/Excel | AD-6 (import synchrone), AD-7 (stockage privé), AD-9 (extraction best-effort) ; ligne Capability Map FR1 | ✅ Couvert |
| **FR2** — Classer par dossiers et/ou catégories | AD-5 (classement à plat, une catégorie par document, `category_id` nullable) ; ligne Capability Map FR2/FR10 | ⚠️ Couvert mais restreint — voir Gap 3 |
| **FR3** — Métadonnées (titre, type, catégorie/dossier, date d'ajout) | `Models/Document` cité (AD-4, Structural Seed) ; `category_id` (AD-5) ; `timestamps` Eloquent standard (Consistency Convention "Data & formats") | ⚠️ Couvert partiellement — aucune colonne `title` explicitement nommée nulle part dans la spine (contrairement à `file_path`, `mime_type`, `content_html`, `source`, `category_id`) |
| **FR4** — Prévisualiser dans le navigateur | AD-10 (conversion Office à la demande, cache) ; ligne Capability Map FR4 | ✅ Couvert |
| **FR5** — Télécharger l'original | AD-7 (`Storage::download()`) ; ligne Capability Map FR5 | ✅ Couvert |
| **FR6** — Recherche fulltexte | AD-8 (Scout + driver `database`), AD-9 (champ `extracted_text`) ; ligne Capability Map FR6/FR7 | ✅ Couvert — résout aussi la question ouverte "moteur de recherche" |
| **FR7** — Filtrer par catégorie et type | AD-8 (Binds inclut FR7) ; ligne Capability Map FR6/FR7 pointe vers `DocumentController@index` | ⚠️ Couvert au niveau "home" mais aucune AD ne décrit le mécanisme de filtrage lui-même (scope de requête, paramètres) — acceptable à ce niveau de granularité mais plus faible que FR6 |
| **FR8** — Éditeur WYSIWYG (TipTap) | Stack (TipTap 3.x) ; ligne Capability Map FR8/FR9 → `Editor.vue` | ✅ Couvert |
| **FR9** — Insertion d'images à la volée | Ligne Capability Map FR8/FR9 (gouvernée par "Stack" seulement, aucune AD dédiée) | ❌ Gap réel — voir Gap 1 |
| **FR10** — Enregistrer un document créé avec mêmes propriétés qu'un import | AD-4 (table unifiée `documents`, colonne `source`), AD-5 (Binds FR10) ; ligne Capability Map FR2/FR10 | ✅ Couvert |
| **FR11** — Export PDF fidèle aux images | AD-11 (Browsershot, rendu HTML réel) ; ligne Capability Map FR11 | ✅ Couvert — résolution forte et cohérente avec NFR5 |
| **FR12** — Export Word fidèle aux images | AD-12 (phpword, best-effort) ; ligne Capability Map FR12 | ⚠️ Couvert mais en tension avec NFR5 — voir Gap 2 |
| **NFR1** — Local via Herd, sans infra serveur partagée | Stack ("Environnement dev : Laravel Herd") ; AD-7 (Binds NFR1) ; section Deferred ("Déploiement au-delà du poste local") | ✅ Couvert |
| **NFR2** — Recherche < 1s sur ~350 documents | AD-8 (Binds NFR2) | ✅ Couvert au niveau du choix technique (Scout/database triviellement rapide à cette échelle) ; aucune vérification/benchmark prévue mais raisonnable à ce stade |
| **NFR3** — Aucune authentification/rôles | Section Deferred ("Authentification / permissions / multi-utilisateurs : explicitement hors v1 — NFR3") | ✅ Couvert explicitement |
| **NFR4** — PDF/.docx/.xlsx prioritaires, .doc/.xls non prioritaires | Section Deferred ("Formats hérités .doc/.xls : statut du corpus réel inconnu... NFR4 reste la cible") | ❌ Non résolu, seulement reformulé — voir Gap 4 |
| **NFR5** — Fidélité raisonnable, sauf images inline (critique) | AD-11 (PDF : fidélité forte via Browsershot) ; AD-12 (Word : "risque assumé") | ⚠️ Contradiction partielle — voir Gap 2 |

## Questions ouvertes du PRD — vérification du traitement

| Question ouverte (PRD) | Traitement dans la spine | Verdict |
| --- | --- | --- |
| Formats hérités `.doc`/`.xls` : à vérifier en amont de l'architecture | Section Deferred : "statut du corpus réel inconnu (l'utilisateur n'est pas certain du volume ni des formats)" — **aucune vérification n'a eu lieu**, la spine se contente de reformuler l'incertitude déjà présente dans le PRD (`[ASSUMPTION]` NFR4) et de noter que LibreOffice les couvrirait techniquement si besoin | ❌ **Non résolu** — restaté comme encore ouvert, pas tranché |
| Granularité de classement : dossiers hiérarchiques, catégories à plat, ou les deux | AD-5 tranche explicitement : catégories à plat uniquement, une seule catégorie par document, pas de hiérarchie, pas de many-to-many | ✅ **Résolu** avec une décision claire et justifiée (Prevents/Rule) |
| Choix technique du moteur fulltexte | AD-8 tranche explicitement : Laravel Scout + driver `database`, interdiction des `LIKE` ad hoc | ✅ **Résolu** avec une décision claire et justifiée |

Score : 2 questions sur 3 réellement tranchées ; 1 (formats hérités) simplement reconduite telle quelle sous forme d'un item "Deferred", sans la vérification amont que le PRD demandait explicitement ("à vérifier en amont de l'architecture").

## Gaps found

### Gap 1 — FR9 (insertion d'images à la volée) n'a pas de home architectural concret
FR9 est explicitement désigné par le PRD comme "usage clé" et par NFR5 comme "point de fidélité critique, pas secondaire". Pourtant :
- La ligne Capability Map pour FR8/FR9 pointe uniquement vers `Editor.vue` et est gouvernée par "Stack" — pas par une AD.
- Aucune Action, Controller ou route n'existe pour l'upload d'image pendant la rédaction (le Structural Seed ne liste que `DocumentController` et `CategoryController`, tous deux RESTful sur la ressource document/catégorie).
- Aucun répertoire de stockage n'est prévu pour les images de contenu (le Structural Seed ne définit que `documents/{document_id}/{filename}` pour les fichiers importés et `previews/{document_id}.pdf` pour les caches de conversion — rien pour les images insérées dans un document créé).
- Cela laisse ouvert : où sont stockées les images (disque, table dédiée ?), comment `content_html` référence ces images (chemin relatif, URL applicative, base64 ?), et quelle action orchestre l'upload.

C'est la fonctionnalité la plus explicitement mise en avant par le PRD comme point de fidélité critique, et c'est précisément celle qui n'a aucune AD dédiée — alors que des points de moindre criticité (prévisualisation Office, export Word) en ont chacun une.

### Gap 2 — Contradiction entre AD-12 et le texte de NFR5 sur la fidélité des images en export Word
NFR5 dit explicitement que la fidélité de position/rendu des images inline est "un point de fidélité critique, pas secondaire" — formulé sans distinguer PDF et Word (les deux formats d'export sont cités ensemble : "WYSIWYG → PDF/Word").
AD-12 traite pourtant l'échec de positionnement d'image en export Word comme "un risque assumé, pas un défaut à corriger en priorité".
Ce n'est pas nécessairement une mauvaise décision technique (le mapping HTML→OOXML de `phpoffice/phpword` a des limites réelles), mais la spine ne l'assume pas comme une **révision explicite de NFR5** — elle la présente comme une simple conséquence d'implémentation dans une AD, sans amender ou nuancer le NFR lui-même. Un lecteur qui compare littéralement NFR5 et AD-12 verra une exigence "critique, pas secondaire" être requalifiée en "risque assumé" sans qu'aucun document ne signale le changement de posture. Recommandation : soit amender NFR5 pour restreindre explicitement l'exigence de fidélité image critique au chemin PDF, soit documenter clairement dans la spine que NFR5 est partiellement révisé pour Word.

### Gap 3 — AD-5 (classement à plat) supprime silencieusement la moitié de FR2 sans le signaler comme un choix de portée
FR2 demande "dossiers **et/ou** catégories". AD-5 tranche pour "catégories à plat" et exclut explicitement toute hiérarchie de dossiers ("Rule : table categories... à plat, sans parent... Pas de hiérarchie"). C'est un arbitrage légitime de la question ouverte correspondante, mais la spine ne le présente nulle part comme une réduction de portée par rapport au texte littéral de FR2 (qui envisageait explicitement des "dossiers"). Ce n'est pas un gap bloquant (l'question ouverte du PRD autorisait ce choix), mais ce n'est pas non plus signalé comme une décision qui restreint le FR — à mentionner pour que ce ne soit pas lu comme un oubli plus tard.

### Gap 4 — NFR4 / formats hérités .doc/.xls : la vérification demandée par le PRD n'a pas eu lieu
Le PRD demandait explicitement de vérifier "en amont de l'architecture" si le corpus de ~350 documents contient des formats hérités. La spine ne fait que redire l'incertitude dans sa section Deferred, sans qu'aucune vérification (inventaire du corpus réel, échantillonnage) n'ait visiblement été menée. Ce n'est pas un vice bloquant pour la v1 techniquement (LibreOffice couvrirait ces formats si besoin), mais du point de vue de la tâche demandée par le PRD ("à vérifier en amont de l'architecture"), cette question ouverte n'a pas été traitée — seulement reconduite.

### Gap 5 (mineur) — FR3 : absence d'un champ `title` explicitement nommé
FR3 exige un titre comme métadonnée de base. La spine nomme explicitement `file_path`, `mime_type`, `content_html`, `source`, `category_id` (AD-4, AD-5) mais ne mentionne jamais de colonne `title`/`titre`. Vu le niveau de détail donné aux autres colonnes, cette omission est probablement involontaire plutôt qu'un choix — à corriger lors du passage au schéma de migration détaillé.

## Synthèse

Sur 12 FR + 5 NFR (17 exigences), 12 ont une couverture solide et sans ambiguïté (FR1, FR4, FR5, FR6, FR8, FR10, FR11, NFR1, NFR2, NFR3, plus FR7/NFR2 en couverture correcte quoique légère). 5 exigences présentent une couverture incomplète ou en tension : FR2 (réduction de portée non signalée), FR3 (champ titre non nommé), FR9 (aucun home architectural concret pour la fonctionnalité la plus critique du PRD), FR12/NFR5 (contradiction non assumée explicitement), et NFR4 (question ouverte reconduite, pas vérifiée). Sur les trois "Questions ouvertes" du PRD, deux sont réellement tranchées par la spine (granularité de classement, moteur de recherche) et une (formats hérités) est simplement redite comme encore ouverte sans la vérification que le PRD demandait.
