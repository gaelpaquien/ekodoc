<script setup>
import AppHeader from '@/Components/AppHeader.vue';
import ExtractionTasksPanel from '@/Components/ExtractionTasksPanel.vue';
</script>

<template>
    <div class="min-h-screen bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <AppHeader />
        <main>
            <slot />
        </main>
        <ExtractionTasksPanel />
    </div>
</template>
