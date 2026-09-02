# Deferred Work

<!-- Append-only. Each entry: source_spec, summary, evidence. -->

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: Déposer plusieurs fichiers à la fois dans la modale d'import n'affiche aucun message — seul le premier est importé silencieusement.
  evidence: Edge Case Hunter (step-04 review) — `resources/js/Components/ImportModal.vue` `onDrop()` ne vérifie pas `event.dataTransfer.files.length > 1`. Faible impact (usage solo), amélioration UX à bas coût si une session future touche ce fichier.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: L'extraction de texte de très gros fichiers Word/Excel (proche de la limite 20 Mo) pourrait épuiser `memory_limit` PHP avec une erreur fatale non rattrapable, court-circuitant la garantie "l'extraction échoue silencieusement, jamais l'import".
  evidence: Edge Case Hunter (step-04 review) — risque réel mais faible en pratique (limite 20 Mo + `memory_limit` PHP par défaut généralement suffisant pour ce volume) ; nécessiterait un parsing par flux pour être vraiment corrigé, hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-browse-library.md`
  summary: La logique de dérivation du libellé dans `DocumentTypeBadge.vue` (mapping mime-type, repli `source=created`, repli générique "Document") n'est vérifiée par aucun test automatisé — le dépôt ne contient aucun outil de test JS (pas de Vitest/Jest, pas de script `test` côté front dans `package.json`).
  evidence: Verification Gap (step-04 review) — confirmé par recherche exhaustive (aucune référence à `DocumentTypeBadge` dans un fichier de test, aucun outil de test JS installé). Les tests Pest existants ne vérifient que les props brutes (`mime_type`/`source`) transmises par Inertia, jamais le libellé rendu ; une régression sur l'ordre de priorité des branches (ex. inverser le repli `created`/mime reconnu) ne serait détectée par aucun test. Corriger nécessiterait d'introduire un outil de test JS (Vitest + `@vue/test-utils`), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-2-browse-library.md`
  summary: Le formatage de date en français (`Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' })`) est dupliqué entre `Index.vue` (`formatDate()`) et `Show.vue` (`formattedDate`) au lieu d'être centralisé dans un helper partagé.
  evidence: Blind Hunter (step-04 review) — même schéma que la duplication du libellé de type déjà résolue dans cette story ; coût de duplication encore faible (une seule ligne de logique), à centraliser si une troisième page a besoin d'afficher une date.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Aucun nettoyage de `storage/app/private/previews/{document_id}.pdf` n'existe lors de la suppression d'un document — la Story 1.8 (Supprimer un document) devra faire de ce cache un des éléments nettoyés par `DeleteDocumentAction`.
  evidence: Blind Hunter (step-04 review) — confirmé par lecture du code : rien dans ce diff ne référence de suppression du cache preview ; cohérent avec `epic-1-context.md` qui liste explicitement ce nettoyage comme dépendance de la Story 1.8, pas de la 1.3.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: La conversion LibreOffice tourne de façon synchrone dans la requête HTTP `preview()` (jusqu'à 120s de timeout) — un gros fichier peut épuiser un worker PHP-FPM ou dépasser le timeout du serveur web avant celui du process, laissant un `soffice` orphelin.
  evidence: Blind Hunter (step-04 review) — risque réel mais décision d'architecture déjà tranchée (route synchrone dédiée, pas de job/polling, cf. Boundaries "Never" de spec-1-3) ; à revisiter seulement si des timeouts réels sont observés en usage.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Aucune invalidation du cache `previews/{id}.pdf` si le fichier source d'un document est un jour remplacé/ré-importé — le cache resterait périmé indéfiniment.
  evidence: Blind Hunter (step-04 review) — aucun mécanisme de remplacement de fichier source n'existe encore dans l'app (pas de story de ré-import) ; `epic-1-context.md` anticipe explicitement cette invalidation ("invalidated only by delete/re-import"), à traiter quand cette fonctionnalité sera construite.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Le mapping mime-type→format est dupliqué entre `DocumentController::previewFormat()` (nouveau) et `ExtractDocumentTextJob::formatFromMimeType()` (existant) au lieu d'être centralisé.
  evidence: Blind Hunter (step-04 review) — même schéma que les duplications déjà différées sur cette base de code ; à centraliser si un troisième point de branchement mime-type apparaît.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: Un fichier annoncé `mime_type=application/pdf` mais dont le contenu est en réalité corrompu/invalide n'est jamais détecté côté serveur — l'iframe pointée directement sur la route de preview affiche un rendu vide/cassé au lieu du message explicite "Aperçu indisponible" que reçoit un Word/Excel corrompu.
  evidence: Edge Case Hunter (step-04 review) — confirmé : `preview()` ne valide que l'existence/lisibilité du fichier (`sourceMissing`), jamais la structure PDF elle-même ; aucun des 4 AC métier de la story (issus d'epics.md) ne couvre ce cas, hors du périmètre approuvé par l'humain pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-3-preview-document.md`
  summary: La conversion LibreOffice headless de fichiers Word/Excel importés (potentiellement porteurs de macros) tourne sans isolation (pas de profil utilisateur restreint, pas de désactivation explicite des macros) — risque de sécurité si un fichier malveillant est un jour importé.
  evidence: Blind Hunter (step-04 review) — décision d'architecture déjà tranchée au niveau epic (invocation `soffice --headless --convert-to pdf` brute, epic-1-context.md) ; risque jugé faible en pratique pour un usage local mono-utilisateur v1 (l'utilisateur importe ses propres fichiers), mais à durcir si l'app s'ouvre un jour à des fichiers non maîtrisés.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-categorize-document.md`
  summary: Si l'utilisateur navigue vers un autre document pendant qu'une réassignation de catégorie est encore en vol (`PATCH /documents/{id}/category`), la visite Inertia suivante peut annuler/faire courir en concurrence cette requête, faisant disparaître silencieusement le changement de catégorie.
  evidence: Edge Case Hunter (step-04 review) — comportement par défaut du routeur Inertia (une visite en vol peut être remplacée) ; risque réel mais rare pour un usage local mono-utilisateur, nécessiterait un token d'annulation dédié pour être corrigé proprement.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-5-categorize-document.md`
  summary: Aucun test au niveau composant Vue ne couvre `CategoryPicker.vue` (flux créer/annuler/Échap, mise à jour optimiste) — le dépôt ne contient toujours aucun outil de test JS (pas de Vitest/`@vue/test-utils`).
  evidence: Verification Gap (step-04 review) — même constat déjà différé pour `DocumentTypeBadge.vue` dans spec-1-2 ; s'aggrave à mesure que la logique côté client s'accumule (validation optimiste, gestion d'erreur), mais introduire un outil de test JS reste hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Le raccourci clavier `/` (focus barre de recherche, ignoré si un champ ou la modale d'import a déjà le focus) et le debounce de saisie dans `Index.vue` ne sont couverts par aucun test automatisé — même absence d'outillage de test JS que `CategoryPicker.vue`/`DocumentTypeBadge.vue`.
  evidence: Matrix Test Audit (step-03) — 2 des 5 lignes de la matrice I/O (raccourci `/` hors champ / dans un champ actif) sont purement côté client, non testables par la suite Pest existante ; seule la vérification manuelle décrite dans la spec les couvre.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Le terme de recherche (`?search=`) n'a aucune limite de longueur avant d'être transmis à `Document::search()` — une chaîne arbitrairement longue peut être soumise sans garde-fou sur le coût de la requête `LIKE`.
  evidence: Blind Hunter (step-04 review) — risque réel mais faible en usage local mono-utilisateur ; corriger nécessiterait de décider d'une limite arbitraire, hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucun index `FULLTEXT` sur `documents.extracted_text` — la recherche reste un scan `LIKE '%terme%'` sans index ; l'objectif "<1s pour ~350 documents" (NFR2) n'est donc pas techniquement garanti si le corpus grossit significativement au-delà de cet ordre de grandeur.
  evidence: Blind Hunter (step-04 review) — non bloquant à l'échelle actuelle (~350 documents, scan trivial), et le driver `database` de Scout n'utilise `FULLTEXT` que si la colonne est explicitement déclarée comme telle ; à revisiter si la volumétrie dépasse l'ordre de grandeur documenté dans l'epic.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucun test ne vérifie la casse/les accents du terme de recherche (ex. variante majuscule ou sans accent d'un mot accentué) — le comportement de repli de casse/accents peut différer entre SQLite (tests) et MySQL (production).
  evidence: Blind Hunter (step-04 review) — écart d'infrastructure de test pré-existant (SQLite en test, MySQL en production), pas introduit spécifiquement par ce diff ; nécessiterait de tester contre une vraie base MySQL pour être vérifié fiablement.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-6-search-documents.md`
  summary: Aucune protection contre les réponses Inertia qui arrivent dans le désordre (une requête de recherche plus ancienne mais plus lente pourrait résoudre après une plus récente et écraser des résultats plus à jour) si l'utilisateur tape très vite malgré le debounce de 300ms.
  evidence: Edge Case Hunter (step-04 review) — risque réel mais rare en usage local mono-utilisateur (NFR: pas d'infrastructure partagée) ; corriger proprement nécessiterait un jeton d'annulation/AbortController, hors proportion pour cette story.
