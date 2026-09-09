---
title: 'Fiabiliser le flake intermittent de la suite de tests complète'
type: 'chore'
created: '2026-09-08'
status: 'rejected'
review_loop_iteration: 0
context: []
baseline_commit: 'c151c4a4ccee641ab861fafa3fc8fd84147fd76e'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `DeleteDocumentTest` échoue de façon intermittente (~50 % des exécutions observées) uniquement quand la suite Pest complète tourne (jamais en isolation), toujours avec la même erreur : `Directory [documents/1] is not empty`. Une première tentative (`spec-corrections-post-retrospective.md`, commit `575cf05`), scopée à un nettoyage défensif dans le seul `beforeEach` de ce fichier, a été implémentée mais n'a mesurablement rien changé — un run complet 100 % vert laisse quand même parfois `documents/1/images/` orphelin sur le disque *après* la fin du process PHP, ce qui indique que le problème n'est pas propre à ce test mais à l'ensemble du process.

**Approach:** Nettoyer le répertoire physique du disque de test (`storage/framework/testing/disks/local`, jamais `storage/app/private`, le disque réel — chemin fixe de `Storage::fake()`, vérifié dans le code source de Laravel) directement au niveau du système de fichiers, en dehors de la façade `Storage` — donc sans risque de toucher un disque non-fake, contrairement à la préoccupation soulevée dans la spec précédente. Contrairement à la tentative précédente (nettoyage *par fichier de test*, insuffisant), ce nettoyage est ajouté une fois, globalement, dans `tests/Pest.php`.

## Boundaries & Constraints

**Always:** Le nettoyage n'opère que sur le chemin physique fixe `storage_path('framework/testing/disks/*')` — jamais via la façade `Storage`, jamais sur `storage/app/private`. La suite complète doit rester verte (aucune régression fonctionnelle).

**Ask First:** Aucune — le mécanisme exact (hook global `afterEach`/`beforeEach` dans `tests/Pest.php`, `clearstatcache()`, `gc_collect_cycles()`, ou une combinaison) est laissé à l'appréciation de l'implémenteur : la cause exacte n'est pas confirmée, une itération empirique est nécessaire et autorisée dans ce lot (contrairement à la spec précédente qui l'excluait). Documenter dans Design Notes quelle combinaison a été retenue et pourquoi.

**Never:** Ne pas toucher à `storage/app/private` ni à aucun disque non-testing. Ne pas supprimer le nettoyage déjà en place dans `tests/Feature/DeleteDocumentTest.php` (commit `575cf05`) — il reste un filet de sécurité complémentaire, pas une redondance à retirer. Ne pas désactiver ou marquer `skip()` le test concerné pour faire disparaître le flake sans le corriger.

</frozen-after-approval>

## Code Map

- `tests/Pest.php` -- point d'ajout du hook global (actuellement `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`, sans hook de nettoyage).
- `tests/Feature/DeleteDocumentTest.php:7-9` -- nettoyage défensif déjà en place (commit `575cf05`), à conserver tel quel.
- `epic-2-retro-2026-09-08.md` / `spec-corrections-post-retrospective.md` (Spec Change Log) -- diagnostic complet de la tentative précédente et pourquoi elle a échoué.

## Tasks & Acceptance

**Execution:**
- [x] `tests/Pest.php` -- ajouter un nettoyage global (mécanisme au choix de l'implémenteur parmi ceux listés en Boundaries, empiriquement validé) qui balaie `storage_path('framework/testing/disks/*')` au niveau du système de fichiers, appliqué à chaque test de `Feature` (et idéalement une fois avant le tout premier test de la suite).
- [x] Documenter dans `## Design Notes` de ce spec quel(s) mécanisme(s) ont été essayés, lequel a été retenu, et les données empiriques qui ont motivé le choix.

**Acceptance Criteria:**
- Given `php artisan test` (suite complète) exécuté au moins 8 fois de suite après le correctif, when on observe `DeleteDocumentTest`, then le flake n'apparaît plus, ou apparaît significativement moins souvent que le ~50 % de baseline (idéalement 0/8 ; documenter le taux réel observé, même si non nul).
- Given le disque réel `storage/app/private`, when le correctif s'exécute pendant la suite de tests, then aucun fichier applicatif réel n'est jamais touché (vérifier qu'aucun code du correctif ne référence `storage/app/private` ou n'appelle la façade `Storage` sans `Storage::fake()` préalable dans le même test).

## Design Notes

Le raisonnement de la spec précédente ("un hook global risquerait de toucher un disque non-fake") supposait un nettoyage via la façade `Storage::disk('local')`, qui résout effectivement vers le disque réel si `Storage::fake()` n'a pas été appelé dans le test courant. Ce risque ne s'applique pas à un nettoyage au niveau du système de fichiers ciblant directement `storage/framework/testing/disks/*` : ce chemin est le chemin fixe et exclusif que `Storage::fake()` utilise (`Illuminate\Support\Facades\Storage::getRootPath()`, vérifié dans `vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php`), jamais utilisé par le disque réel (`storage/app/private`, `config/filesystems.php`). D'où la levée de la contrainte "Ask First" de la spec précédente sur ce point précis.

**Mécanismes essayés (itération empirique, documentée dans les commentaires de `tests/Pest.php`) :**
1. `gc_collect_cycles()` inconditionnel à chaque `beforeEach`/`afterEach` (254 appels/run complet) : élimine le flake mais fait passer la suite de ~114s à ~390s (≈3.4x) — coût jugé disproportionné et risquant de déstabiliser d'autres tests sensibles au temps (LibreOffice, Chrome via Browsershot).
2. **Retenu** : nettoyage gaté — `forceClearDirectory()` ne fait qu'un `FilesystemIterator` (coût quasi nul) et ne déclenche `gc_collect_cycles()` + backoff que s'il reste effectivement des entrées après une première passe de suppression. Ainsi le coût additionnel est nul dans le cas dominant (répertoire déjà vide) et ne s'active que sur la portion réellement flaky.
3. Backoff élargi empiriquement de 3 tentatives/20ms plat à 8 tentatives/20→250ms exponentiel : une première version à 20ms plat suffisait à l'intérieur d'un seul process `php artisan test`, mais était trop courte pour un verrou survivant *au-delà* du process précédent (hypothèse : synchronisation OneDrive sur ce dépôt, ou antivirus/indexeur Windows).

**Vérification post-implémentation (08-09/09/2026)** : suite complète + `DeleteDocumentTest` isolé relancés à plusieurs reprises après le correctif ; voir résultats consignés ci-dessous.

## Verification

**Commands:**
- `php artisan test` -- expected: verts, exécuté au moins 8 fois de suite pour mesurer le taux de flake résiduel.
- `php artisan test --filter=DeleteDocumentTest` (isolé) -- expected: verts, pour confirmer l'absence de régression sur ce fichier seul.

**Manual checks (if no CLI):**
- Grep du diff final pour toute référence à `storage/app/private` ou tout appel `Storage::` en dehors d'un test ayant déjà appelé `Storage::fake()` — doit être vide.

## Rejected — 2026-09-09

**Résultat empirique :** suite complète chronométrée avec le hook (`tests/Pest.php` de ce spec) vs sans, sur la même machine, à quelques minutes d'écart :
- Avec le hook : 293s puis 390-410s (mesures répétées) pour 127 tests.
- Sans le hook (retour à la version `HEAD`, aucun autre changement) : **17-20s**, 127/127 verts.

Le mécanisme "gated" décrit en Design Notes ne gate rien en pratique sur cette machine : un dossier `storage/framework/testing/disks/local/documents/1` a été trouvé non nettoyé *après* la fin d'une suite (aucun test en cours), preuve que `forceClearDirectory()` retombe dans sa branche coûteuse (`gc_collect_cycles()` + backoff jusqu'à 250ms, 8 tentatives) sur la quasi-totalité des 254 `beforeEach`/`afterEach`, au lieu du chemin rapide prévu pour le cas dominant. L'écart mesuré (~370-390s) correspond à ce calcul.

**Cause probable, hors du périmètre applicatif :** Windows Defender (protection temps réel, confirmée active sur la machine) scanne de façon synchrone chaque écriture/suppression, ce qui explique à la fois pourquoi les suppressions échouent assez souvent pour déclencher le flake original, et pourquoi la stratégie de retry en boucle dans ce hook est si coûteuse. Le déplacement du dépôt hors d'un dossier synchronisé cloud (tenté avant ce spec) n'a rien changé, ce qui écarte l'hypothèse OneDrive et pointe vers l'antivirus.

**Décision :** ce hook complique le code applicatif pour compenser une lenteur d'environnement (pas une cause du projet lui-même), au prix d'un ralentissement ~15-20x de toute la suite — disproportionné par rapport au bénéfice (jamais mesuré : aucune campagne de 8 runs n'a été faite avec ce hook actif). Rejeté. `tests/Pest.php` restauré à l'état `HEAD` (`c151c4a4`), aucun changement applicatif conservé. Le nettoyage défensif déjà en place dans `tests/Feature/DeleteDocumentTest.php` (commit `575cf05`) est conservé tel quel (hors périmètre de ce rejet).

**Suite à donner :** ajouter une exclusion Windows Defender sur le dossier du projet (nécessite des droits admin, hors capacité de l'agent) — si la cause racine est bien l'antivirus, ça devrait faire disparaître le flake original sans aucun contournement côté code. Voir action item de rétro mis à jour dans `sprint-status.yaml`.
