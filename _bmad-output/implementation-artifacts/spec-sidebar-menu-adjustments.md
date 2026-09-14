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

**Problem:** Le side menu avait trois petits défauts visuels : le toggle thème clair/sombre était isolé en bas plutôt que dans la liste des boutons, aucun séparateur ne distinguait le bloc "EkoDoc - Démo" de la liste des boutons, et le footer affichait un cœur brisé (💔) plutôt qu'un cœur barré.

**Approach:** Déplacer le bouton toggle thème juste après "Configuration" (hors du landmark `<nav>` pour ne pas casser la sémantique de navigation, mais visuellement dans la même liste via `display:contents`) ; ajouter un `<hr>` sous le bloc "EkoDoc - Démo" ; remplacer l'emoji 💔 par une icône SVG cœur barrée de deux traits en croix.

## Suggested Review Order

**Toggle thème déplacé dans la liste**

- Bouton retiré du `<nav aria-label="Navigation principale">` (souci d'accessibilité relevé en revue) et repositionné juste après Configuration, dans un conteneur flex partagé avec `<nav class="contents">` pour garder le même espacement visuel.
  [`Sidebar.vue:162`](../../resources/js/Components/Sidebar.vue#L162)

**Séparateur brand / liste**

- `<hr>` ajouté entre "EkoDoc - Démo" et le conteneur de la liste des boutons.
  [`Sidebar.vue:79`](../../resources/js/Components/Sidebar.vue#L79)

**Cœur barré du footer**

- Emoji 💔 remplacé par un cœur SVG inline barré de deux traits diagonaux (rendu "annulé", pas "brisé").
  [`Sidebar.vue:188`](../../resources/js/Components/Sidebar.vue#L188)

**Tests**

- Nouveaux tests : présence du séparateur, cœur barré en SVG (2 `<line>`), libellé du toggle qui suit son propre état après clic.
  [`Sidebar.spec.js:56`](../../resources/js/Components/__tests__/Sidebar.spec.js#L56)
- Ajustement de l'assertion d'ordre des items de `<nav>` (le toggle n'en fait plus partie).
  [`Sidebar.spec.js:88`](../../resources/js/Components/__tests__/Sidebar.spec.js#L88)
