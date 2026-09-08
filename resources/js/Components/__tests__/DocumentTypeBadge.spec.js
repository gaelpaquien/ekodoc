import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';

// Covers the 3 branches of `label` (Design Notes, spec-corrections-post-
// retrospective — closes the gap noted in deferred-work.md:14-15): a
// recognized mime type wins first, `source === 'created'` is the fallback
// when the mime type isn't recognized, and an unrecognized mime type with
// no `created` source falls back to the generic "Document" label.
describe('DocumentTypeBadge', () => {
    it('shows the PDF label for a recognized PDF mime type', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: { mimeType: 'application/pdf', source: 'imported' },
        });

        expect(wrapper.text()).toBe('PDF');
    });

    it('shows the Word label for a recognized Word mime type', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: {
                mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                source: 'imported',
            },
        });

        expect(wrapper.text()).toBe('Word');
    });

    it('shows the Excel label for a recognized Excel mime type', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: {
                mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                source: 'imported',
            },
        });

        expect(wrapper.text()).toBe('Excel');
    });

    it('shows "Créé" when the mime type is not recognized but the source is "created"', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: { mimeType: null, source: 'created' },
        });

        expect(wrapper.text()).toBe('Créé');
    });

    it('prefers a recognized mime type over the "created" source', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: { mimeType: 'application/pdf', source: 'created' },
        });

        expect(wrapper.text()).toBe('PDF');
    });

    it('falls back to the generic "Document" label when neither branch matches', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: { mimeType: 'text/plain', source: 'imported' },
        });

        expect(wrapper.text()).toBe('Document');
    });

    it('falls back to the generic "Document" label with no props at all', () => {
        const wrapper = mount(DocumentTypeBadge, {
            props: {},
        });

        expect(wrapper.text()).toBe('Document');
    });
});
