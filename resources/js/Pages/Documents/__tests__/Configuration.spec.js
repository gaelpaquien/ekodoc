import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import Configuration from '@/Pages/Documents/Configuration.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as AttachmentsPanel.spec.js/Sidebar.spec.js.
// `useForm()` returns a small reactive stand-in whose `post`/`patch` are
// shared spies so a test can both assert on the call and manually invoke
// its `onSuccess` option to simulate a request completing. `router.delete`
// is a separate spy for the delete-confirmation dialog.
const { routerDeleteMock, formPostMock, formPatchMock } = vi.hoisted(() => ({
    routerDeleteMock: vi.fn(),
    formPostMock: vi.fn(),
    formPatchMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            delete: routerDeleteMock,
        },
        useForm: (initial) => reactive({
            ...initial,
            processing: false,
            errors: {},
            post: formPostMock,
            patch: formPatchMock,
            reset(field) {
                if (field) {
                    this[field] = initial[field];
                } else {
                    Object.assign(this, initial);
                }
            },
            clearErrors: vi.fn(),
        }),
    };
});

const pageState = usePage();

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

const twoTags = [
    { id: 1, name: 'Finance', documents_count: 3 },
    { id: 2, name: 'RH', documents_count: 0 },
];

const threeTagsWithSingular = [
    { id: 1, name: 'Finance', documents_count: 3 },
    { id: 2, name: 'RH', documents_count: 0 },
    { id: 3, name: 'Juridique', documents_count: 1 },
];

describe('Documents/Configuration', () => {
    beforeEach(() => {
        routerDeleteMock.mockClear();
        formPostMock.mockClear();
        formPatchMock.mockClear();
        pageState.props.flash = {};
    });

    // --- Rendu de la liste ---------------------------------------------------

    it('renders one row per tag with its name and document count', () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Finance');
        expect(wrapper.text()).toContain('3 documents');
        expect(wrapper.text()).toContain('RH');
        expect(wrapper.text()).toContain('0 document');
    });

    it('renders the singular "1 document" (not "1 documents") for a tag with exactly one document', () => {
        const wrapper = mount(Configuration, {
            props: { tags: threeTagsWithSingular },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('1 document');
        expect(wrapper.text()).not.toContain('1 documents');
    });

    // --- État vide -------------------------------------------------------------

    it('shows "Aucun tag pour l\'instant." when no tag exists', () => {
        const wrapper = mount(Configuration, {
            props: { tags: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain("Aucun tag pour l'instant.");
    });

    // I/O matrix "Aucun tag": the create action is put forward — focus
    // lands directly on the create field.
    it('focuses the create-tag field on mount when no tag exists', () => {
        const wrapper = mount(Configuration, {
            props: { tags: [] },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        expect(document.activeElement).toBe(wrapper.find('#create-tag-name').element);

        wrapper.unmount();
    });

    it('does not steal focus on mount when at least one tag already exists', () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        expect(document.activeElement).not.toBe(wrapper.find('#create-tag-name').element);

        wrapper.unmount();
    });

    // --- Création ----------------------------------------------------------

    it('posts to /tags with the entered name on submit', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('#create-tag-name').setValue('Juridique');
        await wrapper.find('form').trigger('submit');

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock.mock.calls[0][0]).toBe('/tags');
    });

    // AC2: the doublon is signalled in the field itself, case-insensitive,
    // before any request is sent to the server.
    it('signals a case-insensitive duplicate client-side and sends no request', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('#create-tag-name').setValue('finance');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.text()).toContain('Un tag « Finance » existe déjà.');
        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('does not flag a duplicate for a name that does not match any existing tag', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('#create-tag-name').setValue('Juridique');

        expect(wrapper.text()).not.toContain('existe déjà');
    });

    // --- Renommage -----------------------------------------------------------

    it('reveals an inline rename form pre-filled with the current name on "Renommer"', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Renommer le tag Finance"]').trigger('click');

        const renameInput = wrapper.find('#rename-tag-name-1');
        expect(renameInput.exists()).toBe(true);
        expect(renameInput.element.value).toBe('Finance');
    });

    it('closes the inline rename form on "Annuler" without sending a request', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Renommer le tag Finance"]').trigger('click');
        const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Annuler');
        await cancelButton.trigger('click');

        expect(wrapper.find('#rename-tag-name-1').exists()).toBe(false);
        expect(formPatchMock).not.toHaveBeenCalled();
    });

    it('patches /tags/{id} with the new name on rename submit', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Renommer le tag Finance"]').trigger('click');
        await wrapper.find('#rename-tag-name-1').setValue('Comptabilité');
        await wrapper.findAll('form').at(-1).trigger('submit');

        expect(formPatchMock).toHaveBeenCalledTimes(1);
        expect(formPatchMock.mock.calls[0][0]).toBe('/tags/1');
    });

    it('signals a case-insensitive duplicate on rename, excluding the tag itself', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        // Renaming "Finance" to its own current name (case-variant) must
        // never be flagged as a duplicate of itself.
        await wrapper.find('button[aria-label="Renommer le tag Finance"]').trigger('click');
        await wrapper.find('#rename-tag-name-1').setValue('finance');
        expect(wrapper.text()).not.toContain('existe déjà');

        // Renaming it to another tag's name is still rejected.
        await wrapper.find('#rename-tag-name-1').setValue('rh');
        expect(wrapper.text()).toContain('Un tag « RH » existe déjà.');
    });

    // --- Suppression -------------------------------------------------------------

    it('opens the delete confirmation dialog naming the affected document count', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Finance"]').trigger('click');

        const dialog = wrapper.find('[role="dialog"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.text()).toContain('3 documents');
    });

    it('renders the singular "1 document" (not "1 documents") in the delete confirmation dialog', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: threeTagsWithSingular },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Juridique"]').trigger('click');

        const dialog = wrapper.find('[role="dialog"]');
        expect(dialog.text()).toContain('1 document');
        expect(dialog.text()).not.toContain('1 documents');
    });

    it('moves focus to "Annuler" when the delete dialog opens', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Finance"]').trigger('click');
        await wrapper.vm.$nextTick();

        const cancelButton = wrapper.findAll('[role="dialog"] button').find((b) => b.text() === 'Annuler');
        expect(document.activeElement).toBe(cancelButton.element);

        wrapper.unmount();
    });

    it('closes the delete dialog and restores focus to the trigger on "Annuler"', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        const trigger = wrapper.find('button[aria-label="Supprimer le tag Finance"]');
        await trigger.trigger('click');
        await wrapper.vm.$nextTick();

        const cancelButton = wrapper.findAll('[role="dialog"] button').find((b) => b.text() === 'Annuler');
        await cancelButton.trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);

        wrapper.unmount();
    });

    it('closes the delete dialog on Escape', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Finance"]').trigger('click');
        await wrapper.find('.fixed.inset-0').trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('sends DELETE /tags/{id} when the deletion is confirmed', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Finance"]').trigger('click');
        const confirmButton = wrapper.findAll('[role="dialog"] button').find((b) => b.text().includes('Supprimer'));
        await confirmButton.trigger('click');

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock.mock.calls[0][0]).toBe('/tags/1');
    });

    it('closes the delete dialog and restores focus to the trigger when the DELETE request succeeds', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        const trigger = wrapper.find('button[aria-label="Supprimer le tag Finance"]');
        await trigger.trigger('click');
        const confirmButton = wrapper.findAll('[role="dialog"] button').find((b) => b.text().includes('Supprimer'));
        await confirmButton.trigger('click');

        routerDeleteMock.mock.calls[0][1].onSuccess();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);

        wrapper.unmount();
    });

    it('shows "Impossible de supprimer le tag." when the DELETE request fails', async () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Supprimer le tag Finance"]').trigger('click');
        const confirmButton = wrapper.findAll('[role="dialog"] button').find((b) => b.text().includes('Supprimer'));
        await confirmButton.trigger('click');

        routerDeleteMock.mock.calls[0][1].onError();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Impossible de supprimer le tag.');
    });

    // --- Message post-suppression --------------------------------------------

    it('reads the factual post-deletion message from flash.tagDeleted', () => {
        pageState.props.flash = { tagDeleted: { name: 'Finance', count: 3 } };

        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Tag supprimé — détaché de 3 documents.');
    });

    it('shows no post-deletion message when flash.tagDeleted is absent', () => {
        const wrapper = mount(Configuration, {
            props: { tags: twoTags },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).not.toContain('Tag supprimé');
    });
});
