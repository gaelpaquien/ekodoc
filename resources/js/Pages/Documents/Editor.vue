<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Table } from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import { nextTick, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryPicker from '@/Components/CategoryPicker.vue';

const form = useForm({
    title: '',
    content_html: '',
    category_id: null,
});

const titleInputRef = ref(null);

// Whether the category selector has been revealed yet — starts hidden on a
// brand-new document (UX-DR10, Design Notes spec-2-1) and, once shown,
// stays shown for the rest of the session.
const showCategoryPicker = ref(false);

const editor = useEditor({
    content: '',
    extensions: [
        StarterKit,
        // `resizable: false` — column/row resizing isn't part of this
        // story's toolbar (FR8: titles, lists, tables only).
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    editorProps: {
        attributes: {
            class: 'tiptap-content min-h-[320px] rounded-b-md border border-t-0 border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-900 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100',
            'aria-label': 'Contenu du document',
        },
    },
});

// Focus lands on the title, not the editor body — matches the Import
// modal/document flows: the very first thing to fill in on an empty
// document is what it's called.
onMounted(() => {
    titleInputRef.value?.focus();
});

function insertTable() {
    editor.value
        ?.chain()
        .focus()
        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
        .run();
}

// Save is a two-step gesture the first time a document is created (UX-DR10,
// Design Notes spec-2-1): the category selector is optional and only
// surfaces once the user signals intent to save, rather than being shown
// upfront on an empty editor — matching the I/O matrix, no request is sent
// on this first click. Every click after that submits, even if the picker
// is left on "Non classé" (category_id stays null): a category is never
// blocking.
async function onSaveClick() {
    if (!showCategoryPicker.value) {
        showCategoryPicker.value = true;
        await nextTick();
        document.getElementById('category-picker-select')?.focus();
        return;
    }

    submit();
}

function submit() {
    form.content_html = editor.value?.getHTML() ?? '';

    form.post('/documents/create', {
        onError: () => {
            // Validation errors (e.g. an empty title) surface inline via
            // form.errors below — the drafted content and category choice
            // are left untouched so the user can fix the title and retry.
        },
    });
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <Link href="/" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                &larr; Retour à la bibliothèque
            </Link>

            <div class="mt-4">
                <label for="document-title" class="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Titre
                </label>
                <input
                    id="document-title"
                    ref="titleInputRef"
                    v-model="form.title"
                    type="text"
                    placeholder="Titre du document"
                    class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-lg font-semibold text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                >
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.title }}
                </p>
            </div>

            <div class="mt-6">
                <div
                    role="toolbar"
                    aria-label="Mise en forme du document"
                    class="flex flex-wrap items-center gap-1 rounded-t-md border border-neutral-300 bg-neutral-50 p-2 dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <button
                        v-for="level in [1, 2, 3]"
                        :key="`heading-${level}`"
                        type="button"
                        class="rounded px-2 py-1 text-sm font-medium text-neutral-700 hover:bg-neutral-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        :class="{ 'bg-neutral-200 dark:bg-neutral-700': editor?.isActive('heading', { level }) }"
                        :aria-pressed="editor?.isActive('heading', { level }) ?? false"
                        :aria-label="`Titre niveau ${level}`"
                        @click="editor?.chain().focus().toggleHeading({ level }).run()"
                    >
                        H{{ level }}
                    </button>

                    <span class="mx-1 h-5 w-px bg-neutral-300 dark:bg-neutral-600" aria-hidden="true"></span>

                    <button
                        type="button"
                        class="rounded px-2 py-1 text-sm font-medium text-neutral-700 hover:bg-neutral-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        :class="{ 'bg-neutral-200 dark:bg-neutral-700': editor?.isActive('bulletList') }"
                        :aria-pressed="editor?.isActive('bulletList') ?? false"
                        aria-label="Liste à puces"
                        @click="editor?.chain().focus().toggleBulletList().run()"
                    >
                        • Liste
                    </button>

                    <button
                        type="button"
                        class="rounded px-2 py-1 text-sm font-medium text-neutral-700 hover:bg-neutral-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        :class="{ 'bg-neutral-200 dark:bg-neutral-700': editor?.isActive('orderedList') }"
                        :aria-pressed="editor?.isActive('orderedList') ?? false"
                        aria-label="Liste numérotée"
                        @click="editor?.chain().focus().toggleOrderedList().run()"
                    >
                        1. Liste
                    </button>

                    <span class="mx-1 h-5 w-px bg-neutral-300 dark:bg-neutral-600" aria-hidden="true"></span>

                    <button
                        type="button"
                        class="rounded px-2 py-1 text-sm font-medium text-neutral-700 hover:bg-neutral-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        aria-label="Insérer un tableau"
                        @click="insertTable"
                    >
                        Tableau
                    </button>
                </div>

                <EditorContent :editor="editor" />

                <p v-if="form.errors.content_html" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.content_html }}
                </p>
            </div>

            <div v-if="showCategoryPicker" class="mt-6 max-w-xs">
                <CategoryPicker v-model="form.category_id" :disabled="form.processing" />
                <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.category_id }}
                </p>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                    @click="onSaveClick"
                >
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </button>
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
