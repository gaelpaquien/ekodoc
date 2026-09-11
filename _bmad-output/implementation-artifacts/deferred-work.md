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

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: `DeleteDocumentAction` n'a aucune gestion d'échec réel (permissions, disque indisponible, exception levée par `unsearchable()`/`delete()`) — pas de try/catch, pas de log, pas de transaction, contrairement au précédent posé par `ImportDocumentAction::storeFile()`.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review) — un échec en cours de séquence dégraderait vers l'état déjà géré par `sourceMissing()` (fichier absent, ligne toujours présente), ce qui borne l'impact pratique ; corriger correctement suppose une décision produit sur la politique d'échec souhaitée (best-effort silencieux vs. transactionnel vs. journalisé et remonté à l'utilisateur), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Le comportement interactif de la boîte de dialogue de confirmation de suppression (`Show.vue` : piège de focus, Échap, restauration du focus) n'est vérifié par aucun test automatisé.
  evidence: Blind Hunter (step-04 review) — même constat déjà différé pour la Story 1.7 (filtres) : le dépôt ne contient aucun outil de test JS (pas de Vitest/Jest).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: `ImportModal.vue` a le même défaut préexistant que celui corrigé dans le nouveau dialogue de suppression : son piège de focus (`trapFocus()`) n'exclut pas les éléments `disabled`, alors que son bouton de fermeture peut être désactivé (`form.processing`).
  evidence: Edge Case Hunter (step-04 review) — défaut préexistant à cette story, révélé incidemment en corrigeant l'équivalent neuf dans `Show.vue` ; même correctif trivial applicable (`button:not([disabled])`) si une session future touche ce fichier.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Course rare possible entre une conversion de prévisualisation en cours (`ConvertDocumentToPreviewAction`, verrou `Cache::lock` par document) et une suppression concurrente : le fichier `previews/{id}.pdf` pourrait être réécrit après la suppression et rester orphelin indéfiniment.
  evidence: Edge Case Hunter (step-04 review) — improbable dans un usage local mono-utilisateur (nécessite deux requêtes concurrentes sur le même document) ; un correctif correct suppose de décider si la suppression doit bloquer sur le même verrou et combien de temps, un arbitrage produit plutôt qu'un correctif mécanique.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-8-delete-document.md`
  summary: Aucune confirmation visuelle (toast/flash) n'apparaît sur la Bibliothèque après une suppression réussie — l'utilisateur constate seulement l'absence du document.
  evidence: Blind Hunter (step-04 review) — pas spécifique à cette story : `store()`/`updateCategory()` n'ont eux non plus jamais eu de système de flash-message ; l'app n'en a jamais eu nulle part.

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

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: Aucun test au niveau composant Vue ne couvre la nouvelle logique de filtrage d'`Index.vue` (bascule des checkboxes, retrait des chips, les deux drapeaux `isSyncing*FromProps`, l'annulation du debounce de recherche lors d'un changement de filtre) — le dépôt ne contient toujours aucun outil de test JS.
  evidence: Blind Hunter (step-04 review) — même constat déjà différé pour `CategoryPicker.vue`/`DocumentTypeBadge.vue`/le raccourci `/` de Story 1.6 ; introduire un outil de test JS reste hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: `categoryIdsFromQuery()` n'impose aucune limite au nombre d'identifiants acceptés avant de les passer à `whereIn('category_id', ...)` — un `category_id[]=...` arbitrairement long est accepté sans garde-fou de taille.
  evidence: Blind Hunter (step-04 review) — même schéma que l'absence de limite de longueur déjà différée sur le terme de recherche (spec-1-6) ; risque faible en usage local mono-utilisateur, hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: Aucun index base de données sur `documents.mime_type`/`documents.source` alors que le filtre type exécute désormais un `whereIn('mime_type', ...)`/`where('source', ...)` sur chaque requête filtrée — `category_id` bénéficie d'un index via sa contrainte FK, pas ces colonnes.
  evidence: Blind Hunter (step-04 review) — non bloquant à l'échelle actuelle (~350 documents, scan trivial) ; même raisonnement que l'absence d'index `FULLTEXT` déjà différée sur `extracted_text` (spec-1-6).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-7-filter-documents.md`
  summary: La correspondance type→mime est dupliquée entre `DocumentController::TYPE_MIME_MAP` (PHP) et `TYPE_OPTIONS` (Vue, `Index.vue`) sans source commune — un commentaire est le seul lien entre les deux.
  evidence: Blind Hunter (step-04 review) — même schéma que les autres duplications déjà différées sur cette base de code (formatage de date, mapping mime de prévisualisation) ; à centraliser si un troisième point de branchement type/mime apparaît.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: L'enregistrement d'un document créé redirige vers la Fiche document sans afficher le texte "Enregistré." littéral prévu par la voix/ton (EXPERIENCE.md) — aucun mécanisme de flash message n'existe dans l'app pour le porter.
  evidence: Design Notes, step-02 planning — même trou déjà noté à la Story 1.8 (pas de flash/toast après suppression). Nécessiterait d'introduire le partage de session flash côté `HandleInertiaRequests` (absent aujourd'hui), hors proportion pour une seule story ; à traiter comme un ajout transverse si une future story en a de nouveau besoin (ex. export réussi, Story 2.4/2.5).

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: Un second clic sur "Enregistrer" avant de quitter l'Éditeur crée un nouveau document distinct plutôt que de mettre à jour celui déjà enregistré — pas de garde contre le doublon.
  evidence: Design Notes, step-02 planning — décision de scope délibérée : la ré-édition d'un document créé (chargement + mise à jour du même enregistrement) est le sujet de la Story 2.3, qui réutilisera le même chemin de sauvegarde.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: `Show.vue` calcule `isCreated` en comparant `document.source` à la chaîne littérale `'created'` au lieu de référencer une source commune avec l'enum backend `DocumentSource`.
  evidence: Blind Hunter (step-04 review) — même schéma que la duplication type→mime déjà différée (spec-1-7) ; à centraliser si un troisième point de comparaison sur `source` apparaît.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: `Editor.vue::onSaveClick()` focus le sélecteur de catégorie via `document.getElementById('category-picker-select')`, en passant par un id DOM interne à `CategoryPicker.vue` plutôt que par son API de composant — couplage fragile si son markup change.
  evidence: Blind Hunter (step-04 review) — fonctionne aujourd'hui, mais casserait silencieusement (perte de focus, pas d'erreur) si `CategoryPicker.vue` change son id interne.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: La barre d'outils insère un tableau fixe 3×3 (`insertTable`) sans aucun contrôle pour ajouter/supprimer des lignes ou colonnes ensuite.
  evidence: Blind Hunter (step-04 review) — FR8 ne demande que la présence de tableaux dans la barre d'outils, satisfait par l'insertion ; l'édition de structure après insertion reste un angle mort UX si un usage réel s'en plaint.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-1-create-document-editor.md`
  summary: Aucun test automatisé n'observe quelle branche du template `Show.vue` s'affiche réellement pour un document créé (`isCreated` vrai/faux) — `CreateDocumentTest.php` ne vérifie que les props Inertia envoyées, pas le rendu. Une régression sur `isCreated` ferait retomber silencieusement l'affichage sur le message "fichier introuvable" sans qu'aucun test échoue.
  evidence: Verification Gap Reviewer (step-04 review) — confirmé par recherche : aucun framework de test JS/composant n'existe dans le projet (`package.json` ne déclare ni runner ni script `test`, aucun fichier `*.spec.js`/`*.test.js` trouvé) ; fermer ce trou suppose d'introduire un outillage de test frontend (Dusk/Playwright), hors proportion pour cette seule story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun nettoyage automatique des dossiers `documents/tmp/{token}/` abandonnés — un brouillon dans lequel une ou plusieurs images ont été insérées puis jamais enregistré (`Enregistrer` jamais cliqué, page fermée) laisse ses fichiers indéfiniment sur le disque privé, sans qu'aucun `Document` ne les référence.
  evidence: Boundaries & Constraints, spec-2-2 ("Never") — exclusion de périmètre délibérée et documentée par l'humain dès l'intent ; corriger nécessiterait une tâche planifiée (purge des dossiers `tmp/*` plus vieux qu'un certain âge) ou un nettoyage côté client (`beforeunload`, peu fiable), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun test JS/composant ne couvre le dialogue de saisie du texte alternatif ni le glisser-déposer d'image dans `Editor.vue` (calcul de la position d'insertion via `posAtCoords`, piège de focus, Échap) — même absence d'outillage de test JS que le reste du projet (pas de Vitest/`@vue/test-utils`).
  evidence: Même constat déjà différé pour `CategoryPicker.vue`/le dialogue de suppression de `Show.vue` (spec-1-5, spec-1-8) ; introduire un outillage de test frontend reste hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Le déplacement disque des images de brouillon (`relocateDraftImages`) n'est pas couvert par la `DB::transaction()` qui l'englobe — si le `forceFill()->save()` final échoue après un déplacement déjà réussi, les fichiers restent orphelins dans `documents/{id}/images/` alors que la ligne `Document` est annulée par le rollback.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review, spec-2-2) — scénario rare (nécessite un échec DB juste après un `Document::create()` réussi dans la même transaction) ; même nature que le trou déjà accepté sur les dossiers `tmp/{token}` abandonnés (orphelins sans document propriétaire), pas de mécanisme de nettoyage existant à réutiliser.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-2-insert-images-inline.md`
  summary: Aucun test ne force l'échec de `relocateDraftImages()` (ex. `Storage::move()` retournant `false`) pour vérifier que la transaction annule bien la création du `Document` et que le dossier de destination est nettoyé.
  evidence: Verification Gap Reviewer (step-04 review) — même trou déjà accepté sur la branche échec-de-stockage structurellement identique de `ImportDocumentAction::storeFile()` ; aucun pattern de mock/fake d'échec disque n'existe ailleurs dans le projet (`Storage::fake('local')` réussit toujours), introduire ce tooling est hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `Editor.vue` porte à la fois la création et l'édition ; une navigation Inertia qui réutilise cette même instance de composant sans démontage intermédiaire (ex. retour/avance navigateur à travers l'historique Inertia) ne réinitialiserait pas `draftToken`/`form`/`initialSnapshot`/l'instance TipTap depuis les nouveaux props, laissant potentiellement le contenu d'un autre document affiché.
  evidence: Blind Hunter (step-04 review) — aucun chemin de navigation actuel dans l'app ne relie directement deux pages Editor.vue sans passer par la Bibliothèque/la Fiche document (qui démontent le composant), donc non reproductible via le parcours normal ; resterait à vérifier si l'historique navigateur (retour/avance) peut recréer ce cas via le cache de pages d'Inertia.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `UpdateDocumentTest.php` ne couvre pas : un titre de plus de 255 caractères à la mise à jour, un `draft_token` invalide (non-UUID), ni le scénario "image insérée puis retirée avant enregistrement" côté édition (son équivalent existe côté création, spec-2-2).
  evidence: Blind Hunter (step-04 review) — mêmes règles de validation que `CreateDocumentRequest` (déjà testées côté création) ; complète la matrice I/O mais n'en fait pas partie explicitement, hors du périmètre strictement approuvé pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: Un échec de `relocateDraftImages()` (`Storage::move()` retournant `false`) pendant une mise à jour se propage en exception non rattrapée jusqu'à une erreur générique côté client, alors que l'I/O Matrix de cette story exige un "message explicite" ; le même trou existe déjà côté création (spec-2-1/2-2) et n'a jamais été comblé.
  evidence: Blind Hunter (step-04 review) — confirmé par lecture de `DocumentController::update()`/`storeCreated()` : aucun `catch` autour de l'appel à l'Action, aucun gestionnaire d'exception applicatif dans `app/Exceptions`. Pré-existant, pas introduit par cette story ; corriger suppose de décider d'un mécanisme de message d'erreur explicite pour toute l'app (flash d'erreur), hors proportion pour cette seule story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-3-edit-existing-document.md`
  summary: `DeleteDocumentTest::it_permanently_deletes_the_file__the_preview_cache_and_the_document_row__then_redirects_to_the_library` échoue de façon reproductible (`Directory [documents/1] is not empty.`), y compris isolé et sur l'état du dépôt antérieur à cette story (confirmé par un `git stash` de reproduction).
  evidence: Vérification step-04 (`php artisan test`) — échec confirmé pré-existant, sans rapport avec ce diff (spec-1-8, déjà `done`) ; surfacé incidemment par l'exécution de la suite complète exigée par la section Verification de cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: `phpunit.xml` fixe `BROWSERSHOT_CHROME_PATH` à un chemin Windows absolu — `ExportDocumentToPdfTest` échouerait uniformément sur toute machine/CI où Chrome n'est pas installé à cet exact emplacement.
  evidence: Blind Hunter + Edge Case Hunter + Verification Gap Reviewer (step-04 review, convergent) — non bloquant pour l'usage actuel (poste de dev Windows unique, pas de pipeline CI en v1 — NFR1) ; à revisiter si le projet s'ouvre un jour à plusieurs postes/CI.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: Le timeout Browsershot de 60s n'est arrimé à aucun timeout du serveur web/PHP-FPM en production — un rendu lent pourrait déclencher une erreur 504 du serveur avant que le 422 explicite prévu par la spec ne se déclenche.
  evidence: Blind Hunter (step-04 review) — même nature que le risque déjà accepté sur le timeout LibreOffice 120s de `ConvertDocumentToPreviewAction` (spec-1-3) ; non observé en pratique, à revisiter si des timeouts réels apparaissent en usage.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: `export-pdf.blade.php` ne définit aucune règle de pagination CSS (`@page`, `break-inside`/`page-break-*`) — un titre, une ligne de tableau ou une image peut se couper de façon disgracieuse en travers d'un saut de page sur un document long.
  evidence: Blind Hunter (step-04 review) — NFR5 borne explicitement la garantie de fidélité "critique" aux images inline (position/rendu), pas à la pagination générale ; polish visuel hors du périmètre approuvé pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-4-export-document-pdf.md`
  summary: Aucun test ne couvre l'export d'un document `source=created` dont `content_html` est vide/`null`.
  evidence: Blind Hunter (step-04 review) — cas limite réel mais mineur (repli `?? ''` déjà en place dans `ExportDocumentToPdfAction`) ; ne fait pas partie de la matrice I/O approuvée par l'humain pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToPdfAction::resolveImageSources()` et `ExportDocumentToWordAction::resolveImageSources()` sont deux implémentations quasi-jumelles (même scan regex des `<img src>`, substitution différente : data URI vs. chemin disque vs. suppression de balise) au lieu de partager un utilitaire commun de résolution d'images.
  evidence: Blind Hunter (step-04 review) — mirroring délibéré demandé par la spec (Code Map) plutôt qu'un défaut ; à factoriser si un troisième format d'export apparaît un jour, prématuré pour deux occurrences seulement.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: Quand une image référencée dans `content_html` est absente du disque, l'export Word la retire silencieusement et affiche quand même le toast de succès standard ("Export Word généré.") — l'utilisateur n'a aucun signal que le `.docx` téléchargé contient moins de contenu que le document source.
  evidence: Blind Hunter (step-04 review) — comportement conforme à la matrice I/O approuvée dans la spec (best-effort, pas de blocage) ; amélioration UX possible (toast différencié type "Export Word généré (1 image manquante)") si ce cas s'avère fréquent en usage réel.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToWordAction::resolveImageSources()` construit le chemin disque directement à partir du nom de fichier capturé par la regex, sans le valider contre un format attendu (ex. UUID), avant l'appel à `Storage::disk('local')->exists()`/`->path()` — repose entièrement sur la désinfection en amont (`SanitizesDocumentContent`) plutôt que de se défendre elle-même contre un `src` malformé/de type traversée de chemin.
  evidence: Blind Hunter (step-04 review) — même schéma déjà présent dans `ExportDocumentToPdfAction::resolveImageSources()` (spec-2-4, déjà `done`) ; impact limité en usage local mono-utilisateur (NFR3, pas d'auth), à durcir si l'app s'ouvre un jour à des entrées non maîtrisées.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: Un `content_html` très volumineux (beaucoup d'images/de contenu) pourrait épuiser la mémoire ou le temps d'exécution PHP pendant `Html::addHtml()`/`writer->save()`, provoquant une erreur fatale 500 au lieu du `422` explicite prévu par la spec.
  evidence: Edge Case Hunter (step-04 review) — même nature que les limites `memory_limit` déjà différées pour l'extraction de texte (spec-1-1) ; nécessiterait des garde-fous d'infrastructure (limite de taille, `memory_limit` dédié), hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-2-5-export-document-word.md`
  summary: `ExportDocumentToWordTest.php` appelle `createDocumentForExport()`/`uploadDraftImageForExport()` sans les définir — ces fonctions globales ne sont déclarées que dans `ExportDocumentToPdfTest.php` (spec-2-4) ; fonctionne aujourd'hui grâce au chargement de répertoire de Pest/PHPUnit, mais serait fragile si les fichiers de test étaient un jour exécutés en isolation stricte par chemin plutôt que par filtre, ou parallélisés.
  evidence: Verification Gap Reviewer (step-04 review) — vérifié empiriquement non bloquant pour la commande de vérification réellement utilisée (`--filter=`, qui charge tout le répertoire) ; à corriger en extrayant ces helpers vers un fichier de support de test partagé si un outillage d'exécution par fichier isolé est introduit un jour.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: RÉSOLU (2026-09-09) — Le piège de focus (`trapFocus()`) d'`ImportModal.vue` (et de manière identique celui de `Show.vue`, son modèle de référence) n'excluait que les `<button>` désactivés — pas les `<select>`/`<input>` désactivés. `CategoryPicker.vue`, monté à l'intérieur des deux dialogues, rendait un `<select>` désactivé (`:disabled="form.processing"`/`:disabled="disabled"`) qui restait compté comme frontière du piège de focus. Même défaut trouvé par ricochet dans `Editor.vue::trapImageDialogFocus()` (3ᵉ occurrence du même sélecteur, non signalée dans la revue d'origine) et corrigé en même temps.
  evidence: Constat convergent et indépendant des 3 couches de revue (Blind Hunter, Edge Case Hunter, Verification Gap Reviewer) sur le diff de `spec-corrections-post-retrospective.md`. Correctif appliqué : sélecteur étendu à `input:not([disabled]), select:not([disabled]), textarea:not([disabled])` dans les 3 fichiers (`ImportModal.vue`, `Show.vue`, `Editor.vue`). Aucun test Vitest dédié ajouté (pas de simulation Tab/Shift+Tab pendant `form.processing`) — suite JS (13 tests) et suite PHP (127 tests) toutes deux vertes après correctif, aucune régression.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: `App\Support\DocumentMimeTypes` centralise le mapping mime-type PHP mais ne l'a pas étendu à `resources/js/Components/DocumentTypeBadge.vue`, qui garde sa propre copie (`MIME_TYPE_LABELS`) — une 3ᵉ occurrence du même mapping subsiste. Le commentaire du composant ("Single source of truth for the document type label") est maintenant trompeur puisqu'une vraie source unique existe désormais côté PHP/Inertia sans que ce composant s'y raccorde.
  evidence: Blind Hunter (step-04 review). Exclusion de périmètre délibérée de `spec-corrections-post-retrospective.md` ("Never : ne pas toucher à DocumentTypeBadge.vue::MIME_TYPE_LABELS"), donc non traité dans cette itération ; prochaine étape naturelle si le mapping mime-type est retouché à nouveau — reconnecter ce composant à la prop `documentTypeOptions` (ou une prop `documentTypeLabels` dédiée) et corriger/retirer le commentaire devenu inexact.

- source_spec: `_bmad-output/implementation-artifacts/spec-corrections-post-retrospective.md`
  summary: `App\Support\DocumentMimeTypes::MIME_TO_FORMAT` et `::TYPE_TO_MIME` continuent de répéter indépendamment les 3 mêmes littéraux de mime-type complets (`application/pdf`, etc.) dans deux tableaux distincts de la même classe — la centralisation réduit la duplication inter-fichiers mais n'élimine pas la duplication interne à la classe ; une faute de frappe dans l'un et pas l'autre désynchronisant les deux tables silencieusement.
  evidence: Blind Hunter (step-04 review). Pas bloquant (les deux tableaux sont désormais testés — voir patch appliqué dans la même revue), mais une vraie table canonique unique (mime → {format, type, label}) serait plus robuste ; à envisager si un 4ᵉ mapping mime-type apparaît un jour dans le projet.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-1-tag-documents.md`
  summary: Pas de protection anti-doublon insensible à la casse pour `Tag` (équivalent au `LOWER(name)` de l'ancien `CreateCategoryAction`) — deux tags de casse différente (ex. "Facture" / "facture") pourraient un jour coexister.
  evidence: Blind Hunter (step-04 review) — non actionnable maintenant : aucune UI de création de tag n'existe dans cette story (`tags` est peuplée via `TagFactory`/tinker en attendant) ; à traiter quand la story 3.5 (page Configuration, gestion des tags) livrera la création de tag.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-1-tag-documents.md`
  summary: `sameTagIds()`, la comparaison order-insensitive utilisée par le dirty-check d'`Editor.vue` (`isDirty`), n'a aucun test unitaire dédié — rien ne garantit qu'une régression future (ex. inverser le sens de la comparaison) serait détectée.
  evidence: Blind Hunter (step-04 review) — aucun fichier `Editor.spec.js` n'existe dans le projet et sa création ne fait pas partie des tâches listées par ce spec ; même nature que les trous d'outillage de test JS déjà différés sur d'autres pages (Show.vue, Index.vue) dans les stories précédentes.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-2-visual-identity-sidebar.md`
  summary: `resources/js/Components/ExtractionTasksPanel.vue` tronque le titre de tâche (`<span class="truncate">{{ task.title }}</span>`) sans `min-w-0` sur ce span flex — un titre long ne tronque jamais réellement et peut déborder de la ligne.
  evidence: Blind Hunter (step-04 review, iteration 2) — confirmé préexistant : identique dans `git show <baseline_commit>:resources/js/Components/ExtractionTasksPanel.vue`, avant tout changement de cette story ; ce diff n'a retoken que les couleurs de ce composant, jamais touché ce `<span>`. Même correctif trivial (`min-w-0`) que celui appliqué à la ligne de document d'`Index.vue` dans cette même story, si une session future retouche ce fichier.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-2-visual-identity-sidebar.md`
  summary: Dans la barre d'outils de l'Éditeur (`Editor.vue`), l'état "actif" d'un bouton de mise en forme (`editor?.isActive(...)`) utilise la même classe de fond (`bg-surface`) que l'état `:hover`, rendant un bouton actif indiscernable d'un bouton simplement survolé.
  evidence: Blind Hunter (step-04 review, iteration 2) — préexistant : `git show <baseline_commit>` confirme que `bg-neutral-200`/`dark:bg-neutral-700` servait déjà identiquement à l'état actif ET à `hover:` avant cette story ; ce diff n'a fait que retoken la même ambiguïté, pas l'introduire.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `extraction_status` d'une pièce jointe (`pending`/`processing`/`completed`/`failed`) est chargé côté client (`Editor.vue`, `Show.vue`) mais n'est affiché nulle part — un échec d'extraction reste invisible, contrairement au traitement du document parent qui expose déjà `pendingExtractions`.
  evidence: Blind Hunter (step-04 review) — non requis par les AC/la matrice I/O de la spec (silencieux par design, cf. "best-effort, ne bloque jamais l'attachement") ; ajouter un indicateur visuel est un vrai gain UX mais hors du périmètre approuvé, à envisager si l'absence de retour utilisateur s'avère gênante en usage réel.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `useFileDropZone` (extrait d'`ImportModal.vue`, désormais partagé avec `AttachmentsPanel.vue`) traite un sélecteur de fichier annulé par l'utilisateur (aucun fichier choisi) comme un format non supporté, affichant "Format non supporté" au lieu de ne rien faire.
  evidence: Blind Hunter (step-04 review) — défaut préexistant dans `ImportModal.vue` avant cette story ; le refactor en composable le duplique désormais vers un second composant au lieu de le corriger. Correctif trivial (`if (!file) return;` en tête de `validationError()`) si une session future retouche ce fichier.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucun test ne monte le vrai composant `ImportModal.vue` — `Index.spec.js` le stub entièrement (`ImportModal: true`) — alors que sa logique de validation/glisser-déposer a été extraite vers `useFileDropZone` par cette story ; une régression dans le composable partagé, atteinte via `ImportModal.vue`, ne ferait échouer aucun test.
  evidence: Verification Gap Reviewer (step-04 review) — confirmé préexistant : `ImportModal.vue` n'avait déjà aucun fichier de test dédié avant cette story (constat identique dans le rapport d'investigation step-02) ; le refactor déplace le risque sans l'introduire. Corriger nécessiterait un `ImportModal.spec.js` dédié (même patron qu'`AttachmentsPanel.spec.js`), hors périmètre de cette story de fonctionnalité.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: `DocumentAttachmentController` (store/destroy/preview/download) n'impose aucune restriction aux documents `source=created` — un document importé pourrait recevoir/perdre des pièces jointes via une requête directe à ces routes, alors que l'intent de la story les réserve aux documents rédigés dans l'Éditeur (aucun chemin UI n'y mène pour un document importé).
  evidence: Blind Hunter (step-04 review) — cohérent avec la posture de sécurité déjà en place partout ailleurs dans l'app (NFR3 : "aucune authentification n'existe en v1, toujours autorisé") ; pas une régression introduite par cette story, à durcir en bloc si l'app gagne un jour une notion d'autorisation.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucune limite n'existe sur le nombre de pièces jointes par document ; `Document::syncAttachmentsExtractedText()` concatène sans borne le texte extrait de toutes les pièces jointes dans `attachments_extracted_text`, ensuite scanné par `LIKE` (driver Scout `database`).
  evidence: Blind Hunter (step-04 review) — non couvert par les Boundaries/la matrice I/O de la spec ; risque théorique de dégradation à grande échelle, sans rapport avec la cible de performance de l'epic (~350 documents, pas de borne sur le nombre de pièces jointes par document) ; à revisiter si un usage réel accumule beaucoup de pièces jointes sur un même document.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-3-attach-documents.md`
  summary: Aucune distinction visuelle (icône/couleur) entre pièces jointes PDF/Word/Excel dans `AttachmentsPanel.vue`/`Show.vue` — la liste n'affiche qu'un nom de fichier brut, alors que `mime_type` est chargé sur chaque ligne.
  evidence: Blind Hunter (step-04 review) — non requis par la spec ; amélioration UX à bas coût si une session future retouche ces composants (réutiliser `DocumentTypeBadge.vue`, déjà disponible pour le document parent).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-4-search-surface.md`
  summary: `Sidebar.vue` calcule `isLibraryActive` comme `!isSearchActive` (binaire) plutôt qu'une vérification explicite par surface — fonctionne tant qu'il n'existe que deux items de nav, mais un 3ᵉ item (Configuration, Story 3.5) ferait à tort passer "Bibliothèque" actif dessus aussi.
  evidence: Blind Hunter (step-04 review) — comportement conforme à l'intent gelé de cette story (spec-3-4 : "sans changer le comportement d'activation de Bibliothèque sur Fiche document/Éditeur"), pas un défaut introduit hors scope ; à corriger explicitement (vérification par surface plutôt que par exclusion) quand la Story 3.5 ajoute l'item Configuration.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Deux requêtes concurrentes (double-clic, deux onglets) peuvent toutes deux passer la validation de doublon insensible à la casse avant qu'aucune n'ait encore inséré sa ligne, créant deux tags variantes de casse de la même chaîne.
  evidence: Blind Hunter + Edge Case Hunter (step-04 review, convergents) — la contrainte unique DB existante sur `tags.name` (migration spec-3-1) est sensible à la casse, donc ne bloque pas ce cas ; corriger proprement nécessiterait un index unique insensible à la casse (migration) ou une transaction avec re-vérification, hors proportion pour ce diff. Impact quasi nul pour un outil interne mono-utilisateur (NFR3, pas d'authentification).

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: La création et le renommage d'un tag ne produisent aucune confirmation annoncée (pas de `role="status"`/`aria-live`), contrairement à la suppression qui a son message factuel.
  evidence: Blind Hunter (step-04 review) — non requis par les Boundaries & Constraints ni les Acceptance Criteria de spec-3-5 (qui exigent seulement la navigation clavier/focus visible), amélioration d'accessibilité au-delà du périmètre figé.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Les champs de création/renommage de tag n'ont pas d'`aria-describedby` reliant le texte d'erreur (`role="alert"`) au champ concerné.
  evidence: Blind Hunter (step-04 review) — l'erreur est déjà annoncée via `role="alert"`, ceci est un raffinement d'association programmatique supplémentaire, hors périmètre figé de spec-3-5.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-5-tags-configuration.md`
  summary: Le reste de la page (formulaire de création, liste) n'est pas marqué `inert`/`aria-hidden` pendant que la boîte de dialogue de suppression est ouverte.
  evidence: Blind Hunter (step-04 review) — pattern préexistant identique dans `Show.vue` (dialogue de suppression document, spec-1-8), reproduit fidèlement par spec-3-5 sans régression propre à cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: "Supprimer le tableau" retire le tableau sans boîte de dialogue de confirmation — un clic accidentel supprime instantanément le tableau et son contenu.
  evidence: Blind Hunter (step-04 review) — atténué par l'historique d'annulation TipTap (Ctrl+Z, `StarterKit`) disponible tant que le document n'est pas rechargé, contrairement aux suppressions permanentes (document/tag) qui ont, elles, une boîte de confirmation ; à revisiter si des pertes accidentelles sont rapportées en usage réel.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: Coller un extrait HTML externe contenant un `<table>` alors que le curseur est déjà dans un tableau existant n'est pas gardé par le correctif de cette story — seul le bouton "Insérer un tableau" est protégé, pas le collage, donc le même bug (tableau imbriqué) reste atteignable par ce second chemin.
  evidence: Blind Hunter (step-04 review) — vecteur jugé rare pour un outil interne mono-utilisateur (usage typique : rédaction native dans l'Éditeur, pas de collage de HTML externe avec tableaux) ; corriger nécessiterait une garde côté schéma ProseMirror (`appendTransaction`) plutôt qu'un simple guard sur le bouton, hors proportion pour cette story.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: Le comportement exact de `deleteTable()` lorsqu'il est invoqué depuis l'intérieur d'un tableau imbriqué préexistant (créé avant ce correctif) n'est ni spécifié ni testé — seul le chargement sans erreur de ce contenu legacy est couvert par l'AC/la matrice I/O, pas sa suppression.
  evidence: Blind Hunter (step-04 review) — scénario rare (suppose qu'un tel document legacy existe déjà en base) ; le comportement par défaut de la commande TipTap native reste raisonnable par construction (aucune manipulation DOM manuelle ajoutée par cette story), mais n'a pas été vérifié manuellement pour ce cas précis.

- source_spec: `_bmad-output/implementation-artifacts/spec-3-6-nested-table-fix.md`
  summary: `Editor.spec.js` (nouveau) ne suit pas la convention `attachTo: document.body` + `wrapper.unmount()` déjà en place dans `Configuration.spec.js`/`Search.spec.js` — les 5 montages du fichier n'appellent jamais `onBeforeUnmount`, donc le nettoyage du listener `beforeunload` et de l'abonnement `router.on('before', ...)` d'`Editor.vue` n'est jamais exercé par ce test (accumulation silencieuse across les 5 `mount()`, sans échec observé sur la suite actuelle).
  evidence: Blind Hunter (step-04 review, itération 2) — suite complète (80/80) verte malgré cette lacune ; à corriger si `Editor.spec.js` grossit ou si des avertissements de fuite apparaissent, en alignant sur le patron déjà établi par les specs voisines du même dossier.
