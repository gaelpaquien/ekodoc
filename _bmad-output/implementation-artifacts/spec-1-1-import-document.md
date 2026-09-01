---
title: 'Story 1.1 — Importer un document existant'
type: 'feature'
created: '2026-08-31'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: '2ee5842cc14af4fc8db1f5c4c977cdb9dd37c77b'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** EkoDoc n'existe pas encore — aucun projet, aucune base pour importer un document PDF/Word/Excel existant et le retrouver plus tard.

**Approach:** Amorcer un projet Laravel 13 frais (Inertia.js 3.0 + Vue 3 + Vite 8.x + Tailwind CSS 4.x, sans starter d'auth) avec la table `documents`, puis livrer le flux d'import complet : modale drag-and-drop → validation de format → stockage privé → extraction de texte best-effort → redirection vers la Fiche document.

## Boundaries & Constraints

**Always:**
- Installation Laravel 13 manuelle, sans Breeze/Jetstream ni aucun scaffolding d'authentification (NFR3).
- Paradigme Controller (fin) → Action → DTO → Model ; `ImportDocumentAction` est le seul point d'écriture du fichier et du `Document`.
- Fichier original stocké sur `storage/app/private/documents/{id}/{filename}`, jamais sur le disque `public` (AD-7).
- Formats acceptés à l'import : PDF, `.docx`, `.xlsx` uniquement (NFR4). Tout autre format → erreur immédiate nommant les formats acceptés, aucun envoi serveur, la modale reste ouverte.
- Extraction de texte (`smalot/pdfparser`, `phpoffice/phpword`, `phpoffice/phpspreadsheet`) tourne dans le même traitement HTTP synchrone que l'import (AD-6). Un échec d'extraction n'interrompt jamais l'import : `extracted_text` reste `NULL`, un warning est loggé (AD-9).
- Après succès, redirection Inertia directe vers la Fiche document du fichier importé — jamais de page d'import séparée.
- Modale d'import intégralement navigable au clavier, focus visible (UX-DR25).
- Code, classes et logs en anglais ; interface utilisateur en français.

**Ask First:**
- Domaine local Herd et point de lien (racine du repo vs sous-dossier), si ce n'est pas déjà décidé au moment de l'implémentation.
- Nom de la base MySQL locale à créer, si aucune convention n'existe déjà.

**Never:**
- Authentification, rôles ou permissions (NFR3).
- File d'attente / job pour l'import — traitement synchrone uniquement (AD-6).
- API JSON ou `Route::apiResource` (AD-13) — pages Inertia uniquement.
- OCR sur PDF scanné en v1.
- Gestion des catégories/dossiers — hors scope, couvert par la Story 1.5.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Import PDF valide | `.pdf` déposé/sélectionné | `Document` (`source=imported`) créé, fichier stocké, `extracted_text` rempli, redirection vers la Fiche document | N/A |
| Import Word/Excel valide | `.docx`/`.xlsx` déposé | Idem, extraction via `phpoffice/phpword`/`phpspreadsheet` | N/A |
| Format non supporté | `.pptx` déposé | Aucun envoi serveur ; message d'erreur nommant les formats acceptés ; modale reste ouverte | Validation côté client puis serveur (`ImportDocumentRequest`) |
| Échec d'extraction (ex. PDF scanné) | Fichier valide, extraction impossible | `Document` créé quand même, `extracted_text=NULL`, warning loggé | Bloc try/catch isolé dans `ImportDocumentAction`, ne bloque jamais l'import |
| Fichier trop volumineux / corrompu | Fichier illisible par le parseur | Import du fichier accepté (stockage OK) ; seule l'extraction échoue silencieusement comme ci-dessus | Idem cas précédent |

</frozen-after-approval>

## Code Map

- Repo racine -- vide hors `.claude/`, `_bmad/`, `_bmad-output/`, `.git/` : aucun code existant. Cible d'installation Laravel directement à la racine (`composer create-project laravel/laravel .`), sans conflit avec les dossiers BMad déjà présents.
- `_bmad-output/planning-artifacts/architecture/architecture-ekodoc-2026-08-31/ARCHITECTURE-SPINE.md` -- référence stack/paradigme (AD-1 à AD-10).
- `_bmad-output/implementation-artifacts/epic-1-context.md` -- contexte distillé de l'Epic 1, à charger avec cette spec.

## Tasks & Acceptance

**Execution:**
- [x] `composer.json` -- `composer create-project laravel/laravel .` (PHP 8.5), retirer tout scaffolding d'auth si généré -- amorce le projet
- [x] `package.json`, `vite.config.js`, `resources/css/app.css` -- installer et câbler manuellement Inertia.js 3.0 + Vue 3 + Vite 8.x + Tailwind CSS 4.x (config CSS-first, pas de `tailwind.config.js`) -- respecte le stack décidé, aucun starter officiel
- [x] `database/migrations/xxxx_create_documents_table.php` -- table `documents` (`id`, `title`, `source` enum, `file_path`, `mime_type`, `extracted_text` nullable, timestamps) -- AD-4, table unifiée import/création
- [x] `app/Models/Document.php` -- modèle Eloquent, cast enum `source` -- socle données
- [x] `app/Actions/ImportDocumentAction.php` -- valide, stocke sur disque privé, extrait le texte best-effort, crée le `Document` -- seul point d'écriture (paradigme Action)
- [x] `app/Http/Requests/ImportDocumentRequest.php` -- valide extension/MIME (pdf/docx/xlsx) et taille -- garde-fou serveur
- [x] `app/Http/Controllers/DocumentController.php` -- `store()` fin : construit le DTO, appelle l'Action, redirige vers la Fiche document -- paradigme Controller
- [x] `app/DataTransferObjects/ImportDocumentData.php` -- DTO `readonly` -- frontière de l'Action
- [x] `resources/js/Pages/Documents/Show.vue` -- Fiche document minimale (titre, type, date) -- cible de la redirection post-import
- [x] `resources/js/Components/ImportModal.vue` -- dropzone/sélecteur, erreurs de format inline, focus clavier géré -- UX-DR17, UX-DR25
- [x] `tests/Feature/ImportDocumentTest.php` -- couvre la matrice I/O ci-dessus -- Pest, red-green-refactor

**Acceptance Criteria:**
- [x] Given un fichier PDF/`.docx`/`.xlsx` valide, when il est importé via la modale, then un `Document` (`source=imported`) existe en base avec titre/type/date, le fichier est sur le disque privé, et l'utilisateur atterrit sur sa Fiche document
- [x] Given un format non supporté, when il est déposé dans la zone d'import, then aucun `Document` n'est créé, le message d'erreur nomme les formats acceptés, et la modale reste ouverte
- [x] Given l'extraction de texte échoue pour un fichier par ailleurs valide, when l'import se termine, then le `Document` existe avec `extracted_text=NULL` et aucune exception non gérée ne remonte à l'utilisateur
- [x] Given la modale d'import est ouverte, when l'utilisateur navigue uniquement au clavier, then tous les contrôles (dropzone, bouton parcourir, fermeture) sont atteignables avec un focus visible (implémenté via un focus trap manuel + `role="dialog"`/`aria-modal`/focus visible Tailwind ; non couvert par un test automatisé JS — aucun outil Dusk/Playwright dans la stack, vérification manuelle requise, voir Verification)

## Spec Change Log

<!-- Append-only, peuplé par step-04 lors des boucles de revue. -->

### 2026-09-01 — Correctif post-clôture : POST trop volumineux non géré

- **Découvert par l'utilisateur** en testant un import PDF réel (~10 Mo) : `PostTooLargeException` non catchée → page 500 brute, au lieu du message convivial attendu. Ce trou avait été identifié par l'Edge Case Hunter en step-04 (finding "File exceeds PHP's upload_max_filesize/post_max_size but is under the app's 20MB rule") mais n'avait, par erreur, pas été inclus dans le lot de correctifs envoyés à l'agent d'implémentation — écart assumé.
- **Cause racine** : `php.ini` de l'installation Herd locale avait `upload_max_filesize=2M` / `post_max_size=8M` (valeurs par défaut PHP), bien en-deçà de la limite applicative de 20 Mo — un fichier valide de taille normale était rejeté par PHP lui-même avant même que Laravel ne traite la requête.
- **Corrigé** :
  1. `php.ini` (`C:\Users\g.paquien\.config\herd\bin\php85\php.ini`) : `upload_max_filesize` → `22M`, `post_max_size` → `25M` (marge au-dessus des 20 Mo applicatifs), `memory_limit` → `256M` (marge pour l'extraction phpword/phpspreadsheet sur des fichiers volumineux, en lien avec le risque mémoire déjà noté dans `deferred-work.md`). Changement local à cette machine, hors du dépôt Git.
  2. `bootstrap/app.php` : ajout d'un handler `$exceptions->render(PostTooLargeException::class, ...)` qui redirige vers la page précédente avec le même message d'erreur convivial que `ImportDocumentRequest::messages()['file.max']`, en défense en profondeur — au cas où la limite `post_max_size` d'une future machine/déploiement serait de nouveau trop basse, l'échec reste un message explicite et non une exception non gérée (cohérent avec la convention "jamais d'exception non gérée").
- Vérification : `php artisan test` → 11/11 toujours au vert (suite non affectée, ce cas n'est pas couvert par un test automatisé — le déclenchement réel dépend de `post_max_size`, difficile à tester unitairement sans alourdir la suite ; vérification manuelle en conditions réelles requise, voir Verification).

### 2026-09-01 — Correctif post-clôture : timeout fatal sur l'extraction d'un vrai gros PDF

- **Découvert par l'utilisateur** en testant un import réel d'un PDF de 135 pages (~10 Mo, "Formation SPEED v2") : chargement de ~30s puis plus rien — aucun document créé, ni en base ni sur le disque.
- **Cause racine** : `ImportDocumentAction::extractText()` tournait à l'intérieur de la même transaction MySQL que `Document::create()`/le stockage du fichier. `php.ini` avait `max_execution_time=30` ; `smalot/pdfparser` sur un PDF réel complexe de 135 pages dépasse ce budget → PHP tue le script avec une erreur fatale **non rattrapable par un `try`/`catch`** → la transaction ne committe jamais → import entièrement perdu, en contradiction directe avec la garantie "l'échec d'extraction n'interrompt jamais l'import" (AD-9).
- **Corrigé** :
  1. `app/Actions/ImportDocumentAction.php` : l'extraction de texte est sortie de la transaction MySQL — elle s'exécute désormais *après* que le `Document` et le fichier soient déjà committés. Un échec catastrophique (y compris un timeout fatal) ne peut plus jamais annuler un import déjà réussi ; au pire, `extracted_text` reste `NULL`.
  2. `set_time_limit(180)` ajouté juste avant l'extraction, pour laisser un vrai budget à un document volumineux/complexe de terminer son extraction avec succès plutôt que d'échouer inutilement.
  3. `php.ini` : `max_execution_time` → `180` (défense en profondeur, cohérent avec le point 2).
- **Limite connue** : si l'hébergement local de Herd applique un timeout au niveau du pool PHP-FPM (`request_terminate_timeout`) distinct de `max_execution_time`, `set_time_limit()` ne peut pas le contourner — à vérifier si le problème persiste malgré ce correctif.
- Vérification : `php artisan test` → 11/11 toujours au vert. Reproduction du timeout non couverte par un test automatisé (nécessiterait un vrai fichier volumineux/complexe ou un sleep artificiel, coûteux pour la suite) ; vérification manuelle avec le fichier réel de l'utilisateur requise.
- **Suite (même jour)** : après ce correctif, le document volumineux était bien créé/stocké (comportement voulu), mais une 502 apparaissait côté navigateur et `extracted_text` restait `NULL` — la limite en cause n'était pas `php.ini` mais le proxy **nginx** de Herd, dont le timeout FastCGI par défaut (60s, non explicite dans la config) est plus court que le budget PHP de 180s : nginx coupe la réponse au client avant que PHP n'ait fini, même si PHP continue et committe en arrière-plan. Corrigé en ajoutant `fastcgi_read_timeout 200s` / `fastcgi_send_timeout 200s` à `C:\Users\g.paquien\.config\herd\config\valet\Nginx\ekodoc.test.conf` (config générée par Herd pour ce site — hors du dépôt Git, local à la machine ; **note** : un futur `herd link`/`herd secure` sur ce site pourrait régénérer ce fichier et effacer cet ajout, à surveiller). `herd restart` exécuté pour appliquer.

### 2026-09-01 — Révision d'architecture : extraction de texte passée en file d'attente (AD-6 amendé)

- **Décision utilisateur** : plutôt que d'empiler indéfiniment des augmentations de timeout (PHP + nginx), sortir l'extraction de texte du cycle de requête HTTP via une vraie file d'attente Laravel, avec un panneau de suivi visible en UI pour les extractions en cours/en attente. `AD-6` de `ARCHITECTURE-SPINE.md` amendé en conséquence (`[AMENDED 2026-09-01]`, decision loggée dans le memlog de l'Epic Architecture) ; `AD-9` mis à jour pour refléter le nouveau mécanisme.
- **Implémenté** :
  - `app/Enums/ExtractionStatus.php` (`pending`/`processing`/`completed`/`failed`) + colonne `documents.extraction_status` (migration `2026_09_01_115918_add_extraction_status_to_documents_table.php`, défaut `completed` pour les lignes historiques déjà traitées à l'ancienne).
  - `app/Jobs/ExtractDocumentTextJob.php` : reprend telle quelle la logique d'extraction (précédemment dans `ImportDocumentAction`), s'exécute hors requête HTTP via la queue `database`. Passe `extraction_status` à `processing` au démarrage, `completed`/`failed` à la fin.
  - `ImportDocumentAction` : ne fait plus que stocker + créer le `Document` (`extraction_status = pending`) dans sa transaction, puis `ExtractDocumentTextJob::dispatch($document)` — la réponse HTTP revient immédiatement après le stockage, plus jamais bloquée par l'extraction. `set_time_limit(180)` retiré (plus nécessaire, la requête ne fait plus le travail long).
  - `HandleInertiaRequests::share()` : nouvelle prop partagée `pendingExtractions` (documents `pending`/`processing`).
  - `resources/js/Components/ExtractionTasksPanel.vue` : petit panneau dépliable/pliable en bas d'écran, listant les extractions en cours/en attente, avec sondage (`router.reload({ only: ['pendingExtractions'] })`) toutes les 3s tant que la liste n'est pas vide. Intégré dans `AppLayout.vue`.
- **Compromis assumé** (documenté dans AD-6) : nécessite qu'un worker tourne (`php artisan queue:work` ou `queue:listen`) pour que l'extraction se fasse réellement — en dev, à lancer manuellement à côté du site Herd. Sans worker actif, les imports restent `pending` indéfiniment (visibles dans le panneau, mais jamais traités) ; c'est un compromis conscient, pas une régression silencieuse.
- Tests ajoutés (`tests/Feature/ImportDocumentTest.php`) : dispatch du job vérifié (`Queue::fake()` + `Queue::assertPushed`), statut `pending` immédiatement après import, présence/absence dans la prop `pendingExtractions`, `extraction_status` explicite sur les chemins succès (`Completed`) et échec (`Failed`).
- Vérification : `php artisan test` → 14/14 (95 assertions) ; `npm run build` → OK. `php artisan migrate` exécuté contre MySQL local.

### 2026-09-01 — Correctif : le worker plantait par épuisement mémoire sur le même PDF réel

- **Découvert par l'utilisateur** en lançant le worker sur le vrai PDF de 135 pages : `Allowed memory size of 268435456 bytes exhausted`. Même famille de problème que le timeout précédent — une erreur fatale PHP non rattrapable, mais cette fois côté mémoire plutôt que temps, et qui plante le **worker lui-même** (pas juste la requête HTTP, puisque l'extraction tourne maintenant en file d'attente). Risque aggravant identifié : sans garde-fou, le job resterait "reserved" après le crash puis serait automatiquement repris par un worker relancé après expiration du délai de réservation — replantant indéfiniment sur le même fichier.
- **Corrigé** :
  1. `php.ini` : `memory_limit` remonté de `256M` à `1024M` (marge généreuse, cohérent avec un poste de dev solo sans pression de concurrence).
  2. `app/Jobs/ExtractDocumentTextJob.php` : ajout d'un `register_shutdown_function` détectant une erreur fatale non rattrapée (mémoire, mais aussi tout autre `E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`) au moment où le script se termine anormalement. Si détecté : `extraction_status = failed` et suppression explicite du job de la file (`$this->job->delete()`) pour empêcher toute nouvelle tentative automatique.
- **Limite assumée** : si le pic mémoire dépasse même 1024 Mo, le document reste marqué `failed` proprement (plus de boucle infinie), mais son texte ne sera jamais indexé — limite acceptée pour l'instant, à revisiter si des documents encore plus volumineux/complexes apparaissent en usage réel.
- Vérification : `php artisan test` → 14/14 toujours au vert. Le scénario de crash mémoire réel n'est pas couvert par un test automatisé (nécessiterait un vrai gros fichier ou un `memory_limit` artificiellement bas dans le test, fragile) ; vérification manuelle avec le fichier réel de l'utilisateur, worker relancé après ce correctif.
- **Note opérationnelle** : contrairement à `php artisan` (CLI, relit `php.ini` à chaque appel) et au site web (relu via `herd restart`), un `php artisan queue:work` déjà lancé est un processus long qui a chargé l'ancien `php.ini` au démarrage — il faut l'arrêter et le relancer pour qu'il prenne en compte le nouveau `memory_limit`.

### 2026-09-01 — Implémentation initiale

- Décisions "Ask First" non tranchées avec l'utilisateur (aucun moyen de le faire pendant cette implémentation) : domaine Herd et nom de la base MySQL locale. **Choix par défaut appliqué** : `.env` reste sur `DB_CONNECTION=sqlite` (défaut du skeleton Laravel 13, aucune dépendance externe requise) au lieu de MySQL via Herd — `herd services:list` a confirmé que les services (dont MySQL) nécessitent Herd Pro, non installé sur ce poste, et `127.0.0.1:3306` n'est pas joignable. À revoir avec l'utilisateur avant d'aller plus loin sur les stories suivantes si MySQL est réellement souhaité.
- Fichiers Boost (`AGENTS.md`, `CLAUDE.md`) générés par défaut par `composer create-project` ont été supprimés : ils ne font pas partie du Code Map et duplique/contredit les conventions d'agents déjà en place dans ce repo (`.claude/`, `_bmad/`).
- Ajout non listé dans les Tasks mais nécessaire : `app/Enums/DocumentSource.php` (enum backé `imported|created`, utilisé par la migration et le cast du modèle) et une page `resources/js/Pages/Documents/Index.vue` minimale (liste + bouton "Importer") servant de point d'entrée pour héberger `ImportModal.vue`, puisque aucune story de bibliothèque (1.2) n'existe encore pour l'accueillir. Rien au-delà d'un lien vers la Fiche document n'y a été construit — le filtrage/recherche/carte restent hors scope (Story 1.2).
- `tests/Pest.php` : `RefreshDatabase` activé (était commenté par défaut) — nécessaire pour que les tests Feature disposent de la table `documents` en SQLite mémoire.
- Tests par défaut du skeleton (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`) supprimés : ils testaient la page d'accueil Blade désormais remplacée par la page Inertia `Documents/Index`.
- Critère d'acceptation clavier : couvert par l'implémentation (focus trap, `role="dialog"`, focus visible) mais pas par un test automatisé — aucun outil de test navigateur (Dusk/Playwright) dans la stack retenue par l'architecture. Vérification manuelle nécessaire (voir section Verification).

### 2026-09-01 — Résolution de la décision "Ask First" : base de données

- Décision utilisateur : MySQL installé indépendamment de Herd (Herd étant en édition gratuite, sans le service MySQL). MySQL Community Server 8.0 installé localement (service Windows `MySQL80`), son dossier `bin` ajouté au PATH utilisateur.
- Base `ekodoc` créée (utf8mb4/utf8mb4_unicode_ci) ; `.env` basculé de `DB_CONNECTION=sqlite` à `mysql` (`DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=ekodoc`, `DB_USERNAME=root`).
- `php artisan migrate:fresh` exécuté avec succès contre MySQL ; `php artisan test` rejoué (8/8, isolé sur SQLite mémoire via `phpunit.xml`, sans changement) — aucune régression.
- L'architecture (`ARCHITECTURE-SPINE.md`, stack MySQL 8.x) est donc respectée telle quelle ; aucune mise à jour de la spine nécessaire.

### 2026-09-01 — Step 4 review : 6 correctifs "patch" appliqués

Revue à trois lentilles (Blind Hunter, Edge Case Hunter, Verification Gap) sur l'implémentation initiale. Six findings classés "patch" (correctifs mécaniques, intent de la spec inchangé) :

1. **Ligne `Document` orpheline en cas d'échec de stockage** — `app/Actions/ImportDocumentAction.php` : `Document::create()` s'exécutait avant `storeAs()`, et la valeur de retour de `storeAs()` (potentiellement `false`, le disque `local` ayant `throw => false`) n'était jamais vérifiée. Corrigé : tout `__invoke()` tourne désormais dans `DB::transaction()`, `storeAs()` est vérifié explicitement (`false` déclenche une `RuntimeException`), le dossier de destination est nettoyé (`deleteDirectory`) et l'erreur est loguée avant d'être relancée — la transaction annule alors la ligne `Document` créée, aucune ligne orpheline possible.
2. **Test manquant : limite de taille (20 Mo)** — ajouté dans `tests/Feature/ImportDocumentTest.php` (`rejects a file over the 20MB limit and creates no document`).
3. **Test manquant : `index()` avec des données réelles** — ajouté (`lists previously imported documents on the index page`), vérifie que le document importé apparaît bien dans la prop `documents` avec son `id`/`title`.
4. **Extraction basée sur la mauvaise donnée de type de fichier** — `extractText()` se basait sur `getClientOriginalExtension()` (extension du nom de fichier fourni par le client) alors que la validation `ImportDocumentRequest` (règle `mimes`) classe par contenu réel détecté (`guessExtension()`/MIME réel). Un fichier valide dont l'extension du nom ne correspond pas à son contenu réel passait la validation mais tombait dans `default => null` à l'extraction. Corrigé : dispatch désormais basé sur `$document->mime_type` (le MIME réellement détecté à l'upload, `getMimeType()`), mappé vers pdf/docx/xlsx via `formatFromMimeType()`. Test ajouté : `extracts text based on the real file content even when the filename extension does not match`.
5. **Pas de vérification de taille côté client** — `resources/js/Components/ImportModal.vue` : `handleFile()` ne vérifiait que l'extension avant l'envoi ; un fichier trop volumineux était intégralement envoyé avant le rejet serveur. Ajout d'une vérification `file.size > 20 * 1024 * 1024` affichant le même message immédiatement, sans aller-retour réseau.
6. **Pas de garde contre la fermeture pendant un envoi en cours** — `close()` (bouton fermer / touche Échap) ignorait l'état `form.processing`, permettant à l'utilisateur de croire avoir annulé alors que l'import se terminait et redirigeait quand même. Corrigé : `close()` ne fait plus rien tant que `form.processing` est vrai ; le bouton de fermeture est désormais `:disabled`/`aria-disabled` pendant l'envoi, cohérent avec le bouton "Parcourir" qui avait déjà cette garde.

Vérification post-correctifs : `php artisan test` → 11/11 passés (67 assertions) ; `npm run build` → build Vite sans erreur.

Deux findings de la revue n'ont volontairement PAS été traités ici (hors scope "patch") : un faux positif Blind Hunter sur des fixtures de test soi-disant manquantes (elles existent, binaires, non lisibles par l'outil de revue en extrait texte), et une piste de durcissement XXE/mémoire sur fichiers volumineux, journalisée dans `deferred-work.md` plutôt que traitée dans cette story (hors scope pour un outil mono-utilisateur local).

## Design Notes

Le câblage Laravel 13 + Inertia 3 + Vue 3 + Vite 8 + Tailwind 4 se fait à la main : aucun starter officiel (Breeze/Jetstream) ne couvre ces versions sans scaffolding d'auth indésirable. Tailwind 4 utilise une configuration CSS-first (`@import "tailwindcss"` dans `app.css`, pas de `tailwind.config.js` par défaut) — ne pas reproduire les patterns Tailwind 3.

## Verification

**Commands:**
- `php artisan test --filter=ImportDocumentTest` -- expected: tous les tests passent
- `npm run build` -- expected: build Vite sans erreur

**Manual checks (if no CLI):**
- Ouvrir le site Herd local et importer réellement un PDF, un `.docx` et un `.xlsx` depuis l'UI ; vérifier la redirection vers la Fiche document et la présence du fichier sur `storage/app/private/documents/`.

## Suggested Review Order

**Point d'entrée : action d'import (transaction + extraction)**

- Point d'entrée du flux : transaction protégeant la création du `Document` et le stockage du fichier contre un échec partiel.
  [`ImportDocumentAction.php:31`](../../app/Actions/ImportDocumentAction.php#L31)

- Stockage du fichier original : échec explicite (`RuntimeException` + nettoyage) si `storeAs()` échoue, plutôt qu'un `file_path` invalide silencieux.
  [`ImportDocumentAction.php:58`](../../app/Actions/ImportDocumentAction.php#L58)

- Extraction de texte best-effort : dispatch désormais sur le MIME réel détecté, plus sur l'extension du nom de fichier client.
  [`ImportDocumentAction.php:93`](../../app/Actions/ImportDocumentAction.php#L93)

- Mapping MIME → format de parseur : corrige la divergence entre validation (contenu réel) et extraction (ex-extension client).
  [`ImportDocumentAction.php:118`](../../app/Actions/ImportDocumentAction.php#L118)

**Validation & schéma**

- Garde-fou serveur : formats acceptés + plafond de taille (20 Mo), messages d'erreur explicites en français.
  [`ImportDocumentRequest.php:21`](../../app/Http/Requests/ImportDocumentRequest.php#L21)

- Table `documents` unifiée (import/création future) : colonnes spécifiques nullable pour rester partagée avec l'Epic 2.
  [`create_documents_table.php:15`](../../database/migrations/2026_08_31_152623_create_documents_table.php#L15)

**Routage & Controller (paradigme Controller fin → Action)**

- Trois routes Inertia (pas d'API JSON) : point d'entrée navigable pour comprendre le flux complet.
  [`web.php:6`](../../routes/web.php#L6)

- `store()` : construit le DTO, appelle l'Action, redirige vers la Fiche document — aucune logique métier dans le controller.
  [`DocumentController.php:29`](../../app/Http/Controllers/DocumentController.php#L29)

**UI d'import (modale)**

- Validation client de taille avant envoi : miroir du garde-fou serveur pour éviter un upload inutile.
  [`ImportModal.vue:39`](../../resources/js/Components/ImportModal.vue#L39)

- Traitement du fichier (drop/sélection) : vérifie format puis taille avant `form.post`.
  [`ImportModal.vue:43`](../../resources/js/Components/ImportModal.vue#L43)

- Fermeture bloquée pendant un import en cours (Échap ou bouton) : évite une redirection surprise après un "annuler" apparent.
  [`ImportModal.vue:85`](../../resources/js/Components/ImportModal.vue#L85)

**Tests**

- Tests ajoutés lors de la revue : limite de taille, cohérence MIME/extension, listing de l'index avec données réelles.
  [`ImportDocumentTest.php:75`](../../tests/Feature/ImportDocumentTest.php#L75)
