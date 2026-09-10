<script setup>
// Read-only display chip for a document's assigned tags (library card,
// Document Detail) — deliberately styled distinctly from the Library's
// active-filter chip (Index.vue: bordered, blue) so the two are never
// visually confused (Code Map, spec-3-1). Mirrors DocumentTypeBadge.vue's
// shape as the badge/chip convention to follow.
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <span
        class="inline-flex w-fit items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
    >
        {{ name }}
    </span>
</template>
