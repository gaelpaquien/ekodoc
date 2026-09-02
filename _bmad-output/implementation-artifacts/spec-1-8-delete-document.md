---
title: 'Story 1.8 - Supprimer un document'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md']
baseline_commit: 'd37051347769b907a2ab277fe8f3d1704046d705'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Un document devenu inutile ne peut jamais être retiré de la bibliothèque — celle-ci ne fait que grossir.

**Approach:** Bouton "Supprimer" sur la Fiche document, derrière une boîte de dialogue de confirmation (pas d'undo), qui déclenche `DeleteDocumentAction` : suppression définitive et ordonnée (index Scout → fichier original + images → cache de prévisualisation → ligne `Document`), pas de `SoftDeletes` (AD-15).

## Boundaries & Constraints

**Always:** Confirmation explicite avant toute suppression (UX-DR21). `DeleteDocumentAction` est l'unique point d'entrée de la suppression. Ordre de nettoyage respecté : Scout → fichiers → cache preview → ligne DB. Après succès, retour vers la Bibliothèque.

**Ask First:** _Aucun._

**Never:** Pas de `SoftDeletes`/corbeille/undo en v1. Pas de suppression en masse depuis la Bibliothèque (hors scope, Fiche document uniquement).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Suppression confirmée, fichier présent | Clic "Supprimer" puis confirmation | Index Scout, fichiers (dossier `documents/{id}`), cache preview (`previews/{id}.pdf` si présent) et ligne `Document` supprimés ; retour à la Bibliothèque, document absent de la liste | N/A |
| Suppression annulée | Clic "Supprimer" puis "Annuler"/Échap | Boîte fermée, aucune suppression, aucune requête envoyée | N/A |
| Fichier source déjà manquant (`sourceMissing`) | Suppression confirmée sur un document dont le fichier est absent | Nettoyage Scout + DB réussit quand même ; `deleteDirectory`/`delete` sur fichiers absents ne lève pas d'erreur | N/A |
| Aucun cache de prévisualisation | Document PDF natif (jamais converti) | Suppression réussit sans tenter de supprimer un fichier preview inexistant | N/A |
| Document introuvable (id invalide) | `DELETE /documents/{id}` sur un id inexistant | 404, comportement standard de route model binding | Laravel gère nativement |

</frozen-after-approval>

## Code Map

- `routes/web.php:12` -- ajouter `Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy')`.
- `app/Http/Controllers/DocumentController.php` -- `destroy(Document $document, DeleteDocumentAction $action)`, patron `updateCategory()` ; redirige vers `to_route('documents.index')`.
- `app/Actions/DeleteDocumentAction.php` (nouveau) -- invokable, patron `CategorizeDocumentAction`. Ordre strict : `$document->unsearchable()` (explicite -- l'event `deleted` de Scout se déclenche une fois la ligne déjà retirée) → `Storage::disk('local')->deleteDirectory("documents/{$document->id}")` (original + images, même dossier -- AD-15) → `Storage::disk('local')->delete("previews/{$document->id}.pdf")` (no-op si absent) → `$document->delete()`.
- `app/DataTransferObjects/DeleteDocumentData.php` (nouveau) -- `final readonly class { public Document $document }`, patron `CategorizeDocumentData.php`.
- `app/Actions/ConvertDocumentToPreviewAction.php:27` -- `PREVIEW_DIRECTORY`, source du chemin `previews/{id}.pdf` à réutiliser tel quel.
- `resources/js/Pages/Documents/Show.vue:198-214` -- bouton "Supprimer" près de "Télécharger" ; dialogue inline, accessibilité calquée sur `resources/js/Components/ImportModal.vue` (`role="dialog"`, piège de focus, Échap, focus restauré au déclencheur).
- `tests/Feature/DownloadDocumentTest.php` -- patron Pest (`Storage::fake('local')`) pour `DeleteDocumentTest.php`.

## Tasks & Acceptance

**Execution:**
- [x] `app/DataTransferObjects/DeleteDocumentData.php` -- créer le DTO `{document: Document}` -- frontière d'Action, jamais un modèle brut passé directement.
- [x] `app/Actions/DeleteDocumentAction.php` -- créer l'Action, ordre Scout → fichiers → preview → ligne DB -- unique point d'écriture de la suppression (AD-15).
- [x] `app/Http/Controllers/DocumentController.php` -- ajouter `destroy()`, construit le DTO et appelle l'Action -- Contrôleur fin, patron `updateCategory()`.
- [x] `routes/web.php` -- ajouter la route `DELETE /documents/{document}` -- expose `destroy()`.
- [x] `resources/js/Pages/Documents/Show.vue` -- bouton "Supprimer" + boîte de dialogue de confirmation accessible -- pas de suppression sans confirmation explicite (UX-DR21).
- [x] `tests/Feature/DeleteDocumentTest.php` -- couvre la matrice I/O -- garantit l'ordre de nettoyage et la tolérance aux fichiers déjà absents.

**Acceptance Criteria:**
- Given la Fiche document, when je clique "Supprimer", then une boîte de dialogue de confirmation apparaît avant toute suppression effective.
- Given la suppression confirmée, when `DeleteDocumentAction` s'exécute, then il retire dans l'ordre l'entrée Scout, le fichier original (+ images), le cache de prévisualisation s'il existe, puis la ligne `Document`.
- Given la suppression réussie, then je suis redirigé vers la Bibliothèque et le document n'y apparaît plus.

## Design Notes

`deleteDirectory()`/`delete()` sur un chemin absent ne lèvent pas d'exception sur le disque `local` (`throw => false`, comme `ImportDocumentAction::storeFile()`) -- pas de vérification d'existence préalable nécessaire.

## Verification

**Commands:**
- `herd php artisan test --filter=DeleteDocumentTest` -- expected: verts.
- `herd php artisan test` -- expected: suite complète verte, aucune régression 1.1-1.7.

**Manual checks (if no CLI):**
- Depuis la Fiche document, cliquer "Supprimer" : la boîte de confirmation apparaît, Échap/Annuler la ferme sans effet. Confirmer : retour à la Bibliothèque, document absent. Re-télécharger un document supprimé via son ancienne URL : 404.

## Suggested Review Order

**Suppression définitive et ordonnée (AD-15)**

- Point d'entrée unique de la suppression : Scout → fichiers → cache preview → ligne DB, dans cet ordre strict.
  [`DeleteDocumentAction.php:42`](../../app/Actions/DeleteDocumentAction.php#L42)

- `unsearchable()` explicite plutôt que l'event `deleted` de Scout ; commentaire corrigé pour refléter que le driver `database` en fait un no-op aujourd'hui (patch review).
  [`DeleteDocumentAction.php:14`](../../app/Actions/DeleteDocumentAction.php#L14)

- `deleteDirectory()`/`delete()` tolèrent un chemin déjà absent sans lever d'exception -- aucune vérification d'existence préalable.
  [`DeleteDocumentAction.php:49`](../../app/Actions/DeleteDocumentAction.php#L49)

- Contrôleur fin : construit le DTO, délègue à l'Action, redirige -- ne touche jamais aux fichiers/index/ligne directement.
  [`DocumentController.php:247`](../../app/Http/Controllers/DocumentController.php#L247)

- Route dédiée, méthode HTTP DELETE sémantique.
  [`web.php:13`](../../routes/web.php#L13)

**Confirmation accessible côté client**

- Boîte de dialogue modale, accessibilité calquée sur `ImportModal.vue` (`role="dialog"`, piège de focus, Échap).
  [`Show.vue:367`](../../resources/js/Pages/Documents/Show.vue#L367)

- Piège de focus corrigé pour exclure les boutons désactivés pendant la suppression, sans quoi Tab pouvait sortir de la modale (patch review).
  [`Show.vue:144`](../../resources/js/Pages/Documents/Show.vue#L144)

- Garde de ré-entrance sur la confirmation : un double-clic rapide avant le re-rendu de `:disabled` ne déclenche plus deux requêtes (patch review).
  [`Show.vue:111`](../../resources/js/Pages/Documents/Show.vue#L111)

**Périphériques**

- Couvre la matrice I/O (suppression confirmée avec cache preview et fichier associé, fichier source déjà absent, PDF natif sans cache, id inexistant, suppression rejouée sur un id déjà supprimé) ; premier test renforcé pour prouver le nettoyage multi-fichiers, test de double suppression ajouté (patch review).
  [`DeleteDocumentTest.php:25`](../../tests/Feature/DeleteDocumentTest.php#L25)
