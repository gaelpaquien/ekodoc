import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';

// `@inertiajs/vue3`'s `Link` needs no real Inertia app to exercise Sidebar's
// own logic (nav items, theme toggle) — stubbed as a plain anchor, same
// reusable-mock approach as TagSelector.spec.js's `usePage()` mock.
// `usePage().component` drives which nav item renders active (Boundaries &
// Constraints, spec-3-4) — a mutable reactive object (rather than a fixed
// return value) lets each test set the current page's component name.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ component: 'Documents/Index' });

    return {
        Link: {
            name: 'Link',
            props: ['href'],
            template: '<a :href="href"><slot /></a>',
        },
        usePage: () => pageState,
    };
});

// ImportModal is stubbed (spec-sidebar-document-actions, Code Map): the real
// component calls `useForm()` from `@inertiajs/vue3` unconditionally at
// setup time, which the mock above doesn't provide — this file only needs
// to assert that Sidebar renders it and drives its `open` prop.
// Same reusable-stub convention as the `Link` mock above.
vi.mock('@/Components/ImportModal.vue', () => ({
    default: {
        name: 'ImportModal',
        props: ['open'],
        emits: ['close'],
        template: '<div v-if="open" data-testid="import-modal-stub">ImportModal stub</div>',
    },
}));

const pageState = usePage();

describe('Sidebar', () => {
    afterEach(() => {
        pageState.component = 'Documents/Index';

        document.documentElement.classList.remove('dark');

        try {
            localStorage.removeItem('ekodoc-theme');
        } catch (e) {
            // Private browsing / storage disabled — nothing to clean up.
        }
    });

    it('renders the brand, the nav items and the footer', () => {
        const wrapper = mount(Sidebar);

        expect(wrapper.text()).toContain('EkoDoc');
        expect(wrapper.text()).toContain('Bibliothèque');
        expect(wrapper.text()).toContain('Recherche');
        expect(wrapper.text()).toContain('Configuration');
        expect(wrapper.text()).toContain('Made with 💔 Claude');
    });

    // AC1: "Créer un document" and "Importer" render at the top of the
    // sidebar, above the nav links, on every surface (Boundaries &
    // Constraints: no fixed page condition gates them).
    it('renders "Créer un document" and "Importer" above the nav links', () => {
        const wrapper = mount(Sidebar);

        const createLink = wrapper.find('a[href="/documents/create"]');
        expect(createLink.exists()).toBe(true);
        expect(createLink.text()).toBe('Créer un document');

        const buttons = wrapper.findAll('button');
        expect(buttons[0].text()).toBe('Importer');
    });

    // AC3: clicking "Importer" opens the modal (rendered here via the
    // stubbed ImportModal, `open` prop reflecting the click).
    it('opens the import modal when "Importer" is clicked', async () => {
        const wrapper = mount(Sidebar);

        expect(wrapper.find('[data-testid="import-modal-stub"]').exists()).toBe(false);

        const importButton = wrapper.findAll('button').find((button) => button.text() === 'Importer');
        await importButton.trigger('click');

        expect(wrapper.find('[data-testid="import-modal-stub"]').exists()).toBe(true);
    });

    // AC1/AC5: "Bibliothèque" renders active on every surface except the
    // dedicated Recherche/Configuration ones (Library, Document Detail,
    // Editor — spec-3-2/spec-3-5), driven by `usePage().component` rather
    // than a fixed constant.
    it('renders the Bibliothèque nav item as active when the current page is not Recherche', () => {
        pageState.component = 'Documents/Show';
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');
        const searchLink = wrapper.find('a[href="/recherche"]');

        expect(navLink.attributes('aria-current')).toBe('page');
        expect(navLink.classes()).toContain('bg-primary');
        expect(searchLink.attributes('aria-current')).toBeUndefined();
        expect(searchLink.classes()).not.toContain('bg-primary');
    });

    // Retro Epic 3, item 12: `isLibraryActive` is now an explicit whitelist
    // of `Documents/*` surfaces rather than an exclusion — this asserts the
    // Editor is actually one of the listed entries, not just that some
    // surface (Show, above) still works.
    it('renders the Bibliothèque nav item as active on the Documents/Editor page', () => {
        pageState.component = 'Documents/Editor';
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');

        expect(navLink.attributes('aria-current')).toBe('page');
        expect(navLink.classes()).toContain('bg-primary');
    });

    // AC1: a "Recherche" item links to `/recherche` and renders active only
    // on that dedicated surface, "Bibliothèque" turning inactive there.
    it('renders the Recherche nav item as active on the Documents/Search page, Bibliothèque turning inactive', () => {
        pageState.component = 'Documents/Search';
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');
        const searchLink = wrapper.find('a[href="/recherche"]');

        expect(searchLink.exists()).toBe(true);
        expect(searchLink.attributes('aria-current')).toBe('page');
        expect(searchLink.classes()).toContain('bg-primary');
        expect(navLink.attributes('aria-current')).toBeUndefined();
        expect(navLink.classes()).not.toContain('bg-primary');
    });

    // Code Map, spec-3-5: a "Configuration" item links to `/configuration`
    // and renders active only on that dedicated surface, "Bibliothèque"
    // turning inactive there too, same shape as the Recherche case above.
    it('renders the Configuration nav item as active on the Documents/Configuration page, Bibliothèque turning inactive', () => {
        pageState.component = 'Documents/Configuration';
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');
        const configLink = wrapper.find('a[href="/configuration"]');

        expect(configLink.exists()).toBe(true);
        expect(configLink.attributes('aria-current')).toBe('page');
        expect(configLink.classes()).toContain('bg-primary');
        expect(navLink.attributes('aria-current')).toBeUndefined();
        expect(navLink.classes()).not.toContain('bg-primary');
    });

    // Retrospective Epic 3, action item 12: `isLibraryActive` is an explicit
    // whitelist of `Documents/*` surfaces, not "everything that isn't
    // Recherche/Configuration" — a future, unlisted `Documents/X` page must
    // default to no nav item active at all, rather than silently lighting up
    // "Bibliothèque".
    it('activates no nav item for a Documents/* surface not in the whitelist', () => {
        pageState.component = 'Documents/X';
        const wrapper = mount(Sidebar);

        expect(wrapper.find('a[href="/"]').attributes('aria-current')).toBeUndefined();
        expect(wrapper.find('a[href="/recherche"]').attributes('aria-current')).toBeUndefined();
        expect(wrapper.find('a[href="/configuration"]').attributes('aria-current')).toBeUndefined();
    });

    // AC5: navigating away from a document reached through the Recherche
    // results (i.e. landing back on a non-Search surface) restores
    // "Bibliothèque" as active.
    it('restores Bibliothèque as active after navigating from Recherche to another surface', () => {
        pageState.component = 'Documents/Search';
        const wrapper = mount(Sidebar);
        expect(wrapper.find('a[href="/"]').attributes('aria-current')).toBeUndefined();

        pageState.component = 'Documents/Show';

        return wrapper.vm.$nextTick().then(() => {
            expect(wrapper.find('a[href="/"]').attributes('aria-current')).toBe('page');
            expect(wrapper.find('a[href="/recherche"]').attributes('aria-current')).toBeUndefined();
        });
    });

    // I/O matrix "Navigation clavier sidebar", updated by
    // spec-sidebar-document-actions: focus order is now "Créer un document"
    // then "Importer" then the three nav links then the theme toggle then
    // footer — the footer itself is static text, not a separate focusable
    // stop.
    it('exposes "Créer un document", "Importer", the three nav links then the theme toggle as focusable items, in that order', () => {
        const wrapper = mount(Sidebar);
        const focusable = wrapper.findAll('a, button');

        expect(focusable).toHaveLength(6);
        expect(focusable[0].element.tagName).toBe('A');
        expect(focusable[0].text()).toBe('Créer un document');
        expect(focusable[1].element.tagName).toBe('BUTTON');
        expect(focusable[1].text()).toBe('Importer');
        expect(focusable[2].element.tagName).toBe('A');
        expect(focusable[3].element.tagName).toBe('A');
        expect(focusable[4].element.tagName).toBe('A');
        expect(focusable[5].element.tagName).toBe('BUTTON');
    });

    // I/O matrix "Toggle thème": clicking flips `.dark` on <html> and
    // persists the choice — logic reused verbatim from the old
    // AppHeader.vue, only relocated into the sidebar.
    it('toggles the `.dark` class on <html> and persists the choice in localStorage', async () => {
        document.documentElement.classList.remove('dark');
        const wrapper = mount(Sidebar);
        const toggle = wrapper.find('[aria-label="Passer en mode sombre"]');

        expect(document.documentElement.classList.contains('dark')).toBe(false);

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('ekodoc-theme')).toBe('dark');

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('ekodoc-theme')).toBe('light');
    });
});
