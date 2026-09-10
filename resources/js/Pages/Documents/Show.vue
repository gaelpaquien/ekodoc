<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    sourceMissing: {
        type: Boolean,
        default: false,
    },
});

// Unlike the Import modal (where the document doesn't exist yet),
// TagSelector's change here writes immediately: the Document Detail page
// is the "reassign tags" surface from the I/O matrix (spec-3-1), so every
// emitted update:modelValue is persisted through PATCH
// /documents/{id}/tags — the only place that route is called from.
const tagIds = ref((props.document.tags ?? []).map((tag) => tag.id));
const isSavingTags = ref(false);
const tagsError = ref('');

// Inertia can reuse this component instance across a <Link> navigation
// from one document to another — resync the local selection whenever the
// underlying document prop changes rather than keeping the previous
// document's tags selected.
watch(() => props.document.id, () => {
    tagIds.value = (props.document.tags ?? []).map((tag) => tag.id);
    tagsError.value = '';
});

function onTagsChange(value) {
    tagIds.value = value;
    isSavingTags.value = true;
    tagsError.value = '';

    router.patch(`/documents/${props.document.id}/tags`, { tag_ids: value }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            // The optimistic selection above was never actually persisted
            // — revert to props.document.tags (the last state actually
            // confirmed by the server) rather than a locally-captured
            // "previous" value, so two edits fired in quick succession
            // can never have an earlier request's error revert stomp a
            // later request's already-applied selection.
            tagIds.value = (props.document.tags ?? []).map((tag) => tag.id);
            tagsError.value = errors.tag_ids ?? 'Impossible de mettre à jour les tags.';
        },
        onFinish: () => {
            isSavingTags.value = false;
        },
    });
}

const formattedDate = computed(() => {
    if (!props.document.created_at) {
        return '';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(props.document.created_at));
});

const previewUrl = computed(() => `/documents/${props.document.id}/preview`);
const downloadUrl = computed(() => `/documents/${props.document.id}/download`);
const exportPdfUrl = computed(() => `/documents/${props.document.id}/export/pdf`);
const exportWordUrl = computed(() => `/documents/${props.document.id}/export/word`);

// A created document (spec-2-1) has no original file on disk — `file_path`
// is deliberately null (AD-9) — so it is never subject to the
// file-missing/download flow below; its content lives in `content_html`
// and renders directly instead of through the file preview/iframe path.
const isCreated = computed(() => props.document.source === 'created');

// FR11/spec-2-4: a fetch() rather than a plain <a href> so success/failure
// can be told apart from the HTTP status (UX-DR20) — a bare <a> would hide
// a 422 behind Laravel's default error page instead of surfacing it here.
// The button itself always stays visible/enabled outside of an in-flight
// request (UX-DR11) so a failed export can always be retried immediately.
const isExportingPdf = ref(false);
const exportPdfError = ref('');
const showExportPdfToast = ref(false);
let exportPdfToastTimer = null;

// Same-origin request — the Content-Disposition header set by
// DocumentController::exportPdf() is readable from fetch() without any
// CORS exposure list, so the already-slugified filename it carries (with
// its own `document-{id}` fallback for an empty title, Str::slug()) is
// parsed from there rather than re-derived from props.document.title on
// the client, which could diverge (different empty-title fallback, no
// character sanitization).
function filenameFromContentDisposition(header) {
    if (!header) {
        return null;
    }

    const match = /filename="?([^";]+)"?/i.exec(header);

    return match ? match[1] : null;
}

async function exportToPdf() {
    if (isExportingPdf.value) {
        return;
    }

    isExportingPdf.value = true;
    exportPdfError.value = '';

    try {
        const response = await fetch(exportPdfUrl.value, {
            headers: { Accept: 'application/pdf' },
        });

        if (!response.ok) {
            exportPdfError.value = 'Export PDF impossible pour l\'instant, merci de réessayer.';
            return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const filename = filenameFromContentDisposition(response.headers.get('content-disposition'))
            ?? `${props.document.title || 'document'}.pdf`;

        // Success is delivered as a blob (not a navigation), so the
        // download is triggered manually via a temporary anchor rather
        // than letting the browser handle Content-Disposition itself
        // (Design Notes, spec-2-4).
        const link = window.document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        window.document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);

        triggerExportPdfToast();
    } catch (error) {
        exportPdfError.value = 'Export PDF impossible pour l\'instant, merci de réessayer.';
    } finally {
        isExportingPdf.value = false;
    }
}

function triggerExportPdfToast() {
    showExportPdfToast.value = true;

    if (exportPdfToastTimer) {
        clearTimeout(exportPdfToastTimer);
    }

    exportPdfToastTimer = setTimeout(() => {
        showExportPdfToast.value = false;
        exportPdfToastTimer = null;
    }, 3000);
}

// FR12/spec-2-5: mirrors exportToPdf() exactly, same fetch/blob-download/
// toast shape, just against the Word export route and its own
// isExportingWord/exportWordError/showExportWordToast refs (Code Map,
// spec-2-5) — kept as a separate function/state trio rather than
// parameterizing exportToPdf() itself, so a PDF export in flight never
// disables/conflicts with a concurrent Word export or vice versa.
const isExportingWord = ref(false);
const exportWordError = ref('');
const showExportWordToast = ref(false);
let exportWordToastTimer = null;

async function exportToWord() {
    if (isExportingWord.value) {
        return;
    }

    isExportingWord.value = true;
    exportWordError.value = '';

    try {
        const response = await fetch(exportWordUrl.value, {
            headers: { Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
        });

        if (!response.ok) {
            exportWordError.value = 'Export Word impossible pour l\'instant, merci de réessayer.';
            return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const filename = filenameFromContentDisposition(response.headers.get('content-disposition'))
            ?? `${props.document.title || 'document'}.docx`;

        const link = window.document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        window.document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);

        triggerExportWordToast();
    } catch (error) {
        exportWordError.value = 'Export Word impossible pour l\'instant, merci de réessayer.';
    } finally {
        isExportingWord.value = false;
    }
}

function triggerExportWordToast() {
    showExportWordToast.value = true;

    if (exportWordToastTimer) {
        clearTimeout(exportWordToastTimer);
    }

    exportWordToastTimer = setTimeout(() => {
        showExportWordToast.value = false;
        exportWordToastTimer = null;
    }, 3000);
}

// Deletion always requires explicit confirmation (UX-DR21, AD-15) — no
// undo/SoftDeletes, so the dialog is the only guard against an accidental
// destructive request. Accessibility mirrors ImportModal.vue: role="dialog",
// a focus trap, Escape to cancel, and focus restored to the trigger on close.
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const deleteError = ref('');
const deleteDialogRef = ref(null);
const cancelDeleteButtonRef = ref(null);
let deleteTriggerElement = null;

async function openDeleteDialog(event) {
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
    if (isDeleting.value) {
        // A rapid double-click can fire before Vue re-renders the
        // `:disabled` attribute onto the Confirm button — guard here too
        // so a second click never sends a second DELETE request.
        return;
    }

    isDeleting.value = true;
    deleteError.value = '';

    router.delete(`/documents/${props.document.id}`, {
        onError: () => {
            deleteError.value = 'Impossible de supprimer le document.';
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

const isPdf = computed(() => props.document.mime_type === 'application/pdf');
const isOfficeDocument = computed(() => [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
].includes(props.document.mime_type));

// Word/Excel go through fetch() (a binary request, not an Inertia visit) so
// "converting" and "conversion failed" can be told apart, which a bare
// <iframe src> can't do: success (2xx, application/pdf) builds a blob URL
// for the iframe; failure (non-2xx) shows the message outside the iframe.
const officePreviewState = ref('loading'); // 'loading' | 'ready' | 'error'
const officePreviewBlobUrl = ref(null);

// Inertia can reuse this component instance across a <Link> navigation from
// one document to another (same page component, new props, no remount) —
// a bare onMounted() would then keep showing the previous document's
// preview. requestSequence guards both that case and a slower one: it's
// bumped every time a load starts (by loadOfficePreview itself, or by the
// watcher below on navigation/unmount), so a fetch()/blob() continuation
// that resolves after it's been superseded — by a newer load, or by unmount
// — is detected and never touches officePreviewState or creates a
// never-released blob URL.
let requestSequence = 0;

function revokeOfficePreviewBlobUrl() {
    if (officePreviewBlobUrl.value) {
        URL.revokeObjectURL(officePreviewBlobUrl.value);
        officePreviewBlobUrl.value = null;
    }
}

async function loadOfficePreview() {
    const requestId = ++requestSequence;

    revokeOfficePreviewBlobUrl();
    officePreviewState.value = 'loading';

    try {
        const response = await fetch(previewUrl.value, {
            headers: { Accept: 'application/pdf' },
        });

        if (requestId !== requestSequence) {
            return;
        }

        if (!response.ok || !(response.headers.get('content-type') || '').includes('application/pdf')) {
            officePreviewState.value = 'error';
            return;
        }

        const blob = await response.blob();

        if (requestId !== requestSequence) {
            return;
        }

        officePreviewBlobUrl.value = URL.createObjectURL(blob);
        officePreviewState.value = 'ready';
    } catch (error) {
        if (requestId === requestSequence) {
            officePreviewState.value = 'error';
        }
    }
}

function refreshPreview() {
    if (!props.sourceMissing && isOfficeDocument.value) {
        loadOfficePreview();
    } else {
        // Not (or no longer) an Office document under preview — invalidate
        // any load still in flight for the previous document and drop its
        // blob URL rather than leaving it cached but unreferenced.
        requestSequence += 1;
        revokeOfficePreviewBlobUrl();
        officePreviewState.value = 'loading';
    }
}

onMounted(refreshPreview);

// Re-run whenever the component is reused for a different document (Inertia
// navigating Show -> Show without unmounting).
watch(() => props.document.id, refreshPreview);

onBeforeUnmount(() => {
    requestSequence += 1;
    revokeOfficePreviewBlobUrl();

    if (exportPdfToastTimer) {
        clearTimeout(exportPdfToastTimer);
    }

    if (exportWordToastTimer) {
        clearTimeout(exportWordToastTimer);
    }
});
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <Link href="/" class="text-sm text-muted hover:text-foreground hover:underline">
                &larr; Retour à la bibliothèque
            </Link>

            <h1 class="mt-4 text-2xl font-semibold text-foreground">
                {{ document.title }}
            </h1>

            <dl class="mt-6 space-y-2 text-sm text-foreground">
                <div class="flex items-center gap-2">
                    <dt class="font-medium">Type :</dt>
                    <dd>
                        <DocumentTypeBadge :mime-type="document.mime_type" :source="document.source" />
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium">Ajouté le :</dt>
                    <dd>{{ formattedDate }}</dd>
                </div>
                <div class="flex items-start gap-2">
                    <dt class="mt-2 font-medium">Tags :</dt>
                    <dd class="w-full max-w-xs">
                        <TagSelector :model-value="tagIds" :disabled="isSavingTags" @update:model-value="onTagsChange" />
                        <p v-if="tagsError" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                            {{ tagsError }}
                        </p>
                    </dd>
                </div>
            </dl>

            <div class="mt-6 flex gap-3">
                <a
                    v-if="!isCreated && !sourceMissing"
                    :href="downloadUrl"
                    class="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                >
                    Télécharger
                </a>
                <button
                    v-else-if="!isCreated"
                    type="button"
                    disabled
                    class="inline-flex cursor-not-allowed rounded-md bg-surface-alt px-4 py-2 text-sm font-medium text-muted"
                >
                    Télécharger
                </button>

                <!-- Only a document authored in the editor has content_html
                     to reopen and correct (Boundaries & Constraints,
                     spec-2-3) — an imported document is never routed
                     through this link. -->
                <Link
                    v-if="isCreated"
                    :href="`/documents/${document.id}/edit`"
                    class="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                >
                    Modifier
                </Link>

                <!-- FR11/spec-2-4: only a created document has content_html
                     to export — an imported document already has a native
                     PDF or goes through the Office preview above instead.
                     Stays visible/style primaire even while exporting or
                     after a failed attempt (UX-DR11): only :disabled
                     changes, so retrying never requires a page reload. -->
                <button
                    v-if="isCreated"
                    type="button"
                    class="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                    :disabled="isExportingPdf"
                    @click="exportToPdf"
                >
                    {{ isExportingPdf ? 'Export en cours…' : 'Exporter en PDF' }}
                </button>

                <!-- FR12/spec-2-5: same v-if as the PDF export button above
                     (only a created document has content_html to export) —
                     style secondaire (bordered, not filled) to sit next to
                     it (UX-DR11), same disabled-only-while-exporting shape
                     so retrying never requires a page reload. -->
                <button
                    v-if="isCreated"
                    type="button"
                    class="inline-flex rounded-md border border-border bg-surface-alt px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                    :disabled="isExportingWord"
                    @click="exportToWord"
                >
                    {{ isExportingWord ? 'Export en cours…' : 'Exporter en Word' }}
                </button>

                <button
                    type="button"
                    class="inline-flex rounded-md border border-red-600 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-950/30"
                    @click="openDeleteDialog"
                >
                    Supprimer
                </button>
            </div>

            <p v-if="exportPdfError" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
                {{ exportPdfError }}
            </p>
            <p v-if="exportWordError" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
                {{ exportWordError }}
            </p>

            <div class="mt-8">
                <!-- eslint-disable-next-line vue/no-v-html -- content authored by the same local user in the app's own WYSIWYG editor (spec-2-1); no auth boundary exists in v1 (NFR3). -->
                <div
                    v-if="isCreated"
                    class="tiptap-content min-h-[200px] rounded-md border border-border bg-background px-4 py-3 text-sm text-foreground"
                    v-html="document.content_html"
                ></div>

                <div
                    v-else-if="sourceMissing"
                    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    Fichier source introuvable ou illisible. La prévisualisation n'est pas disponible.
                </div>

                <iframe
                    v-else-if="isPdf"
                    :src="previewUrl"
                    class="h-[75vh] w-full rounded-md border border-border"
                    title="Aperçu du document"
                ></iframe>

                <template v-else-if="isOfficeDocument">
                    <div
                        v-if="officePreviewState === 'loading'"
                        class="flex items-center gap-2 rounded-md border border-border p-4 text-sm text-muted"
                    >
                        Conversion de l'aperçu en cours…
                    </div>
                    <div
                        v-else-if="officePreviewState === 'error'"
                        class="rounded-md border border-border p-4 text-sm text-muted"
                    >
                        Aperçu indisponible pour ce fichier.
                    </div>
                    <iframe
                        v-else
                        :src="officePreviewBlobUrl"
                        class="h-[75vh] w-full rounded-md border border-border"
                        title="Aperçu du document"
                    ></iframe>
                </template>

                <div
                    v-else
                    class="rounded-md border border-border p-4 text-sm text-muted"
                >
                    Aperçu indisponible pour ce type de document.
                </div>
            </div>
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
                aria-labelledby="delete-dialog-title"
                aria-describedby="delete-dialog-description"
                class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl"
            >
                <h2 id="delete-dialog-title" class="text-lg font-semibold text-foreground">
                    Supprimer ce document ?
                </h2>
                <p id="delete-dialog-description" class="mt-2 text-sm text-muted">
                    « {{ document.title }} » sera supprimé définitivement, avec son fichier et son aperçu. Cette action est irréversible.
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

        <!-- Minimal, purpose-built toasts (spec-2-4 Design Notes: no
             existing toast component in the project) — each auto-dismisses
             via its own trigger*Toast()'s timer, never blocks interaction.
             Both share this one fixed flex-column container (rather than
             each rendering its own `fixed inset-x-0 bottom-4`) so a PDF
             export and a Word export triggered within the same 3s window
             stack one above the other instead of overlapping. -->
        <div class="fixed inset-x-0 bottom-4 z-40 flex flex-col items-center gap-2 px-4">
            <div
                v-if="showExportPdfToast"
                role="status"
                aria-live="polite"
                class="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background shadow-lg"
            >
                Export PDF généré.
            </div>

            <div
                v-if="showExportWordToast"
                role="status"
                aria-live="polite"
                class="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background shadow-lg"
            >
                Export Word généré.
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Mirrors Editor.vue's tiptap-content rules so a created document's
   headings/lists/tables render the same way they looked while drafting. */
:deep(.tiptap-content h1) {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content p) {
    margin: 0.5rem 0;
}

:deep(.tiptap-content img) {
    max-width: 100%;
    height: auto;
    margin: 0.75rem 0;
    border-radius: 0.25rem;
}

:deep(.tiptap-content ul) {
    list-style: disc;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

:deep(.tiptap-content ol) {
    list-style: decimal;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

:deep(.tiptap-content table) {
    border-collapse: collapse;
    width: 100%;
    margin: 0.75rem 0;
}

:deep(.tiptap-content table td),
:deep(.tiptap-content table th) {
    border: 1px solid #d4d4d4;
    padding: 0.375rem 0.5rem;
}

:deep(.tiptap-content table th) {
    background-color: #f5f5f5;
    font-weight: 600;
    text-align: left;
}

:global(.dark) :deep(.tiptap-content table td),
:global(.dark) :deep(.tiptap-content table th) {
    border-color: #404040;
}

:global(.dark) :deep(.tiptap-content table th) {
    background-color: #262626;
}
</style>
