---
title: 'Cœur du footer rempli de rouge + renommage produit EkoDoc → BMAD Démo'
type: 'chore'
created: '2026-09-15'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: 'd0e179bc42794e6ec301dbdf78843327740fb78b'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Le cœur du footer est barré mais pas rempli, et le nom "EkoDoc" subsiste encore dans le produit (UI, titres, clés techniques, tests, fixtures, config d'outillage BMAD) alors que le projet doit désormais s'appeler uniquement "BMAD Démo".

**Approach:** Ajouter un remplissage rouge plein au path SVG du cœur (Sidebar.vue) sans toucher au barré. Remplacer toutes les occurrences produit de "EkoDoc"/"ekodoc" par "BMAD Démo" (texte UI) ou "bmad-demo" (identifiants techniques), en conservant la convention de casse existante de chaque occurrence.

## Boundaries & Constraints

**Always:**
- Texte visible utilisateur → "BMAD Démo" (avec accent).
- Identifiants techniques (clé localStorage, préfixes de fichiers temp, nom de package npm, `project_name` BMAD) → "bmad-demo", en respectant le séparateur déjà utilisé à cet endroit (kebab ou snake).
- Le remplissage du cœur reste rouge (`text-red-600 dark:text-red-400` déjà présent) ; les deux `<line>` du barré restent inchangées.
- Chaque changement de texte/clé est répercuté dans les tests qui l'assertent.

**Ask First:** Si une occurrence "ekodoc" (insensible à la casse) est trouvée en dehors des fichiers listés dans Code Map pendant l'implémentation.

**Never:**
- Ne pas toucher `_bmad-output/planning-artifacts/**` ni `_bmad-output/implementation-artifacts/**` (historique de planification figé — décision utilisateur explicite).
- Ne pas renommer le répertoire racine du projet ni le repository git (l'utilisateur s'en charge lui-même).
- Ne pas modifier les `<line>` du barré du cœur.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Recherche plein texte après rename | `GET /recherche?search=BMAD` | Retourne les documents dont `extracted_text`/`attachments_extracted_text` contient "BMAD Démo sample ... content" | N/A |
| Persistance du thème après rename | Toggle du thème sombre/clair | Lit/écrit la clé `bmad-demo-theme` dans `localStorage` ; aucun résidu `ekodoc-theme` | Storage indisponible → thème non persisté (comportement déjà existant, inchangé) |

</frozen-after-approval>

## Code Map

- `resources/js/Components/Sidebar.vue` -- L8 (commentaire), L17 (clé localStorage `ekodoc-theme`), L76 (texte marque "EkoDoc - Démo"), L192 (`<path>` du cœur, `fill="none"` hérité → ajouter `fill="currentColor"`)
- `resources/js/Components/__tests__/Sidebar.spec.js` -- L36, L45, L54 (commentaire), L285, L291 : assertions sur "EkoDoc" / clé `ekodoc-theme`
- `resources/views/app.blade.php` -- L7 (`config('app.name', 'EkoDoc - Démo')`), L12 (clé `ekodoc-theme`)
- `.env.example` -- L1 (`APP_NAME="EkoDoc - Démo"`)
- `app/Actions/ExportDocumentToWordAction.php` -- L48 (`tempnam(..., 'ekodoc_word_')`)
- `tests/Feature/ExportDocumentToWordTest.php` -- L63 (`tempnam(..., 'ekodoc_word_test_')`)
- `tests/Feature/ImportDocumentTest.php` -- L31, L44, L56, L102 (assertions "EkoDoc sample pdf/docx/xlsx content")
- `tests/Feature/CreateDocumentTest.php` -- L363 (assertion), L365 (`search=EkoDoc`)
- `tests/Feature/AttachDocumentFileTest.php` -- L34, L75 (assertions), L77 (`search=EkoDoc`)
- `tests/Feature/DetachDocumentFileTest.php` -- L47 (assertion), L54 (`search=EkoDoc`)
- `tests/Fixtures/sample.pdf` -- flux texte brut "EkoDoc sample pdf content for extraction testing." (PDF minimal écrit à la main, `/Length` à recalculer si édité)
- `tests/Fixtures/sample.docx` -- XML zippé, texte "EkoDoc sample docx content" (générable via `phpoffice/phpword`, déjà en dépendance)
- `tests/Fixtures/sample.xlsx` -- XML zippé, texte "EkoDoc sample xlsx content" (générable via `phpoffice/phpspreadsheet`, déjà en dépendance)
- `package.json` -- aucun champ `"name"` actuellement
- `package-lock.json` -- L2 `"name": "ekodoc"` (dérivé du nom de dossier, à régénérer)
- `_bmad/config.toml` -- L14 `project_name = "ekodoc"`
- `_bmad/bmm/config.yaml` -- L13 `project_name: ekodoc`
- `_bmad/core/config.yaml` -- L7 `project_name: ekodoc`

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/Sidebar.vue` -- ajouter `fill="currentColor"` au `<path>` du cœur (L192) ; remplacer "EkoDoc - Démo" → "BMAD Démo" (L76) ; renommer la clé `ekodoc-theme` → `bmad-demo-theme` (L8, L17) -- cœur plein rouge + suppression de "EkoDoc"
- [x] `resources/js/Components/__tests__/Sidebar.spec.js` -- aligner assertions et clé localStorage sur les nouveaux textes/clé (L36, L45, L54, L285, L291) -- tests cohérents avec le composant
- [x] `resources/views/app.blade.php` -- `'EkoDoc - Démo'` → `'BMAD Démo'` (L7) ; `ekodoc-theme` → `bmad-demo-theme` (L12) -- titre HTML et script de thème cohérents
- [x] `.env.example` -- `APP_NAME="BMAD Démo"` -- nom d'app par défaut cohérent
- [x] `app/Actions/ExportDocumentToWordAction.php` -- préfixe `ekodoc_word_` → `bmad_demo_word_` -- suppression de "EkoDoc" du code produit
- [x] `tests/Feature/ExportDocumentToWordTest.php` -- préfixe `ekodoc_word_test_` → `bmad_demo_word_test_` -- test aligné
- [x] `tests/Feature/{ImportDocumentTest,CreateDocumentTest,AttachDocumentFileTest,DetachDocumentFileTest}.php` -- `'EkoDoc sample ... content'` → `'BMAD Démo sample ... content'` ; `search=EkoDoc` → `search=BMAD` -- tests alignés sur le nouveau contenu des fixtures
- [x] `tests/Fixtures/sample.pdf` -- régénérer avec "BMAD Démo sample pdf content for extraction testing." en conservant un PDF valide -- fixture sans "EkoDoc"
- [x] `tests/Fixtures/sample.docx` -- régénérer (PhpWord ou édition directe du XML zippé) avec "BMAD Démo sample docx content" -- fixture sans "EkoDoc"
- [x] `tests/Fixtures/sample.xlsx` -- régénérer (PhpSpreadsheet ou édition directe du XML zippé) avec "BMAD Démo sample xlsx content" -- fixture sans "EkoDoc"
- [x] `package.json` -- ajouter `"name": "bmad-demo"` -- fixe le nom indépendamment du dossier racine
- [x] `package-lock.json` -- régénérer (`npm install`) pour que `"name"` devienne `"bmad-demo"` -- cohérence avec package.json
- [x] `_bmad/config.toml`, `_bmad/bmm/config.yaml`, `_bmad/core/config.yaml` -- `project_name` → `bmad-demo` -- outillage BMAD sans "EkoDoc"

**Acceptance Criteria:**
- Given le footer affiché, when on observe le cœur, then il est rempli de rouge plein et reste barré par les deux lignes existantes.
- Given le code produit, les tests, les fixtures et la config `_bmad/*`, when on grep insensible à la casse "ekodoc", then aucune occurrence ne subsiste en dehors de `_bmad-output/planning-artifacts/**` et `_bmad-output/implementation-artifacts/**`.
- Given la suite de tests, when `npm run test` et `composer test` s'exécutent après les changements, then tous les tests passent sans régression liée au renommage.

## Spec Change Log

## Verification

**Commands:**
- `npm run test` -- expected: tous les tests Vitest passent (`Sidebar.spec.js` inclus)
- `composer test` -- expected: tous les tests Pest/PHPUnit passent (Import/Create/Attach/Detach/ExportDocumentToWord)

**Manual checks (if no CLI):**
- Lancer `npm run dev` + `php artisan serve`, ouvrir l'app, vérifier visuellement le cœur plein rouge barré dans le footer et le titre d'onglet "BMAD Démo".

## Suggested Review Order

**Cœur du footer rempli de rouge**

- Le changement visuel demandé : le path SVG du cœur passe de contour à remplissage plein.
  [`Sidebar.vue:192`](../../resources/js/Components/Sidebar.vue#L192)

- Assertion ajoutée en revue pour verrouiller le remplissage (absente de l'implémentation initiale).
  [`Sidebar.spec.js:79`](../../resources/js/Components/__tests__/Sidebar.spec.js#L79)

**Renommage — branding UI**

- Nom de marque affiché dans la sidebar.
  [`Sidebar.vue:76`](../../resources/js/Components/Sidebar.vue#L76)

- Titre HTML par défaut de l'application.
  [`app.blade.php:7`](../../resources/views/app.blade.php#L7)

- Nom d'app par défaut pour les nouveaux environnements.
  [`.env.example:1`](../../.env.example#L1)

**Renommage — clé de thème localStorage (avec migration)**

- Correctif de revue : migre silencieusement l'ancienne clé `ekodoc-theme` pour ne pas faire perdre leur préférence aux utilisateurs existants.
  [`app.blade.php:12`](../../resources/views/app.blade.php#L12)

- Écriture de la nouvelle clé au toggle, cohérente avec la lecture ci-dessus.
  [`Sidebar.vue:17`](../../resources/js/Components/Sidebar.vue#L17)

- Tests alignés sur la nouvelle clé.
  [`Sidebar.spec.js:288`](../../resources/js/Components/__tests__/Sidebar.spec.js#L288)

**Renommage — identifiants techniques backend**

- Préfixe de fichier temporaire lors de l'export Word.
  [`ExportDocumentToWordAction.php:48`](../../app/Actions/ExportDocumentToWordAction.php#L48)

**Renommage — fixtures et tests d'extraction (dont correctif d'encodage PDF)**

- Correctif de revue : le fixture PDF regénéré déclare désormais `/Encoding /WinAnsiEncoding` sur sa police, sans quoi un lecteur PDF strict affiche "é" comme "Ø" (StandardEncoding par défaut) — vérifié via `smalot/pdfparser`.
  [`sample.pdf`](../../tests/Fixtures/sample.pdf)

- Assertions sur le texte extrait, désormais accentué.
  [`ImportDocumentTest.php:31`](../../tests/Feature/ImportDocumentTest.php#L31)

- Fixtures docx/xlsx régénérées avec le nouveau contenu texte.
  [`sample.docx`](../../tests/Fixtures/sample.docx) · [`sample.xlsx`](../../tests/Fixtures/sample.xlsx)

**Renommage — config outillage et package**

- Nom de package explicite, indépendant du nom du dossier racine.
  [`package.json:2`](../../package.json#L2)

- `project_name` de l'outillage BMAD.
  [`_bmad/config.toml:14`](../../_bmad/config.toml#L14)
