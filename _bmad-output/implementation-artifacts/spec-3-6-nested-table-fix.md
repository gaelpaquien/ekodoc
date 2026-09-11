---
title: 'Corriger l''insertion de tableaux imbriqués dans l''éditeur'
type: 'bugfix'
created: '2026-09-11'
status: 'done'
review_loop_iteration: 1
context: []
baseline_commit: 'e44beb2377d94b255d32ea7924758197aa5df25e'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Cliquer sur "Insérer un tableau" (barre d'outils de l'Éditeur, TipTap, FR8) alors que le curseur est déjà à l'intérieur d'un tableau crée un tableau imbriqué inexploitable, et aucune action ne permet de supprimer un tableau une fois inséré.

**Approche :** Désactiver le bouton "Insérer un tableau" quand le curseur est dans un tableau existant (`editor.isActive('table')`), et ajouter un bouton "Supprimer le tableau" dans la barre d'outils, actif uniquement dans ce même contexte, utilisant la commande native `deleteTable()` de `@tiptap/extension-table` (déjà installée, aucune dépendance nouvelle).

## Boundaries & Constraints

**Always:** `insertTable()` vérifie `editor.isActive('table')` avant d'exécuter la chaîne d'insertion — jamais de tableau créé dans une cellule de tableau ; le bouton "Supprimer le tableau" n'est actif que lorsque `editor.isActive('table')` est vrai et appelle uniquement `editor.chain().focus().deleteTable().run()`, jamais de manipulation DOM manuelle des nœuds tableau ; les deux boutons suivent le style et le pattern `disabled` déjà en place dans cette barre d'outils (`disabled:cursor-not-allowed disabled:opacity-50`, même pattern que `AttachmentsPanel.vue`) ; un test Vitest couvre la garde anti-imbrication et l'état `disabled` des deux boutons, suivant le patron de mock déjà établi (`Configuration.spec.js`).

**Ask First:** aucune décision bloquante identifiée — HALT si ambiguïté en cours d'implémentation.

**Never:** pas de correction rétroactive des documents déjà enregistrés contenant un tableau imbriqué existant — correctif préventif uniquement, aucun script de migration/nettoyage ; pas de contrôles d'ajout/suppression de ligne/colonne au-delà de ce qui existe déjà (hors périmètre de cette story) ; la ligne d'AC sur un "message d'erreur qui propose de réessayer" (epics.md, Story 3.6) est explicitement exclue du périmètre — confirmé avec l'humain comme un artefact de copier-coller depuis les stories d'export PDF/Word (UX-DR20), sans rapport avec le simple rechargement d'un document dans l'Éditeur.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Curseur dans un tableau existant, clic "Insérer un tableau" | `editor.isActive('table') === true` | Bouton désactivé, aucun tableau imbriqué créé | N/A |
| Curseur hors de tout tableau, clic "Insérer un tableau" | `editor.isActive('table') === false` | Tableau 3×3 inséré, comportement inchangé | N/A |
| Curseur dans un tableau, clic "Supprimer le tableau" | `editor.isActive('table') === true` | Tableau retiré proprement du contenu, aucune structure orpheline | N/A |
| Curseur hors de tout tableau | `editor.isActive('table') === false` | Bouton "Supprimer le tableau" désactivé | N/A |
| Réouverture d'un document créé avant ce correctif, contenant un tableau imbriqué | `content_html` contient `<table>` dans une `<td>` | Le contenu reste chargeable dans l'Éditeur sans erreur JS | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Pages/Documents/Editor.vue:243` -- `insertTable()` -- ajouter la garde `editor.value?.isActive('table')` avant d'exécuter la chaîne d'insertion.
- `resources/js/Pages/Documents/Editor.vue:601-608` -- bouton "Tableau" -- ajouter `:disabled="editor?.isActive('table')"` + classes `disabled:cursor-not-allowed disabled:opacity-50` (pattern identique à `AttachmentsPanel.vue:289`).
- `resources/js/Pages/Documents/Editor.vue` (juste après le bouton "Tableau", avant le séparateur suivant) -- nouveau bouton "Supprimer le tableau", `:disabled="!editor?.isActive('table')"`, `@click="deleteTable"` -- fonction nommée `deleteTable()` (miroir de `insertTable()`, patch step-04 itération 2 : cohérence de style avec le bouton d'insertion).
- `app/Actions/Concerns/SanitizesDocumentContent.php:38-43` -- lecture seule : confirme que `ALLOWED_TAGS` autorise déjà `table`/`tr`/`td` à toute profondeur d'imbrication (aucune vérification d'ancêtre) — aucun changement backend requis, le correctif est uniquement préventif côté Éditeur.
- `resources/js/Pages/Documents/__tests__/Editor.spec.js` (nouveau) -- test de composant Vitest/`@vue/test-utils` montant `Editor.vue`, avec un `editor` TipTap mocké (`isActive`, `chain().focus().insertTable(...).run()`, `chain().focus().deleteTable().run()` en espions) -- couvre la garde anti-imbrication et l'état `disabled` des deux boutons.
- `resources/js/Pages/Documents/__tests__/Configuration.spec.js` -- lecture seule, patron de référence pour mocker `@inertiajs/vue3` (`useForm`/`usePage`/`router`) et les stubs globaux (`AppLayout`), à réutiliser pour les dépendances non-TipTap d'`Editor.vue`.

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Pages/Documents/Editor.vue` -- garder `insertTable()` contre l'imbrication + désactiver "Tableau" en contexte + ajouter le bouton "Supprimer le tableau" -- AC1, AC2
- [x] `resources/js/Pages/Documents/__tests__/Editor.spec.js` (nouveau) -- tester la garde anti-imbrication et l'état `disabled` des deux boutons -- AC1, AC2

**Acceptance Criteria:**
- Given le curseur positionné dans un tableau déjà inséré, when je clique sur "Insérer un tableau", then aucun tableau imbriqué n'est créé (bouton désactivé)
- Given un tableau présent dans le document, when je clique sur "Supprimer le tableau", then il est retiré proprement du contenu sans structure orpheline
- Given un document créé avant ce correctif et contenant déjà un tableau imbriqué, when il est rouvert dans l'Éditeur, then son contenu reste chargeable sans erreur JS

## Spec Change Log

- **Trigger:** Verification Gap Reviewer (step-04, iteration 1) — this spec's `Never` clause claimed "no JS test framework exists in this repo," used to justify skipping automated tests. Confirmed false: Vitest was added in commit `575cf05` (before this story), and equivalent mocked-component tests already exist for sibling files (`Configuration.spec.js`, `Index.spec.js`, `AttachmentsPanel.spec.js`, `TagSelector.spec.js`).
- **Amended:** Removed the false "no test tooling" claim from `Never`. Added a `Vitest` test requirement to `Always`, a new Code Map entry pointing at `Configuration.spec.js` as the mocking pattern to reuse, a new Execution task for `Editor.spec.js`, and a `npm run test -- Editor` command to Verification.
- **Known-bad state avoided:** shipping a regression fix for a bug that already occurred once, with zero automated tripwire against the same bug resurfacing on a future `Editor.vue` refactor — based on a factually incorrect premise rather than a genuine proportionality call.
- **KEEP:** The already-implemented `resources/js/Pages/Documents/Editor.vue` changes (insertTable guard, both buttons' `disabled` bindings) are correct, match this Code Map, and passed full manual browser verification (Puppeteer-driven) against every I/O matrix row — preserve them unchanged. Only the missing test file is to be added.

## Design Notes

Désactiver (plutôt que remplacer par une action "ajouter ligne/colonne") a été choisi pour "Insérer un tableau" en contexte : epics.md autorise les deux options, et désactiver reste l'option la plus sûre et cohérente avec les autres boutons déjà désactivables de cette barre d'outils — aucune nouvelle interaction à apprendre.

Aucun changement backend n'est nécessaire : `SanitizesDocumentContent::ALLOWED_TAGS` autorise déjà `table`/`tr`/`td` sans limite de profondeur (il ne vérifie que le tag de chaque élément, jamais son ancêtre), donc un tableau imbriqué déjà enregistré traverse le sanitizer sans erreur — cohérent avec l'AC ("pas de correction rétroactive automatique requise, correctif préventif uniquement").

## Verification

**Commands:**
- `npm run build` -- expected: build Vite sans erreur
- `npm run test -- Editor` -- expected: le nouveau test de composant passe, aucune régression sur la suite existante

**Manual checks (if no CLI):**
- Curseur dans une cellule de tableau : "Insérer un tableau" est désactivé, aucun tableau imbriqué n'apparaît
- Curseur dans un tableau : "Supprimer le tableau" le retire proprement (pas de `<table>`/`<tr>` orphelin dans le HTML sauvegardé)
- Hors de tout tableau : "Insérer un tableau" insère toujours un 3×3, "Supprimer le tableau" est désactivé
- Créer manuellement (tinker/DB) un document avec un tableau imbriqué dans `content_html`, l'ouvrir dans l'Éditeur : le contenu se charge sans erreur console

## Suggested Review Order

**Garde anti-imbrication**

- Point d'entrée : la garde s'exécute avant toute autre logique d'insertion — retour anticipé si le curseur est déjà dans un tableau.
  [`Editor.vue:243`](../../resources/js/Pages/Documents/Editor.vue#L243)

**Boutons de la barre d'outils**

- État `disabled` calculé directement depuis `editor.isActive('table')`, même pattern que les autres boutons contextuels de cette barre d'outils.
  [`Editor.vue:612`](../../resources/js/Pages/Documents/Editor.vue#L612)

- Nouveau bouton de suppression, condition inverse à l'insertion ; fonction nommée dédiée plutôt qu'expression inline, en miroir du style d'`insertTable()`.
  [`Editor.vue:255`](../../resources/js/Pages/Documents/Editor.vue#L255)

- Câblage du bouton à la fonction nommée `deleteTable()`.
  [`Editor.vue:622`](../../resources/js/Pages/Documents/Editor.vue#L622)

**Tests**

- Éditeur TipTap mocké (`isActive`/`chain` en espions) pour isoler le comportement des deux boutons sans monter un vrai ProseMirror.
  [`Editor.spec.js:50`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L50)

- Vérifie la garde JS elle-même (pas seulement l'attribut `disabled`) en forçant un clic malgré un bouton techniquement activé.
  [`Editor.spec.js:146`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L146)

