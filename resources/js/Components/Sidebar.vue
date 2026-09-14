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
//
// "Bibliothèque" itself is an explicit whitelist of every `Documents/*`
// surface it actually covers, not "everything that isn't Recherche/
// Configuration" (retrospective Epic 3, action item 12) — an exclusion list
// silently lights up "Bibliothèque" for any future page nobody thought to
// add to it here; a whitelist instead defaults a new, unlisted surface to
// no nav item active at all.
const LIBRARY_SURFACES = ['Documents/Index', 'Documents/Editor', 'Documents/Show'];

const page = usePage();
const isSearchActive = computed(() => page.component === 'Documents/Search');
const isConfigActive = computed(() => page.component === 'Documents/Configuration');

// "Créer un document" gets its own active state: `Documents/Editor` serves
// both create (`document` prop null/absent) and edit (prop present) — only
// the former is "Créer un document" itself, matching what the URL/action
// actually is.
const isCreateActive = computed(() => page.component === 'Documents/Editor' && !page.props.document);

// "Importer un document" gets its own active state, mirroring
// isCreateActive above (spec-import-document-page) — now a dedicated page
// (`Documents/Import`) reached via a plain `Link`, not a modal, so it needs
// the same route-aware active treatment as "Créer un document".
const isImportActive = computed(() => page.component === 'Documents/Import');

// `isLibraryActive` excludes the create route: without this, "Documents"
// and "Créer un document" would both light up lime at once on
// `/documents/create` (both cover `Documents/Editor`) — one nav item should
// read as "current" at a time, so create mode belongs to "Créer un
// document" alone, editing an existing document still to "Documents".
const isLibraryActive = computed(() => LIBRARY_SURFACES.includes(page.component) && !isCreateActive.value);

// "Créer un document"/"Importer" moved here from Documents/Index.vue (spec
// spec-sidebar-document-actions) so both actions are available on all 5
// surfaces, not just the Library page content. "Importer un document" now
// navigates to its own dedicated page (spec-import-document-page) instead
// of opening a modal — no more open-state ref to own here.
</script>

<template>
    <aside
        class="sticky top-0 flex h-screen w-sidebar-width shrink-0 flex-col overflow-y-auto border-r border-border bg-surface-alt px-4 py-5 text-foreground"
    >
        <span class="mb-5 block px-2 text-center text-sm font-semibold tracking-tight">
            EkoDoc - Démo
        </span>

        <nav class="flex flex-col gap-0.5" aria-label="Navigation principale">
            <Link
                href="/"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isLibraryActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isLibraryActive ? 'page' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5v-13Z" />
                    <path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5v-13Z" />
                </svg>
                Documents
            </Link>
            <Link
                href="/documents/create"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isCreateActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isCreateActive ? 'page' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                    <path d="M14 3v5h5" />
                    <line x1="9.5" y1="15" x2="14.5" y2="15" />
                    <line x1="12" y1="12.5" x2="12" y2="17.5" />
                </svg>
                Créer un document
            </Link>
            <Link
                href="/documents/import"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isImportActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isImportActive ? 'page' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M12 15V3" />
                    <path d="m7 8 5-5 5 5" />
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                </svg>
                Importer un document
            </Link>
            <Link
                href="/recherche"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isSearchActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isSearchActive ? 'page' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m21 21-4.3-4.3" />
                </svg>
                Recherche
            </Link>
            <Link
                href="/configuration"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isConfigActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isConfigActive ? 'page' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <line x1="4" y1="6" x2="20" y2="6" />
                    <circle cx="9" cy="6" r="2" />
                    <line x1="4" y1="12" x2="20" y2="12" />
                    <circle cx="15" cy="12" r="2" />
                    <line x1="4" y1="18" x2="20" y2="18" />
                    <circle cx="7" cy="18" r="2" />
                </svg>
                Configuration
            </Link>
        </nav>

        <div class="flex-1"></div>

        <button
            type="button"
            class="flex items-center justify-center gap-2 rounded-md px-3 py-2 text-xs text-muted transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
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

        <p class="px-3 pt-1 text-center text-sm text-muted">
            Made with 💔 Claude
        </p>
    </aside>
</template>
