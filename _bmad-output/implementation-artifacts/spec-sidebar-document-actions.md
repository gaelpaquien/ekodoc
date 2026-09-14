---
title: 'Déplacer les actions Créer/Importer un document dans le side menu'
type: 'feature'
created: '2026-09-14'
status: 'done'
review_loop_iteration: 0
context: []
baseline_commit: '9c3e3954a3361910baef490252d2bfd2c3c8cfb4'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les boutons "Créer un document" et "Importer" ne sont accessibles que depuis le contenu de la page Bibliothèque (en-tête + état vide), alors que le side menu (`Sidebar.vue`) est fixe et identique sur les 5 surfaces (UX-DR3). Ces actions ne sont donc pas disponibles depuis la Recherche, la Configuration, l'Éditeur ou la Fiche document.

**Approach:** Déplacer les deux actions dans `Sidebar.vue`, au-dessus des liens de navigation (Bibliothèque/Recherche/Configuration), pour qu'elles soient présentes en permanence sur toutes les surfaces. Retirer les boutons du contenu de la page Bibliothèque, y compris le CTA "Importer un document" de l'état vide, devenu redondant.

## Boundaries & Constraints

**Always:**
- "Créer un document" reste un `Link` Inertia vers `/documents/create` (inchangé).
- "Importer" reste un déclencheur de `ImportModal.vue`, réutilisé tel quel sans modification (il est déjà autonome : POST `/documents`, redirige vers `documents.show` en cas de succès, gère son propre focus trap/Escape).
- L'état `isImportModalOpen` et le rendu de `<ImportModal>` migrent dans `Sidebar.vue` (le composant qui les déclenche porte leur état, même convention que l'actuel `Index.vue`).
- Les deux actions sont placées en haut de la sidebar, sous le libellé "EkoDoc" et avant la `<nav>` des 3 liens.
- Garder le style visuel actuel des boutons (bouton secondaire bordé pour "Créer un document", bouton `bg-primary` plein pour "Importer") tel que défini aujourd'hui dans `Index.vue`.

**Ask First:** _Aucune décision supplémentaire nécessaire — le scope a été validé avec l'utilisateur avant la rédaction de ce spec._

**Never:**
- Ne pas dupliquer les boutons à la fois dans la sidebar et dans le contenu de la page Bibliothèque.
- Ne pas modifier le comportement interne d'`ImportModal.vue` (upload, validation, redirection).
- Ne pas toucher aux 3 liens de navigation existants ni à leur logique d'état actif (`isLibraryActive`/`isSearchActive`/`isConfigActive`).

</frozen-after-approval>

## Code Map

- `resources/js/Components/Sidebar.vue` -- ajouter les deux actions (Link "Créer un document" + bouton "Importer") au-dessus du `<nav>` (ligne 55) ; ajouter `import ImportModal from '@/Components/ImportModal.vue'`, un `ref` `isImportModalOpen`, et `<ImportModal :open="isImportModalOpen" @close="isImportModalOpen = false" />` dans le template (ex. juste après `</aside>` ou en fin de template, peu importe car `fixed inset-0`).
- `resources/js/Pages/Documents/Index.vue` -- retirer le bloc d'en-tête `<div class="flex items-center gap-3">` (lignes 152-166 : Link "Créer un document" + bouton "Importer") ; retirer le bouton CTA "Importer un document" de l'état vide sans filtres actifs (lignes 251-257) et son `@click="isImportModalOpen = true"` ; retirer `isImportModalOpen` (ref, ligne 40), l'import et l'usage de `<ImportModal>` (lignes 5, 306) devenus inutilisés dans cette page.
- `resources/js/Components/ImportModal.vue` -- aucune modification (composant déjà autonome, réutilisé sans changement).
- `resources/js/Components/__tests__/Sidebar.spec.js` -- le test "exposes exactly the three nav links then the theme toggle as focusable items, in that order" (ligne 144) doit être mis à jour : l'ordre attendu devient Créer un document (A) → Importer (BUTTON) → 3 liens nav (A) → toggle thème (BUTTON), soit 6 éléments focusables au lieu de 4. Ajouter des tests couvrant le rendu des deux nouveaux boutons et l'ouverture de la modale au clic sur "Importer" (`ImportModal` sera stubbé, même convention que le stub `Link`).
- `resources/js/Pages/Documents/__tests__/Index.spec.js` -- retirer l'assertion `expect(wrapper.text()).toContain('Importer un document');` (ligne 60, plus vraie car le bouton disparaît de l'état vide) ; retirer l'entrée `ImportModal: true` du `globalStubs` (ligne 36) et le commentaire associé (lignes 31-33), le composant n'étant plus importé par `Index.vue`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/Sidebar.vue` -- ajouter le Link "Créer un document" et le bouton "Importer" en haut de la sidebar, avec l'état `isImportModalOpen` et le rendu d'`ImportModal` -- rend les deux actions disponibles sur les 5 surfaces
- [x] `resources/js/Pages/Documents/Index.vue` -- retirer les boutons d'en-tête et le CTA d'import de l'état vide, ainsi que l'état/import devenus inutiles -- évite la duplication de l'action désormais portée par la sidebar
- [x] `resources/js/Components/__tests__/Sidebar.spec.js` -- mettre à jour le test de focus séquentiel et ajouter la couverture des deux nouveaux boutons -- garde la suite Pest/Vitest verte et documente le nouveau comportement
- [x] `resources/js/Pages/Documents/__tests__/Index.spec.js` -- retirer les assertions et stubs devenus obsolètes -- garde la suite alignée avec le template simplifié

**Acceptance Criteria:**
- Given l'utilisateur est sur n'importe laquelle des 5 surfaces (Bibliothèque, Recherche, Configuration, Éditeur, Fiche document), when il regarde la sidebar, then il voit "Créer un document" et "Importer" en haut, au-dessus des liens de navigation.
- Given l'utilisateur clique sur "Créer un document" dans la sidebar, when la navigation aboutit, then il arrive sur `/documents/create` (comportement inchangé du Link existant).
- Given l'utilisateur clique sur "Importer" dans la sidebar, when la modale s'ouvre et qu'il importe un fichier valide, then il est redirigé vers la fiche du document créé (comportement inchangé d'`ImportModal.vue`).
- Given l'utilisateur est sur la page Bibliothèque, when il regarde le contenu de la page (en-tête et état vide), then aucun bouton "Créer un document" ni "Importer" n'y est plus affiché.

## Design Notes

Le placement "en haut, au-dessus de la navigation" et le retrait du CTA d'état vide (redondant avec l'action désormais permanente) ont été validés explicitement avec l'utilisateur avant la rédaction de ce spec (portée sur les 5 surfaces également confirmée).

## Verification

**Commands:**
- `npm run test -- Sidebar` -- expected: tous les tests de `Sidebar.spec.js` passent, y compris le test de focus mis à jour et les nouveaux tests
- `npm run test -- Index` -- expected: tous les tests de `Index.spec.js` passent sans les assertions retirées
- `php artisan test --filter=Document` (ou `pest --coverage` si exécuté en fin de story) -- expected: aucune régression côté back (aucun fichier PHP touché par ce spec)

**Manual checks (if no CLI):**
- Ouvrir l'app en local (`herd open` ou équivalent), naviguer sur les 5 surfaces et vérifier visuellement la présence des deux boutons en haut de la sidebar, dans les deux thèmes clair/sombre.

## Suggested Review Order

**Actions déplacées dans la sidebar**

- Point d'entrée : les deux actions ajoutées en haut de la sidebar, groupées pour l'accessibilité, avec leur état local.
  [`Sidebar.vue:63`](../../resources/js/Components/Sidebar.vue#L63)

- Le déclencheur "Importer" pilote l'état de la modale, réutilisée telle quelle.
  [`Sidebar.vue:52`](../../resources/js/Components/Sidebar.vue#L52)

- `ImportModal` migre dans la sidebar : rendu hors de `<aside>` (racine multiple), aucune modification interne.
  [`Sidebar.vue:135`](../../resources/js/Components/Sidebar.vue#L135)

**Retrait de la duplication dans la Bibliothèque**

- En-tête simplifié : les deux boutons et leur wrapper flex (devenu inutile) disparaissent.
  [`Index.vue:146`](../../resources/js/Pages/Documents/Index.vue#L146)

- État vide sans filtres : CTA "Importer un document" retiré (décision produit), classes de layout simplifiées.
  [`Index.vue:230`](../../resources/js/Pages/Documents/Index.vue#L230)

**Tests**

- Ordre de focus clavier mis à jour (6 éléments) + nouveaux tests de rendu/ouverture modale.
  [`Sidebar.spec.js:63`](../../resources/js/Components/__tests__/Sidebar.spec.js#L63)

- Garde-fou anti-duplication : vérifie l'absence des boutons dans le rendu propre d'`Index.vue`.
  [`Index.spec.js:54`](../../resources/js/Pages/Documents/__tests__/Index.spec.js#L54)

- Stub `ImportModal` ajouté car `AppLayout` monte désormais la sidebar réelle.
  [`AppLayout.spec.js:29`](../../resources/js/Layouts/__tests__/AppLayout.spec.js#L29)

## Post-Approval Adjustments

<!-- Ce spec a le statut `done` et son <frozen-after-approval> n'a pas été rouvert : les ajustements
     ci-dessous ont été faits en itérant directement avec l'humain après la première implémentation,
     pas via une boucle de revue step-04 — d'où cette note plutôt qu'une entrée dans Spec Change Log
     (réservé aux boucles bad_spec/intent_gap). Le Code Map ci-dessus décrit l'état au moment de
     l'approbation initiale (commit `30a97a3`) et n'a pas été réécrit ; cette section documente ce qui
     a changé depuis, pour qu'un futur lecteur ne se fie pas au Code Map seul.
-->

Commit `5fb2832` (après `30a97a3`) a apporté, sur retours humains successifs :

- **Libellés** : "Bibliothèque" → "Documents", "Importer" → "Importer un document" (cohérence avec le nom du bouton "Créer un document").
- **Icônes** : chaque item du menu (Documents, Créer un document, Importer un document, Recherche, Configuration, toggle thème) a désormais une icône SVG inline, alignée à gauche comme le reste du menu.
- **Structure** : "Créer un document" et "Importer un document" ne sont plus un bloc séparé au-dessus de `<nav>` (avec fond/bordure propres) — ils sont intégrés au même `<nav>` que les 3 liens existants, dans l'ordre Documents / Créer un document / Importer un document / Recherche / Configuration. Le style visuel décrit dans Boundaries & Constraints (bouton bordé vs `bg-primary` plein) a été remplacé par un style unique, identique à celui des liens de nav.
- **État actif** : "Créer un document" a son propre état actif (`isCreateActive`, route `/documents/create` uniquement, distinct de l'édition d'un document existant) et exclut désormais explicitement "Documents" de son propre état actif sur cette route (les deux ne s'allument plus ensemble). "Importer un document" n'a volontairement aucun état actif — c'est une popup, pas un changement de page.
- **Largeur sidebar** : `--spacing-sidebar-width` passé de 220px à 240px (`resources/css/app.css`).
- **Hors scope initial** : les liens "&larr; Retour à la bibliothèque" de `Editor.vue`/`Show.vue` ont été retirés (navigation désormais exclusive à la sidebar) — changement demandé dans la même conversation mais non couvert par l'Intent d'origine de ce spec.

Tests : `resources/js/Components/__tests__/Sidebar.spec.js` mis à jour en conséquence à chaque étape (95/95 tests passent sur l'ensemble de la suite au commit `5fb2832`).

Commit `b8c1ea2` (retouches demandées hors epic, voir `spec-sidebar-menu-adjustments.md`) :

- **Toggle thème** : sorti de `<nav aria-label="Navigation principale">` (ce n'est pas une destination de navigation) mais reste visuellement dans la même liste, juste après "Configuration", via un conteneur `flex flex-col gap-0.5` partagé et `<nav class="contents">`.
- **Séparateur** : un `<hr>` a été ajouté entre le bloc "EkoDoc - Démo" et la liste des boutons.
- **Footer** : l'emoji "💔" a été remplacé par une icône SVG cœur barrée de deux traits en croix (rendu "annulé", pas "brisé").
