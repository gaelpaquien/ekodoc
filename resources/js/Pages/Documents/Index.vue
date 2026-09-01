<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportModal from '@/Components/ImportModal.vue';

defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});

const isImportModalOpen = ref(false);
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

            <p v-if="documents.length === 0" class="text-neutral-600 dark:text-neutral-400">
                Aucun document pour le moment.
            </p>

            <ul v-else class="divide-y divide-neutral-200 dark:divide-neutral-800">
                <li v-for="document in documents" :key="document.id" class="py-3">
                    <Link :href="`/documents/${document.id}`" class="text-blue-600 hover:underline dark:text-blue-400">
                        {{ document.title }}
                    </Link>
                </li>
            </ul>

            <ImportModal :open="isImportModalOpen" @close="isImportModalOpen = false" />
        </div>
    </AppLayout>
</template>
