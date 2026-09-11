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

    // I/O matrix "Navigation clavier sidebar": focus order is nav (all
    // three items) then toggle then footer — the footer itself is static
    // text, not a separate focusable stop.
    it('exposes exactly the three nav links then the theme toggle as focusable items, in that order', () => {
        const wrapper = mount(Sidebar);
        const focusable = wrapper.findAll('a, button');

        expect(focusable).toHaveLength(4);
        expect(focusable[0].element.tagName).toBe('A');
        expect(focusable[1].element.tagName).toBe('A');
        expect(focusable[2].element.tagName).toBe('A');
        expect(focusable[3].element.tagName).toBe('BUTTON');
    });

    // I/O matrix "Toggle thème": clicking flips `.dark` on <html> and
    // persists the choice — logic reused verbatim from the old
    // AppHeader.vue, only relocated into the sidebar.
    it('toggles the `.dark` class on <html> and persists the choice in localStorage', async () => {
        document.documentElement.classList.remove('dark');
        const wrapper = mount(Sidebar);
        const toggle = wrapper.find('button');

        expect(document.documentElement.classList.contains('dark')).toBe(false);

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('ekodoc-theme')).toBe('dark');

        await toggle.trigger('click');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('ekodoc-theme')).toBe('light');
    });
});
