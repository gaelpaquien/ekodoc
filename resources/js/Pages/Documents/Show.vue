<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
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
});
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <Link href="/" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                &larr; Retour à la bibliothèque
            </Link>

            <h1 class="mt-4 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ document.title }}
            </h1>

            <dl class="mt-6 space-y-2 text-sm text-neutral-700 dark:text-neutral-300">
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
            </dl>

            <div class="mt-6">
                <a
                    v-if="!sourceMissing"
                    :href="downloadUrl"
                    class="inline-flex rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                >
                    Télécharger
                </a>
                <button
                    v-else
                    type="button"
                    disabled
                    class="inline-flex cursor-not-allowed rounded-md bg-neutral-300 px-4 py-2 text-sm font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-600"
                >
                    Télécharger
                </button>
            </div>

            <div class="mt-8">
                <div
                    v-if="sourceMissing"
                    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    Fichier source introuvable ou illisible. La prévisualisation n'est pas disponible.
                </div>

                <iframe
                    v-else-if="isPdf"
                    :src="previewUrl"
                    class="h-[75vh] w-full rounded-md border border-neutral-200 dark:border-neutral-800"
                    title="Aperçu du document"
                ></iframe>

                <template v-else-if="isOfficeDocument">
                    <div
                        v-if="officePreviewState === 'loading'"
                        class="flex items-center gap-2 rounded-md border border-neutral-200 p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-400"
                    >
                        Conversion de l'aperçu en cours…
                    </div>
                    <div
                        v-else-if="officePreviewState === 'error'"
                        class="rounded-md border border-neutral-200 p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-400"
                    >
                        Aperçu indisponible pour ce fichier.
                    </div>
                    <iframe
                        v-else
                        :src="officePreviewBlobUrl"
                        class="h-[75vh] w-full rounded-md border border-neutral-200 dark:border-neutral-800"
                        title="Aperçu du document"
                    ></iframe>
                </template>

                <div
                    v-else
                    class="rounded-md border border-neutral-200 p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-400"
                >
                    Aperçu indisponible pour ce type de document.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
