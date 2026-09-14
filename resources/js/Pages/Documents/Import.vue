<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';
import { useFileDropZone } from '@/Composables/useFileDropZone';

// Dedicated page (spec-import-document-page) replacing the former
// `ImportModal.vue` popup — same dropzone/TagSelector/useForm logic, minus
// every modal-only concern (role="dialog", focus trap, Escape, focus
// restoration to the trigger): an Inertia page already handles all of that
// natively, same reasoning as Editor.vue's own migration off a modal.
//
// One modal-era guarantee still needed an explicit replacement: the old
// overlay physically blocked clicks on the sidebar while open, so an
// in-flight upload could never be interrupted by navigating away. A plain
// page has no such shield — the sidebar stays fully clickable — so a
// `router.on('before', ...)` guard below blocks any Inertia navigation
// while `form.processing` is true, the same protection `ImportModal.vue`'s
// `close()` gave against losing an upload underway.

const ACCEPTED_LABEL = 'PDF, Word (.docx), Excel (.xlsx)';

const form = useForm({
    file: null,
    tag_ids: [],
});

const clientError = ref('');
const fileInputRef = ref(null);

const { isDragging, validationError, onDragover, onDragleave, fileFromDropEvent, fileFromInputEvent } = useFileDropZone({
    acceptedExtensions: ['pdf', 'docx', 'xlsx'],
    acceptedLabel: ACCEPTED_LABEL,
    maxFileSizeBytes: 20 * 1024 * 1024,
    maxFileSizeLabel: '20 Mo',
});

const errorMessage = computed(() => clientError.value || form.errors.file || form.errors.tag_ids || '');

function handleFile(file) {
    clientError.value = '';
    form.clearErrors('file');

    const error = validationError(file);

    if (error) {
        clientError.value = error;
        form.reset('file');
        return;
    }

    form.file = file;
    form.post('/documents', {
        forceFormData: true,
        onError: () => {
            // Server-side validation failed (e.g. size). Page stays as-is,
            // the error message renders from form.errors.file.
        },
    });
}

function onInputChange(event) {
    handleFile(fileFromInputEvent(event));
}

function onDrop(event) {
    handleFile(fileFromDropEvent(event));
}

function openFilePicker() {
    fileInputRef.value?.click();
}

const removeNavigationGuard = router.on('before', () => {
    if (form.processing) {
        return window.confirm(
            "Un import est en cours. Si vous quittez cette page maintenant, l'import sera annulé. Voulez-vous vraiment quitter ?",
        );
    }
});

onUnmounted(removeNavigationGuard);
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-lg px-4 py-10">
            <h1 class="mb-4 text-lg font-semibold text-foreground">
                Importer un document
            </h1>

            <div
                class="flex flex-col items-center justify-center gap-3 rounded-md border-2 border-dashed border-border bg-surface p-8 text-center"
                :class="{ 'border-primary bg-primary/10': isDragging }"
                @dragover.prevent="onDragover"
                @dragleave.prevent="onDragleave"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-muted">
                    Glissez-déposez un fichier ici, ou
                </p>
                <button
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    :disabled="form.processing"
                    @click="openFilePicker"
                >
                    Parcourir
                </button>
                <input
                    ref="fileInputRef"
                    type="file"
                    class="sr-only"
                    accept=".pdf,.docx,.xlsx"
                    aria-label="Sélectionner un fichier à importer"
                    @change="onInputChange"
                />
                <p class="text-xs text-muted">
                    Formats acceptés : {{ ACCEPTED_LABEL }}
                </p>
            </div>

            <div class="mt-4">
                <TagSelector v-model="form.tag_ids" :disabled="form.processing" />
            </div>

            <p v-if="form.processing" class="mt-3 text-sm text-muted" role="status">
                Import en cours…
            </p>

            <p v-if="errorMessage" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                {{ errorMessage }}
            </p>
        </div>
    </AppLayout>
</template>
