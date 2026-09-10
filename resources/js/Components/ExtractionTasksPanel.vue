<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const tasks = computed(() => page.props.pendingExtractions ?? []);
const isExpanded = ref(false);

const statusLabels = {
    pending: 'En attente',
    processing: 'Extraction en cours…',
};

let pollTimer = null;

function poll() {
    router.reload({ only: ['pendingExtractions'], preserveScroll: true, preserveState: true });
}

watch(
    tasks,
    (list) => {
        if (list.length > 0 && !pollTimer) {
            pollTimer = setInterval(poll, 3000);
        } else if (list.length === 0 && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <div v-if="tasks.length > 0" class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-4">
        <div class="w-full max-w-md rounded-lg border border-border bg-surface shadow-lg">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 rounded-lg px-4 py-3 text-sm font-medium text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :aria-expanded="isExpanded"
                @click="isExpanded = !isExpanded"
            >
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Extraction de texte : {{ tasks.length }} en cours
                </span>
                <svg
                    class="h-4 w-4 shrink-0 text-muted transition-transform"
                    :class="{ 'rotate-180': isExpanded }"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </button>

            <ul v-if="isExpanded" class="max-h-56 overflow-y-auto border-t border-border px-4 py-2">
                <li v-for="task in tasks" :key="task.id" class="flex items-center justify-between gap-3 py-1.5 text-sm text-foreground">
                    <span class="truncate">{{ task.title }}</span>
                    <span class="shrink-0 text-xs text-muted">
                        {{ statusLabels[task.extraction_status] ?? task.extraction_status }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
