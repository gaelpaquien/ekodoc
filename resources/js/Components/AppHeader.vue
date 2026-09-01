<script setup>
import { ref } from 'vue';

const isDark = ref(document.documentElement.classList.contains('dark'));

function toggleTheme() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);

    try {
        localStorage.setItem('ekodoc-theme', isDark.value ? 'dark' : 'light');
    } catch (e) {
        // Private browsing or storage disabled: theme just won't persist across reloads.
    }
}
</script>

<template>
    <header class="border-b border-neutral-200 dark:border-neutral-800">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
            <span class="text-lg font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                EkoDoc
            </span>

            <button
                type="button"
                class="rounded-md p-2 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                :aria-label="isDark ? 'Passer en mode clair' : 'Passer en mode sombre'"
                @click="toggleTheme"
            >
                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
                </svg>
            </button>
        </div>
    </header>
</template>
