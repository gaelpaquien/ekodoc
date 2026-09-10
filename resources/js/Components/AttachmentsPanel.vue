<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useFileDropZone } from '@/Composables/useFileDropZone';

// Retractable side panel mounted in Editor.vue (spec-3-3, FR13) — never on
// Show.vue, which only ever renders a plain read-only list (Boundaries &
// Constraints: "pas d'ajout/retrait depuis Show.vue"). Two modes, chosen by
// the host (Editor.vue) from whether the document already has an id:
//
// - `immediate`: the document is already saved. Every add/remove hits the
//   server right away (AttachDocumentFileAction/DetachDocumentFileAction)
//   through a standard Inertia visit — `preserveState`/`preserveScroll`
//   throughout so an in-progress, unsaved edit to the title/editor content
//   elsewhere on the same page is never disturbed (Boundaries & Constraints:
//   "ajout/retrait ne touche jamais content_html ni isDirty"). The
//   `attachments` prop itself is what refreshes after each request — this
//   component holds no server-authoritative local copy of it.
// - `draft`: the document doesn't exist yet. A file is uploaded to a
//   temporary, draft-token-keyed area (UploadDraftAttachmentAction) with no
//   row created yet — this component tracks the kept list itself via
//   `v-model:attachments` (`update:attachments`) so the host can include it
//   in the `draft_attachments` payload sent to "Enregistrer". Removing one
//   here is a pure local `splice` — no request at all (Boundaries &
//   Constraints: a removed draft file is deliberately left behind in
//   `tmp/{token}/attachments/`, same accepted gap as draft images).
//
// `v-model:uploading` (`update:uploading`) mirrors `isUploading` outward so
// the host can disable "Enregistrer" while a draft upload is still in
// flight (code review finding) — without it, a save mid-upload could
// silently exclude that file from `draft_attachments`.
const props = defineProps({
    attachments: {
        type: Array,
        default: () => [],
    },
    mode: {
        type: String,
        required: true,
        validator: (value) => ['immediate', 'draft'].includes(value),
    },
    // Required when mode === 'immediate' — the id every attach/detach/
    // preview/download URL is built from.
    documentId: {
        type: [Number, String],
        default: null,
    },
    // Required when mode === 'draft' — the same client-generated token
    // Editor.vue already uses for inline images, keying where a draft
    // attachment's file temporarily lands.
    draftToken: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:attachments', 'update:uploading']);

const ACCEPTED_LABEL = 'PDF, Word (.docx), Excel (.xlsx)';

const { isDragging, validationError, onDragover, onDragleave, fileFromDropEvent, fileFromInputEvent } = useFileDropZone({
    acceptedExtensions: ['pdf', 'docx', 'xlsx'],
    acceptedLabel: ACCEPTED_LABEL,
    maxFileSizeBytes: 20 * 1024 * 1024,
    maxFileSizeLabel: '20 Mo',
});

// Expanded by default — the empty state ("Aucune pièce jointe.") and the
// drop zone must be visible without an extra click (Verification, manual
// checks: "affiché sur Éditeur et Fiche document sans pièce jointe").
// "Rétractable" describes the toggle affording collapse, not a
// collapsed-by-default starting state.
const isOpen = ref(true);
const panelId = `attachments-panel-${Math.random().toString(36).slice(2)}`;

function toggleOpen() {
    isOpen.value = !isOpen.value;
}

const fileInputRef = ref(null);

function openFilePicker() {
    fileInputRef.value?.click();
}

const clientError = ref('');
const isUploading = ref(false);

// Surfaced to the host (Editor.vue, `v-model:uploading`) so "Enregistrer"
// can be disabled while a draft attachment upload is still in flight (code
// review finding) — without this, a save triggered mid-upload could exclude
// that file from `draft_attachments` with no error ever shown.
watch(isUploading, (value) => {
    emit('update:uploading', value);
});

// --- Mode immediate : useForm(), un attach/detach par requête réelle -------

const immediateForm = useForm({ file: null });

function attachImmediateFile(file) {
    immediateForm.file = file;
    immediateForm.post(`/documents/${props.documentId}/attachments`, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            isUploading.value = true;
        },
        onSuccess: () => {
            immediateForm.reset();
        },
        onError: () => {
            clientError.value = immediateForm.errors.file ?? 'Impossible de joindre ce fichier.';
        },
        onFinish: () => {
            isUploading.value = false;
        },
    });
}

const detachingAttachmentId = ref(null);

function detachImmediateAttachment(attachment) {
    if (detachingAttachmentId.value !== null) {
        return;
    }

    detachingAttachmentId.value = attachment.id;

    router.delete(`/documents/${props.documentId}/attachments/${attachment.id}`, {
        preserveState: true,
        preserveScroll: true,
        onError: () => {
            clientError.value = 'Impossible de retirer cette pièce jointe.';
        },
        onFinish: () => {
            detachingAttachmentId.value = null;
        },
    });
}

// --- Mode draft : upload vers la zone temporaire, liste tenue localement ---

const page = usePage();

function uploadDraftFile(file) {
    isUploading.value = true;

    router.post('/documents/create/attachments', {
        draft_token: props.draftToken,
        file,
    }, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            clientError.value = errors.file ?? errors.draft_token ?? 'Impossible de joindre ce fichier.';
        },
        onFinish: () => {
            isUploading.value = false;
        },
    });
}

// Sole point where a draft-uploaded attachment actually reaches the local
// list — fired once the upload's Inertia redirect lands back on this same
// page and the shared flash.uploadedAttachment prop carries the freshly
// stored file's filename/original_filename/mime_type (AD-13, mirrors
// Editor.vue's own flash.uploadedImage watcher).
watch(
    () => page.props.flash?.uploadedAttachment,
    (uploadedAttachment) => {
        if (props.mode !== 'draft' || !uploadedAttachment) {
            return;
        }

        // Same cross-draft/cross-tab guard as flash.uploadedImage: ignore
        // an upload meant for a different draft sharing the same session.
        if (uploadedAttachment.draftToken !== props.draftToken) {
            return;
        }

        emit('update:attachments', [...props.attachments, {
            filename: uploadedAttachment.filename,
            original_filename: uploadedAttachment.original_filename,
            mime_type: uploadedAttachment.mime_type,
        }]);
    },
);

function removeDraftAttachment(attachment) {
    emit('update:attachments', props.attachments.filter((candidate) => candidate.filename !== attachment.filename));
}

// --- Entrée commune (bouton + glisser-déposer) ------------------------------

function handleFile(file) {
    // Re-entrancy guard (code review finding, mirrors
    // detachImmediateAttachment()'s own detachingAttachmentId check) — a
    // second drop/pick while a previous upload is still in flight is
    // silently ignored rather than firing an overlapping request.
    if (isUploading.value) {
        return;
    }

    clientError.value = '';
    immediateForm.clearErrors('file');

    const error = validationError(file);

    if (error) {
        clientError.value = error;
        return;
    }

    if (props.mode === 'immediate') {
        attachImmediateFile(file);
    } else {
        uploadDraftFile(file);
    }
}

function onInputChange(event) {
    handleFile(fileFromInputEvent(event));
}

function onDrop(event) {
    handleFile(fileFromDropEvent(event));
}

function removeAttachment(attachment) {
    if (props.mode === 'immediate') {
        detachImmediateAttachment(attachment);
    } else {
        removeDraftAttachment(attachment);
    }
}

function isRemoving(attachment) {
    return props.mode === 'immediate' && detachingAttachmentId.value === attachment.id;
}

const previewUrl = (attachment) => `/documents/${props.documentId}/attachments/${attachment.id}/preview`;
const downloadUrl = (attachment) => `/documents/${props.documentId}/attachments/${attachment.id}/download`;

const attachmentCountLabel = computed(() => (props.attachments.length > 0 ? ` (${props.attachments.length})` : ''));
</script>

<template>
    <div class="rounded-md border border-border bg-surface-alt">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md px-4 py-3 text-left text-sm font-medium text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            @click="toggleOpen"
        >
            <span>Pièces jointes{{ attachmentCountLabel }}</span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="h-4 w-4 shrink-0 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            >
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <div v-if="isOpen" :id="panelId" class="border-t border-border px-4 py-4">
            <div
                class="flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-border bg-surface p-4 text-center"
                :class="{ 'border-primary bg-primary/10': isDragging }"
                @dragover.prevent="onDragover"
                @dragleave.prevent="onDragleave"
                @drop.prevent="onDrop"
            >
                <p class="text-xs text-muted">
                    Glissez-déposez un fichier ici, ou
                </p>
                <button
                    type="button"
                    class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                    :disabled="isUploading"
                    @click="openFilePicker"
                >
                    Parcourir
                </button>
                <input
                    ref="fileInputRef"
                    type="file"
                    class="sr-only"
                    accept=".pdf,.docx,.xlsx"
                    aria-label="Sélectionner une pièce jointe"
                    :disabled="isUploading"
                    @change="onInputChange"
                >
                <p class="text-xs text-muted">
                    Formats acceptés : {{ ACCEPTED_LABEL }}
                </p>
            </div>

            <p v-if="isUploading" class="mt-2 text-xs text-muted" role="status">
                Envoi en cours…
            </p>

            <p v-if="clientError" class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">
                {{ clientError }}
            </p>

            <p v-if="attachments.length === 0" class="mt-3 text-sm text-muted">
                Aucune pièce jointe.
            </p>

            <ul v-else class="mt-3 flex flex-col gap-2">
                <li
                    v-for="attachment in attachments"
                    :key="attachment.id ?? attachment.filename"
                    class="flex items-center justify-between gap-2 rounded-md bg-surface px-3 py-2 text-sm text-foreground"
                >
                    <span class="truncate" :title="attachment.original_filename">
                        {{ attachment.original_filename }}
                    </span>

                    <span class="flex shrink-0 items-center gap-2">
                        <template v-if="mode === 'immediate'">
                            <a
                                :href="previewUrl(attachment)"
                                target="_blank"
                                rel="noopener"
                                class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            >
                                Aperçu
                            </a>
                            <a
                                :href="downloadUrl(attachment)"
                                class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            >
                                Télécharger
                            </a>
                        </template>
                        <button
                            type="button"
                            class="rounded-sm px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:bg-red-950/30"
                            :disabled="isRemoving(attachment)"
                            :aria-label="`Retirer la pièce jointe ${attachment.original_filename}`"
                            @click="removeAttachment(attachment)"
                        >
                            Retirer
                        </button>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
