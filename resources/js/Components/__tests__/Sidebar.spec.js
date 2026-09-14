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
    const pageState = reactive({ component: 'Documents/Index', props: {} });

    return {
        Link: {
            name: 'Link',
            props: ['href'],
            template: '<a :href="href"><slot /></a>',
        },
        usePage: () => pageState,
    };
});

const pageState = usePage();

describe('Sidebar', () => {
    afterEach(() => {
        pageState.component = 'Documents/Index';
        pageState.props = {};

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
        expect(wrapper.text()).toContain('Documents');
        expect(wrapper.text()).toContain('Recherche');
        expect(wrapper.text()).toContain('Configuration');
        expect(wrapper.text()).toContain('Made with');
        expect(wrapper.text()).toContain('Claude');
        expect(wrapper.text()).not.toContain('💔');
    });

    // Separator between the "EkoDoc - Démo" brand and the nav/toggle button
    // list, added on human request.
    it('renders a separator between the brand and the button list', () => {
        const wrapper = mount(Sidebar);

        expect(wrapper.find('hr').exists()).toBe(true);
    });

    // The footer's broken-heart emoji ("💔") was replaced with an inline
    // heart SVG crossed out by two diagonal lines (a "cancelled" heart, not
    // a cracked one), on human request.
    it('renders the footer heart as a crossed-out SVG icon, not the broken-heart emoji', () => {
        const wrapper = mount(Sidebar);
        const footer = wrapper.findAll('p').find((p) => p.text().includes('Claude'));

        expect(footer.find('svg').exists()).toBe(true);
        expect(footer.findAll('line')).toHaveLength(2);
    });

    // AC1: "Créer un document" and "Importer un document" render as part of
    // the same nav list as "Documents"/"Recherche"/"Configuration", in
    // order, on every surface (Boundaries & Constraints: no fixed page
    // condition gates them).
    it('renders "Créer un document" and "Importer un document" between Documents and Recherche', () => {
        const wrapper = mount(Sidebar);

        const createLink = wrapper.find('a[href="/documents/create"]');
        expect(createLink.exists()).toBe(true);
        expect(createLink.text()).toBe('Créer un document');

        const importLink = wrapper.find('a[href="/documents/import"]');
        expect(importLink.exists()).toBe(true);
        expect(importLink.text()).toBe('Importer un document');

        const items = wrapper.findAll('nav a, nav button').map((item) => item.text());
        expect(items).toEqual(['Documents', 'Créer un document', 'Importer un document', 'Recherche', 'Configuration']);
    });

    // spec-import-document-page: "Importer un document" is now a plain
    // `Link` to its own dedicated page, exactly like "Créer un document" —
    // no more modal to open, just an Inertia navigation to `/documents/import`.
    it('navigates to /documents/import via a Link, no modal involved', () => {
        const wrapper = mount(Sidebar);

        const importLink = wrapper.find('a[href="/documents/import"]');
        expect(importLink.exists()).toBe(true);
        expect(wrapper.find('[data-testid="import-modal-stub"]').exists()).toBe(false);
    });

    // "Créer un document" renders with the same lime active treatment as the
    // nav links, but only on the create route itself (`Documents/Editor`
    // with no `document` prop) — not while editing an existing document.
    // "Documents" and "Créer un document" are mutually exclusive: only one
    // reads as current at a time, even though both cover `Documents/Editor`.
    it('renders "Créer un document" as active only on the create route, "Documents" turning inactive there — and the reverse while editing', () => {
        pageState.component = 'Documents/Editor';
        pageState.props = {};
        const createWrapper = mount(Sidebar);
        const create = createWrapper.find('a[href="/documents/create"]');
        const documentsLink = createWrapper.find('a[href="/"]');

        expect(create.attributes('aria-current')).toBe('page');
        expect(create.classes()).toContain('bg-primary');
        expect(documentsLink.attributes('aria-current')).toBeUndefined();
        expect(documentsLink.classes()).not.toContain('bg-primary');

        pageState.props = { document: { id: 1, title: 'Existing' } };
        const editWrapper = mount(Sidebar);
        const edit = editWrapper.find('a[href="/documents/create"]');
        const documentsLinkWhileEditing = editWrapper.find('a[href="/"]');

        expect(edit.attributes('aria-current')).toBeUndefined();
        expect(edit.classes()).not.toContain('bg-primary');
        expect(documentsLinkWhileEditing.attributes('aria-current')).toBe('page');
        expect(documentsLinkWhileEditing.classes()).toContain('bg-primary');
    });

    // spec-import-document-page: "Importer un document" now renders with the
    // same lime active treatment as the nav links, active only on its own
    // dedicated page (`Documents/Import`) — mirrors the "Créer un document"
    // active-state test above.
    it('renders "Importer un document" as active only on the Documents/Import page, Documents turning inactive', () => {
        pageState.component = 'Documents/Import';
        const wrapper = mount(Sidebar);
        const importLink = wrapper.find('a[href="/documents/import"]');
        const documentsLink = wrapper.find('a[href="/"]');

        expect(importLink.attributes('aria-current')).toBe('page');
        expect(importLink.classes()).toContain('bg-primary');
        expect(documentsLink.attributes('aria-current')).toBeUndefined();
        expect(documentsLink.classes()).not.toContain('bg-primary');
    });

    // AC1/AC5: "Documents" renders active on every surface except the
    // dedicated Recherche/Configuration ones (Library, Document Detail,
    // Editor — spec-3-2/spec-3-5), driven by `usePage().component` rather
    // than a fixed constant.
    it('renders the Documents nav item as active when the current page is not Recherche', () => {
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
    // surface (Show, above) still works. Uses edit mode (`document` prop
    // present): create mode is "Créer un document"'s own active state
    // instead, covered separately above.
    it('renders the Documents nav item as active on the Documents/Editor page while editing an existing document', () => {
        pageState.component = 'Documents/Editor';
        pageState.props = { document: { id: 1, title: 'Existing' } };
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');

        expect(navLink.attributes('aria-current')).toBe('page');
        expect(navLink.classes()).toContain('bg-primary');
    });

    // AC1: a "Recherche" item links to `/recherche` and renders active only
    // on that dedicated surface, "Documents" turning inactive there.
    it('renders the Recherche nav item as active on the Documents/Search page, Documents turning inactive', () => {
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
    // and renders active only on that dedicated surface, "Documents"
    // turning inactive there too, same shape as the Recherche case above.
    it('renders the Configuration nav item as active on the Documents/Configuration page, Documents turning inactive', () => {
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
    // "Documents".
    it('activates no nav item for a Documents/* surface not in the whitelist', () => {
        pageState.component = 'Documents/X';
        const wrapper = mount(Sidebar);

        expect(wrapper.find('a[href="/"]').attributes('aria-current')).toBeUndefined();
        expect(wrapper.find('a[href="/recherche"]').attributes('aria-current')).toBeUndefined();
        expect(wrapper.find('a[href="/configuration"]').attributes('aria-current')).toBeUndefined();
    });

    // AC5: navigating away from a document reached through the Recherche
    // results (i.e. landing back on a non-Search surface) restores
    // "Documents" as active.
    it('restores Documents as active after navigating from Recherche to another surface', () => {
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
    // spec-sidebar-document-actions: focus order is now Documents, Créer un
    // document, Importer un document, Recherche, Configuration, then the
    // theme toggle then footer — the footer itself is static text, not a
    // separate focusable stop.
    it('exposes Documents, Créer un document, Importer un document, Recherche, Configuration then the theme toggle as focusable items, in that order', () => {
        const wrapper = mount(Sidebar);
        const focusable = wrapper.findAll('a, button');

        expect(focusable).toHaveLength(6);
        expect(focusable[0].element.tagName).toBe('A');
        expect(focusable[0].text()).toBe('Documents');
        expect(focusable[1].element.tagName).toBe('A');
        expect(focusable[1].text()).toBe('Créer un document');
        expect(focusable[2].element.tagName).toBe('A');
        expect(focusable[2].text()).toBe('Importer un document');
        expect(focusable[3].element.tagName).toBe('A');
        expect(focusable[3].text()).toBe('Recherche');
        expect(focusable[4].element.tagName).toBe('A');
        expect(focusable[4].text()).toBe('Configuration');
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
        expect(toggle.text()).toBe('Thème clair');

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('ekodoc-theme')).toBe('dark');
        expect(toggle.text()).toBe('Thème sombre');

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('ekodoc-theme')).toBe('light');
        expect(toggle.text()).toBe('Thème clair');
    });
});
