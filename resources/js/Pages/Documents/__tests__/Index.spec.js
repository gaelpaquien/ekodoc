import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Documents/Index.vue';

// `@inertiajs/vue3` is mocked rather than imported for real (same reusable
// shape as TagSelector.spec.js/Sidebar.spec.js): `Link` is stubbed as a
// plain anchor — this file no longer needs `usePage()`/`router` since
// Index.vue dropped all filter logic (spec-nettoyage-sidebar-et-page-
// documents), only document-row rendering and pagination remain.
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

// AppLayout is stubbed: this file exercises Index.vue's own template
// (empty-state messaging, document-row markup), not the layout chrome
// (out of scope for spec-3-2's I/O matrix). ImportModal is no longer
// imported by Index.vue (spec-sidebar-document-actions moved it into
// Sidebar.vue), so no stub is needed for it here.
const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

describe('Documents/Index', () => {
    // spec-nettoyage-sidebar-et-page-documents: filters were removed
    // entirely — an empty library always shows the plain message, never a
    // "filtres actifs" variant.
    it('shows the plain empty-library message when there are no documents', () => {
        const wrapper = mount(Index, {
            props: { documents: { data: [], links: [] } },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain("Aucun document pour l'instant.");
        expect(wrapper.findAll('li').length).toBe(0);
        expect(wrapper.find('a[href="/documents/create"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Importer');
    });

    // I/O matrix: "Ligne de document" — badge + title + 2 TagChip + date,
    // whole row clickable to the Document Detail.
    it('renders a document-row with the type badge, title, its tags and the date, the whole row linking to the document', () => {
        const wrapper = mount(Index, {
            props: {
                documents: {
                    data: [
                        {
                            id: 42,
                            title: 'Contrat prestataire',
                            mime_type: 'application/pdf',
                            source: 'imported',
                            created_at: '2026-01-15T10:30:00Z',
                            tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
                        },
                    ],
                    links: [],
                },
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

    // AC "Bibliothèque paginée": with more than one page, pagination links
    // are rendered from `documents.links`, a disabled (`url: null`) link
    // never rendering as a clickable anchor.
    it('renders pagination links from documents.links when more than one page exists', () => {
        const wrapper = mount(Index, {
            props: {
                documents: {
                    data: [
                        {
                            id: 1,
                            title: 'Doc 1',
                            mime_type: 'application/pdf',
                            source: 'imported',
                            created_at: '2026-01-15T10:30:00Z',
                            tags: [],
                        },
                    ],
                    links: [
                        { url: null, label: '&laquo; Précédent', active: false },
                        { url: '/?page=1', label: '1', active: true },
                        { url: '/?page=2', label: '2', active: false },
                        { url: '/?page=2', label: 'Suivant &raquo;', active: false },
                    ],
                },
            },
            global: { stubs: globalStubs },
        });

        const nav = wrapper.find('nav[aria-label="Pagination"]');
        expect(nav.exists()).toBe(true);

        const pageTwoLink = nav.find('a[href="/?page=2"]');
        expect(pageTwoLink.exists()).toBe(true);

        // 3 of the 4 links carry a url ("1", "2", "Suivant »") — the
        // disabled "Précédent" (url: null) renders as plain text, never an
        // anchor.
        expect(nav.findAll('a').length).toBe(3);
        expect(nav.text()).toContain('Précédent');
    });
});
