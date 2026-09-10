import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Documents/Index.vue';

// `@inertiajs/vue3` is mocked rather than imported for real (same reusable
// shape as TagSelector.spec.js/Sidebar.spec.js): `Link` is stubbed as a
// plain anchor, `usePage()` returns a fixed `tags`/`documentTypeOptions`
// prop set, and `router`/no navigation is ever actually triggered by the
// two I/O-matrix lines this file covers (empty-filtered message, document-
// row rendering) — a no-op stub is enough.
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({
        props: {
            tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
            documentTypeOptions: [
                { value: 'application/pdf', label: 'PDF' },
                { value: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', label: 'Word' },
                { value: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', label: 'Excel' },
            ],
        },
    }),
    router: { get: vi.fn(), on: vi.fn(() => () => {}) },
}));

// AppLayout and ImportModal are stubbed: this file exercises Index.vue's own
// template (empty-state messaging, document-row markup), not the layout
// chrome or the import flow (both out of scope for spec-3-2's I/O matrix).
const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    ImportModal: true,
};

describe('Documents/Index', () => {
    // I/O matrix: "Bibliothèque vide/filtrée sans résultat".
    it('shows the no-results-for-filters message and no rows when filters are active but no document matches', () => {
        const wrapper = mount(Index, {
            props: { documents: [], search: '', tagFilters: [1], typeFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Aucun document ne correspond à ces filtres.');
        expect(wrapper.findAll('li').length).toBe(0);
    });

    // I/O matrix: "Ligne de document" — badge + title + 2 TagChip + date,
    // whole row clickable to the Document Detail.
    it('renders a document-row with the type badge, title, its tags and the date, the whole row linking to the document', () => {
        const wrapper = mount(Index, {
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
                search: '',
                tagFilters: [],
                typeFilters: [],
            },
            global: { stubs: globalStubs },
        });

        const row = wrapper.find('a[href="/documents/42"]');

        expect(row.exists()).toBe(true);
        expect(row.text()).toContain('PDF');
        expect(row.text()).toContain('Contrat prestataire');
        expect(row.text()).toContain('Finance');
        expect(row.text()).toContain('RH');
        // Formatted via Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }) —
        // asserting on the year is enough to confirm a date was rendered
        // without coupling the test to exact locale formatting.
        expect(row.text()).toContain('2026');
    });
});
