# Deferred Work

<!-- Append-only. Each entry: source_spec, summary, evidence. -->

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: Déposer plusieurs fichiers à la fois dans la modale d'import n'affiche aucun message — seul le premier est importé silencieusement.
  evidence: Edge Case Hunter (step-04 review) — `resources/js/Components/ImportModal.vue` `onDrop()` ne vérifie pas `event.dataTransfer.files.length > 1`. Faible impact (usage solo), amélioration UX à bas coût si une session future touche ce fichier.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: `Document` déclare `use HasFactory` mais aucune `DocumentFactory` n'existe encore.
  evidence: Blind Hunter (step-04 review) — ne casse rien tant que `Document::factory()` n'est pas appelé ; probablement nécessaire pour les tests de la Story 1.2 (bibliothèque). À créer à ce moment-là.

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: Le mapping `DocumentSource` → libellé français est dupliqué en dur dans `resources/js/Pages/Documents/Show.vue` au lieu d'être centralisé.
  evidence: Blind Hunter (step-04 review) — seulement 2 valeurs aujourd'hui (`imported`/`created`), coût de duplication encore faible ; à centraliser si un composant partagé de libellés de type apparaît (Story 1.2 affichera aussi ces badges).

- source_spec: `_bmad-output/implementation-artifacts/spec-1-1-import-document.md`
  summary: L'extraction de texte de très gros fichiers Word/Excel (proche de la limite 20 Mo) pourrait épuiser `memory_limit` PHP avec une erreur fatale non rattrapable, court-circuitant la garantie "l'extraction échoue silencieusement, jamais l'import".
  evidence: Edge Case Hunter (step-04 review) — risque réel mais faible en pratique (limite 20 Mo + `memory_limit` PHP par défaut généralement suffisant pour ce volume) ; nécessiterait un parsing par flux pour être vraiment corrigé, hors proportion pour cette story.
