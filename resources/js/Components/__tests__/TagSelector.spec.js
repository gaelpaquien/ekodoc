import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import TagSelector from '@/Components/TagSelector.vue';

// `@inertiajs/vue3` is mocked rather than imported for real: TagSelector
// only ever touches `usePage()` (for the shared `tags` prop) — no full
// Inertia app/router setup is needed to exercise its own logic (same
// reusable mock shape as CategoryPicker.spec.js, the component this
// replaces). The factory below is self-contained (only a dynamic
// `import('vue')` inside it) so it needs no `vi.hoisted()`.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { tags: [] } });

    return {
        usePage: () => pageState,
    };
});

const pageState = usePage();

async function settle() {
    await nextTick();
    await nextTick();
}

describe('TagSelector', () => {
    beforeEach(() => {
        pageState.props.tags = [
            { id: 1, name: 'Contrats' },
            { id: 2, name: 'Factures' },
            { id: 3, name: 'Comptes rendus' },
        ];
    });

    it('renders no chip when modelValue is empty', () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        expect(wrapper.findAll('button[aria-label^="Retirer le tag"]')).toHaveLength(0);
    });

    it('renders a read/remove chip for every already-selected tag', () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [1, 3] } });

        expect(wrapper.text()).toContain('Contrats');
        expect(wrapper.text()).toContain('Comptes rendus');
        expect(wrapper.text()).not.toContain('Factures');
    });

    it('filters the suggestion list live as the user types, excluding already-selected tags', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [1] } });

        await wrapper.find('input').trigger('focus');
        await wrapper.find('input').setValue('fact');
        await settle();

        const options = wrapper.findAll('li[role="option"]');
        expect(options).toHaveLength(1);
        expect(options[0].text()).toBe('Factures');
    });

    it('adds a tag as a chip when a suggestion is clicked', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[1]]]);
    });

    it('adds the highlighted suggestion as a chip on Enter, never a free-text value', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await wrapper.find('input').setValue('Factures');
        await settle();

        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[2]]]);
    });

    it('never creates a new tag on Enter when the typed text matches no existing tag', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await wrapper.find('input').setValue('Nouveau Tag Inexistant');
        await settle();

        expect(wrapper.findAll('li[role="option"]')).toHaveLength(0);

        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('moves the highlighted suggestion with ArrowDown/ArrowUp before selecting it with Enter', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();

        // Suggestions are ordered as in the shared `tags` prop: Contrats(1),
        // Factures(2), Comptes rendus(3). ArrowDown twice moves highlight
        // from Contrats to Comptes rendus.
        await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[3]]]);
    });

    it('removes a tag when its chip remove button is clicked', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [1, 2] } });

        await wrapper.find('button[aria-label="Retirer le tag Contrats"]').trigger('click');
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[2]]]);
    });

    it('closes the suggestion list on Escape without emitting a change', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();
        expect(wrapper.findAll('li[role="option"]').length).toBeGreaterThan(0);

        await wrapper.find('input').trigger('keydown', { key: 'Escape' });
        await settle();

        expect(wrapper.findAll('li[role="option"]')).toHaveLength(0);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('marks the combobox input as keyboard-focusable with an accessible expanded state', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });
        const input = wrapper.find('input[role="combobox"]');

        expect(input.attributes('aria-expanded')).toBe('false');

        await input.trigger('focus');
        await settle();

        expect(input.attributes('aria-expanded')).toBe('true');
    });
});
