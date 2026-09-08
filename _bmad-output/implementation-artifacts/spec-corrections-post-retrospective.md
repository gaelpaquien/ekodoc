---
title: 'Corrections issues des rétrospectives Epic 1 et Epic 2'
type: 'chore'
created: '2026-09-08'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '111901973cf1c9edd8866783c2d53c1b35ad34fc'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les rétrospectives des Epic 1 et Epic 2 (`epic-1-retro-2026-09-08.md`, `epic-2-retro-2026-09-08.md`) ont produit 4 éléments d'action ouverts dans `sprint-status.yaml` : un défaut de focus-trap préexistant, une duplication de mapping mime-type/format à 3 endroits, l'absence totale d'outillage de test JS, et un flake intermittent (~50 %) de la suite de tests complète.

**Approach:** Quatre correctifs indépendants, chacun scopé au strict nécessaire : (1) copier le correctif `:not([disabled])` déjà validé dans `Show.vue` vers `ImportModal.vue` ; (2) extraire une source PHP unique du mapping mime-type/format, consommée par les deux endroits identiques et exposée à Vue via une prop Inertia partagée plutôt que dupliquée ; (3) installer Vitest + `@vue/test-utils` et écrire une vraie suite de tests pour les deux composants autonomes nommés par la rétrospective (`DocumentTypeBadge.vue`, `CategoryPicker.vue`) — `Index.vue`/`Show.vue` restent un suivi incrémental, notés en Design Notes plutôt que forcés dans ce lot ; (4) fiabiliser `DeleteDocumentTest` par un nettoyage défensif du disque de test après le `Storage::fake()` déjà en place.

## Boundaries & Constraints

**Always:** Chaque correctif reste isolé à son fichier/dossier annoncé — pas de refactor opportuniste au-delà de ce qui est listé. La suite Pest complète doit rester verte (au sens : aucune régression introduite par ces changements ; le flake préexistant de l'item 4 est justement ce qu'on cherche à réduire, pas un critère d'échec de cette spec). `TYPE_MIME_MAP`/`previewFormat()`/`formatFromMimeType()` gardent exactement le même comportement observable (mêmes clés, mêmes valeurs, mêmes cas `null`).

**Ask First:** Aucune décision supplémentaire anticipée — les 4 correctifs et leur conception sont déjà tranchés par les rétrospectives et cette spec.

**Never:** Ne pas toucher à `DocumentTypeBadge.vue::MIME_TYPE_LABELS` (mapping différent, déjà noté "single source of truth" dans son propre commentaire, hors périmètre de cet item). Ne pas écrire de tests Vitest pour `Index.vue`/`Show.vue` dans ce lot (dépendances Inertia plus lourdes à mocker — suivi séparé). Ne pas modifier le comportement du disque `local` en dehors du contexte de test (le nettoyage défensif de l'item 4 n'agit que sur l'instance déjà fakée par `Storage::fake()` dans le même test).

</frozen-after-approval>

## Code Map

- `resources/js/Components/ImportModal.vue:114-116` -- sélecteur `trapFocus()` à corriger, même motif que `Show.vue:299-301`.
- `app/Support/DocumentMimeTypes.php` -- NOUVEAU. Source unique : `MIME_TO_FORMAT`, `TYPE_TO_MIME`, `TYPE_LABELS`, `formatFromMime()`.
- `app/Http/Controllers/DocumentController.php:52-56` (`TYPE_MIME_MAP`), `:562-570` (`previewFormat()`) -- à remplacer par des appels à `DocumentMimeTypes`.
- `app/Jobs/ExtractDocumentTextJob.php:122-130` (`formatFromMimeType()`) -- idem.
- `app/Http/Middleware/HandleInertiaRequests.php:39-65` (`share()`) -- ajouter la prop partagée `documentTypeOptions`, même pattern que `categories`.
- `resources/js/Pages/Documents/Index.vue:27-35` (`TYPE_OPTIONS`) -- dériver les entrées pdf/word/excel de `page.props.documentTypeOptions`, garder `created` en dur (non lié à un mime-type).
- `package.json` -- ajouter `vitest`, `@vue/test-utils`, `jsdom` en devDependencies + script `"test": "vitest run"`.
- `vitest.config.js` -- NOUVEAU, réutilise l'alias `@` de `vite.config.js`, environment `jsdom`.
- `resources/js/Components/__tests__/DocumentTypeBadge.spec.js` -- NOUVEAU.
- `resources/js/Components/__tests__/CategoryPicker.spec.js` -- NOUVEAU, mock `@inertiajs/vue3` (`usePage`, `router`).
- `tests/Feature/DeleteDocumentTest.php:7-9` (`beforeEach`) -- ajouter un nettoyage défensif post-`Storage::fake()`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/ImportModal.vue` -- changer le sélecteur `trapFocus()` de `'button, [href]...'` à `'button:not([disabled]), [href]...'` -- même correctif que `Show.vue`, referme le seul écart entre les deux boîtes de dialogue.
- [x] `app/Support/DocumentMimeTypes.php` -- créer la classe avec `MIME_TO_FORMAT`, `TYPE_TO_MIME`, `TYPE_LABELS`, `formatFromMime(?string): ?string` -- source unique, valeurs identiques à celles déjà en place (aucun changement de comportement).
- [x] `app/Http/Controllers/DocumentController.php` -- remplacer `TYPE_MIME_MAP` par `DocumentMimeTypes::TYPE_TO_MIME` à chaque usage ; remplacer le corps de `previewFormat()` par un appel à `DocumentMimeTypes::formatFromMime()` (méthode conservée pour ne pas changer l'appelant).
- [x] `app/Jobs/ExtractDocumentTextJob.php` -- remplacer `formatFromMimeType()` par un appel à `DocumentMimeTypes::formatFromMime()`.
- [x] `app/Http/Middleware/HandleInertiaRequests.php` -- ajouter la prop partagée `documentTypeOptions` (liste `{value, label}` dérivée de `DocumentMimeTypes::TYPE_TO_MIME`/`TYPE_LABELS`).
- [x] `resources/js/Pages/Documents/Index.vue` -- dériver `TYPE_OPTIONS` de `page.props.documentTypeOptions` + l'entrée locale `created`.
- [x] `package.json` + `vitest.config.js` -- installer et configurer Vitest/`@vue/test-utils`/`jsdom`, script `test`.
- [x] `resources/js/Components/__tests__/DocumentTypeBadge.spec.js` -- couvrir les 3 branches de `label` (mime reconnu, `source=created`, repli générique) -- ferme le trou noté dans `deferred-work.md:14-15`.
- [x] `resources/js/Components/__tests__/CategoryPicker.spec.js` -- couvrir le flux créer/annuler/Échap et la présélection après création -- ferme le trou noté dans `deferred-work.md:70-71`.
- [x] `tests/Feature/DeleteDocumentTest.php` -- ajouter dans `beforeEach`, après `Storage::fake('local')`, un `deleteDirectory('documents')`/`deleteDirectory('previews')` défensif sur le disque fake -- code écrit, mais **AC non satisfait** (voir ci-dessous et Spec Change Log).

**Acceptance Criteria:**
- Given `ImportModal.vue` ouvert avec son bouton de fermeture désactivé (`form.processing`), when l'utilisateur tabule en arrière depuis le premier élément focusable, then le focus saute au dernier élément *non désactivé*, jamais au bouton désactivé. **[Satisfait]**
- Given une requête de filtre par type (`?type[]=pdf`), when `DocumentController::index()` s'exécute, then le comportement de filtrage reste identique à avant (mêmes documents retournés) — seule la source du mapping change. **[Satisfait — 120/121 tests non liés au flake verts sur 2 exécutions complètes de vérification indépendante]**
- Given la Bibliothèque chargée, when `Index.vue` affiche les options de filtre par type, then les libellés PDF/Word/Excel proviennent de `page.props.documentTypeOptions`, pas d'un tableau local dupliqué. **[Satisfait]**
- Given `php artisan test` exécuté 5 fois de suite (suite complète), when on observe `DeleteDocumentTest`, then le taux d'échec du flake diminue mesurablement par rapport aux ~50 % observés en rétrospective (idéalement 0/5, sans garantie absolue vu la nature intermittente). **[NON satisfait — voir Spec Change Log]**

## Spec Change Log

**2026-09-08 — Item 4 (flake) implémenté mais AC non satisfait, non re-scopé automatiquement.**
Le correctif exact demandé (`deleteDirectory('documents')`/`deleteDirectory('previews')` dans `beforeEach`, après `Storage::fake('local')`) a été écrit tel que spécifié. Vérification indépendante (implémenteur + relecture) : 2 exécutions supplémentaires de la suite complète après le correctif → 1 verte / 1 avec le même échec `DeleteDocumentTest`, cohérent avec les 5 exécutions de l'implémenteur (1 verte / 4 échecs). Aucune amélioration mesurable par rapport à la baseline ~50 % de la rétrospective.
Diagnostic de l'implémenteur (non vérifié en profondeur, mais cohérent avec les observations) : le dossier orphelin `documents/{id}/images/` survit parfois à *tout le process* PHP (observé après une exécution par ailleurs 100 % verte), pas seulement au `beforeEach` d'un test — signe d'un verrou/handle Windows qui persiste au niveau du process plutôt que d'un état simplement laissé par le test précédent. Un nettoyage par-test, aussi défensif soit-il, ne peut pas lever un verrou déjà pris avant que ce `beforeEach` ne s'exécute.
Options non appliquées (hors périmètre autorisé par les Boundaries de cette spec — "Ask First" déclenché, décision humaine requise) : hook global `Pest.php` (risque déjà écarté dans les Design Notes : toucherait potentiellement un disque non-fake), `gc_collect_cycles()` avant le nettoyage (hypothèse plausible si le verrou vient d'un objet Flysystem non collecté, jamais testée de façon concluante), ou accepter le flake tel quel comme risque documenté.
**KEEP** : les 3 autres correctifs (items 1-3) sont solides, vérifiés indépendamment, sans lien avec cet échec.

## Design Notes

`DocumentMimeTypes` reste une classe à constantes + une méthode statique, pas un enum PHP : `TYPE_TO_MIME`/`TYPE_LABELS` sont des tableaux associatifs (clé courte → valeur), pas naturellement représentables par un `enum:string` sans perdre la double table de correspondance. Cohérent avec le style déjà en place (`DocumentSource` reste un enum simple séparé, pour la colonne `source`, pas touché ici).

Couverture Vitest volontairement limitée à `DocumentTypeBadge.vue` et `CategoryPicker.vue` dans ce lot : ce sont les deux composants autonomes (aucune dépendance à `useForm`/page complète), donc le socle le moins coûteux à poser correctement. `Index.vue` (recherche/filtres) et le piège de focus de `Show.vue`, également nommés par la rétrospective de l'Epic 1, restent un suivi volontairement différé — même mock Inertia réutilisable une fois écrit ici, mais ces deux fichiers demandent de monter une page complète (routeur, `usePage`, `useForm`), hors de ce qui tient en un lot proportionné. À journaliser dans `deferred-work.md` si non repris à la fin de cette spec.

Le nettoyage défensif de `DeleteDocumentTest` cible spécifiquement les deux racines connues (`documents/`, `previews/`) plutôt qu'un nettoyage global du disque : safe par construction (n'agit que sur l'instance fake déjà créée par `Storage::fake('local')` sur la même ligne précédente, jamais sur le disque réel), et scopé au seul fichier de test concerné plutôt qu'un hook global `Pest.php` qui risquerait de toucher un disque non-fake dans un test qui ne l'a jamais faké.

## Verification

**Commands:**
- `php artisan test` -- expected: verts (aucune régression sur les 121 tests existants).
- `php artisan test` répété 5 fois -- expected: `DeleteDocumentTest` échoue moins souvent qu'avant (baseline ~50 %).
- `npm run build` -- expected: build Vite réussi (aucune régression sur le bundle applicatif).
- `npm run test` -- expected: nouveaux tests Vitest verts (`DocumentTypeBadge.spec.js`, `CategoryPicker.spec.js`).

**Manual checks (if no CLI):**
- Ouvrir la Bibliothèque, vérifier que les filtres par type affichent toujours PDF/Word/Excel/Créé avec les bons libellés.
- Ouvrir la modale d'import, désactiver son bouton de fermeture (upload en cours), tabuler en arrière depuis le premier champ : le focus doit ignorer le bouton désactivé.

## Suggested Review Order

**Centralisation du mapping mime-type/format**

- Point d'entrée : source unique `MIME_TO_FORMAT`/`TYPE_TO_MIME`/`TYPE_LABELS` + `formatFromMime()`, garde `null` explicite ajoutée en revue pour éviter un warning de dépréciation PHP.
  [`DocumentMimeTypes.php:18`](../../app/Support/DocumentMimeTypes.php#L18)

- Les deux anciens points de duplication délèguent maintenant à la source unique, comportement inchangé.
  [`DocumentController.php:556`](../../app/Http/Controllers/DocumentController.php#L556)
  [`ExtractDocumentTextJob.php:125`](../../app/Jobs/ExtractDocumentTextJob.php#L125)

- `TYPE_TO_MIME` consommé pour le filtrage par type, seule la source du mapping change.
  [`DocumentController.php:117`](../../app/Http/Controllers/DocumentController.php#L117)

- Nouvelle prop Inertia partagée exposant le mapping type→libellé côté client, avec repli `?? $type` ajouté en revue contre une désynchronisation `TYPE_TO_MIME`/`TYPE_LABELS`.
  [`HandleInertiaRequests.php:64`](../../app/Http/Middleware/HandleInertiaRequests.php#L64)

- `TYPE_OPTIONS` dérive désormais de cette prop partagée plutôt que d'un tableau local dupliqué (l'entrée `created` reste locale, non liée à un mime-type).
  [`Index.vue:37`](../../resources/js/Pages/Documents/Index.vue#L37)

**Piège de focus — `ImportModal.vue`**

- Sélecteur aligné sur `Show.vue` : exclut les boutons désactivés. Limite connue et différée : ne couvre pas les `<select>`/`<input>` désactivés de `CategoryPicker.vue` monté dans la même boîte de dialogue (voir `deferred-work.md`).
  [`ImportModal.vue:113`](../../resources/js/Components/ImportModal.vue#L113)

**Fiabilisation du flake `DeleteDocumentTest` — tentative, AC non satisfait**

- Nettoyage défensif ajouté tel que scopé par le spec ; n'a pas mesurablement réduit le flake (voir Spec Change Log). Une nouvelle tentative avec un périmètre élargi est prévue séparément.
  [`DeleteDocumentTest.php:7`](../../tests/Feature/DeleteDocumentTest.php#L7)

**Outillage Vitest et nouveaux tests**

- Configuration Vitest, réutilise l'alias `@` de `vite.config.js`.
  [`vitest.config.js:1`](../../vitest.config.js#L1)

- Couverture des 3 branches de `DocumentTypeBadge.vue`, ferme `deferred-work.md:14-15`.
  [`DocumentTypeBadge.spec.js:1`](../../resources/js/Components/__tests__/DocumentTypeBadge.spec.js#L1)

- Couverture du flux créer/annuler/Échap de `CategoryPicker.vue` avec mock `@inertiajs/vue3`, ferme `deferred-work.md:70-71`.
  [`CategoryPicker.spec.js:1`](../../resources/js/Components/__tests__/CategoryPicker.spec.js#L1)

- Nouveau test unitaire PHP pour `DocumentMimeTypes::formatFromMime()`, ajouté en revue.
  [`DocumentMimeTypesTest.php:1`](../../tests/Unit/DocumentMimeTypesTest.php#L1)

- Assertion de forme sur la prop `documentTypeOptions`, même patron que l'assertion existante sur `categories`, ajoutée en revue.
  [`CategorizeDocumentTest.php:184`](../../tests/Feature/CategorizeDocumentTest.php#L184)
