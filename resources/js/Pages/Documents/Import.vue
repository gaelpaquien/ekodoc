<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';
import { useFileDropZone } from '@/Composables/useFileDropZone';

// Dedicated page (spec-import-document-page) replacing the former
// `ImportModal.vue` popup — same dropzone/TagSelector/useForm logic, minus
// every modal-only concern (role="dialog", focus trap, Escape, focus
// restoration to the trigger): an Inertia page already handles all of that
// natively, same reasoning as Editor.vue's own migration off a modal.
//
// Since spec-corrections-documents-ui, importing a document is a 2-step
// flow (Intent): step 1 below is the dropzone alone — the upload creates
// the Document row exactly as before (ImportDocumentAction, unchanged),
// but DocumentController::store() now redirects back to this same page
// instead of straight to the document's detail page, flashing the freshly
// created document via `flash.uploadedDocument` (mirrors Editor.vue's own
// `uploadedImage` channel, AD-13/`back()->with()`). The watcher below picks
// that up and flips the page into step 2 (the review screen): "Supprimer"
// hard-deletes the document and returns to step 1 to start over, or a
// TagSelector + "Enregistrer" assigns tags (PATCH .../tags?redirect=show)
// and redirects to the document's own page.
//
// One modal-era guarantee still needed an explicit replacement: the old
// overlay physically blocked clicks on the sidebar while open, so an
// in-flight upload could never be interrupted by navigating away. A plain
// page has no such shield — the sidebar stays fully clickable — so a
// `router.on('before', ...)` guard below blocks any Inertia navigation
// while `form.processing` is true (the upload itself), and again while
// `uploadedDocument` is set and not yet saved/deleted (the review step) —
// the same protection `ImportModal.vue`'s `close()` gave against losing an
// upload underway, extended to cover losing an un-tagged import too.

const ACCEPTED_LABEL = 'PDF, Word (.docx), Excel (.xlsx)';

const page = usePage();

// Step 2 state (I/O matrix, spec-corrections-documents-ui) — populated once
// the upload's Inertia redirect lands back on this same page with
// `flash.uploadedDocument` set. Reset to null after a successful
// "Supprimer" so the page falls back to the step-1 dropzone; Laravel's
// flash bag ages the prop itself back out after the one request that
// follows the redirect, same as Editor.vue's `uploadedImage`, so there's
// nothing else to clean up here.
const uploadedDocument = ref(null);

watch(
    () => page.props.flash?.uploadedDocument,
    (value) => {
        if (value) {
            uploadedDocument.value = value;
        }
    },
);

const form = useForm({
    file: null,
});

const clientError = ref('');
const fileInputRef = ref(null);

const { isDragging, validationError, onDragover, onDragleave, fileFromDropEvent, fileFromInputEvent } = useFileDropZone({
    acceptedExtensions: ['pdf', 'docx', 'xlsx'],
    acceptedLabel: ACCEPTED_LABEL,
    maxFileSizeBytes: 20 * 1024 * 1024,
    maxFileSizeLabel: '20 Mo',
});

const uploadErrorMessage = computed(() => clientError.value || form.errors.file || '');

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
    // preserveState keeps this component instance mounted across the
    // upload's redirect back to this same page (Code Map,
    // spec-corrections-documents-ui) — without it, the follow-up visit
    // would tear this instance down and rebuild it, and the flash watcher
    // above would never see the change land.
    form.post('/documents', {
        forceFormData: true,
        preserveState: true,
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

// --- Étape 2 : revue (Supprimer / tags + Enregistrer) -----------------------

const tagsForm = useForm({ tag_ids: [] });
const isDeleting = ref(false);
const deleteError = ref('');
const saveError = ref('');

// Own requests below (delete-to-restart, save-tags) are this component's
// own doing, not the user trying to leave — bypasses the navigation guard
// below entirely rather than asking the user to confirm leaving a page
// they never asked to leave (mirrors Editor.vue's `programmaticNavigation`).
let programmaticNavigation = false;

function deleteUploadedDocument() {
    if (!uploadedDocument.value || isDeleting.value) {
        return;
    }

    if (!window.confirm(
        "Ce document sera supprimé définitivement et vous repartirez de zéro. Voulez-vous continuer ?",
    )) {
        return;
    }

    isDeleting.value = true;
    deleteError.value = '';
    programmaticNavigation = true;

    router.delete(`/documents/${uploadedDocument.value.id}?redirect=import`, {
        preserveState: true,
        onSuccess: () => {
            // The server has nothing left to flash (the document is gone) —
            // reset every piece of local state back to the step-1 dropzone.
            uploadedDocument.value = null;
            tagsForm.reset('tag_ids');
            tagsForm.clearErrors();
            form.reset('file');
            clientError.value = '';
        },
        onError: () => {
            deleteError.value = 'Impossible de supprimer le document.';
        },
        onFinish: () => {
            isDeleting.value = false;
            // Reset only once the request actually settles — while it's
            // in flight, a genuine navigation attempt (e.g. a sidebar
            // click mid-delete) must still fall through to the guard
            // below rather than silently bypass it.
            programmaticNavigation = false;
        },
    });
}

function saveTags() {
    if (!uploadedDocument.value) {
        return;
    }

    programmaticNavigation = true;
    saveError.value = '';

    // redirect=show (Design Notes, spec-corrections-documents-ui) tells
    // DocumentController::updateTags() this call is finalizing the import
    // review, not the Document Detail page's own reassignment — it
    // redirects to the document's page instead of back() here.
    tagsForm.patch(`/documents/${uploadedDocument.value.id}/tags?redirect=show`, {
        onError: () => {
            // Tag validation errors surface inline via tagsForm.errors.tag_ids
            // below — stays on step 2 (I/O matrix, spec-corrections-documents-ui).
            // Anything else (network failure, 500, a 404 if the document was
            // concurrently deleted, …) has no per-field error to render, so
            // it needs its own generic message.
            if (!tagsForm.errors.tag_ids) {
                saveError.value = "Impossible d'enregistrer les tags.";
            }
        },
        onFinish: () => {
            // Reset only once the request actually settles — see the same
            // reasoning in deleteUploadedDocument() above.
            programmaticNavigation = false;
        },
    });
}

const removeNavigationGuard = router.on('before', () => {
    if (programmaticNavigation) {
        return;
    }

    if (form.processing) {
        return window.confirm(
            "Un import est en cours. Si vous quittez cette page maintenant, l'import sera annulé. Voulez-vous vraiment quitter ?",
        );
    }

    if (uploadedDocument.value) {
        return window.confirm(
            "Le document importé restera sans tags si vous quittez maintenant. Voulez-vous continuer ?",
        );
    }
});

// router.on('before') only ever fires for Inertia-mediated navigation
// (sidebar links, etc.) — it has no reach over an actual browser refresh or
// tab close. The document itself is already a real persisted row (no draft
// state, Design Notes, spec-corrections-documents-ui), so leaving here
// isn't data loss, just an untagged document until someone edits it later
// from its own page — but that path deserves the same warning the in-app
// one gives, so it isn't silently different. Same condition as the guard
// above, minus the form.processing branch (Inertia's own `beforeunload`
// coverage, if any, doesn't apply — this listener uses its own explicit
// condition to stay in sync with the in-app guard's second branch).
function handleBeforeUnload(event) {
    if (uploadedDocument.value && !isDeleting.value && !tagsForm.processing) {
        event.preventDefault();
        // Modern browsers show their own generic prompt text regardless of
        // this value — set only for older-browser compatibility.
        event.returnValue = '';
    }
}

window.addEventListener('beforeunload', handleBeforeUnload);

onUnmounted(() => {
    removeNavigationGuard();
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-lg px-4 py-10">
            <h1 class="mb-4 text-lg font-semibold text-foreground">
                Importer un document
            </h1>

            <template v-if="!uploadedDocument">
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

                <p v-if="form.processing" class="mt-3 text-sm text-muted" role="status">
                    Import en cours…
                </p>

                <p v-if="uploadErrorMessage" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ uploadErrorMessage }}
                </p>
            </template>

            <template v-else>
                <div class="flex items-center gap-3 rounded-md border border-border bg-surface p-4">
                    <DocumentTypeBadge class="shrink-0" :mime-type="uploadedDocument.mime_type" />
                    <span class="min-w-0 flex-1 truncate font-medium text-foreground">
                        {{ uploadedDocument.title }}
                    </span>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-red-600 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-950/30"
                        :disabled="isDeleting"
                        @click="deleteUploadedDocument"
                    >
                        {{ isDeleting ? 'Suppression…' : 'Supprimer' }}
                    </button>
                </div>

                <p v-if="deleteError" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ deleteError }}
                </p>

                <div class="mt-4">
                    <TagSelector v-model="tagsForm.tag_ids" :disabled="tagsForm.processing || isDeleting" />
                    <p v-if="tagsForm.errors.tag_ids" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                        {{ tagsForm.errors.tag_ids }}
                    </p>
                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="tagsForm.processing"
                        @click="saveTags"
                    >
                        {{ tagsForm.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <p v-if="saveError" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                        {{ saveError }}
                    </p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
