<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Table } from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import Image from '@tiptap/extension-image';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';

// Present only when reopening a previously created document to correct it
// (spec-2-3) — absent (null) on a brand-new draft, in which case every
// branch below falls back to the create-mode behaviour that already
// shipped in Story 2.1/2.2.
const props = defineProps({
    document: {
        type: Object,
        default: null,
    },
});

// Generated once, client-side, the moment the editor opens (Design Notes,
// spec-2-2) — keys every image this session uploads before it's relocated
// into the document's own directory, and travels along with the final save
// so Create/UpdateDocumentAction know which tmp/{token} directory to
// relocate. crypto.randomUUID() needs no server round-trip, so the very
// first image can upload immediately — needed just the same whether this
// session is drafting a new document or editing an existing one, since an
// image inserted mid-edit still has no permanent home until "Enregistrer".
const draftToken = crypto.randomUUID();

const form = useForm({
    title: props.document?.title ?? '',
    content_html: props.document?.content_html ?? '',
    tag_ids: (props.document?.tags ?? []).map((tag) => tag.id),
    draft_token: draftToken,
});

const titleInputRef = ref(null);
const tagSelectorRef = ref(null);

// Whether the tag selector has been revealed yet. On a brand-new document
// it starts hidden (UX-DR10, Design Notes spec-2-1) behind a two-step
// "Enregistrer" gesture; in edit mode there is no such gesture to reserve —
// the tags the document already has are shown immediately (Code Map,
// spec-2-3/spec-3-1). Once shown, stays shown for the rest of the session.
const showTagSelector = ref(!!props.document);

// Tracks the editor's current HTML outside of TipTap itself so it can be
// compared reactively against the snapshot below — TipTap's own state
// isn't reactive to Vue on its own.
const currentContentHtml = ref(form.content_html);

// Set once, right after the editor mounts (onCreate below), to the
// title/content/tags the form actually started from — comparing
// against a live loaded state rather than a mutation counter avoids a
// false "dirty" positive from e.g. a click into the editor that changes
// nothing (Design Notes, spec-2-3). Null until then, during which isDirty
// stays false: nothing typed yet is nothing to lose.
const initialSnapshot = ref(null);

function snapshotCurrentState() {
    return {
        title: form.title,
        contentHtml: currentContentHtml.value,
        tagIds: form.tag_ids,
    };
}

// Order-insensitive comparison — reselecting the same set of tags in a
// different order (remove then re-add, say) is not a real change.
function sameTagIds(a, b) {
    if (a.length !== b.length) {
        return false;
    }

    const sortedA = [...a].sort((x, y) => x - y);
    const sortedB = [...b].sort((x, y) => x - y);

    return sortedA.every((value, index) => value === sortedB[index]);
}

// Discreet "unsaved changes" indicator on the Save button (Boundaries &
// Constraints, spec-2-3) — compares the live title/content/tags against
// the snapshot taken when the editor became ready, not an edit counter, so
// an edit that's undone back to the original state doesn't stay flagged
// dirty forever.
const isDirty = computed(() => {
    if (!initialSnapshot.value) {
        return false;
    }

    const current = snapshotCurrentState();

    return current.title !== initialSnapshot.value.title
        || current.contentHtml !== initialSnapshot.value.contentHtml
        || !sameTagIds(current.tagIds, initialSnapshot.value.tagIds);
});

// Existing content must be loaded into TipTap before typing is allowed
// (Boundaries & Constraints, spec-2-3) — `editable` starts false only when
// reopening a document; a brand-new draft has nothing to wait for and stays
// editable from the very first render, unchanged from Story 2.1. Flipped
// back on, and the loading indicator cleared, in onCreate below — in
// practice `useEditor()` constructs the TipTap instance (and loads
// `content`) synchronously, so this resolves before the very first render;
// the flag is kept anyway rather than hard-coded to false, since a
// same-tick resolution is an implementation detail of TipTap's Vue
// wrapper, not a guarantee this code should assume holds forever.
const isLoadingContent = ref(!!props.document);

const editor = useEditor({
    content: form.content_html,
    editable: !props.document,
    extensions: [
        StarterKit,
        // `resizable: false` — column/row resizing isn't part of this
        // story's toolbar (FR8: titles, lists, tables only).
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        // `allowBase64: false` (the default) closes off a pasted/dropped
        // image ever landing in the document as a base64 `src` — every
        // image reaches the document only through uploadImage() below,
        // which always inserts the app's own served URL (AD-14, Boundaries
        // & Constraints spec-2-2).
        Image.configure({ allowBase64: false }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-content min-h-[320px] rounded-b-md border border-t-0 border-border bg-background px-4 py-3 text-sm text-foreground focus:outline-none',
            'aria-label': 'Contenu du document',
        },
    },
    onCreate: ({ editor: mountedEditor }) => {
        currentContentHtml.value = mountedEditor.getHTML();
        initialSnapshot.value = snapshotCurrentState();

        if (!mountedEditor.isEditable) {
            mountedEditor.setEditable(true);
        }

        isLoadingContent.value = false;
    },
    onUpdate: ({ editor: updatedEditor }) => {
        currentContentHtml.value = updatedEditor.getHTML();
    },
});

// Focus lands on the title, not the editor body — matches the Import
// modal/document flows: the very first thing to fill in on an empty
// document is what it's called.
onMounted(() => {
    titleInputRef.value?.focus();
    window.addEventListener('beforeunload', onBeforeUnload);
});

// --- Garde contre la perte de modifications non enregistrées (spec-2-3) ----
//
// Two exit paths exist: an Inertia navigation (a <Link>, a browser
// back/forward the client intercepts, or this very page's own save/upload
// requests — router.post()/form.patch() are visits too) and closing the
// tab/browser outright, which Inertia's router never sees. Both are guarded
// the same way — confirm if isDirty, otherwise let it through — but the
// save and image-upload requests below are this component's own doing, not
// the user trying to leave, so `programmaticNavigation` lets them bypass
// the confirmation entirely rather than asking the user to confirm leaving
// a page they never asked to leave.
let programmaticNavigation = false;

const unregisterNavigationGuard = router.on('before', (event) => {
    if (programmaticNavigation || !isDirty.value) {
        return;
    }

    if (!window.confirm('Des modifications non enregistrées seront perdues si vous quittez cette page. Voulez-vous continuer ?')) {
        event.preventDefault();
    }
});

function onBeforeUnload(event) {
    if (!isDirty.value) {
        return;
    }

    // Both are required for the confirmation prompt to appear across
    // browsers — the string itself is never actually shown (browsers use
    // their own generic wording), but a value must still be set.
    event.preventDefault();
    event.returnValue = '';
}

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    unregisterNavigationGuard();
});

function insertTable() {
    editor.value
        ?.chain()
        .focus()
        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
        .run();
}

// --- Insertion d'image (bouton + glisser-déposer, spec-2-2) -----------------
//
// Both entry points below (onImageInputChange/onEditorDrop) funnel into the
// very same openImageDialog()/uploadPendingImage() pair — the toolbar
// button and drag-and-drop share one code path end to end (Boundaries &
// Constraints, spec-2-2), including the same mandatory-alt dialog. Nothing
// here ever inserts a local blob/base64 preview into the TipTap document
// itself; the document only ever receives the URL that comes back from the
// server after a real upload.

const imageInputRef = ref(null);
const isImageDialogOpen = ref(false);
const pendingImageFile = ref(null);
const pendingImageAlt = ref('');
const imageDialogError = ref('');
const isUploadingImage = ref(false);
const isDraggingImage = ref(false);
const imageAltInputRef = ref(null);
const imageDialogRef = ref(null);
let imageDialogTriggerElement = null;
// Selection to restore before inserting — set from the drop coordinates for
// drag-and-drop so the image lands exactly where it was dropped; left null
// for the toolbar button, which inserts at the editor's current cursor.
let pendingInsertPos = null;

function openFilePicker() {
    imageInputRef.value?.click();
}

async function openImageDialog(file, insertPos = null) {
    pendingImageFile.value = file;
    pendingImageAlt.value = '';
    pendingInsertPos = insertPos;
    imageDialogError.value = '';
    imageDialogTriggerElement = document.activeElement;
    isImageDialogOpen.value = true;
    await nextTick();
    imageAltInputRef.value?.focus();
}

function closeImageDialog() {
    if (isUploadingImage.value) {
        // An upload is in flight: ignore the close request rather than
        // letting the user believe they cancelled while it still completes
        // underneath them.
        return;
    }

    isImageDialogOpen.value = false;
    pendingImageFile.value = null;
    pendingImageAlt.value = '';
    imageDialogError.value = '';
    pendingInsertPos = null;

    if (imageDialogTriggerElement instanceof HTMLElement) {
        imageDialogTriggerElement.focus();
    }
}

function onImageInputChange(event) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';

    if (file) {
        openImageDialog(file);
    }
}

function onEditorDrop(event) {
    isDraggingImage.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;

    if (!file) {
        return;
    }

    // No client-side file-type gate here — same as the file-picker path
    // (onImageInputChange), which has none either. A non-image drop still
    // opens the dialog and reaches the server, whose UploadEditorImageRequest
    // validation rejects it with a message surfaced via imageDialogError,
    // rather than the drop silently doing nothing.
    // Places the cursor at the exact drop location before the dialog even
    // opens, so the image lands "entre deux blocs de texte" at the drop
    // point rather than wherever the caret happened to be last.
    const coords = editor.value?.view.posAtCoords({ left: event.clientX, top: event.clientY });
    openImageDialog(file, coords?.pos ?? null);
}

function uploadPendingImage() {
    if (isUploadingImage.value) {
        return;
    }

    const alt = pendingImageAlt.value.trim();

    if (!alt) {
        imageDialogError.value = 'Merci de renseigner un texte alternatif.';
        return;
    }

    isUploadingImage.value = true;
    imageDialogError.value = '';

    // A background round-trip, never a real navigation away from the
    // editor (Design Notes, spec-2-2) — bypasses the unsaved-changes guard
    // above for the same reason (it's this component's own request, not
    // the user trying to leave). The upload endpoint itself is shared
    // as-is between create and edit (Boundaries & Constraints, spec-2-3) —
    // draftToken alone keys where the file lands and, later, which prefix
    // the sanitizer allows it under.
    programmaticNavigation = true;
    router.post('/documents/create/images', {
        draft_token: draftToken,
        image: pendingImageFile.value,
        alt,
    }, {
        forceFormData: true,
        // Both keep the in-progress draft (title, editor content, tag
        // choices) exactly as the user left it — the upload is a background
        // round-trip, never a real navigation away from the editor (Design
        // Notes, spec-2-2).
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isImageDialogOpen.value = false;
            pendingImageFile.value = null;
            pendingImageAlt.value = '';
        },
        onError: (errors) => {
            imageDialogError.value = errors.image ?? errors.alt ?? errors.draft_token
                ?? 'Impossible d\'insérer cette image.';
        },
        onFinish: () => {
            isUploadingImage.value = false;
        },
    });
    programmaticNavigation = false;
}

function onImageDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeImageDialog();
        return;
    }

    if (event.key === 'Tab') {
        trapImageDialogFocus(event);
    }
}

function trapImageDialogFocus(event) {
    const focusable = imageDialogRef.value?.querySelectorAll(
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

// Sole point where an uploaded image actually reaches the TipTap document —
// fired once the upload's Inertia redirect lands back on this same page and
// the shared flash.uploadedImage prop carries the freshly stored file's
// url/alt (AD-13: never response()->json(), read here via preserveState
// instead). Laravel's own flash bag ages this back out after this one
// request, so there's nothing to clear on this end.
const page = usePage();

watch(
    () => page.props.flash?.uploadedImage,
    (uploadedImage) => {
        if (!uploadedImage) {
            return;
        }

        // The flash channel is one global slot per session, not scoped to
        // this draft — two tabs drafting different new documents under the
        // same session cookie would otherwise be able to cross-insert each
        // other's uploads. draftToken ties the flashed payload back to
        // *this* draft; anything else is silently ignored (the file itself
        // stays safely on disk either way).
        if (uploadedImage.draftToken !== draftToken) {
            return;
        }

        const chain = editor.value?.chain().focus();

        if (!chain) {
            return;
        }

        if (pendingInsertPos !== null) {
            chain.setTextSelection(pendingInsertPos);
        }

        chain.setImage({ src: uploadedImage.url, alt: uploadedImage.alt }).run();
        pendingInsertPos = null;
    },
);

// Save is a two-step gesture the first time a document is created (UX-DR10,
// Design Notes spec-2-1): the tag selector is optional and only surfaces
// once the user signals intent to save, rather than being shown upfront on
// an empty editor — matching the I/O matrix, no request is sent on this
// first click. Every click after that submits, even if no tag was ever
// picked (tag_ids stays []): a tag is never blocking.
async function onSaveClick() {
    if (!showTagSelector.value) {
        showTagSelector.value = true;
        await nextTick();
        tagSelectorRef.value?.focus();
        return;
    }

    submit();
}

function submit() {
    form.content_html = editor.value?.getHTML() ?? currentContentHtml.value;

    const options = {
        onSuccess: () => {
            // The save succeeded and content_html/tag_ids now match what's
            // persisted — re-baseline so isDirty drops back to false rather
            // than staying stuck true from the comparison above (relevant
            // mainly if the redirect result is ever rendered as this same
            // component instance).
            initialSnapshot.value = snapshotCurrentState();
        },
        onError: () => {
            // Validation errors (e.g. an empty title) surface inline via
            // form.errors below — the drafted content and tag choices are
            // left untouched so the user can fix the title and retry.
        },
    };

    // This is the editor's own intentional save request, not the user
    // trying to leave — bypasses the unsaved-changes navigation guard
    // above rather than asking them to confirm leaving the very page they
    // asked to save.
    programmaticNavigation = true;

    if (props.document) {
        form.patch(`/documents/${props.document.id}`, options);
    } else {
        form.post('/documents/create', options);
    }

    programmaticNavigation = false;
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <Link href="/" class="text-sm text-muted hover:text-foreground hover:underline">
                &larr; Retour à la bibliothèque
            </Link>

            <div class="mt-4">
                <label for="document-title" class="mb-1 block text-sm font-medium text-foreground">
                    Titre
                </label>
                <input
                    id="document-title"
                    ref="titleInputRef"
                    v-model="form.title"
                    type="text"
                    placeholder="Titre du document"
                    class="w-full rounded-sm border border-border bg-background px-3 py-2 text-lg font-semibold text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                >
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.title }}
                </p>
            </div>

            <div class="mt-6">
                <div
                    role="toolbar"
                    aria-label="Mise en forme du document"
                    class="flex flex-wrap items-center gap-1 rounded-t-md border border-border bg-surface-alt p-2"
                >
                    <button
                        v-for="level in [1, 2, 3]"
                        :key="`heading-${level}`"
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :class="{ 'bg-surface': editor?.isActive('heading', { level }) }"
                        :aria-pressed="editor?.isActive('heading', { level }) ?? false"
                        :aria-label="`Titre niveau ${level}`"
                        @click="editor?.chain().focus().toggleHeading({ level }).run()"
                    >
                        H{{ level }}
                    </button>

                    <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

                    <button
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :class="{ 'bg-surface': editor?.isActive('bulletList') }"
                        :aria-pressed="editor?.isActive('bulletList') ?? false"
                        aria-label="Liste à puces"
                        @click="editor?.chain().focus().toggleBulletList().run()"
                    >
                        • Liste
                    </button>

                    <button
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :class="{ 'bg-surface': editor?.isActive('orderedList') }"
                        :aria-pressed="editor?.isActive('orderedList') ?? false"
                        aria-label="Liste numérotée"
                        @click="editor?.chain().focus().toggleOrderedList().run()"
                    >
                        1. Liste
                    </button>

                    <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

                    <button
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        aria-label="Insérer un tableau"
                        @click="insertTable"
                    >
                        Tableau
                    </button>

                    <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>

                    <button
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        aria-label="Insérer une image"
                        @click="openFilePicker"
                    >
                        Image
                    </button>
                    <input
                        ref="imageInputRef"
                        type="file"
                        class="sr-only"
                        accept="image/*"
                        aria-label="Sélectionner une image à insérer"
                        @change="onImageInputChange"
                    >
                </div>

                <p
                    v-if="isLoadingContent"
                    class="rounded-b-md border border-t-0 border-border bg-background px-4 py-3 text-sm text-muted"
                >
                    Chargement du contenu…
                </p>
                <div
                    v-else
                    class="relative"
                    :class="{ 'outline outline-2 outline-offset-[-2px] outline-primary': isDraggingImage }"
                    @dragover.prevent="isDraggingImage = true"
                    @dragleave.prevent="isDraggingImage = false"
                    @drop.prevent="onEditorDrop"
                >
                    <EditorContent :editor="editor" />
                </div>

                <p v-if="form.errors.content_html" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.content_html }}
                </p>
            </div>

            <div v-if="showTagSelector" class="mt-6 max-w-xs">
                <TagSelector ref="tagSelectorRef" v-model="form.tag_ids" :disabled="form.processing" />
                <p v-if="form.errors.tag_ids" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.tag_ids }}
                </p>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <span class="relative inline-flex">
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="form.processing"
                        @click="onSaveClick"
                    >
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <!-- Discreet "unsaved changes" pastille (Boundaries & Constraints,
                         spec-2-3) — decorative only, the adjacent text carries the
                         same information for assistive tech. -->
                    <span
                        v-if="isDirty"
                        class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-500 ring-2 ring-background"
                        aria-hidden="true"
                    ></span>
                </span>
                <span v-if="isDirty" class="text-sm text-muted">
                    Modifications non enregistrées
                </span>
            </div>
        </div>

        <div
            v-if="isImageDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown="onImageDialogKeydown"
        >
            <div
                ref="imageDialogRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="image-dialog-title"
                class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl"
            >
                <h2 id="image-dialog-title" class="text-lg font-semibold text-foreground">
                    Insérer une image
                </h2>
                <p class="mt-1 text-sm text-muted">
                    {{ pendingImageFile?.name }}
                </p>

                <div class="mt-4">
                    <label for="image-alt-input" class="mb-1 block text-sm font-medium text-foreground">
                        Texte alternatif
                    </label>
                    <input
                        id="image-alt-input"
                        ref="imageAltInputRef"
                        v-model="pendingImageAlt"
                        type="text"
                        placeholder="Décrivez cette image"
                        class="w-full rounded-sm border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="isUploadingImage"
                        @keydown.enter.prevent="uploadPendingImage"
                    >
                </div>

                <p v-if="imageDialogError" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ imageDialogError }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="isUploadingImage"
                        @click="closeImageDialog"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="isUploadingImage"
                        @click="uploadPendingImage"
                    >
                        {{ isUploadingImage ? 'Insertion…' : 'Insérer' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.tiptap-content) {
    outline: none;
}

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
