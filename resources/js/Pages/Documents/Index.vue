<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportModal from '@/Components/ImportModal.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: '',
    },
});

const isImportModalOpen = ref(false);
const searchTerm = ref(props.search);
const searchInputRef = ref(null);

let debounceTimer = null;
// Set right before a programmatic (non-typed) write to `searchTerm` so the
// debounce watcher below can tell it apart from the user actually typing
// and skip re-navigating — otherwise syncing from `props.search` (e.g. a
// browser back/forward restoring a different `?search=`) would itself
// trigger a redundant `router.get` 300ms later that clobbers the history
// entry navigation just restored.
let isSyncingFromProps = false;

// Keeps the local input in sync when the server-provided `search` prop
// changes from outside this component's own typing (e.g. browser
// back/forward navigation restoring a different `?search=`).
watch(
    () => props.search,
    (value) => {
        if (value !== searchTerm.value) {
            isSyncingFromProps = true;
            searchTerm.value = value;
        }
    },
);

watch(searchTerm, (value) => {
    if (isSyncingFromProps) {
        isSyncingFromProps = false;
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        router.get(
            '/',
            value ? { search: value } : {},
            { preserveState: true, replace: true, only: ['documents', 'search'] },
        );
    }, 300);
});

// A whitespace-only term is treated as empty by the server (it `trim()`s
// before deciding whether to filter), so the empty-results messaging must
// agree: otherwise a search box containing only spaces would wrongly show
// "no results for your search" instead of the generic empty-library message.
const trimmedSearchTerm = computed(() => searchTerm.value.trim());

function clearSearch() {
    searchTerm.value = '';
}

// `/` focuses the search bar unless a field is already active, so the
// character itself is never inserted into whatever the user is typing
// (Boundaries & Constraints, spec-1-6). Also ignored while the Import
// modal is open — otherwise `/` would steal focus out to the search bar
// from behind the dialog (e.g. while its close button is focused) and
// break ImportModal's own Escape/Tab focus trap — and ignored with any
// modifier held, so an OS/browser shortcut like Ctrl+/ is never hijacked.
function onGlobalKeydown(event) {
    if (
        event.key !== '/'
        || isImportModalOpen.value
        || event.ctrlKey
        || event.metaKey
        || event.altKey
    ) {
        return;
    }

    const active = document.activeElement;
    const isEditable = active && (
        active.tagName === 'INPUT'
        || active.tagName === 'TEXTAREA'
        || active.isContentEditable
    );

    if (isEditable) {
        return;
    }

    event.preventDefault();
    searchInputRef.value?.focus();
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

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

            <div class="relative mb-6">
                <input
                    ref="searchInputRef"
                    v-model="searchTerm"
                    type="search"
                    placeholder="Rechercher un document (appuyez sur / pour y accéder)"
                    class="w-full rounded-md border border-neutral-200 bg-white px-4 py-2 text-sm text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100"
                    aria-label="Rechercher un document"
                >
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div v-if="documents.length === 0 && trimmedSearchTerm" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Aucun document ne correspond à votre recherche.
                    </p>
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                        @click="clearSearch"
                    >
                        Vider la recherche
                    </button>
                </div>

                <div v-else-if="documents.length === 0" class="flex flex-col items-center gap-4 py-16 text-center">
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
            </div>

            <ImportModal :open="isImportModalOpen" @close="isImportModalOpen = false" />
        </div>
    </AppLayout>
</template>
