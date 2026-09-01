---
title: 'Story 1.4 - Télécharger le fichier original'
type: 'feature'
created: '2026-09-01'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: '18287bf7889bdf05a3901dbf217427af0e107947'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Vérifier et documenter formellement une capacité déjà livrée : la Story 1.3 a introduit, comme sous-produit de son travail sur la prévisualisation, une route et un bouton de téléchargement qui satisfont déjà intégralement les critères d'acceptation de la Story 1.4.

**Approach:** Aucun nouveau code de fonctionnalité. Vérification approfondie de l'existant (lecture du code, exécution des tests) puis fermeture du dernier écart constaté : absence de test couvrant explicitement le téléchargement d'un fichier lisible (intégrité du contenu streamé) et sa disponibilité indépendante de l'état de conversion de la prévisualisation. Cette spec documente la constatation et la couverture de test ajoutée.

## Boundaries & Constraints

**Always:** Le téléchargement passe par `Storage::disk('local')->download()` (route applicative dédiée, jamais un lien direct vers un disque public). Il reste disponible tant que le fichier source est lisible, indépendamment de l'état de la prévisualisation (en cours de conversion ou en échec).

**Ask First:** _Aucun — travail de vérification et de couverture de test uniquement, zéro décision architecturale nouvelle._

**Never:** Pas de nouvelle route, pas de changement de comportement du bouton "Télécharger" ou de `DocumentController::download()`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fichier source lisible | Document importé, `file_path` présent | Téléchargement HTTP 200, `content-disposition: attachment`, contenu identique à l'original | N/A |
| Prévisualisation en cours/échouée (Word/Excel) | Conversion office non aboutie ou en cours | Téléchargement reste disponible et fonctionnel (aucun couplage avec le cache/verrou de prévisualisation) | N/A |
| Fichier source manquant | `file_path` absent/illisible | 404 (déjà couvert par la Story 1.3) | `Storage::exists()` vérifié via `sourceMissing()` |

</frozen-after-approval>

## Code Map

- `app/Http/Controllers/DocumentController.php:94-99` — `download()`, déjà conforme (livré avec la Story 1.3).
- `resources/js/Pages/Documents/Show.vue:148-163` — bouton "Télécharger", déjà conforme.
- `tests/Feature/DownloadDocumentTest.php` (nouveau) — couvre l'intégrité du contenu téléchargé et la disponibilité pendant une conversion de prévisualisation en cours.
- `phpunit.xml` — ajout de `LIBREOFFICE_BINARY=soffice` dans `<php>` pour rendre la suite de tests indépendante de la configuration locale de la machine (bug d'isolation révélé lors de cette vérification, sans lien direct avec la Story 1.4 mais nécessaire pour que la suite passe de façon déterministe).

## Tasks & Acceptance

- [x] Vérifier que `download()` streame via une route applicative, jamais un lien direct vers le disque `public` (AD-7, FR5) — confirmé par lecture de `config/filesystems.php` (`local` disk racine `storage/app/private`, jamais servi en statique) et `DocumentController.php`.
- [x] Vérifier que le téléchargement reste disponible même si la prévisualisation échoue ou est en cours de conversion — confirmé par lecture de code (`download()` ne touche jamais le cache/verrou de prévisualisation) et par un nouveau test dédié.
- [x] Ajouter un test d'intégrité du contenu téléchargé (bytes identiques à l'original) — `DownloadDocumentTest.php`.
- [x] Ajouter un test de disponibilité du téléchargement pendant une conversion office en cours/échouée — `DownloadDocumentTest.php`.
- [x] Corriger le bug d'isolation des tests révélé par la vérification (`LIBREOFFICE_BINARY` non épinglé dans `phpunit.xml`) — `phpunit.xml`.
- [x] Suite de tests complète exécutée et verte (25 → 27 tests).

## Spec Change Log

- 2026-09-01 — Constat initial : Story 1.4 déjà satisfaite par la Story 1.3. Sur demande explicite de l'utilisateur, vérification approfondie effectuée avant clôture (plutôt qu'une clôture immédiate sans vérification). Écart trouvé et comblé : absence de test explicite sur l'intégrité/la disponibilité du téléchargement, et un bug d'isolation des tests (`LIBREOFFICE_BINARY`) révélé au passage et corrigé.

## Design Notes

Aucune décision d'architecture nouvelle. Le comportement de téléchargement (route dédiée, indépendance vis-à-vis de l'état de prévisualisation) était un effet de bord voulu de la conception de la Story 1.3 — voir `spec-1-3-preview-document.md`, section Boundaries & Constraints ("Le téléchargement reste actif indépendamment de l'état de la preview, sauf fichier source manquant/illisible").

## Verification

- `herd php artisan test` — 27/27 tests verts (214 → suite complète après ajout), 0 échec après correction de `phpunit.xml`.
- `herd php artisan test --filter=DownloadDocumentTest` — 2/2 tests verts.
- Lecture directe de `config/filesystems.php` confirmant l'absence d'exposition publique du disque `local`.
