<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
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
        </div>
    </AppLayout>
</template>
