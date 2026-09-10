<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportModal from '@/Components/ImportModal.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';
import TagSelector from '@/Components/TagSelector.vue';
import TagChip from '@/Components/TagChip.vue';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: '',
    },
    tagFilters: {
        type: Array,
        default: () => [],
    },
    typeFilters: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const allTags = computed(() => page.props.tags ?? []);

// Fixed set of four types (Boundaries & Constraints, spec-1-7: no fifth
// type) — value matches the `type[]` query value the server recognizes
// (DocumentMimeTypes::TYPE_TO_MIME + `created`). The pdf/word/excel entries
// come from the shared `documentTypeOptions` Inertia prop (single source of
// truth, Epic 1/2 retrospectives action item 3); `created` stays a local
// entry since it filters on `source`, not a mime type, and has no
// server-side mime-type counterpart to derive it from.
const TYPE_OPTIONS = computed(() => [
    ...(page.props.documentTypeOptions ?? []),
    { value: 'created', label: 'Créé' },
]);

const isImportModalOpen = ref(false);
const searchTerm = ref(props.search);
const selectedTagIds = ref([...props.tagFilters]);
const selectedTypes = ref([...props.typeFilters]);
const searchInputRef = ref(null);

let debounceTimer = null;
// Set right before a programmatic (non-typed) write to `searchTerm`/the
// filter selections so the watchers below can tell it apart from an actual
// user edit and skip re-navigating — otherwise syncing from server props
// (e.g. a browser back/forward restoring a different `?search=` or
// `?tag_id[]=`) would itself trigger a redundant `router.get` that
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
    () => props.tagFilters,
    (value) => {
        isSyncingFiltersFromProps = true;
        selectedTagIds.value = [...value];
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
// same `router.get` (Design Notes, spec-1-6/1-7) — `tag_id[]=` and
// `type[]=` echo through Inertia's default bracket array serialization.
// Clearing any pending debounced search navigation here avoids a redundant
// duplicate request when a filter selection (immediate, no debounce) is
// made while a search-term debounce is still pending.
function navigate() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    const params = {};

    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    if (selectedTagIds.value.length > 0) {
        params.tag_id = selectedTagIds.value;
    }

    if (selectedTypes.value.length > 0) {
        params.type = selectedTypes.value;
    }

    router.get(
        '/',
        params,
        { preserveState: true, replace: true, only: ['documents', 'search', 'tagFilters', 'typeFilters'] },
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

// Filters are a discrete selection, not free typing — no debounce,
// navigate immediately (Design Notes, spec-1-7).
watch([selectedTagIds, selectedTypes], () => {
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
    () => selectedTagIds.value.length > 0 || selectedTypes.value.length > 0,
);

function clearSearch() {
    searchTerm.value = '';
}

function clearFilters() {
    selectedTagIds.value = [];
    selectedTypes.value = [];
}

function clearSearchAndFilters() {
    searchTerm.value = '';
    selectedTagIds.value = [];
    selectedTypes.value = [];
}

function toggleType(type, checked) {
    selectedTypes.value = checked
        ? [...selectedTypes.value, type]
        : selectedTypes.value.filter((value) => value !== type);
}

function removeTagFilter(tagId) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
}

function removeTypeFilter(type) {
    selectedTypes.value = selectedTypes.value.filter((value) => value !== type);
}

function tagName(tagId) {
    return allTags.value.find((tag) => tag.id === tagId)?.name ?? 'Tag';
}

function typeLabel(type) {
    return TYPE_OPTIONS.value.find((option) => option.value === type)?.label ?? type;
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
                <h1 class="text-2xl font-semibold text-foreground">
                    Bibliothèque de documents
                </h1>
                <div class="flex items-center gap-3">
                    <Link
                        href="/documents/create"
                        class="rounded-md border border-border bg-surface-alt px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    >
                        Créer un document
                    </Link>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="isImportModalOpen = true"
                    >
                        Importer
                    </button>
                </div>
            </div>

            <div class="relative mb-6">
                <input
                    ref="searchInputRef"
                    v-model="searchTerm"
                    type="search"
                    placeholder="Rechercher un document (appuyez sur / pour y accéder)"
                    class="w-full rounded-md border border-border bg-background px-4 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    aria-label="Rechercher un document"
                >
            </div>

            <div class="mb-6 flex flex-col gap-3 rounded-lg border border-border p-4">
                <fieldset class="max-w-xs">
                    <legend class="mb-2 text-sm font-medium text-foreground">
                        Filtrer par tag
                    </legend>
                    <TagSelector v-model="selectedTagIds" />
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-foreground">
                        Type
                    </legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <label
                            v-for="option in TYPE_OPTIONS"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm text-foreground"
                        >
                            <input
                                type="checkbox"
                                :value="option.value"
                                :checked="selectedTypes.includes(option.value)"
                                class="rounded-sm border-border text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                                @change="toggleType(option.value, $event.target.checked)"
                            >
                            {{ option.label }}
                        </label>
                    </div>
                </fieldset>

                <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="text-sm text-muted">Filtres actifs :</span>
                    <button
                        v-for="tagId in selectedTagIds"
                        :key="`tag-${tagId}`"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-label="`Retirer le filtre tag ${tagName(tagId)}`"
                        @click="removeTagFilter(tagId)"
                    >
                        {{ tagName(tagId) }}
                        <span aria-hidden="true">×</span>
                    </button>
                    <button
                        v-for="type in selectedTypes"
                        :key="`type-${type}`"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-label="`Retirer le filtre type ${typeLabel(type)}`"
                        @click="removeTypeFilter(type)"
                    >
                        {{ typeLabel(type) }}
                        <span aria-hidden="true">×</span>
                    </button>
                    <button
                        type="button"
                        class="text-xs font-medium text-muted underline hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="clearFilters"
                    >
                        Retirer tous les filtres
                    </button>
                </div>
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div v-if="documents.length === 0 && hasActiveFilters" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document ne correspond à ces filtres.
                    </p>
                    <button
                        type="button"
                        class="rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="clearSearchAndFilters"
                    >
                        {{ trimmedSearchTerm ? 'Retirer les filtres et la recherche' : 'Retirer les filtres' }}
                    </button>
                </div>

                <div v-else-if="documents.length === 0 && trimmedSearchTerm" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document ne correspond à votre recherche.
                    </p>
                    <button
                        type="button"
                        class="rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="clearSearch"
                    >
                        Vider la recherche
                    </button>
                </div>

                <div v-else-if="documents.length === 0" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document pour l'instant.
                    </p>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="isImportModalOpen = true"
                    >
                        Importer un document
                    </button>
                </div>

                <ul v-else class="border-t border-border">
                    <li v-for="document in documents" :key="document.id">
                        <Link
                            :href="`/documents/${document.id}`"
                            class="flex items-center gap-3 border-b border-border px-2 py-3 transition hover:rounded-sm hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        >
                            <DocumentTypeBadge class="shrink-0" :mime-type="document.mime_type" :source="document.source" />
                            <span class="min-w-0 flex-1 truncate font-medium text-foreground">
                                {{ document.title }}
                            </span>
                            <div v-if="document.tags && document.tags.length > 0" class="flex shrink-0 flex-wrap gap-1">
                                <TagChip v-for="tag in document.tags" :key="tag.id" :name="tag.name" />
                            </div>
                            <span class="w-24 shrink-0 text-right text-xs text-muted">{{ formatDate(document.created_at) }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <ImportModal :open="isImportModalOpen" @close="isImportModalOpen = false" />
        </div>
    </AppLayout>
</template>
