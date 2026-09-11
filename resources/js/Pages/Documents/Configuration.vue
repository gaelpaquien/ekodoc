<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// Sole channel back from TagController::destroy() (AD-13 pattern, mirrors
// `uploadedImage`/`uploadedAttachment`): Laravel's flash bag ages it out
// automatically after the one request that follows the redirect, so no
// manual cleanup is needed here.
const tagDeletedMessage = computed(() => {
    const flashed = page.props.flash?.tagDeleted;

    return flashed ? `Tag supprimé — détaché de ${flashed.count} documents.` : '';
});

// --- Création ----------------------------------------------------------

const createForm = useForm({ name: '' });
const createNameInputRef = ref(null);

// Case-insensitive duplicate check against the already-loaded `tags` prop
// (AC2) — surfaced in the field before the round trip to the server, which
// still re-checks and remains the actual source of truth (CreateTagRequest).
const createDuplicateName = computed(() => {
    const candidate = createForm.name.trim().toLowerCase();

    if (candidate === '') {
        return null;
    }

    return props.tags.find((tag) => tag.name.toLowerCase() === candidate) ?? null;
});

const createDuplicateError = computed(() => (
    createDuplicateName.value ? `Un tag « ${createDuplicateName.value.name} » existe déjà.` : ''
));

const createNameError = computed(() => createDuplicateError.value || createForm.errors.name || '');

function submitCreate() {
    if (createDuplicateName.value) {
        return;
    }

    createForm.post('/tags', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name');
        },
    });
}

// Given no tag exists, the only meaningful action on this page is creating
// one — carries focus there right away (I/O matrix "Aucun tag").
onMounted(() => {
    if (props.tags.length === 0) {
        createNameInputRef.value?.focus();
    }
});

// --- Renommage -----------------------------------------------------------

const renamingTagId = ref(null);
const renameForm = useForm({ name: '' });
let renameTriggerElement = null;

// A plain `ref="renameInputRef"` would be auto-collected into an array here
// (Vue's "ref inside v-for" behavior applies to any descendant of the
// `v-for="tag in tags"` <li>, not just a direct child) even though only one
// row's input ever actually renders at a time (the `v-if` above). A
// function ref sidesteps that: Vue just invokes it with the element (or
// `null` on unmount) instead of pushing into an array.
let renameInputEl = null;
function setRenameInputRef(el) {
    renameInputEl = el;
}

const renameDuplicateName = computed(() => {
    const candidate = renameForm.name.trim().toLowerCase();

    if (candidate === '') {
        return null;
    }

    return props.tags.find((tag) => tag.id !== renamingTagId.value && tag.name.toLowerCase() === candidate) ?? null;
});

const renameDuplicateError = computed(() => (
    renameDuplicateName.value ? `Un tag « ${renameDuplicateName.value.name} » existe déjà.` : ''
));

const renameNameError = computed(() => renameDuplicateError.value || renameForm.errors.name || '');

async function startRename(tag, event) {
    renamingTagId.value = tag.id;
    renameTriggerElement = event?.currentTarget ?? document.activeElement;
    renameForm.reset('name');
    renameForm.clearErrors();
    renameForm.name = tag.name;
    await nextTick();
    renameInputEl?.focus();
    renameInputEl?.select();
}

// Mirrors closeDeleteDialog()'s focus-restoration pattern — cancelling or
// completing a rename must never silently drop focus to <body>.
function focusRenameTrigger() {
    if (renameTriggerElement instanceof HTMLElement) {
        renameTriggerElement.focus();
    }
}

function cancelRename() {
    renamingTagId.value = null;
    renameForm.reset('name');
    renameForm.clearErrors();
    focusRenameTrigger();
}

function submitRename(tag) {
    if (renameDuplicateName.value) {
        return;
    }

    renameForm.patch(`/tags/${tag.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            renamingTagId.value = null;
            focusRenameTrigger();
        },
    });
}

// --- Suppression -----------------------------------------------------------

// Mirrors Show.vue's delete dialog exactly (Boundaries & Constraints,
// spec-3-5): role="dialog", a focus trap, Escape to cancel, focus restored
// to the trigger on close.
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const deleteError = ref('');
const tagToDelete = ref(null);
const deleteDialogRef = ref(null);
const cancelDeleteButtonRef = ref(null);
let deleteTriggerElement = null;

async function openDeleteDialog(tag, event) {
    tagToDelete.value = tag;
    deleteTriggerElement = event?.currentTarget ?? document.activeElement;
    deleteError.value = '';
    isDeleteDialogOpen.value = true;
    await nextTick();
    // Default focus lands on "Annuler", not the destructive action itself —
    // a stray Enter press right after opening must never confirm deletion.
    cancelDeleteButtonRef.value?.focus();
}

function closeDeleteDialog() {
    if (isDeleting.value) {
        // A delete request is in flight: ignore the close request rather
        // than letting the user believe they cancelled while the deletion
        // still completes underneath them.
        return;
    }

    isDeleteDialogOpen.value = false;
    deleteError.value = '';

    if (deleteTriggerElement instanceof HTMLElement) {
        deleteTriggerElement.focus();
    }
}

function confirmDelete() {
    if (isDeleting.value || !tagToDelete.value) {
        // A rapid double-click can fire before Vue re-renders the
        // `:disabled` attribute onto the Confirm button — guard here too
        // so a second click never sends a second DELETE request.
        return;
    }

    isDeleting.value = true;
    deleteError.value = '';

    router.delete(`/tags/${tagToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteDialogOpen.value = false;

            if (deleteTriggerElement instanceof HTMLElement) {
                deleteTriggerElement.focus();
            }
        },
        onError: () => {
            deleteError.value = 'Impossible de supprimer le tag.';
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function onDeleteDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeDeleteDialog();
        return;
    }

    if (event.key === 'Tab') {
        trapDeleteDialogFocus(event);
    }
}

function trapDeleteDialogFocus(event) {
    const focusable = deleteDialogRef.value?.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (!focusable || focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="mb-6 text-2xl font-semibold text-foreground">
                Configuration
            </h1>

            <p v-if="tagDeletedMessage" role="status" aria-live="polite" class="mb-6 rounded-md border border-border bg-surface-alt px-4 py-2 text-sm text-foreground">
                {{ tagDeletedMessage }}
            </p>

            <form class="mb-8 flex flex-col gap-2 rounded-lg border border-border p-4" @submit.prevent="submitCreate">
                <label for="create-tag-name" class="text-sm font-medium text-foreground">
                    Créer un tag
                </label>
                <div class="flex gap-2">
                    <input
                        id="create-tag-name"
                        ref="createNameInputRef"
                        v-model="createForm.name"
                        type="text"
                        placeholder="Nom du tag"
                        maxlength="255"
                        class="w-full max-w-xs rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-invalid="!!createNameError"
                        :disabled="createForm.processing"
                    >
                    <button
                        type="submit"
                        class="shrink-0 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="createForm.processing || !!createDuplicateName"
                    >
                        Créer
                    </button>
                </div>
                <p v-if="createNameError" class="text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ createNameError }}
                </p>
            </form>

            <div v-if="tags.length === 0" class="flex flex-col items-center gap-2 py-16 text-center">
                <p class="text-muted">
                    Aucun tag pour l'instant.
                </p>
            </div>

            <ul v-else class="border-t border-border">
                <li
                    v-for="tag in tags"
                    :key="tag.id"
                    class="flex items-center gap-3 border-b border-border px-2 py-3"
                >
                    <template v-if="renamingTagId === tag.id">
                        <form class="flex flex-1 items-center gap-2" @submit.prevent="submitRename(tag)">
                            <div class="flex-1">
                                <label :for="`rename-tag-name-${tag.id}`" class="sr-only">
                                    Nouveau nom du tag « {{ tag.name }} »
                                </label>
                                <input
                                    :id="`rename-tag-name-${tag.id}`"
                                    :ref="setRenameInputRef"
                                    v-model="renameForm.name"
                                    type="text"
                                    maxlength="255"
                                    class="w-full max-w-xs rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                                    :aria-invalid="!!renameNameError"
                                    :disabled="renameForm.processing"
                                    @keydown.escape="cancelRename"
                                >
                                <p v-if="renameNameError" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                                    {{ renameNameError }}
                                </p>
                            </div>
                            <button
                                type="submit"
                                class="shrink-0 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                                :disabled="renameForm.processing || !!renameDuplicateName"
                            >
                                Enregistrer
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                                :disabled="renameForm.processing"
                                @click="cancelRename"
                            >
                                Annuler
                            </button>
                        </form>
                    </template>
                    <template v-else>
                        <span class="min-w-0 flex-1 truncate font-medium text-foreground">
                            {{ tag.name }}
                        </span>
                        <span class="shrink-0 text-xs text-muted">
                            {{ tag.documents_count }} document{{ tag.documents_count === 1 ? '' : 's' }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            :aria-label="`Renommer le tag ${tag.name}`"
                            @click="(event) => startRename(tag, event)"
                        >
                            Renommer
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-red-600 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-950/30"
                            :aria-label="`Supprimer le tag ${tag.name}`"
                            @click="(event) => openDeleteDialog(tag, event)"
                        >
                            Supprimer
                        </button>
                    </template>
                </li>
            </ul>
        </div>

        <div
            v-if="isDeleteDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown="onDeleteDialogKeydown"
        >
            <div
                ref="deleteDialogRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-tag-dialog-title"
                aria-describedby="delete-tag-dialog-description"
                class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl"
            >
                <h2 id="delete-tag-dialog-title" class="text-lg font-semibold text-foreground">
                    Supprimer ce tag ?
                </h2>
                <p id="delete-tag-dialog-description" class="mt-2 text-sm text-muted">
                    « {{ tagToDelete?.name }} » sera détaché de {{ tagToDelete?.documents_count }} document{{ tagToDelete?.documents_count === 1 ? '' : 's' }}. Les documents eux-mêmes ne seront jamais modifiés ni supprimés. Cette action est irréversible.
                </p>

                <p v-if="deleteError" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ deleteError }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        ref="cancelDeleteButtonRef"
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="isDeleting"
                        @click="closeDeleteDialog"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-500 dark:hover:bg-red-400 dark:focus-visible:ring-background"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Suppression…' : 'Supprimer' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
