import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Search from '@/Pages/Documents/Search.vue';

// `@inertiajs/vue3` is mocked rather than imported for real (same reusable
// shape as Index.spec.js/Sidebar.spec.js): `Link` is stubbed as a plain
// anchor, `usePage()` returns a fixed `tags` prop set, and `router` is a
// no-op stub — no navigation is actually triggered by the I/O-matrix lines
// this file covers (neutral empty state, no-results message, document-row
// rendering).
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({
        props: {
            tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
        },
    }),
    router: { get: vi.fn(), on: vi.fn(() => () => {}) },
}));

// AppLayout is stubbed: this file exercises Search.vue's own template
// (neutral state, no-results messaging, document-row markup), not the
// layout chrome.
const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

describe('Documents/Search', () => {
    // AC2: no term typed yet ⇒ neutral state, no result rows, no
    // "no results" message either (Recherche never shows the whole
    // library).
    it('shows no rows and no message in the neutral state when search is empty', () => {
        const wrapper = mount(Search, {
            props: { documents: [], search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.findAll('li').length).toBe(0);
        expect(wrapper.text()).not.toContain('Aucun document ne correspond');
    });

    // I/O matrix "Terme sans résultat".
    it('shows the no-results message when a search term is active but matches nothing', () => {
        const wrapper = mount(Search, {
            props: { documents: [], search: 'xyz123', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Aucun document ne correspond à votre recherche.');
    });

    // I/O matrix "Ligne de document" — badge + title + tags + date, whole
    // row clickable to the Document Detail — same shape as Index.vue's row.
    it('renders a document-row with the type badge, title, its tags and the date, the whole row linking to the document', () => {
        const wrapper = mount(Search, {
            props: {
                documents: [
                    {
                        id: 42,
                        title: 'Contrat prestataire',
                        mime_type: 'application/pdf',
                        source: 'imported',
                        created_at: '2026-01-15T10:30:00Z',
                        tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
                    },
                ],
                search: 'contrat',
                tagFilters: [],
            },
            global: { stubs: globalStubs },
        });

        const row = wrapper.find('a[href="/documents/42"]');

        expect(row.exists()).toBe(true);
        expect(row.text()).toContain('PDF');
        expect(row.text()).toContain('Contrat prestataire');
        expect(row.text()).toContain('Finance');
        expect(row.text()).toContain('RH');
        expect(row.text()).toContain('2026');
    });

    // AC2: focus lands on the search field as soon as the surface loads.
    it('focuses the search input on mount', () => {
        const wrapper = mount(Search, {
            props: { documents: [], search: '', tagFilters: [] },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        expect(document.activeElement).toBe(wrapper.find('input[type="search"]').element);

        wrapper.unmount();
    });

    // Boundaries & Constraints: a tag filter alone, with no term typed,
    // stays in the neutral state — Recherche never falls back to showing
    // the whole library (AC2, same rule as an empty term).
    it('stays neutral (no rows, no message) when only a tag filter is active and search is empty', () => {
        const wrapper = mount(Search, {
            props: { documents: [], search: '', tagFilters: [1] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.findAll('li').length).toBe(0);
        expect(wrapper.text()).not.toContain('Aucun document ne correspond');
    });
});
