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

    // spec-fix-multi-tag-selection: the suggestion list used to close after
    // every selection, and refocusing the (already-focused) input never
    // re-fires the `focus` event that reopens it — a second tag required
    // Tab/Escape then a reclick. It must now stay open across selections.
    it('keeps the suggestion list open across successive selections, letting a second tag be picked without refocusing', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();

        // Still open — no blur/Escape happened, and no external reclick was
        // needed to see it again.
        expect(wrapper.findAll('li[role="option"]').length).toBeGreaterThan(0);
        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');

        // Mirrors what every host page does on `update:modelValue` (a
        // `v-model` binding) — the component itself holds no selection
        // state of its own, so the test must feed the emitted value back in
        // exactly like a real parent would before the second pick.
        await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue')[0][0] });

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[1]], [[1, 2]]]);
        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
    });

    // spec-fix-multi-tag-selection, review_loop_iteration 1: mirrors the
    // mouse-based "keeps the suggestion list open across successive
    // selections" test above, but through the keyboard path (ArrowDown +
    // Enter) — the list must stay open and a second Enter-selection must
    // succeed without any refocus, exactly like the mouse path.
    it('keeps the suggestion list open across successive keyboard selections via Enter, letting a second tag be picked without refocusing', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();

        // Suggestions ordered Contrats(1), Factures(2), Comptes rendus(3);
        // focus already highlights index 0 (Contrats) — one ArrowDown moves
        // the highlight to Factures(2) before Enter selects it.
        await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.emitted('update:modelValue')).toEqual([[[2]]]);

        await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue')[0][0] });

        // Remaining suggestions: Contrats(1), Comptes rendus(3) — highlight
        // reset to index 0 (Contrats); one ArrowDown moves it to Comptes
        // rendus(3) before this second Enter selects it, with no refocus in
        // between.
        await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[2]], [[2, 3]]]);
        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
    });

    // spec-fix-multi-tag-selection, review_loop_iteration 1 (amendment):
    // Show.vue disables TagSelector synchronously (`:disabled="isSavingTags"`)
    // right after a selection, while the list is still open, for its
    // immediate-write PATCH. A native `disabled` input is always
    // browser-force-blurred, which used to close the list despite the fix
    // above — `readonly`+guards must keep it open (inertly) instead.
    it('keeps the suggestion list open (inertly) when disabled turns true right after a selection, then becomes fully interactive again without refocusing once disabled turns back false', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [], disabled: false } });

        await wrapper.find('input').trigger('focus');
        await settle();

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();
        await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue')[0][0] });

        // Simulates Show.vue's synchronous `isSavingTags` flip right after
        // the selection above, while the list is already open.
        await wrapper.setProps({ disabled: true });
        await settle();

        // Still open, but now inert: readonly (never force-blurred, unlike
        // native `disabled`), and no further interaction succeeds.
        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('input').attributes('readonly')).toBeDefined();
        expect(wrapper.find('input').attributes('aria-disabled')).toBe('true');

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await wrapper.find('input').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.find('input').trigger('keydown', { key: 'Enter' });
        await settle();

        // No further emission happened while disabled.
        expect(wrapper.emitted('update:modelValue')).toEqual([[[1]]]);

        // Back to interactive, immediately, with no reclick/refocus.
        await wrapper.setProps({ disabled: false });
        await settle();

        expect(wrapper.find('input').attributes('readonly')).toBeUndefined();
        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[[1]], [[1, 2]]]);
    });

    // review_loop_iteration 1 (second pass): removeTag() had no `disabled`
    // guard, unlike selectTag()/onKeydown() — a chip's remove button stays
    // native `disabled` (Code Map), but the handler itself must also refuse
    // to emit while disabled, as a defense-in-depth match for the others.
    it('does not remove a tag when its chip remove button is clicked while disabled', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [1], disabled: true } });

        await wrapper.find('button[aria-label="Retirer le tag Contrats"]').trigger('click');
        await settle();

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('shows the no-match message and stays open after picking the last available tag', async () => {
        // Only one tag (id 3) is left unselected — picking it should not
        // auto-close the list; the empty-suggestions message renders in
        // its place instead, matching the "already selected" behaviour of
        // every other suggestion.
        const wrapper = mount(TagSelector, { props: { modelValue: [1, 2] } });

        await wrapper.find('input').trigger('focus');
        await settle();

        expect(wrapper.findAll('li[role="option"]')).toHaveLength(1);

        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();
        await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue')[0][0] });
        await settle();

        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.findAll('li[role="option"]')).toHaveLength(0);
        expect(wrapper.find('li[role="presentation"]').text()).toBe('Aucun tag ne correspond.');
    });

    it('still closes on Escape, resetting the query, after one or more selections were made', async () => {
        const wrapper = mount(TagSelector, { props: { modelValue: [] } });

        await wrapper.find('input').trigger('focus');
        await settle();
        await wrapper.find('li[role="option"]').trigger('mousedown');
        await settle();
        await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue')[0][0] });
        await wrapper.find('input').setValue('fact');
        await settle();

        await wrapper.find('input').trigger('keydown', { key: 'Escape' });
        await settle();

        expect(wrapper.findAll('li[role="option"]')).toHaveLength(0);
        expect(wrapper.find('input[role="combobox"]').attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('input').element.value).toBe('');
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

    // Retro Epic 3, item 6: the Library filter's own TagSelector and the
    // Import modal's TagSelector are mounted at the same time once the
    // modal opens — their ids must never collide, or `label[for]`/
    // `aria-controls`/`aria-activedescendant` all point at the wrong node.
    it('generates distinct ids for two simultaneously mounted instances', async () => {
        const wrapperA = mount(TagSelector, { props: { modelValue: [] } });
        const wrapperB = mount(TagSelector, { props: { modelValue: [] } });

        const inputA = wrapperA.find('input');
        const inputB = wrapperB.find('input');

        expect(inputA.attributes('id')).not.toBe(inputB.attributes('id'));
        expect(wrapperA.find('label').attributes('for')).toBe(inputA.attributes('id'));
        expect(wrapperB.find('label').attributes('for')).toBe(inputB.attributes('id'));

        await inputA.trigger('focus');
        await settle();

        expect(inputA.attributes('aria-controls')).not.toBe(undefined);
        expect(wrapperA.find('ul').attributes('id')).toBe(inputA.attributes('aria-controls'));
        // Instance B never opened its listbox — its aria-controls must stay
        // unset rather than accidentally pointing at instance A's listbox id.
        expect(inputB.attributes('aria-controls')).toBeUndefined();
    });
});
