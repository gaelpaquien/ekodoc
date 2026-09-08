import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router, usePage } from '@inertiajs/vue3';
import CategoryPicker from '@/Components/CategoryPicker.vue';

// `@inertiajs/vue3` is mocked rather than imported for real: CategoryPicker
// only ever touches `usePage()` (for the shared `categories` prop) and
// `router.post()` (to create a category) — no full Inertia app/router setup
// is needed to exercise its own logic (Design Notes,
// spec-corrections-post-retrospective: same reusable mock shape ready to
// reuse for `Index.vue`/`Show.vue` if that deferred coverage is picked up
// later). The factory below is self-contained (only a dynamic `import('vue')`
// inside it, no reference to this file's own top-level consts) so it needs
// no `vi.hoisted()` — Vitest hoists `vi.mock()` calls above every import in
// the file, including `reactive`/`router`/`usePage` imports elsewhere here,
// so a factory that closed over those would hit them before initialization.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { categories: [] } });

    return {
        usePage: () => pageState,
        router: { post: vi.fn() },
    };
});

// `usePage()` always returns the same shared (reactive) object from the
// mock factory above — safe to resolve once here and mutate per-test.
const pageState = usePage();
const routerPost = router.post;

async function settle() {
    // startCreating()/cancelCreating()/submitNewCategory()'s onSuccess are
    // all `async` functions that themselves `await nextTick()` before
    // moving focus — a single `await nextTick()` after a trigger() isn't
    // reliably enough ticks for that inner await to have resolved too.
    await nextTick();
    await nextTick();
    await nextTick();
}

describe('CategoryPicker', () => {
    beforeEach(() => {
        pageState.props.categories = [{ id: 1, name: 'Contrats' }];
        routerPost.mockReset();
    });

    it('opens the inline create form and focuses the name input', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        const nameInput = wrapper.find('input[type="text"]');
        expect(nameInput.exists()).toBe(true);
        expect(document.activeElement).toBe(nameInput.element);

        wrapper.unmount();
    });

    it('cancels creation via the Annuler button: closes the form, sends no request, and returns focus to the trigger', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        await wrapper.find('input[type="text"]').setValue('Brouillon');
        await wrapper.findAll('button').find((b) => b.text() === 'Annuler').trigger('click');
        await settle();

        expect(wrapper.find('input[type="text"]').exists()).toBe(false);
        expect(routerPost).not.toHaveBeenCalled();
        // v-if toggles the select/create-form branches, tearing down and
        // recreating the DOM node each time — re-query rather than reuse
        // the pre-toggle wrapper reference, which now points at a detached
        // element.
        expect(document.activeElement).toBe(wrapper.find('button[type="button"]').element);

        wrapper.unmount();
    });

    it('cancels creation via Escape on the name input: closes the form and sends no request', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        await wrapper.find('input[type="text"]').setValue('Brouillon');
        // Passing `key: 'Escape'` explicitly rather than relying on
        // vue-test-utils' own `keydown.escape` modifier-name mapping (its
        // built-in table uses the alias `esc`, not `escape`) — the
        // component's own `@keydown.escape` listener matches on
        // `event.key`, so this exercises exactly what a real Escape
        // keypress dispatches.
        await wrapper.find('input[type="text"]').trigger('keydown', { key: 'Escape' });
        await settle();

        expect(wrapper.find('input[type="text"]').exists()).toBe(false);
        expect(routerPost).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('shows a validation error and sends no request when submitting an empty name', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        await wrapper.findAll('button').find((b) => b.text() === 'Créer').trigger('click');
        await settle();

        expect(wrapper.text()).toContain('Merci de saisir un nom de catégorie.');
        expect(routerPost).not.toHaveBeenCalled();
        // The form stays open so the user can correct the empty name.
        expect(wrapper.find('input[type="text"]').exists()).toBe(true);

        wrapper.unmount();
    });

    it('creates a category, preselects it by name once the shared categories prop refreshes, and returns focus to the trigger', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        await wrapper.find('input[type="text"]').setValue('Facturation');
        await wrapper.findAll('button').find((b) => b.text() === 'Créer').trigger('click');
        await settle();

        expect(routerPost).toHaveBeenCalledTimes(1);
        const [url, payload, options] = routerPost.mock.calls[0];
        expect(url).toBe('/categories');
        expect(payload).toEqual({ name: 'Facturation' });

        // Mirrors the real Inertia flow: the shared `categories` prop has
        // already refreshed with the newly created row by the time
        // onSuccess runs, and CategoryPicker preselects it by matching the
        // name it just submitted (Design Notes, spec-1-5 — no id comes back
        // out of band).
        pageState.props.categories = [
            { id: 1, name: 'Contrats' },
            { id: 2, name: 'Facturation' },
        ];

        await options.onSuccess();
        options.onFinish?.();
        await settle();

        expect(wrapper.emitted('update:modelValue')).toEqual([[2]]);
        expect(wrapper.find('input[type="text"]').exists()).toBe(false);
        expect(document.activeElement).toBe(wrapper.find('button[type="button"]').element);

        wrapper.unmount();
    });

    it('shows the server error message and keeps the form open when creation fails', async () => {
        const wrapper = mount(CategoryPicker, { attachTo: document.body });

        await wrapper.find('button[type="button"]').trigger('click');
        await settle();

        await wrapper.find('input[type="text"]').setValue('Facturation');
        await wrapper.findAll('button').find((b) => b.text() === 'Créer').trigger('click');
        await settle();

        const [, , options] = routerPost.mock.calls[0];
        options.onError({ name: 'Ce nom existe déjà.' });
        options.onFinish?.();
        await settle();

        expect(wrapper.text()).toContain('Ce nom existe déjà.');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.find('input[type="text"]').exists()).toBe(true);

        wrapper.unmount();
    });
});
