import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TextInput from '@/Components/TextInput.vue';

// Single source of truth for text-field styling (code review feedback,
// spec-corrections-documents-ui): border-radius/padding/focus consistency
// across every real text field in the app is now this component's job, not
// each page's. Covered here: the two behaviors every caller actually
// relies on (v-model, `ref` exposing `.focus()`/`.select()`) and the two
// escape hatches a caller can use without breaking that consistency (`type`,
// `size`) — not the exact Tailwind classes, which would make this test
// brittle to unrelated visual tweaks.
describe('TextInput', () => {
    it('binds v-model both ways', async () => {
        const wrapper = mount(TextInput, {
            props: {
                modelValue: 'Rapport.pdf',
                'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
            },
        });

        expect(wrapper.find('input').element.value).toBe('Rapport.pdf');

        await wrapper.find('input').setValue('Contrat.docx');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Contrat.docx']);
    });

    it('exposes focus() and select(), forwarded to the underlying <input> (callers keep a template ref for exactly this)', () => {
        const wrapper = mount(TextInput, { attachTo: document.body });
        const input = wrapper.find('input').element;

        let focusCalled = false;
        let selectCalled = false;
        input.focus = () => { focusCalled = true; };
        input.select = () => { selectCalled = true; };

        wrapper.vm.focus();
        wrapper.vm.select();

        expect(focusCalled).toBe(true);
        expect(selectCalled).toBe(true);

        wrapper.unmount();
    });

    it('defaults to type="text" and renders type="search" when passed (Search.vue)', () => {
        const textWrapper = mount(TextInput);
        expect(textWrapper.find('input').attributes('type')).toBe('text');

        const searchWrapper = mount(TextInput, { props: { type: 'search' } });
        expect(searchWrapper.find('input').attributes('type')).toBe('search');
    });

    it('applies the `size` prop instead of the default text-sm (Editor.vue\'s title field needs text-lg font-semibold)', () => {
        const wrapper = mount(TextInput, { props: { size: 'text-lg font-semibold' } });
        const classes = wrapper.find('input').classes();

        expect(classes).toContain('text-lg');
        expect(classes).toContain('font-semibold');
        expect(classes).not.toContain('text-sm');
    });

    it('falls through arbitrary attributes (disabled, placeholder, aria-*) onto the <input>', () => {
        const wrapper = mount(TextInput, {
            props: { disabled: true, placeholder: 'Nom du tag', 'aria-invalid': true },
        });
        const input = wrapper.find('input');

        expect(input.attributes('disabled')).toBeDefined();
        expect(input.attributes('placeholder')).toBe('Nom du tag');
        expect(input.attributes('aria-invalid')).toBe('true');
    });
});
