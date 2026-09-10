import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TagChip from '@/Components/TagChip.vue';

describe('TagChip', () => {
    it('renders the given tag name as read-only text', () => {
        const wrapper = mount(TagChip, { props: { name: 'Factures' } });

        expect(wrapper.text()).toBe('Factures');
    });

    it('renders no interactive element — read-only display, never a filter/remove affordance', () => {
        const wrapper = mount(TagChip, { props: { name: 'Contrats' } });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.findAll('input, select, textarea, a')).toHaveLength(0);
    });
});
