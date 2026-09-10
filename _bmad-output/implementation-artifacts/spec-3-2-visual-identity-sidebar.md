---
title: 'Nouvelle identité visuelle et navigation par sidebar'
type: 'feature'
created: '2026-09-10'
status: 'done'
review_loop_iteration: 1
context: []
baseline_commit: '19332b078bca0bdab92dee7083673404740850a0'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** L'application utilise encore la palette bleu sobre v1 et n'a pas de navigation persistante ; le passage à un modèle de classement plus riche (tags, pièces jointes à venir, configuration à venir) demande une identité visuelle et une navigation qui reflètent ce changement (sprint-change-proposal 2026-09-09, `DESIGN.md` v2).

**Approche :** Appliquer les tokens de couleur `DESIGN.md` v2 (neutres chauds beige/brun + accent lime `#C6FF00`) dans `resources/css/app.css`, introduire une sidebar fixe dans `AppLayout.vue` (remplaçant `AppHeader.vue`) portant la navigation, le toggle thème et le footer, et remplacer la carte document de la Bibliothèque par une ligne de document (`document-row`) — sans pagination, portée par la story 3.4.

## Boundaries & Constraints

**Always:** tokens de couleur définis une seule fois dans `resources/css/app.css` sous `@theme` (paires clair/sombre), jamais de valeur hex répétée en dur dans les composants ; la sidebar est fixe, toujours visible, jamais repliable/masquée, présente sur les 3 surfaces existantes (Bibliothèque, Fiche document, Éditeur) ; le toggle thème clair/sombre existant (`AppHeader.vue` : `localStorage['ekodoc-theme']`, classe `.dark` sur `<html>`, script SSR-safe dans `app.blade.php`) est réutilisé tel quel, seulement déplacé/restylé dans la sidebar — ne pas réécrire sa logique ; tous les éléments interactifs (nav, toggle) restent intégralement navigables au clavier avec focus visible ; le document-row réutilise `DocumentTypeBadge.vue` et `TagChip.vue` existants sans les dupliquer.

**Ask First:** aucune décision bloquante identifiée — si un point d'ambiguïté apparaît en cours d'implémentation, HALT et demander avant de trancher.

**Never:** aucune validation de contraste WCAG requise sur cette palette (décision produit explicite) ; ne pas ajouter les items de nav "Recherche"/"Configuration" (leurs surfaces n'existent pas encore, stories 3.4/3.5) — seule "Bibliothèque" apparaît, active en fond lime ; ne pas introduire de mode responsive/mobile pour la sidebar (aucun pattern mobile défini dans `DESIGN.md`) ; ne pas toucher `document_tag`/tags/pièces jointes (hors scope, story purement visuelle/navigation) ; ne pas paginer la Bibliothèque ni retirer sa barre de recherche embarquée — ces deux changements sont explicitement portés par la story 3.4 (epics.md L509-511), pas par 3.2.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Navigation clavier sidebar | Utilisateur tabule dans la sidebar | Focus visible sur chaque item, ordre = ordre de lecture (nav puis toggle puis footer) | N/A |
| Toggle thème | Clic sur le toggle dans la sidebar | `.dark` bascule sur `<html>`, préférence persistée dans `localStorage`, aucune régression du script SSR-safe | Si `localStorage` indisponible (navigation privée), le thème change visuellement mais n'est pas persisté (comportement déjà existant, à préserver) |
| Bibliothèque vide/filtrée sans résultat | 0 document correspondant aux filtres actifs | Message "Aucun document ne correspond à ces filtres." préservé, pas de lignes | N/A |
| Ligne de document | Document avec 2 tags | Ligne affiche badge type + titre + 2 `TagChip` + date, toute la ligne cliquable vers la Fiche document, pas de fond au repos, fond `surface` au survol | N/A |

</frozen-after-approval>

## Code Map

- `resources/css/app.css` (12 lignes) -- `@theme` ne définit que `--font-sans` ; ajouter les tokens couleur v2 en paires clair/sombre consommées via le `@custom-variant dark` déjà présent (L3) : `background #F5F3F0`/`#1B1815`, `surface #EFEBE6`/`#211D19`, `surface-alt #EAE6E1`/`#24201B`, `border #DBD5CD`/`#362F27`, `foreground #221F1A`/`#ECE7E0`, `muted #7A7266`/`#A69C8D`, `primary #C6FF00` (identique clair/sombre), `primary-foreground #12130A`. Ajouter aussi `--radius-sm:4px/--radius-md:8px/--radius-lg:12px` et `--spacing-sidebar-width:220px` si non déjà couverts par les défauts Tailwind.
- `resources/js/Layouts/AppLayout.vue` (14 lignes) -- wrapper actuel `<div><AppHeader/><main><slot/></main><ExtractionTasksPanel/></div>` sans sidebar, fond `bg-white dark:bg-neutral-950` codé en dur -- remplacer par layout flex `sidebar (220px, fixe) + main`, monter un nouveau `Components/Sidebar.vue` à la place d'`AppHeader`, fond/texte via les nouveaux tokens. Ne pas ajouter de `max-w-*` arbitraire sur `<main>` : `Index.vue`/`Editor.vue`/`Show.vue` imposent déjà leur propre largeur de contenu (`max-w-3xl`) — un second plafond sur `<main>` serait sans effet visible et incohérent avec la règle "un token, un seul endroit".
- `resources/js/Components/AppHeader.vue` (41 lignes) -- seule logique de toggle thème existante : `isDark` ref (L4), `toggleTheme()` (L6-15, `.dark` sur `documentElement`, `localStorage.setItem('ekodoc-theme', ...)`), icônes soleil/lune (L31-37) -- extraire cette logique (inchangée) dans le nouveau `Sidebar.vue` ; le libellé "EkoDoc" (L21-23) devient le brand en haut de sidebar. Supprimer `AppHeader.vue` une fois vidé de sa substance, ou le laisser vide s'il reste référencé ailleurs (vérifier avant suppression).
- `resources/js/Components/Sidebar.vue` (nouveau) -- brand "EkoDoc" en haut, nav listant "Bibliothèque" (actif en fond `primary` sur la route courante), toggle thème (logique reprise d'`AppHeader.vue`), footer "Made with 💔 Claude" (`{colors.muted}`, `body-sm`) -- fond `surface-alt`, bordure droite `border`, largeur `sidebar-width`, entièrement navigable au clavier (`focus-visible` sur chaque item).
- `resources/js/Pages/Documents/Index.vue` (L392-410, ligne de document) -- liste actuelle : `<ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">` de cartes `<Link>` avec fond blanc/neutral-900, bordure, `hover:border-blue-500` -- remplacer par une liste de `document-row` (pas de grille, une colonne) : fond transparent au repos, `surface` au survol, bordure basse `border`, toute la ligne cliquable, composée de `DocumentTypeBadge` + titre + `TagChip` (déjà en place pour les tags, story 3.1) + date ajoutée. Le `<span>` du titre (`flex-1 truncate`) doit aussi porter `min-w-0` : sans cela, un flex-item ne rétrécit jamais sous son contenu (`min-width: auto` par défaut), et `truncate`/l'ellipsis ne s'active jamais sur un titre long. La barre de recherche embarquée et l'absence de pagination restent inchangées ici (portées par la story 3.4, epics.md L509-511).
- `resources/js/Pages/Documents/Index.vue` (reste du chrome : en-tête "Créer un document"/"Importer", barre de recherche, `fieldset` de filtres, cases à cocher type, chips de filtre actif/tag) -- **corrigé (review_loop_iteration 1, finding blind-hunter) :** ces éléments utilisent encore `bg-white`/`border-neutral-*`/`text-neutral-*`/`bg-blue-*` — retoken intégral avec les tokens v2 (`background`/`surface`/`border`/`foreground`/`muted`) ; tout bouton d'action primaire (ex. "Importer") passe de `bg-blue-600` à `bg-primary text-primary-foreground` (un seul accent, jamais deux couleurs d'accent simultanées) ; les chips de filtre actif/tag (actuellement `border-blue-200 bg-blue-50 text-blue-700`) adoptent le token `surface-alt`/`foreground` au repos, `primary`/`primary-foreground` à l'état actif (composant `filter-chip` de `DESIGN.md`).
- `resources/js/Components/ImportModal.vue`, `resources/js/Components/TagSelector.vue` -- **corrigé (review_loop_iteration 1) :** même retoken intégral (`bg-white`/`neutral-*`/`blue-*` → tokens v2), même règle "un seul accent" pour tout bouton primaire.
- `resources/js/Pages/Documents/Editor.vue` -- **corrigé (review_loop_iteration 1) :** champ titre, barre d'outils, bouton Enregistrer, dialogue d'insertion d'image — même retoken intégral, même règle accent unique.
- `resources/js/Pages/Documents/Show.vue` -- **corrigé (review_loop_iteration 1) :** boutons d'action (Télécharger/Modifier/Exporter), dialogue de suppression — même retoken intégral, même règle accent unique.
- `resources/js/Components/ExtractionTasksPanel.vue` -- **corrigé (review_loop_iteration 1, oublié au premier passage) :** `bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800` → tokens v2 (`background`/`border`) ; ce panneau s'affiche sur toutes les pages, il doit suivre la palette comme le reste.
- `resources/js/Components/TagChip.vue`, `DocumentTypeBadge.vue` -- remplacer les classes `neutral-*` par les tokens v2 (`surface-alt` pour le fond, `muted`/`foreground` pour le texte). **Corrigé (review_loop_iteration 1) :** l'instruction initiale "sans changer leur structure" était erronée — `DESIGN.md` définit des rayons différents par composant (`filetype-badge` → `{rounded.sm}` = 4px, `tag-chip` → `{rounded.lg}` = 12px) ; les deux gardent aujourd'hui `rounded-full`, à corriger vers `rounded-sm` (badge) et `rounded-lg` (chip) respectivement.
- Tout élément avec `focus-visible:outline-blue-600` (convention répétée dans le repo) -- **corrigé (review_loop_iteration 1, finding blind-hunter) :** un simple remplacement `blue-600` → `primary` échoue sur fond clair (`#C6FF00` sur blanc ≈ 1.2:1, largement sous le plancher ~3:1 attendu pour un indicateur de focus, alors que le "focus visible" reste une exigence WCAG 2.2 AA de cette story même si la palette elle-même est dispensée de validation de contraste). Garder `focus-visible:outline-primary` comme couleur d'accent mais lui adjoindre un halo contrasté visible sur les deux thèmes, par exemple `focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background` en plus de l'`outline` existant — ou toute autre combinaison utilisant uniquement les tokens déjà définis (`foreground`/`background`) qui garantit un focus visible aussi bien sur `bg-white`/`surface` clair que sur fond sombre. Appliquer la même correction partout où `focus-visible:outline-blue-600` a été remplacé.
- `resources/js/Layouts/__tests__/AppLayout.spec.js` (nouveau) -- aucun test ne couvrait `AppLayout.vue` avant cette story ; ajouter un test minimal (même mock Inertia que `Sidebar.spec.js`, `ExtractionTasksPanel` stubbé) qui vérifie que `Sidebar` est bien monté dans `AppLayout` -- garde-fou contre une régression future où le layout cesserait silencieusement de rendre la sidebar.
- `resources/views/app.blade.php` (script SSR-safe L9-19, lecture `localStorage['ekodoc-theme']` / `prefers-color-scheme`, pose `.dark` sur `<html>` avant peinture) -- ne pas modifier, déjà correct et compatible avec le nouveau toggle.

## Tasks & Acceptance

**Execution:**
- [x] `resources/css/app.css` -- ajouter les tokens couleur v2 (clair/sombre) + rayons + `sidebar-width` sous `@theme` -- fondation pour tous les composants restylés
- [x] `resources/js/Components/Sidebar.vue` -- créer le composant (brand, nav "Bibliothèque" actif, toggle thème repris d'`AppHeader.vue`, footer "Made with 💔 Claude") -- UX-DR2/3/4, UX-DR38
- [x] `resources/js/Layouts/AppLayout.vue` -- monter `Sidebar.vue` à la place d'`AppHeader.vue`, layout flex sidebar+main, sans `max-w-*` arbitraire sur `<main>` -- point d'insertion unique pour les 3 surfaces existantes
- [x] `resources/js/Components/AppHeader.vue` -- supprimer une fois sa logique migrée vers `Sidebar.vue` (vérifié : uniquement importé par `AppLayout.vue`)
- [x] `resources/js/Pages/Documents/Index.vue` -- remplacer la grille de cartes par une liste de `document-row` avec `min-w-0` sur le titre (badge + titre + tags + date, ligne cliquable, fond transparent/hover `surface`), sans toucher à la recherche/pagination (hors scope, story 3.4) -- UX-DR5
- [x] `resources/js/Pages/Documents/Index.vue` -- retokeniser le reste du chrome (en-tête, barre de recherche, filtres, chips actives) -- tokens v2 partout, un seul accent (`primary`) sur les boutons/chips primaires
- [x] `resources/js/Components/ImportModal.vue`, `TagSelector.vue` -- retokeniser intégralement (tokens v2, accent unique)
- [x] `resources/js/Pages/Documents/Editor.vue` -- retokeniser intégralement (titre, barre d'outils, bouton Enregistrer, dialogue image) -- accent unique
- [x] `resources/js/Pages/Documents/Show.vue` -- retokeniser intégralement (boutons d'action, dialogue de suppression) -- accent unique
- [x] `resources/js/Components/ExtractionTasksPanel.vue` -- retokeniser (fond/bordure) -- oublié au premier passage
- [x] `resources/js/Components/TagChip.vue`, `DocumentTypeBadge.vue` -- remplacer les classes `neutral-*` par les tokens v2 (`surface-alt`, `muted`, `foreground`) et corriger le rayon (`rounded-sm` badge, `rounded-lg` chip, au lieu de `rounded-full`)
- [x] Éléments à `focus-visible:outline-blue-600` -- remplacer par `focus-visible:outline-primary` + halo contrasté (`ring-foreground`/`ring-background` selon le thème) -- focus visible sur fond clair ET sombre
- [x] `resources/js/Components/__tests__/Sidebar.spec.js` -- couvrir la matrice I/O (navigation clavier, toggle thème, item actif)
- [x] `resources/js/Pages/Documents/__tests__/Index.spec.js` -- couvrir les 2 lignes de la matrice I/O non testées ailleurs (message "Aucun document ne correspond à ces filtres.", rendu de la ligne de document)
- [x] `resources/js/Layouts/__tests__/AppLayout.spec.js` -- vérifier que `Sidebar` est bien monté dans `AppLayout`

**Acceptance Criteria:**
- Given l'application EkoDoc, when n'importe quelle surface existante (Bibliothèque, Fiche document, Éditeur) s'affiche, then les tokens `DESIGN.md` v2 sont appliqués (neutres chauds, jamais blanc/noir pur, accent lime unique) et une sidebar fixe est visible avec l'item "Bibliothèque" actif en fond lime
- Given la sidebar affichée, when elle est parcourue au clavier, then chaque item (nav, toggle thème) reçoit un focus visible, dans l'ordre de lecture
- Given un document avec des tags, when sa ligne s'affiche dans la Bibliothèque, then elle montre badge type + titre + chips de tags + date, et toute la ligne est cliquable vers la Fiche document

## Spec Change Log

- **review_loop_iteration 1 (bad_spec, revue step-04) :** Finding déclencheur (blind-hunter, corroboré par edge-case-hunter) : le Code Map/Tasks initial ne couvrait le retoken que de `Sidebar.vue`, `AppLayout.vue`, `Index.vue` (ligne de document seule) et `TagChip`/`DocumentTypeBadge` — alors que l'AC1 ("n'importe quelle surface... tokens appliqués... accent lime unique") exigeait une couverture complète. Résultat observé (état connu-mauvais évité) : `ImportModal.vue`, `TagSelector.vue`, `Editor.vue`, `Show.vue`, le reste du chrome d'`Index.vue` et `ExtractionTasksPanel.vue` restaient sur `bg-white`/`neutral-*`/`bg-blue-*`, produisant deux accents visibles simultanément (bleu + lime) sur l'app — contradiction directe avec `DESIGN.md` ("jamais de deuxième couleur d'accent") et avec l'AC1 elle-même. Deux défauts techniques additionnels repérés au même passage : le remplacement aveugle `focus-visible:outline-blue-600` → `outline-primary` échoue en contraste sur fond clair (~1.2:1) ; le badge/chip gardaient `rounded-full` alors que `DESIGN.md` prescrit des rayons différents par composant. Amendé : Code Map et Tasks élargis à tous les fichiers listés ci-dessus, plus les corrections focus-ring et rayon. Le Boundaries/Intent/AC d'origine (frozen) restent inchangés — ils étaient déjà corrects, seule l'exécution planifiée était trop étroite.
  - **KEEP (à préserver telle quelle à la re-dérivation) :** la structure de `Sidebar.vue` (brand, nav, toggle, footer) et son contenu ; le shell flex `AppLayout.vue` (sidebar 220px + main) ; la logique du toggle thème reprise verbatim d'`AppHeader.vue` (ne pas la réécrire) ; la forme de la `document-row` (badge + titre + tags + date, une colonne) ; le pattern de mock Inertia déjà établi dans `Sidebar.spec.js`/`Index.spec.js` (`usePage`/`Link`/`useForm` mockés, pas d'app Inertia complète) — à réutiliser tel quel pour `AppLayout.spec.js`.

## Design Notes

Le toggle thème et le script SSR-safe (`app.blade.php`) sont déjà fonctionnels et corrects depuis v1 — cette story les déplace et les restyle, elle ne les réécrit pas. `AppHeader.vue` disparaît en tant que composant séparé : son rôle (brand + toggle) est absorbé par la sidebar, conformément au mockup (`key-bibliotheque.html`) qui n'a pas de header horizontal distinct.

Le retoken doit être **intégral** : toute classe `bg-white`/`bg-neutral-*`/`border-neutral-*`/`text-neutral-*`/`bg-blue-*`/`text-blue-*` sur une surface existante (Bibliothèque, Fiche document, Éditeur, modales/panneaux associés) est dans le périmètre de cette story, pas seulement les fichiers explicitement cités en premier jet — l'AC1 ne souffre aucune exception. Un seul accent (`primary`, lime) à la fois dans toute l'UI : tout `bg-blue-600`/`text-blue-*` sur un bouton ou une chip d'action devient `primary`/`primary-foreground`.

## Verification

**Commands:**
- `php artisan test --filter=Document` -- expected: tests Feature existants passent sans régression (aucun changement de contrat de réponse)
- `npm run test -- Sidebar` -- expected: tests du nouveau composant passent
- `npm run build` -- expected: build Vite/Tailwind sans erreur (nouveaux tokens `@theme` valides)

**Manual checks (if no CLI):**
- Vérifier visuellement en clair et en sombre que la sidebar, les lignes de document et les chips respectent la palette v2 (aucun blanc/noir pur)
- Vérifier au clavier (Tab) que le focus reste visible sur toute la sidebar et que l'ordre de tabulation suit l'ordre visuel

## Suggested Review Order

**Fondation des tokens**

- Palette v2 définie une seule fois (paires clair/sombre via sélecteur `.dark`), plus rayons et largeur de sidebar.
  [`app.css:15`](../../resources/css/app.css#L15)

- `<body>` retokenisé — plus de `bg-white`/`neutral-950` codés en dur avant même le montage Vue.
  [`app.blade.php:24`](../../resources/views/app.blade.php#L24)

**Sidebar & navigation**

- Point d'entrée pour comprendre l'intention design : structure brand + nav + toggle + footer, épinglée au scroll.
  [`Sidebar.vue:33`](../../resources/js/Components/Sidebar.vue#L33)

- Item "Bibliothèque" toujours actif (seule destination existante) — simplification volontaire, à revisiter en 3.4/3.5.
  [`Sidebar.vue:28`](../../resources/js/Components/Sidebar.vue#L28)

- Toggle thème repris à l'identique d'`AppHeader.vue`, seulement déplacé/restylé.
  [`Sidebar.vue:12`](../../resources/js/Components/Sidebar.vue#L12)

- Layout flex sidebar+main, point de montage unique pour les 3 surfaces existantes.
  [`AppLayout.vue:7`](../../resources/js/Layouts/AppLayout.vue#L7)

**Ligne de document (Bibliothèque)**

- Carte remplacée par une ligne pleine largeur ; `min-w-0` nécessaire pour que la troncature du titre fonctionne réellement.
  [`Index.vue:399`](../../resources/js/Pages/Documents/Index.vue#L399)

- Reste du chrome (recherche, filtres, chips actives) retokenisé avec accent lime unique sur les éléments primaires.
  [`Index.vue:269`](../../resources/js/Pages/Documents/Index.vue#L269)

**Halo de focus (correctif review_loop_iteration 1)**

- Contraste insuffisant d'un anneau de focus lime sur fond clair : halo `ring-foreground`/`ring-background` ajouté en complément.
  [`Sidebar.vue:42`](../../resources/js/Components/Sidebar.vue#L42)

- Bouton destructif (dialogue de suppression) gardé en rouge par design, mais aligné sur le même halo de focus et corrigé pour le mode sombre.
  [`Show.vue:616`](../../resources/js/Pages/Documents/Show.vue#L616)

**Corrections review_loop_iteration 2**

- Surbrillance clavier du `TagSelector` quasi invisible (`surface-alt` sur `surface`) — remplacée par `bg-border`, nettement plus distincte.
  [`TagSelector.vue:203`](../../resources/js/Components/TagSelector.vue#L203)

**Rayons par composant (`DESIGN.md`)**

- Badge type de fichier et chip de tag n'ont plus le même rayon générique : `rounded-sm` vs `rounded-lg`, par composant.
  [`TagChip.vue:17`](../../resources/js/Components/TagChip.vue#L17)

**Peripherals**

- `resources/js/Components/ExtractionTasksPanel.vue`, `ImportModal.vue`, `TagSelector.vue`, `Editor.vue` : même retoken intégral (fond/bordure/texte/accent unique), pas de logique modifiée.
  [`ExtractionTasksPanel.vue:42`](../../resources/js/Components/ExtractionTasksPanel.vue#L42)

- Tests : `Sidebar.spec.js`, `AppLayout.spec.js`, `Index.spec.js` — couvrent la matrice I/O et le montage de la sidebar.
  [`Sidebar.spec.js:1`](../../resources/js/Components/__tests__/Sidebar.spec.js#L1)
