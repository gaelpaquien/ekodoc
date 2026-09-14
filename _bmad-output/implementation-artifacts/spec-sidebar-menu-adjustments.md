---
title: 'Trois retouches du side menu (toggle thème, séparateur, cœur du footer)'
type: 'chore'
created: '2026-09-14'
status: 'done'
review_loop_iteration: 0
context: []
route: 'one-shot'
---

# Trois retouches du side menu (toggle thème, séparateur, cœur du footer)

## Intent

**Problem:** Le side menu avait plusieurs petits défauts visuels : le toggle thème clair/sombre était isolé en bas plutôt que dans la liste des boutons ; aucun séparateur ne distinguait le bloc "EkoDoc - Démo" de la liste des boutons, ni la liste du footer ; le footer affichait un cœur brisé (💔) plutôt qu'un cœur barré, et le premier essai de cœur SVG rendait un cœur décentré (croix mal alignée) et gris au lieu de rouge.

**Approach:** Déplacer le bouton toggle thème juste après "Configuration" (hors du landmark `<nav>` pour ne pas casser la sémantique de navigation, mais visuellement dans la même liste via `display:contents`) ; ajouter un `<hr>` sous le bloc "EkoDoc - Démo" et un second au-dessus du footer ; remplacer l'emoji 💔 par une icône SVG cœur rouge, symétrique (centrée en x=12), barrée de deux traits en croix centrés sur le cœur.

## Suggested Review Order

**Toggle thème déplacé dans la liste**

- Bouton retiré du `<nav aria-label="Navigation principale">` (souci d'accessibilité relevé en revue) et repositionné juste après Configuration, dans un conteneur flex partagé avec `<nav class="contents">` pour garder le même espacement visuel.
  [`Sidebar.vue:162`](../../resources/js/Components/Sidebar.vue#L162)

**Séparateurs brand / liste / footer**

- `<hr>` ajouté entre "EkoDoc - Démo" et le conteneur de la liste des boutons.
  [`Sidebar.vue:79`](../../resources/js/Components/Sidebar.vue#L79)
- Second `<hr>` ajouté entre la liste des boutons et le footer "Made with ... Claude" (retour humain après le premier essai).
  [`Sidebar.vue:187`](../../resources/js/Components/Sidebar.vue#L187)

**Cœur barré du footer**

- Emoji 💔 remplacé par un cœur SVG inline rouge (`text-red-600 dark:text-red-400`), tracé symétrique et barré de deux traits diagonaux centrés (rendu "annulé", pas "brisé") — le tracé du premier essai n'était pas centré sur le cœur, corrigé sur retour humain.
  [`Sidebar.vue:191`](../../resources/js/Components/Sidebar.vue#L191)

**Tests**

- Tests : présence des 2 séparateurs, cœur rouge barré en SVG (2 `<line>`), libellé du toggle qui suit son propre état après clic.
  [`Sidebar.spec.js:56`](../../resources/js/Components/__tests__/Sidebar.spec.js#L56)
- Ajustement de l'assertion d'ordre des items de `<nav>` (le toggle n'en fait plus partie).
  [`Sidebar.spec.js:88`](../../resources/js/Components/__tests__/Sidebar.spec.js#L88)
