<script setup>
import { computed } from 'vue';

const props = defineProps({
    mimeType: {
        type: String,
        default: null,
    },
    source: {
        type: String,
        default: null,
    },
});

// Single source of truth for the document type label — shared by the
// library card (Index.vue) and the document detail page (Show.vue).
const MIME_TYPE_LABELS = {
    'application/pdf': 'PDF',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
};

const label = computed(() => {
    if (props.mimeType && MIME_TYPE_LABELS[props.mimeType]) {
        return MIME_TYPE_LABELS[props.mimeType];
    }

    if (props.source === 'created') {
        return 'Créé';
    }

    return 'Document';
});
</script>

<template>
    <span
        class="inline-flex w-fit items-center rounded-full border border-neutral-300 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:border-neutral-700 dark:text-neutral-300"
    >
        {{ label }}
    </span>
</template>
