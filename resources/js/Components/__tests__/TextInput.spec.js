import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TextInput from '@/Components/TextInput.vue';

// Single source of truth for text-field styling (code review feedback,
// spec-corrections-documents-ui): border-radius/padding/height/font-size/
// focus consistency across every real text field in the app is now this
// component's job, not each page's — including the editor's title field,
// which used to render its own text-lg/font-semibold size (human feedback:
// its text visibly didn't match every other field's). Covered here: the
// two behaviors every caller actually relies on (v-model, `ref` exposing
// `.focus()`/`.select()`) and the one escape hatch a caller can still use
// without breaking that consistency (`type`) — not the exact Tailwind
// classes, which would make this test brittle to unrelated visual tweaks.
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

    it('always renders text-sm — no caller overrides font size/weight (every field must look the same)', () => {
        const wrapper = mount(TextInput);

        expect(wrapper.find('input').classes()).toContain('text-sm');
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
