<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Theme toggle: logic reused verbatim from the old AppHeader.vue (Boundaries
// & Constraints, spec-3-2 — "réutilisé tel quel, seulement déplacé/restylé
// dans la sidebar"). Same isDark ref, same toggleTheme(), same
// localStorage['ekodoc-theme'] key, same `.dark` class on <html> — the
// SSR-safe script in app.blade.php already resolves this before paint.
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

// Active state is route-aware via `usePage().component` (Boundaries &
// Constraints, spec-3-4) rather than a fixed constant: "Recherche" is
// active only on its own dedicated surface (`Documents/Search`), and
// "Bibliothèque" is active on every other existing surface — Library,
// Document Detail, Editor — same as before (AC1, spec-3-2).
//
// "Configuration" gets the same dedicated-surface treatment (Code Map,
// spec-3-5): active only on `Documents/Configuration`, with "Bibliothèque"
// redefined to exclude it too, alongside Recherche.
const page = usePage();
const isSearchActive = computed(() => page.component === 'Documents/Search');
const isConfigActive = computed(() => page.component === 'Documents/Configuration');
const isLibraryActive = computed(() => !isSearchActive.value && !isConfigActive.value);
</script>

<template>
    <aside
        class="sticky top-0 flex h-screen w-sidebar-width shrink-0 flex-col overflow-y-auto border-r border-border bg-surface-alt px-3 py-5 text-foreground"
    >
        <span class="mb-5 block px-2 text-sm font-semibold tracking-tight">
            EkoDoc
        </span>

        <nav class="flex flex-col gap-0.5" aria-label="Navigation principale">
            <Link
                href="/"
                class="rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isLibraryActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isLibraryActive ? 'page' : undefined"
            >
                Bibliothèque
            </Link>
            <Link
                href="/recherche"
                class="rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isSearchActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isSearchActive ? 'page' : undefined"
            >
                Recherche
            </Link>
            <Link
                href="/configuration"
                class="rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isConfigActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isConfigActive ? 'page' : undefined"
            >
                Configuration
            </Link>
        </nav>

        <div class="flex-1"></div>

        <button
            type="button"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-xs text-muted transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
            :aria-label="isDark ? 'Passer en mode clair' : 'Passer en mode sombre'"
            @click="toggleTheme"
        >
            <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
            </svg>
            {{ isDark ? 'Thème sombre' : 'Thème clair' }}
        </button>

        <p class="px-3 pt-1 text-sm text-muted">
            Made with 💔 Claude
        </p>
    </aside>
</template>
