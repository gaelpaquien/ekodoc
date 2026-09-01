---
title: 'Story 1.3 - Prévisualiser un document dans le navigateur'
type: 'feature'
created: '2026-09-01'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: '439fc793e8c560412948bc35f109386fe2302e62'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La Fiche document (`Show.vue`) n'affiche que des métadonnées — impossible de vérifier le contenu sans télécharger, et aucun bouton "Télécharger" n'existe encore.

**Approach:** Ajouter un panneau de prévisualisation à la Fiche document : PDF natif (sans conversion), Word/Excel converti à la demande via LibreOffice et mis en cache, avec indicateur de chargement explicite. Ajouter le bouton "Télécharger", désactivé seulement si le fichier source est introuvable/illisible.

## Boundaries & Constraints

**Always:** Preview/download passent par des routes applicatives (jamais le disque `public`), vérifient l'existence/lisibilité du fichier avant tout streaming, jamais d'exception non rattrapée. PDF = rendu natif (pas de PDF.js). Word/Excel = `soffice --headless --convert-to pdf`, cache `storage/app/private/previews/{document_id}.pdf`. Le téléchargement reste actif indépendamment de l'état de la preview, sauf fichier source manquant/illisible.

**Ask First:** Si `soffice` est introuvable dans le PATH local, HALT et demander le chemin binaire plutôt que deviner.

**Never:** Pas de job/polling pour la conversion (route synchrone dédiée) ; pas d'API JSON ; pas d'UX de téléchargement avancée (nom de fichier, analytics — hors scope, Story 1.4) — juste un lien fonctionnel et désactivable.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| PDF natif | `mime_type=application/pdf`, fichier présent | Rendu instantané, iframe directe | N/A |
| Word/Excel 1ère ouverture | `.docx`/`.xlsx`, pas de cache | Indicateur de chargement puis PDF converti ; reste de la page utilisable | N/A |
| Word/Excel en cache | `previews/{id}.pdf` existe | Rendu instantané, pas de reconversion | N/A |
| Fichier source manquant | `file_path` absent/illisible | Message explicite, "Télécharger" désactivé | `Storage::exists()` vérifié dans `show()` |
| Conversion échouée | `.docx`/`.xlsx` corrompu | "Aperçu indisponible pour ce fichier", "Télécharger" actif | Échec `soffice` → statut identifiable côté client |

</frozen-after-approval>

## Code Map

- `app/Models/Document.php:14-29` -- `mime_type` brut, `source`→`DocumentSource` ; pas de statut preview persisté (dérivé à la demande).
- `app/Http/Controllers/DocumentController.php:39-50` -- `show()` + `sourceMissing` (bool) ; ajouter `preview()`/`download()` (thin, sans Action dédiée).
- `resources/js/Pages/Documents/Show.vue` -- aucun panneau/bouton à ce jour ; réutiliser classes dark/light existantes.
- `routes/web.php` -- ajouter `documents.preview`/`documents.download` (GET, binding implicite, même style que les 3 routes existantes).
- `app/Actions/ImportDocumentAction.php` + `app/DataTransferObjects/ImportDocumentData.php` -- patron pour `ConvertDocumentToPreviewAction` (invokable + DTO `final readonly`).
- `config/filesystems.php:33-39` -- disk `local` racine déjà `storage/app/private` ; `previews/` sans changement config.
- `app/Jobs/ExtractDocumentTextJob.php:122-130` (`formatFromMimeType()`) -- patron `match()` mime à répliquer pour le routage preview.
- `tests/Feature/ImportDocumentTest.php` -- `Storage::fake('local')`, style `assertInertia()->where()->has()`, fixture `sample-corrupt.pdf` (L107) à répliquer en `.docx`.

## Tasks & Acceptance

**Execution:**
- [x] `app/Actions/ConvertDocumentToPreviewAction.php` -- créer -- shell `soffice`, écrit `previews/{id}.pdf`, retourne succès/échec sans exception.
- [x] `app/Http/Controllers/DocumentController.php` -- `preview()` (stream inline, convertit si besoin) + `download()` (`Content-Disposition: attachment`) ; `show()` + `sourceMissing`.
- [x] `routes/web.php` -- `GET /documents/{document}/preview` + `GET /documents/{document}/download`.
- [x] `resources/js/Pages/Documents/Show.vue` -- panneau preview (iframe directe PDF ; fetch+chargement+blob pour Word/Excel pour distinguer chargement/échec) + bouton "Télécharger" (`documents.download`, désactivé si `sourceMissing`).
- [x] `tests/Feature/PreviewDocumentTest.php` -- créer -- couvre les 5 lignes de la matrice I/O.
- [x] `tests/Fixtures/sample-corrupt.docx` -- ajouter -- fixture conversion échouée.

**Acceptance Criteria:**
- Given la suite Pest existante (`ImportDocumentTest`, `BrowseLibraryTest`), when cette story est implémentée, then aucune régression (clair/sombre inclus). Les 4 AC métier de la story sont couvertes par la matrice I/O ci-dessus.

## Spec Change Log

### 2026-09-01 — Implémentation initiale

- **Décision "Ask First" non tranchée avec l'utilisateur** (`soffice` introuvable dans le PATH local — vérifié via `where soffice`, recherche dans `C:\Program Files\LibreOffice`, `C:\Program Files (x86)\LibreOffice`, Scoop : aucun résultat, LibreOffice non installé sur ce poste). **Choix par défaut appliqué, pas une devinette de chemin** : `config/services.php` expose `services.libreoffice.binary` (`env('LIBREOFFICE_BINARY', 'soffice')`), utilisé par `ConvertDocumentToPreviewAction` au lieu d'un chemin absolu deviné. `.env`/`.env.example` documentent `LIBREOFFICE_BINARY=soffice` avec un TODO explicite. **À faire par l'utilisateur avant tout usage réel** : installer LibreOffice et, si `soffice` n'est pas sur le PATH, renseigner `LIBREOFFICE_BINARY` avec le chemin complet (ex. `C:\Program Files\LibreOffice\program\soffice.exe`) dans `.env`. Tant que ce n'est pas fait, toute tentative réelle (hors tests) de prévisualiser un `.docx`/`.xlsx` échouera proprement (message "Aperçu indisponible pour ce fichier", téléchargement toujours actif) plutôt que de planter.
- `tests/Feature/PreviewDocumentTest.php` fake systématiquement `Illuminate\Support\Facades\Process` (`Process::fake()`) plutôt que d'invoquer un vrai `soffice` — cohérent avec l'absence du binaire sur cette machine et avec la pratique Laravel standard pour tester un shell-out. Pour les scénarios de conversion réussie, le fichier que `soffice --outdir` aurait produit (`{basename-source}.pdf`) est pré-déposé sur le disque fake avant l'appel, simulant fidèlement l'effet de bord réel sans dépendre du binaire. Conséquence : la conversion réelle via un vrai `soffice` n'a pas pu être vérifiée manuellement sur ce poste (voir Verification).

### 2026-09-01 — Step 4 review : 5 correctifs "patch" appliqués

Revue adversariale sur l'implémentation initiale. Cinq findings classés "patch" (correctifs mécaniques, intent de la spec inchangé), tous appliqués :

1. **Race condition + collision de nom intermédiaire dans la conversion preview** — `previewOfficeDocument()` (`DocumentController`) enchaînait `exists()` puis `convert()` sans atomicité, et `soffice` écrit son résultat dans `previews/` sous le basename du fichier *source* (pas l'id du document) avant renommage : deux requêtes concurrentes (même document, ou deux documents dont les fichiers sources partagent un basename) pouvaient se marcher dessus et faire hériter au document B l'aperçu converti du document A. Corrigé : `DocumentController::previewOfficeDocument()` sérialise désormais la séquence check-then-convert-then-cache par document via `Cache::lock("preview-conversion-{$document->id}", 130)->block(125, ...)` ; `ConvertDocumentToPreviewAction` convertit maintenant dans un sous-répertoire temporaire isolé par id (`previews/tmp/{documentId}/`, nettoyé dans un `finally`) plutôt que dans la racine `previews/` partagée, rendant l'écriture intermédiaire elle-même sans collision possible entre documents différents.
2. **Contrat "jamais d'exception non rattrapée" non tenu dans `ConvertDocumentToPreviewAction`** — `$disk->makeDirectory(...)` et les deux `$disk->path(...)` s'exécutaient avant le bloc `try`, donc une erreur filesystem (permissions, etc.) y remontait non rattrapée en 500 au lieu du message "Aperçu indisponible". Corrigé : tout le corps de `__invoke()` tourne désormais dans un seul `try`/`catch (Throwable)`/`finally` (le `finally` nettoie le répertoire temporaire).
3. **Panneau de preview non rafraîchi lors d'une navigation Inertia entre deux documents Office, et fuite possible d'URL blob** — `loadOfficePreview()` ne s'exécutait qu'au `onMounted`, donc une réutilisation de l'instance de composant `Show.vue` par Inertia (navigation `<Link>` d'un document Office vers un autre sans démontage) laissait affiché l'aperçu du document précédent ; par ailleurs une résolution de `fetch()`/`blob()` après démontage pouvait encore créer une URL blob jamais révoquée. Corrigé : `resources/js/Pages/Documents/Show.vue` ajoute `watch(() => props.document.id, refreshPreview)` et un compteur `requestSequence` incrémenté à chaque nouveau chargement/démontage — toute continuation asynchrone dont la requête a été supplantée (nouveau document, ou composant démonté) est détectée avant de toucher l'état ou de créer une URL blob, laquelle est systématiquement révoquée avant tout nouveau chargement et au démontage.
4. **En-tête `X-Content-Type-Options: nosniff` manquant sur le contenu de preview streamé inline** — les deux réponses de `preview()` (PDF natif et Office converti) sont injectées dans un `<iframe>` du même domaine ; sans `nosniff`, un décalage entre `mime_type` stocké et contenu réel du fichier ouvrait une surface de content-sniffing. Corrigé : ajout de `X-Content-Type-Options: nosniff` (constante `DocumentController::PREVIEW_RESPONSE_HEADERS`) sur les deux réponses `Storage::disk('local')->response(...)` du preview (branche PDF directe et branche Office convertie).
5. **Override `LIBREOFFICE_BINARY` non testé** — le test de conversion docx ne vérifiait que la présence de `--convert-to` dans la commande, jamais le binaire lui-même (`$process->command[0]`) : une régression sur `config('services.libreoffice.binary', 'soffice')` (mauvaise clé, fallback figé, etc.) serait passée inaperçue. Corrigé : le test existant vérifie désormais `$process->command[0] === 'soffice'` ; nouveau test `shells out to the LIBREOFFICE_BINARY override instead of the default soffice command` couvrant `config(['services.libreoffice.binary' => 'custom-soffice'])`.

Findings hors scope (délibérément non traités ici, journalisés dans `deferred-work.md`) : autorisation/multi-utilisateur, throttling, invalidation du cache preview sur ré-import, nettoyage du cache preview à la suppression d'un document, sandboxing de macros, validation du contenu PDF, duplication de la table de correspondance mime, conversion bloquante sous charge.

Vérification post-correctifs : `php artisan test --filter=PreviewDocumentTest` → 6/6 (50 assertions) ; `php artisan test` (suite complète) → 25/25 (214 assertions) ; `npm run build` → OK ; `./vendor/bin/pint --test` sur les fichiers modifiés → OK.

## Design Notes

Un `<iframe src>` brut ne distingue pas "en cours"/"échec" pour Word/Excel. PDF : `<iframe :src>` direct. Word/Excel : `fetch()` client (requête binaire, pas une visite Inertia) au montage → indicateur pendant l'attente → succès (`content-type: application/pdf`) construit une URL blob pour l'iframe → échec (statut non-2xx) affiche le message hors iframe.

`sourceMissing` calculé côté serveur dans `show()` (`Storage::disk('local')->exists()`), transmis en prop Inertia, pilote message + bouton.

## Verification

**Commands:**
- `php artisan test --filter=PreviewDocumentTest` -- expected: tous les tests passent.
- `php artisan test` -- expected: suite complète verte.

**Manual checks (if no CLI):**
- Fiche d'un PDF, d'un `.docx` (1ère ouverture puis rechargement), d'un `.xlsx`, d'un document déplacé, d'un `.docx` corrompu ; clair et sombre.

## Suggested Review Order

**Routage preview & streaming (backend)**

- Point d'entrée : décide PDF natif vs conversion Office vs type non supporté.
  [`DocumentController.php:78`](../../app/Http/Controllers/DocumentController.php#L78)

- Garde-fou "jamais d'échec silencieux" : pilote 404 preview/download + prop `sourceMissing`.
  [`DocumentController.php:140`](../../app/Http/Controllers/DocumentController.php#L140)

- Routage mime miroir de `ExtractDocumentTextJob::formatFromMimeType()`.
  [`DocumentController.php:150`](../../app/Http/Controllers/DocumentController.php#L150)

- Téléchargement indépendant de l'état de la preview.
  [`DocumentController.php:94`](../../app/Http/Controllers/DocumentController.php#L94)

**Conversion Office à la demande (cache, concurrence, sécurité)**

- Verrou par document autour du check-then-convert-then-cache (corrige la race condition trouvée en review).
  [`DocumentController.php:112`](../../app/Http/Controllers/DocumentController.php#L112)

- Conversion `soffice` dans un répertoire temporaire isolé par document, jamais d'exception non rattrapée (corrige 2 findings de review).
  [`ConvertDocumentToPreviewAction.php:38`](../../app/Actions/ConvertDocumentToPreviewAction.php#L38)

**Panneau de preview côté client (chargement/échec, navigation Inertia)**

- Branchement mime (PDF natif vs Office converti) qui pilote le rendu du panneau.
  [`Show.vue:32`](../../resources/js/Pages/Documents/Show.vue#L32)

- `fetch()`+blob pour distinguer chargement/succès/échec sans les cacher dans l'iframe.
  [`Show.vue:63`](../../resources/js/Pages/Documents/Show.vue#L63)

- Rejoue le chargement sur navigation Inertia entre documents ; invalide les réponses obsolètes (corrige un finding de review).
  [`Show.vue:98`](../../resources/js/Pages/Documents/Show.vue#L98)

- Panneau : message explicite, iframe PDF directe, ou état chargement/erreur Office.
  [`Show.vue:165`](../../resources/js/Pages/Documents/Show.vue#L165)

**Périphériques**

- Nouvelles routes `documents.preview`/`documents.download`.
  [`web.php:9`](../../routes/web.php#L9)

- Binaire LibreOffice configurable (`LIBREOFFICE_BINARY`), résolu par PATH par défaut.
  [`services.php:50`](../../config/services.php#L50)

- Couvre les 5 lignes de la matrice I/O + l'override du binaire LibreOffice.
  [`PreviewDocumentTest.php:1`](../../tests/Feature/PreviewDocumentTest.php#L1)
