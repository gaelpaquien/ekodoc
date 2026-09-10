<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import ExtractionTasksPanel from '@/Components/ExtractionTasksPanel.vue';
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <Sidebar />
        <main class="min-w-0 flex-1">
            <slot />
        </main>
        <ExtractionTasksPanel />
    </div>
</template>
