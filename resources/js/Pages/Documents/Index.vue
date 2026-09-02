<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
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
    categoryFilters: {
        type: Array,
        default: () => [],
    },
    typeFilters: {
        type: Array,
        default: () => [],
    },
});

// Fixed set of four types (Boundaries & Constraints, spec-1-7: no fifth
// type) — value matches the `type[]` query value the server recognizes
// (DocumentController::TYPE_MIME_MAP + `created`).
const TYPE_OPTIONS = [
    { value: 'pdf', label: 'PDF' },
    { value: 'word', label: 'Word' },
    { value: 'excel', label: 'Excel' },
    { value: 'created', label: 'Créé' },
];

const page = usePage();
const categories = computed(() => page.props.categories ?? []);

const isImportModalOpen = ref(false);
const searchTerm = ref(props.search);
const selectedCategoryIds = ref([...props.categoryFilters]);
const selectedTypes = ref([...props.typeFilters]);
const searchInputRef = ref(null);

let debounceTimer = null;
// Set right before a programmatic (non-typed) write to `searchTerm`/the
// filter selections so the watchers below can tell it apart from an actual
// user edit and skip re-navigating — otherwise syncing from server props
// (e.g. a browser back/forward restoring a different `?search=` or
// `?category_id[]=`) would itself trigger a redundant `router.get` that
// clobbers the history entry navigation just restored. Two independent
// flags because a single response can update `search` and the filter
// props together, and each side must consume only its own signal.
let isSyncingSearchFromProps = false;
let isSyncingFiltersFromProps = false;

// Keeps the local input in sync when the server-provided `search` prop
// changes from outside this component's own typing (e.g. browser
// back/forward navigation restoring a different `?search=`).
watch(
    () => props.search,
    (value) => {
        if (value !== searchTerm.value) {
            isSyncingSearchFromProps = true;
            searchTerm.value = value;
        }
    },
);

// Same back/forward sync as `search`, for the filter arrays.
watch(
    () => props.categoryFilters,
    (value) => {
        isSyncingFiltersFromProps = true;
        selectedCategoryIds.value = [...value];
    },
);

watch(
    () => props.typeFilters,
    (value) => {
        isSyncingFiltersFromProps = true;
        selectedTypes.value = [...value];
    },
);

// One shared navigation call for search + filters, all reflected in the
// same `router.get` (Design Notes, spec-1-6/1-7) — `category_id[]=` and
// `type[]=` echo through Inertia's default bracket array serialization.
// Clearing any pending debounced search navigation here avoids a redundant
// duplicate request when a filter checkbox (immediate, no debounce) is
// toggled while a search-term debounce is still pending.
function navigate() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    const params = {};

    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    if (selectedCategoryIds.value.length > 0) {
        params.category_id = selectedCategoryIds.value;
    }

    if (selectedTypes.value.length > 0) {
        params.type = selectedTypes.value;
    }

    router.get(
        '/',
        params,
        { preserveState: true, replace: true, only: ['documents', 'search', 'categoryFilters', 'typeFilters'] },
    );
}

watch(searchTerm, () => {
    if (isSyncingSearchFromProps) {
        isSyncingSearchFromProps = false;
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(navigate, 300);
});

// Filters are a discrete selection (checkbox toggle), not free typing —
// no debounce, navigate immediately (Design Notes, spec-1-7).
watch([selectedCategoryIds, selectedTypes], () => {
    if (isSyncingFiltersFromProps) {
        isSyncingFiltersFromProps = false;
        return;
    }

    navigate();
});

// A whitespace-only term is treated as empty by the server (it `trim()`s
// before deciding whether to filter), so the empty-results messaging must
// agree: otherwise a search box containing only spaces would wrongly show
// "no results for your search" instead of the generic empty-library message.
const trimmedSearchTerm = computed(() => searchTerm.value.trim());

const hasActiveFilters = computed(
    () => selectedCategoryIds.value.length > 0 || selectedTypes.value.length > 0,
);

function clearSearch() {
    searchTerm.value = '';
}

function clearFilters() {
    selectedCategoryIds.value = [];
    selectedTypes.value = [];
}

function clearSearchAndFilters() {
    searchTerm.value = '';
    selectedCategoryIds.value = [];
    selectedTypes.value = [];
}

function toggleCategory(categoryId, checked) {
    selectedCategoryIds.value = checked
        ? [...selectedCategoryIds.value, categoryId]
        : selectedCategoryIds.value.filter((id) => id !== categoryId);
}

function toggleType(type, checked) {
    selectedTypes.value = checked
        ? [...selectedTypes.value, type]
        : selectedTypes.value.filter((value) => value !== type);
}

function removeCategoryFilter(categoryId) {
    selectedCategoryIds.value = selectedCategoryIds.value.filter((id) => id !== categoryId);
}

function removeTypeFilter(type) {
    selectedTypes.value = selectedTypes.value.filter((value) => value !== type);
}

function categoryName(categoryId) {
    return categories.value.find((category) => category.id === categoryId)?.name ?? 'Catégorie';
}

function typeLabel(type) {
    return TYPE_OPTIONS.find((option) => option.value === type)?.label ?? type;
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

            <div class="mb-6 flex flex-col gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        Catégorie
                    </legend>
                    <div v-if="categories.length === 0" class="text-sm text-neutral-500 dark:text-neutral-500">
                        Aucune catégorie pour l'instant.
                    </div>
                    <div v-else class="flex flex-wrap gap-x-4 gap-y-2">
                        <label
                            v-for="category in categories"
                            :key="category.id"
                            class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300"
                        >
                            <input
                                type="checkbox"
                                :value="category.id"
                                :checked="selectedCategoryIds.includes(category.id)"
                                class="rounded border-neutral-300 text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-700"
                                @change="toggleCategory(category.id, $event.target.checked)"
                            >
                            {{ category.name }}
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        Type
                    </legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <label
                            v-for="option in TYPE_OPTIONS"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300"
                        >
                            <input
                                type="checkbox"
                                :value="option.value"
                                :checked="selectedTypes.includes(option.value)"
                                class="rounded border-neutral-300 text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-700"
                                @change="toggleType(option.value, $event.target.checked)"
                            >
                            {{ option.label }}
                        </label>
                    </div>
                </fieldset>

                <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="text-sm text-neutral-500 dark:text-neutral-500">Filtres actifs :</span>
                    <button
                        v-for="categoryId in selectedCategoryIds"
                        :key="`category-${categoryId}`"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300"
                        :aria-label="`Retirer le filtre catégorie ${categoryName(categoryId)}`"
                        @click="removeCategoryFilter(categoryId)"
                    >
                        {{ categoryName(categoryId) }}
                        <span aria-hidden="true">×</span>
                    </button>
                    <button
                        v-for="type in selectedTypes"
                        :key="`type-${type}`"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300"
                        :aria-label="`Retirer le filtre type ${typeLabel(type)}`"
                        @click="removeTypeFilter(type)"
                    >
                        {{ typeLabel(type) }}
                        <span aria-hidden="true">×</span>
                    </button>
                    <button
                        type="button"
                        class="text-xs font-medium text-neutral-500 underline hover:text-neutral-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-400 dark:hover:text-neutral-200"
                        @click="clearFilters"
                    >
                        Retirer tous les filtres
                    </button>
                </div>
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div v-if="documents.length === 0 && hasActiveFilters" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Aucun document ne correspond à ces critères.
                    </p>
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-900"
                        @click="clearSearchAndFilters"
                    >
                        {{ trimmedSearchTerm ? 'Retirer les filtres et la recherche' : 'Retirer les filtres' }}
                    </button>
                </div>

                <div v-else-if="documents.length === 0 && trimmedSearchTerm" class="flex flex-col items-center gap-4 py-16 text-center">
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
