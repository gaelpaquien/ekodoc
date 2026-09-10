import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Sidebar from '@/Components/Sidebar.vue';

// `@inertiajs/vue3`'s `Link` needs no real Inertia app to exercise Sidebar's
// own logic (nav item, theme toggle) — stubbed as a plain anchor, same
// reusable-mock approach as TagSelector.spec.js's `usePage()` mock.
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

describe('Sidebar', () => {
    afterEach(() => {
        document.documentElement.classList.remove('dark');

        try {
            localStorage.removeItem('ekodoc-theme');
        } catch (e) {
            // Private browsing / storage disabled — nothing to clean up.
        }
    });

    it('renders the brand, the Bibliothèque nav item and the footer', () => {
        const wrapper = mount(Sidebar);

        expect(wrapper.text()).toContain('EkoDoc');
        expect(wrapper.text()).toContain('Bibliothèque');
        expect(wrapper.text()).toContain('Made with 💔 Claude');
    });

    // AC1: "Bibliothèque" is the sole nav item and renders active on every
    // existing surface (Library, Document Detail, Editor) — not only on the
    // `/` route — since none of the other surfaces have their own nav item
    // yet (Boundaries & Constraints, spec-3-2).
    it('renders the Bibliothèque nav item as active, linking to the library', () => {
        const wrapper = mount(Sidebar);
        const navLink = wrapper.find('a[href="/"]');

        expect(navLink.exists()).toBe(true);
        expect(navLink.attributes('aria-current')).toBe('page');
        expect(navLink.classes()).toContain('bg-primary');
    });

    // I/O matrix "Navigation clavier sidebar": focus order is nav then
    // toggle then footer — the footer itself is static text, not a
    // separate focusable stop.
    it('exposes exactly the nav link then the theme toggle as focusable items, in that order', () => {
        const wrapper = mount(Sidebar);
        const focusable = wrapper.findAll('a, button');

        expect(focusable).toHaveLength(2);
        expect(focusable[0].element.tagName).toBe('A');
        expect(focusable[1].element.tagName).toBe('BUTTON');
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
