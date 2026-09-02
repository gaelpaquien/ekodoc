<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportModal from '@/Components/ImportModal.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';

defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});

const isImportModalOpen = ref(false);

function formatDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(dateString));
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                    Bibliothèque de documents
                </h1>
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    @click="isImportModalOpen = true"
                >
                    Importer
                </button>
            </div>

            <div v-if="documents.length === 0" class="flex flex-col items-center gap-4 py-16 text-center">
                <p class="text-neutral-600 dark:text-neutral-400">
                    Aucun document pour l'instant.
                </p>
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    @click="isImportModalOpen = true"
                >
                    Importer un document
                </button>
            </div>

            <ul v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <li v-for="document in documents" :key="document.id">
                    <Link
                        :href="`/documents/${document.id}`"
                        class="flex h-full flex-col gap-3 rounded-lg border border-neutral-200 bg-white p-4 transition hover:border-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <DocumentTypeBadge :mime-type="document.mime_type" :source="document.source" />
                        <p class="font-medium text-neutral-900 dark:text-neutral-100">
                            {{ document.title }}
                        </p>
                        <div class="mt-auto flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-500">
                            <span>{{ document.category?.name ?? 'Non classé' }}</span>
                            <span>{{ formatDate(document.created_at) }}</span>
                        </div>
                    </Link>
                </li>
            </ul>

            <ImportModal :open="isImportModalOpen" @close="isImportModalOpen = false" />
        </div>
    </AppLayout>
</template>
