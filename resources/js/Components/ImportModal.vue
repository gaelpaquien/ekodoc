<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const ACCEPTED_EXTENSIONS = ['pdf', 'docx', 'xlsx'];
const ACCEPTED_LABEL = 'PDF, Word (.docx), Excel (.xlsx)';
const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;

const form = useForm({
    file: null,
});

const clientError = ref('');
const isDragging = ref(false);
const dialogRef = ref(null);
const fileInputRef = ref(null);
const closeButtonRef = ref(null);
let triggerElement = null;

const errorMessage = computed(() => clientError.value || form.errors.file || '');

function extensionOf(filename) {
    return (filename.split('.').pop() || '').toLowerCase();
}

function isAcceptedFile(file) {
    return !!file && ACCEPTED_EXTENSIONS.includes(extensionOf(file.name));
}

function isWithinSizeLimit(file) {
    return file.size <= MAX_FILE_SIZE_BYTES;
}

function handleFile(file) {
    clientError.value = '';
    form.clearErrors('file');

    if (!isAcceptedFile(file)) {
        clientError.value = `Format non supporté. Formats acceptés : ${ACCEPTED_LABEL}.`;
        form.reset('file');
        return;
    }

    if (!isWithinSizeLimit(file)) {
        clientError.value = `Fichier trop volumineux (20 Mo maximum). Formats acceptés : ${ACCEPTED_LABEL}.`;
        form.reset('file');
        return;
    }

    form.file = file;
    form.post('/documents', {
        forceFormData: true,
        onError: () => {
            // Server-side validation failed (e.g. size). Modal stays open,
            // the error message renders from form.errors.file.
        },
    });
}

function onInputChange(event) {
    const file = event.target.files?.[0] ?? null;
    handleFile(file);
    event.target.value = '';
}

function onDrop(event) {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    handleFile(file);
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function close() {
    if (form.processing) {
        // An upload is in flight: ignore the close request rather than
        // letting the user believe they cancelled while the import still
        // completes and redirects underneath them.
        return;
    }

    clientError.value = '';
    form.reset();
    form.clearErrors();
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key === 'Tab') {
        trapFocus(event);
    }
}

function trapFocus(event) {
    const focusable = dialogRef.value?.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
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

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            triggerElement = document.activeElement;
            await nextTick();
            closeButtonRef.value?.focus();
        } else if (triggerElement instanceof HTMLElement) {
            triggerElement.focus();
        }
    },
);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown="onKeydown"
    >
        <div
            ref="dialogRef"
            role="dialog"
            aria-modal="true"
            aria-labelledby="import-modal-title"
            class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-900"
        >
            <div class="mb-4 flex items-start justify-between">
                <h2 id="import-modal-title" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    Importer un document
                </h2>
                <button
                    ref="closeButtonRef"
                    type="button"
                    class="rounded p-1 text-neutral-500 hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-neutral-100"
                    aria-label="Fermer la fenêtre d'import"
                    :disabled="form.processing"
                    :aria-disabled="form.processing"
                    @click="close"
                >
                    ✕
                </button>
            </div>

            <div
                class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700"
                :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-950/30': isDragging }"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Glissez-déposez un fichier ici, ou
                </p>
                <button
                    type="button"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
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
                <p class="text-xs text-neutral-500 dark:text-neutral-500">
                    Formats acceptés : {{ ACCEPTED_LABEL }}
                </p>
            </div>

            <p v-if="form.processing" class="mt-3 text-sm text-neutral-600 dark:text-neutral-400" role="status">
                Import en cours…
            </p>

            <p v-if="errorMessage" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                {{ errorMessage }}
            </p>
        </div>
    </div>
</template>
