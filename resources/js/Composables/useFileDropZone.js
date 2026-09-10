import { ref } from 'vue';

/**
 * Shared drag-and-drop/file-picker plumbing behind ImportModal.vue (spec-3-1)
 * and AttachmentsPanel.vue (spec-3-3) — extracted from ImportModal.vue
 * verbatim (Code Map, spec-3-3) so the accepted-extension/size validation
 * and drag-state handling never drift between the two call sites. Never
 * touches the network itself — each host still owns its own submit
 * (`form.post()`/`router.post()`), this composable only ever answers "is
 * this file acceptable" and tracks the boolean drag-over state a host's
 * drop zone renders.
 *
 * @param {object} options
 * @param {string[]} options.acceptedExtensions - lowercase, no leading dot.
 * @param {string} options.acceptedLabel - human-readable list for error messages (e.g. "PDF, Word (.docx), Excel (.xlsx)").
 * @param {number} options.maxFileSizeBytes
 * @param {string} options.maxFileSizeLabel - human-readable size for error messages (e.g. "20 Mo").
 */
export function useFileDropZone({ acceptedExtensions, acceptedLabel, maxFileSizeBytes, maxFileSizeLabel }) {
    const isDragging = ref(false);

    function extensionOf(filename) {
        return (filename.split('.').pop() || '').toLowerCase();
    }

    function isAcceptedFile(file) {
        return !!file && acceptedExtensions.includes(extensionOf(file.name));
    }

    function isWithinSizeLimit(file) {
        return !!file && file.size <= maxFileSizeBytes;
    }

    /**
     * Client-side-only gate before any request is ever sent (I/O matrix:
     * "Format non supporté" — "aucun envoi réseau") — returns an empty
     * string when `file` is acceptable, otherwise the message to surface.
     * The server re-validates independently regardless (never trusted as
     * the sole gate) — this only ever saves a round-trip for an obviously
     * rejected file.
     */
    function validationError(file) {
        if (!isAcceptedFile(file)) {
            return `Format non supporté. Formats acceptés : ${acceptedLabel}.`;
        }

        if (!isWithinSizeLimit(file)) {
            return `Fichier trop volumineux (${maxFileSizeLabel} maximum). Formats acceptés : ${acceptedLabel}.`;
        }

        return '';
    }

    function onDragover() {
        isDragging.value = true;
    }

    function onDragleave() {
        isDragging.value = false;
    }

    /**
     * Clears the drag-over state and pulls the single dropped file out of
     * the event — every drop zone in this app only ever accepts one file
     * at a time, so only `files[0]` is ever read.
     */
    function fileFromDropEvent(event) {
        isDragging.value = false;

        return event.dataTransfer?.files?.[0] ?? null;
    }

    /**
     * Pulls the single selected file out of a `<input type="file">` change
     * event and resets the input's value so selecting the exact same file
     * again still fires a future `change` event.
     */
    function fileFromInputEvent(event) {
        const file = event.target.files?.[0] ?? null;
        event.target.value = '';

        return file;
    }

    return {
        isDragging,
        extensionOf,
        isAcceptedFile,
        isWithinSizeLimit,
        validationError,
        onDragover,
        onDragleave,
        fileFromDropEvent,
        fileFromInputEvent,
    };
}
