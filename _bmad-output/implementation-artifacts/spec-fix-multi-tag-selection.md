---
title: 'Corriger la sélection multiple de tags (création et import)'
type: 'bugfix'
created: '2026-09-14'
status: 'done'
review_loop_iteration: 1
context: []
baseline_commit: 'e0623d2647203ee1e5130bf3049fd4e99927b469'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur la page de création d'un document, le champ Tags est masqué tant que l'utilisateur n'a pas cliqué une première fois sur "Enregistrer" (révélation en deux temps, UX-DR10, spec-2-1) — donnant l'impression qu'aucun tag n'est possible avant un premier enregistrement. Sur l'import comme partout ailleurs, `TagSelector.vue` ferme sa liste de suggestions après chaque sélection ; refocaliser l'input via `.focus()` ne redéclenche pas l'événement `focus` (déjà focus), donc un clic sur le champ ne rouvre rien — impossible d'enchaîner un deuxième tag sans passer par Tab/Echap puis reclic.

**Approach:** Retirer la révélation en deux temps sur la page de création (le champ Tags s'affiche dès le premier rendu, comme en édition) et faire en sorte que `TagSelector.vue` garde sa liste de suggestions ouverte après chaque sélection au lieu de la fermer, pour enchaîner plusieurs tags sans repasser par un clic externe.

**Amendement (revue step-04, review_loop_iteration 1) :** la Fiche document (`Show.vue`) désactive `TagSelector` via `:disabled="isSavingTags"` pendant chaque PATCH d'écriture immédiate — un navigateur force toujours un `blur` sur un champ focus qui devient natif `disabled`, ce qui fermait la liste malgré le correctif ci-dessus, contredisant l'invariant "focus jamais perdu" ci-dessous sur ce point de montage précis. Décision humaine (2026-09-14) : étendre le correctif à ce cas plutôt que documenter une exception.

## Boundaries & Constraints

**Always:** `TagSelector.vue` reste le composant unique partagé sur ses 4 points de montage (Import, Editor création/édition, Show, filtre Index) — le correctif de réouverture bénéficie aux 4 sans traitement spécifique à un contexte ; après sélection, la liste reste ouverte, filtrée (le tag choisi disparaît des suggestions comme aujourd'hui), focus jamais perdu, y compris quand `disabled` devient `true` pendant que la liste est déjà ouverte (Show.vue, écriture immédiate) ; fermeture sur blur/Echap inchangée (query réinitialisée) ; aucune saisie libre/création à la volée (contrainte spec-3-1 inchangée) ; le champ Tags de la page de création est visible dès le chargement, sans geste préalable ; quand `disabled` est `true`, aucune interaction ne doit rester possible (ouverture, sélection, navigation clavier) — seul le mécanisme DOM utilisé pour l'exprimer change, jamais la garantie elle-même.

**Ask First:** _Aucune._

**Never:** ne pas toucher au comportement d'`AttachmentsPanel` (décision humaine déjà tranchée, retro epic-3 item 8 — affichage immédiat conservé, hors scope ici) ; ne pas modifier la validation backend `tag_ids` (déjà illimitée côté serveur, `array` sans `max`) ; ne pas changer le comportement du filtre tag de la Bibliothèque au-delà du correctif générique de réouverture.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Sélection successive | 3 tags choisis à la suite sans reblur | Les 3 chips s'ajoutent une à une, la liste reste ouverte après chaque clic, focus jamais perdu | N/A |
| Tous les tags déjà sélectionnés | Dernier tag disponible choisi | Message "Aucun tag ne correspond." affiché, liste reste ouverte (vide) | N/A |
| Nouveau document (création) | `GET /documents/create` | Champ Tags visible immédiatement, sans clic préalable sur "Enregistrer" | N/A |
| Fermeture toujours fonctionnelle | Blur ou Echap après une ou plusieurs sélections | Liste se ferme, `query` réinitialisée (inchangé) | N/A |
| Écriture immédiate en cours (Show.vue) | `disabled` passe à `true` juste après une sélection, liste déjà ouverte | La liste reste ouverte (visuellement inerte), aucune interaction possible (clic suggestion, clavier) tant que `disabled` est `true` ; redevient pleinement interactive sans reclic une fois `disabled` revenu à `false` | N/A |

</frozen-after-approval>

## Code Map

- `resources/js/Components/TagSelector.vue:79-89` (`selectTag`) -- ne plus appeler `closeSuggestions()` ; garder `isOpen` à `true`, réinitialiser `query` et recalculer `highlightedIndex` à partir des suggestions restantes (le tag choisi disparaît déjà via `filteredTags`), `inputRef.value?.focus()` inchangé
- `resources/js/Components/TagSelector.vue:139-141` (`defineExpose`) -- `focus()` devient inutilisé une fois la révélation en deux temps retirée d'`Editor.vue` (seul consommateur) ; supprimer l'expose
- `resources/js/Components/__tests__/TagSelector.spec.js` -- ajouter un test : sélection successive de 2 tags sans refocus intermédiaire (la liste reste ouverte, 2 émissions `update:modelValue` consécutives, chacune avec le cumul attendu)
- `resources/js/Pages/Documents/Editor.vue:84` (`tagSelectorRef`) -- supprimer (plus utilisé)
- `resources/js/Pages/Documents/Editor.vue:86-91,491-496` (`showTagSelector` + commentaires associés) -- initialiser `showTagSelector` à `true` sans condition ; retirer les commentaires décrivant la révélation en deux temps (comportement supprimé) ; réécrire le commentaire de `onSaveClick` en conséquence
- `resources/js/Pages/Documents/Editor.vue:482-499` (`onSaveClick`) -- retirer le bloc `if (!showTagSelector.value) { ...; return; }` -- Save soumet directement dès le premier clic, y compris pour un nouveau document
- `resources/js/Pages/Documents/Editor.vue:671-676` (template) -- retirer le `v-if="showTagSelector"` et l'attribut `ref="tagSelectorRef"` ; le bloc `TagSelector` devient inconditionnel
- `resources/js/Components/TagSelector.vue` (template, `<input>`) -- remplacer `:disabled="disabled"` par `:readonly="disabled"` + `:aria-disabled="disabled"` (un `readonly` ne force jamais de `blur` contrairement à `disabled`, contrairement aux boutons de suppression de chip qui restent en `disabled` natif, sans risque puisqu'ils ne portent jamais le focus pendant une sélection) ; remplacer les classes Tailwind `disabled:cursor-not-allowed disabled:opacity-50` (liées à la pseudo-classe CSS `:disabled`, inopérante sur un champ `readonly`) par un binding `:class` conditionné sur la prop `disabled`
- `resources/js/Components/TagSelector.vue` (`onKeydown`) -- ajouter `if (props.disabled) { return; }` en toute première ligne -- un champ `readonly` continue de recevoir les événements clavier, contrairement à un champ `disabled`
- `resources/js/Components/TagSelector.vue` (`selectTag`) -- ajouter `if (props.disabled) { return; }` en toute première ligne -- filet de sécurité si la liste était déjà ouverte avant que `disabled` ne passe à `true` (les `<li>` de suggestion ne sont jamais eux-mêmes désactivés)
- `resources/js/Components/__tests__/TagSelector.spec.js` -- test : sélection alors que `disabled` devient `true` juste après (liste reste ouverte, `readonly` posé, pas de nouvelle émission tant que `disabled` reste `true`), puis redevient interactive sans reclic une fois `disabled` repassé à `false`
- `resources/js/Pages/Documents/__tests__/Editor.spec.js` -- test : un clic sur "Enregistrer" sur un document neuf (sans prop `document`) appelle directement `form.post('/documents/create', …)` dès le premier clic -- couvre la disparition du geste en deux temps (finding verification-gap)
- `resources/js/Components/__tests__/TagSelector.spec.js` -- test : sélection d'une suggestion au clavier (`Enter`) suivie d'une deuxième sélection au clavier sans perte de focus -- couvre le chemin clavier de "Sélection successive", jusqu'ici seulement testé à la souris (finding blind-hunter)

## Tasks & Acceptance

**Execution:**
- [x] `resources/js/Components/TagSelector.vue` -- `selectTag()` garde la liste ouverte après sélection au lieu de la fermer -- débloque la sélection multiple sans reclic
- [x] `resources/js/Components/TagSelector.vue` -- retirer `defineExpose({ focus })`, devenu inutilisé -- pas de code mort
- [x] `resources/js/Components/__tests__/TagSelector.spec.js` -- test de sélection successive de 2 tags sans refocus -- couvre le scénario "Sélection successive" de la matrice
- [x] `resources/js/Pages/Documents/Editor.vue` -- `showTagSelector` toujours `true`, suppression du geste en deux temps (`onSaveClick`, `tagSelectorRef`, commentaires, `v-if`/`ref` du template) -- le champ Tags est visible dès l'arrivée sur la page de création ; `showTagSelector` lui-même retiré en revue (step-03) car devenu du code mort une fois le `v-if` supprimé
- [x] `resources/js/Components/TagSelector.vue` -- `<input>` en `readonly`+`aria-disabled` au lieu de `disabled` natif quand `disabled` est vrai, classes visuelles reconditionnées, garde `if (props.disabled) return;` ajoutée dans `onKeydown` et `selectTag` -- la Fiche document bénéficie du correctif sans forcer de `blur`
- [x] `resources/js/Components/__tests__/TagSelector.spec.js` -- test de sélection suivie d'un passage à `disabled: true` (liste reste ouverte, inerte) puis retour à `disabled: false` (interactive sans reclic) -- couvre le nouveau scénario matrice "Écriture immédiate en cours"
- [x] `resources/js/Pages/Documents/__tests__/Editor.spec.js` -- test du clic "Enregistrer" sur document neuf -- comble le trou de vérification identifié en revue (finding verification-gap)
- [x] `resources/js/Components/__tests__/TagSelector.spec.js` -- test du chemin clavier (Enter) pour la sélection successive -- comble le trou de couverture identifié en revue (finding blind-hunter)

**Acceptance Criteria:**
- Given l'utilisateur arrive sur `/documents/create`, when la page se charge, then le champ Tags est visible sans avoir cliqué sur "Enregistrer"
- Given l'utilisateur ouvre la liste de suggestions et clique un tag, when il reclique immédiatement dans le champ sans avoir perdu le focus, then la liste de suggestions est toujours visible et un deuxième tag peut être choisi sans étape intermédiaire
- Given ce correctif appliqué à `TagSelector.vue`, when il est utilisé depuis Import, Editor (création/édition), Show ou le filtre de la Bibliothèque, then le comportement de sélection multiple est identique aux 4 endroits, y compris sur la Fiche document pendant une écriture immédiate
- Given un premier clic sur "Enregistrer" pour un document neuf, when le clic est déclenché, then le formulaire est soumis directement (`form.post`), sans étape intermédiaire

## Spec Change Log

- **2026-09-14, review_loop_iteration 1 (intent_gap)** — Finding déclencheur : deux reviewers indépendants (blind-hunter, edge-case-hunter) ont montré que `Show.vue` désactive `TagSelector` (`:disabled="isSavingTags"`) de façon synchrone à chaque sélection pour son écriture immédiate ; un `<input>` focus qui devient natif `disabled` est toujours forcé au `blur` par le navigateur, ce qui refermait la liste malgré le correctif — contredisant l'invariant "focus jamais perdu" et l'AC d'uniformité entre les 4 points de montage, tous deux dans le bloc frozen. Amendé : le bloc frozen (Intent, Always, I/O Matrix) pour couvrir explicitement ce cas et exiger un mécanisme DOM qui ne force pas de `blur`. État connu-mauvais évité : la Fiche document restait la seule des 4 surfaces à exiger un reclic entre chaque tag, malgré la spec prétendant une expérience uniforme. KEEP : le mécanisme de `selectTag`/`highlightedIndex` déjà livré (TagSelector.vue, Editor.vue) reste correct et inchangé — l'amendement n'ajoute qu'un changement `disabled`→`readonly`+gardes JS, sans toucher au reste du correctif déjà vérifié (12 tests verts, build propre).
- **2026-09-14, deuxième passe de revue (patch, pas de loopback)** — Le correctif `readonly`+gardes ci-dessus a lui-même introduit trois trous mineurs, confirmés par plusieurs reviewers : `removeTag()` n'avait pas la même garde `if (props.disabled) return;` que `selectTag`/`onKeydown` ; le survol (`@mouseenter`) d'une suggestion déplaçait encore le surlignage pendant que le champ est inerte ; passer de `disabled` natif à `readonly` laissait le champ dans l'ordre de tabulation clavier (régression par rapport au `disabled` natif, qui l'en excluait). Un quatrième finding (verification-gap) notait qu'aucun test ne montait `Show.vue` lui-même pour vérifier le câblage réel `onTagsChange`/`isSavingTags` de bout en bout — seul `TagSelector.spec.js` testait le contrat `disabled` isolément via `setProps`. Les quatre sont des patches triviaux, sans root cause dans le bloc frozen : garde ajoutée à `removeTag`, `@mouseenter` gardé, `:tabindex="disabled ? -1 : 0"` ajouté, et nouveau `resources/js/Pages/Documents/__tests__/Show.spec.js` créé (mounting réel de `Show.vue` → `TagSelector`, `router.patch` mocké, sans stub `TagSelector`). Aucun changement au bloc frozen. 104 tests verts, build propre.

## Design Notes

La révélation en deux temps du champ Tags (UX-DR10, spec-2-1) était un choix assumé pour ne pas encombrer une page de création vierge — décision humaine reconduite en l'état lors de la rétro Epic 3 (item 8) pour `AttachmentsPanel`, mais explicitement renversée ici pour le champ Tags lui-même, à la demande de l'utilisateur (échange de clarification, 2026-09-14) : aucune contrainte technique ne justifie de masquer ce champ avant un premier enregistrement.

## Verification

**Commands:**
- `npm run test -- TagSelector` -- expected: tous les tests passent, y compris sélection successive (souris et clavier), dernier tag disponible, fermeture après sélection, et le scénario `disabled` pendant une écriture immédiate
- `npm run test -- Editor` -- expected: passe sans régression, y compris le nouveau test de soumission au premier clic sur "Enregistrer"
- `npm run build` -- expected: build Vite sans erreur

**Manual checks (if no CLI):**
- Sur `/documents/create`, vérifier que le champ Tags est visible dès l'arrivée sur la page
- Sur `/documents/import` (et sur la fiche document), sélectionner 2-3 tags à la suite sans cliquer ailleurs entre chaque choix
- Sur la Fiche document, sélectionner un tag et vérifier que la liste reste visible (inerte le temps du PATCH) puis redevient utilisable sans reclic

## Suggested Review Order

**Sélection multiple qui reste ouverte (le cœur du correctif)**

- Point d'entrée : une sélection n'appelle plus `closeSuggestions()` — la liste reste ouverte, filtrée, prête pour la suivante.
  [`TagSelector.vue:79`](../../resources/js/Components/TagSelector.vue#L79)

- Le clavier reçoit désormais la même garde `disabled` que la souris (un champ `readonly` continue de recevoir les `keydown`, contrairement à `disabled`).
  [`TagSelector.vue:104`](../../resources/js/Components/TagSelector.vue#L104)

**Écriture immédiate (Fiche document) — correctif étendu après la 1ère revue**

- `readonly`+`aria-disabled` remplace `disabled` natif sur le champ : un `readonly` ne force jamais de `blur`, contrairement à `disabled`.
  [`TagSelector.vue:194`](../../resources/js/Components/TagSelector.vue#L194)

- `tabindex` restaure l'exclusion du parcours clavier que `disabled` natif offrait gratuitement.
  [`TagSelector.vue:196`](../../resources/js/Components/TagSelector.vue#L196)

- Le survol d'une suggestion ne doit plus déplacer le surlignage tant que le champ est inerte.
  [`TagSelector.vue:224`](../../resources/js/Components/TagSelector.vue#L224)

- `removeTag()` reçoit la même garde `disabled` que `selectTag`/`onKeydown`, par cohérence défensive.
  [`TagSelector.vue:95`](../../resources/js/Components/TagSelector.vue#L95)

**Page de création — suppression de la révélation en deux temps**

- `onSaveClick()` soumet directement dès le premier clic ; l'ancien geste en deux temps (UX-DR10) a disparu.
  [`Editor.vue:473`](../../resources/js/Pages/Documents/Editor.vue#L473)

- Le champ Tags n'est plus gardé par `v-if` : visible dès le premier rendu, création comme édition.
  [`Editor.vue:656`](../../resources/js/Pages/Documents/Editor.vue#L656)

**Tests**

- Sélection successive à la souris sans reclic — le scénario qui a motivé toute cette story.
  [`TagSelector.spec.js:134`](../../resources/js/Components/__tests__/TagSelector.spec.js#L134)

- Simule le cycle `disabled` synchrone de Show.vue (liste inerte puis réinteractive sans reclic).
  [`TagSelector.spec.js:202`](../../resources/js/Components/__tests__/TagSelector.spec.js#L202)

- Verrouille la garde de `removeTag()` ajoutée en 2ᵉ passe de revue.
  [`TagSelector.spec.js:246`](../../resources/js/Components/__tests__/TagSelector.spec.js#L246)

- Preuve que "Enregistrer" soumet bien dès le premier clic, pas seulement que le champ Tags est visible.
  [`Editor.spec.js:280`](../../resources/js/Pages/Documents/__tests__/Editor.spec.js#L280)

- Nouveau fichier : monte la vraie Fiche document (pas de stub `TagSelector`) pour vérifier le câblage réel `onTagsChange`/`isSavingTags` de bout en bout.
  [`Show.spec.js:82`](../../resources/js/Pages/Documents/__tests__/Show.spec.js#L82)
