<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

// Reusable category selector (choose / create / clear) — mounted
// identically in the Import modal and the Document Detail page (Boundaries
// & Constraints, spec-1-5). It never writes `category_id` itself: it only
// emits `update:modelValue`, leaving each host page free to decide whether
// that means "hold it for the next form submit" (ImportModal, since the
// document doesn't exist yet) or "persist immediately"
// (Show.vue, via PATCH /documents/{id}/category).
const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const categories = computed(() => page.props.categories ?? []);

const isCreating = ref(false);
const newCategoryName = ref('');
const createError = ref('');
const isSubmittingCreate = ref(false);
const nameInputRef = ref(null);
const addCategoryButtonRef = ref(null);

function onSelectChange(event) {
    const { value } = event.target;
    emit('update:modelValue', value === '' ? null : Number(value));
}

async function startCreating() {
    isCreating.value = true;
    newCategoryName.value = '';
    createError.value = '';
    await nextTick();
    nameInputRef.value?.focus();
}

async function cancelCreating() {
    isCreating.value = false;
    newCategoryName.value = '';
    createError.value = '';
    // Keyboard/screen-reader focus was on the (now-removed) inline form —
    // return it to the control that opened it rather than dropping it.
    await nextTick();
    addCategoryButtonRef.value?.focus();
}

function submitNewCategory() {
    // Guards against a double-submit if this fires twice in quick
    // succession (e.g. Enter key auto-repeat outrunning the input's
    // `:disabled` binding actually reaching the DOM).
    if (isSubmittingCreate.value) {
        return;
    }

    const name = newCategoryName.value.trim();

    if (!name) {
        createError.value = 'Merci de saisir un nom de catégorie.';
        return;
    }

    isSubmittingCreate.value = true;
    createError.value = '';

    router.post('/categories', { name }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            // No id comes back out of band — the shared `categories` prop
            // has just refreshed on this same response, so preselect the
            // new category by the name known from the form (Design Notes,
            // spec-1-5).
            const created = categories.value.find(
                (category) => category.name.toLowerCase() === name.toLowerCase(),
            );

            if (created) {
                emit('update:modelValue', created.id);
            }

            isCreating.value = false;
            newCategoryName.value = '';
            await nextTick();
            addCategoryButtonRef.value?.focus();
        },
        onError: (errors) => {
            createError.value = errors.name ?? 'Impossible de créer la catégorie.';
        },
        onFinish: () => {
            isSubmittingCreate.value = false;
        },
    });
}
</script>

<template>
    <div>
        <label for="category-picker-select" class="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
            Catégorie
        </label>

        <div v-if="!isCreating" class="flex items-center gap-2">
            <select
                id="category-picker-select"
                :value="modelValue ?? ''"
                :disabled="disabled"
                class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                @change="onSelectChange"
            >
                <option value="">Non classé</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>
            <button
                ref="addCategoryButtonRef"
                type="button"
                class="shrink-0 whitespace-nowrap rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:border-blue-500 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300"
                :disabled="disabled"
                @click="startCreating"
            >
                + Nouvelle catégorie
            </button>
        </div>

        <div v-else class="flex items-center gap-2">
            <input
                ref="nameInputRef"
                v-model="newCategoryName"
                type="text"
                placeholder="Nom de la nouvelle catégorie"
                aria-label="Nom de la nouvelle catégorie"
                class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                :disabled="isSubmittingCreate"
                @keydown.enter.prevent="submitNewCategory"
                @keydown.escape.prevent="cancelCreating"
            />
            <button
                type="button"
                class="shrink-0 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isSubmittingCreate"
                @click="submitNewCategory"
            >
                Créer
            </button>
            <button
                type="button"
                class="shrink-0 rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-300"
                :disabled="isSubmittingCreate"
                @click="cancelCreating"
            >
                Annuler
            </button>
        </div>

        <p v-if="createError" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
            {{ createError }}
        </p>
    </div>
</template>
