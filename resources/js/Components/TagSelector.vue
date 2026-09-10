<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Reusable multi-tag selector — mounted identically everywhere a document's
// tags are assigned or filtered on (Import modal, editor create/edit,
// Document Detail, Library filter). Only ever offers tags already present
// in the shared `tags` Inertia prop (Boundaries & Constraints, spec-3-1):
// no free-text entry, no create-on-the-fly (tag management is story 3.5).
// It never writes anything itself: it only emits `update:modelValue` with
// the full new array of selected tag ids — each host page decides whether
// that means "hold for the next form submit" (Import modal, editor) or
// "persist immediately" (Document Detail, via PATCH
// /documents/{id}/tags), or "refine the library query" (Index.vue).
const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const allTags = computed(() => page.props.tags ?? []);

const query = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(-1);
const inputRef = ref(null);

const selectedTags = computed(() => props.modelValue
    .map((id) => allTags.value.find((tag) => tag.id === id))
    .filter(Boolean));

// Already-selected tags never reappear in the suggestion list — nothing
// left to pick twice, matching sync()'s own set semantics.
const filteredTags = computed(() => {
    const term = query.value.trim().toLowerCase();

    return allTags.value.filter((tag) => {
        if (props.modelValue.includes(tag.id)) {
            return false;
        }

        return term === '' || tag.name.toLowerCase().includes(term);
    });
});

function openSuggestions() {
    if (props.disabled) {
        return;
    }

    isOpen.value = true;
    highlightedIndex.value = filteredTags.value.length > 0 ? 0 : -1;
}

function closeSuggestions() {
    isOpen.value = false;
    highlightedIndex.value = -1;
    // Otherwise a term typed then abandoned (blur, Escape) without picking
    // a suggestion would silently reappear — stale filter and all — the
    // next time this input regains focus.
    query.value = '';
}

function selectTag(tag) {
    emit('update:modelValue', [...props.modelValue, tag.id]);
    query.value = '';
    closeSuggestions();
    inputRef.value?.focus();
}

function removeTag(tagId) {
    emit('update:modelValue', props.modelValue.filter((id) => id !== tagId));
    inputRef.value?.focus();
}

function onKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (!isOpen.value) {
            openSuggestions();
            return;
        }

        if (filteredTags.value.length > 0) {
            highlightedIndex.value = (highlightedIndex.value + 1) % filteredTags.value.length;
        }

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (isOpen.value && filteredTags.value.length > 0) {
            highlightedIndex.value = (highlightedIndex.value - 1 + filteredTags.value.length) % filteredTags.value.length;
        }

        return;
    }

    if (event.key === 'Enter') {
        // No free-text creation (Boundaries & Constraints, spec-3-1):
        // Enter only ever selects an already-highlighted, already-existing
        // suggestion — it never submits the typed text as a new tag.
        if (isOpen.value && highlightedIndex.value >= 0 && filteredTags.value[highlightedIndex.value]) {
            event.preventDefault();
            selectTag(filteredTags.value[highlightedIndex.value]);
        }

        return;
    }

    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        closeSuggestions();
    }
}

// Lets a host page (Editor.vue's two-step "Enregistrer" reveal, Design
// Notes spec-2-1) move focus onto this control right after it's revealed,
// without depending on a DOM id that could collide if more than one
// instance were ever mounted on the same page at once.
defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <div class="w-full">
        <label for="tag-selector-input" class="mb-1 block text-sm font-medium text-foreground">
            Tags
        </label>

        <div v-if="selectedTags.length > 0" class="mb-2 flex flex-wrap gap-2">
            <span
                v-for="tag in selectedTags"
                :key="tag.id"
                class="inline-flex items-center gap-1 rounded-lg bg-surface-alt py-0.5 pl-2.5 pr-1 text-xs font-medium text-foreground"
            >
                {{ tag.name }}
                <button
                    type="button"
                    class="rounded-full p-0.5 text-muted hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed dark:hover:text-red-400 dark:focus-visible:ring-background"
                    :disabled="disabled"
                    :aria-label="`Retirer le tag ${tag.name}`"
                    @click="removeTag(tag.id)"
                >
                    <span aria-hidden="true">×</span>
                </button>
            </span>
        </div>

        <div class="relative">
            <input
                id="tag-selector-input"
                ref="inputRef"
                v-model="query"
                type="text"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="isOpen"
                :aria-controls="isOpen ? 'tag-selector-listbox' : undefined"
                :aria-activedescendant="isOpen && highlightedIndex >= 0 && filteredTags[highlightedIndex]
                    ? `tag-selector-option-${filteredTags[highlightedIndex].id}`
                    : undefined"
                placeholder="Rechercher un tag…"
                class="w-full rounded-sm border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                :disabled="disabled"
                @focus="openSuggestions"
                @blur="closeSuggestions"
                @keydown="onKeydown"
            >

            <ul
                v-if="isOpen"
                id="tag-selector-listbox"
                role="listbox"
                class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-sm border border-border bg-surface py-1 shadow-lg"
            >
                <li
                    v-if="filteredTags.length === 0"
                    role="presentation"
                    class="px-3 py-2 text-sm text-muted"
                >
                    {{ allTags.length === 0 ? "Aucun tag n'existe encore." : 'Aucun tag ne correspond.' }}
                </li>
                <li
                    v-for="(tag, index) in filteredTags"
                    :id="`tag-selector-option-${tag.id}`"
                    :key="tag.id"
                    role="option"
                    :aria-selected="index === highlightedIndex"
                    class="cursor-pointer px-3 py-2 text-sm text-foreground"
                    :class="{ 'bg-border': index === highlightedIndex }"
                    @mousedown.prevent="selectTag(tag)"
                    @mouseenter="highlightedIndex = index"
                >
                    {{ tag.name }}
                </li>
            </ul>
        </div>
    </div>
</template>
