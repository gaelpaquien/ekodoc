<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
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
});

const page = usePage();
const allTags = computed(() => page.props.tags ?? []);

const searchTerm = ref(props.search);
const selectedTagIds = ref([...props.tagFilters]);
const searchInputRef = ref(null);

let debounceTimer = null;
// Set right before a programmatic (non-typed) write to `searchTerm`/the tag
// selection so the watchers below can tell it apart from an actual user
// edit and skip re-navigating — otherwise syncing from server props (e.g. a
// browser back/forward restoring a different `?search=` or `?tag_id[]=`)
// would itself trigger a redundant `router.get` that clobbers the history
// entry navigation just restored. Two independent flags because a single
// response can update `search` and `tagFilters` together, and each side
// must consume only its own signal.
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

// Same back/forward sync as `search`, for the tag filter.
watch(
    () => props.tagFilters,
    (value) => {
        isSyncingFiltersFromProps = true;
        selectedTagIds.value = [...value];
    },
);

// One shared navigation call for search + tag filter, no pagination on this
// surface (Boundaries & Constraints, spec-3-4). Clearing any pending
// debounced search navigation here avoids a redundant duplicate request
// when the tag selection (immediate, no debounce) changes while a
// search-term debounce is still pending.
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

    router.get(
        '/recherche',
        params,
        { preserveState: true, replace: true, only: ['documents', 'search', 'tagFilters'] },
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
watch(selectedTagIds, () => {
    if (isSyncingFiltersFromProps) {
        isSyncingFiltersFromProps = false;
        return;
    }

    navigate();
});

// A whitespace-only term is treated as empty by the server (it `trim()`s
// before deciding whether to filter), so the empty-results messaging must
// agree: otherwise a search box containing only spaces would wrongly show
// "no results" instead of the neutral, no-results-yet state (AC2).
const trimmedSearchTerm = computed(() => searchTerm.value.trim());

function removeTagFilter(tagId) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
}

function tagName(tagId) {
    return allTags.value.find((tag) => tag.id === tagId)?.name ?? 'Tag';
}

// `/` focuses the search bar unless a field is already active, so the
// character itself is never inserted into whatever the user is typing
// (Boundaries & Constraints, spec-1-6) — moved as-is from Index.vue, minus
// the Import modal guard: this surface has no Import modal (Code Map,
// spec-3-4). Also ignored with any modifier held, so an OS/browser
// shortcut like Ctrl+/ is never hijacked.
function onGlobalKeydown(event) {
    if (
        event.key !== '/'
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
    // AC2: the field carries focus as soon as the surface loads, before any
    // term has been typed.
    searchInputRef.value?.focus();
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
            <h1 class="mb-6 text-2xl font-semibold text-foreground">
                Recherche
            </h1>

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

                <div v-if="selectedTagIds.length > 0" class="flex flex-wrap items-center gap-2 pt-1">
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
                </div>
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div v-if="documents.length === 0 && trimmedSearchTerm" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document ne correspond à votre recherche.
                    </p>
                </div>

                <ul v-else-if="documents.length > 0" class="border-t border-border">
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
        </div>
    </AppLayout>
</template>
