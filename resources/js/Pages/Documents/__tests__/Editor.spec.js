import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Editor from '@/Pages/Documents/Editor.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import TagSelector from '@/Components/TagSelector.vue';

// `@tiptap/vue-3` is mocked out entirely — mounting a real TipTap/ProseMirror
// instance in jsdom is unnecessary for this story (only the toolbar's
// `disabled` bindings and which TipTap command a click runs are in scope)
// and brittle. `useEditor()` returns a plain Vue ref wrapping a small
// stand-in editor whose `isActive`/`chain` are shared spies, so a test can
// both control what the toolbar sees and assert exactly which command ran —
// same reusable-mock approach as Configuration.spec.js's mocked `useForm`.
// `chain()` always returns the same fluent object so
// `chain().focus().insertTable(...).run()` / `chain().focus().deleteTable().run()`
// keep working however many links are chained.
const {
    isActiveMock, chainMock, insertTableMock, deleteTableMock, runMock, formPostMock, formPatchMock,
} = vi.hoisted(() => {
    const insertTableMock = vi.fn();
    const deleteTableMock = vi.fn();
    const runMock = vi.fn();
    const formPostMock = vi.fn();
    const formPatchMock = vi.fn();

    const chainObj = {
        focus: () => chainObj,
        insertTable: (...args) => {
            insertTableMock(...args);
            return chainObj;
        },
        deleteTable: (...args) => {
            deleteTableMock(...args);
            return chainObj;
        },
        toggleHeading: () => chainObj,
        toggleBulletList: () => chainObj,
        toggleOrderedList: () => chainObj,
        setTextSelection: () => chainObj,
        setImage: () => chainObj,
        run: (...args) => {
            runMock(...args);
            return chainObj;
        },
    };

    return {
        isActiveMock: vi.fn(() => false),
        chainMock: vi.fn(() => chainObj),
        insertTableMock,
        deleteTableMock,
        runMock,
        formPostMock,
        formPatchMock,
    };
});

vi.mock('@tiptap/vue-3', async () => {
    const { ref } = await import('vue');

    const mockEditor = {
        isActive: isActiveMock,
        chain: chainMock,
        getHTML: () => '',
        isEditable: true,
        setEditable: () => {},
        view: { posAtCoords: () => null },
    };

    return {
        // Invoking `onCreate` synchronously (as TipTap does in practice, same
        // tick) lets tests reach a mounted state where `initialSnapshot` is
        // set and `isDirty` reflects real edits — needed by the navigation
        // guard tests below. Harmless for the table tests above, which never
        // read `isDirty`/`isLoadingContent`.
        useEditor: (options) => {
            options?.onCreate?.({ editor: mockEditor });

            return ref(mockEditor);
        },
        EditorContent: { name: 'EditorContent', template: '<div class="editor-content-stub" />' },
    };
});

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as Configuration.spec.js/AttachmentsPanel.spec.js.
// Nothing here exercises navigation/save, so the spies only need to exist,
// not be asserted on.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            on: vi.fn(() => vi.fn()),
            post: vi.fn(),
        },
        useForm: (initial) => reactive({
            ...initial,
            processing: false,
            errors: {},
            post: formPostMock,
            patch: formPatchMock,
        }),
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    };
});

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    AttachmentsPanel: true,
    TagSelector: true,
};

function mountEditor() {
    return mount(Editor, {
        global: { stubs: globalStubs },
    });
}

describe('Documents/Editor — tableaux imbriqués (spec-3-6)', () => {
    beforeEach(() => {
        insertTableMock.mockClear();
        deleteTableMock.mockClear();
        runMock.mockClear();
        chainMock.mockClear();
        isActiveMock.mockReset();
        isActiveMock.mockImplementation(() => false);
    });

    // --- "Insérer un tableau" ------------------------------------------------

    it('disables "Insérer un tableau" when the cursor is already inside a table', () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('enables "Insérer un tableau" and inserts a 3x3 table when the cursor is outside any table', async () => {
        isActiveMock.mockImplementation(() => false);

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');

        expect(insertTableMock).toHaveBeenCalledWith({ rows: 3, cols: 3, withHeaderRow: true });
        expect(runMock).toHaveBeenCalled();
    });

    // AC1 / Boundaries & Constraints: `insertTable()` itself checks
    // `editor.isActive('table')` before running the insertion chain — this
    // is the JS-level guard, kept as a defense-in-depth check independent of
    // the `disabled` attribute asserted above (which already stops a real
    // click from ever reaching the handler). The native `disabled` property
    // is cleared here so the click still dispatches, isolating the guard.
    it('never runs the insertion chain from insertTable() when the cursor is already inside a table, even if the click still dispatches', async () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');
        button.element.disabled = false;

        await button.trigger('click');

        expect(chainMock).not.toHaveBeenCalled();
        expect(insertTableMock).not.toHaveBeenCalled();
        expect(runMock).not.toHaveBeenCalled();
    });

    // --- "Supprimer le tableau" -----------------------------------------------

    it('disables "Supprimer le tableau" when the cursor is outside any table', () => {
        isActiveMock.mockImplementation(() => false);

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Supprimer le tableau"]');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('enables "Supprimer le tableau" and removes the table when the cursor is inside one', async () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Supprimer le tableau"]');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');

        expect(deleteTableMock).toHaveBeenCalledTimes(1);
        expect(runMock).toHaveBeenCalled();
    });
});

// Retrospective Epic 3, action item 7: AttachmentsPanel's own immediate-mode
// attach/detach requests must not trip Editor's unsaved-changes guard even
// while the document is genuinely dirty elsewhere (title/content/tags).
// `AttachmentsPanel` is left stubbed (see `globalStubs` above) — only its
// `before-request`/`after-request` emits matter here, not its internal
// upload/delete plumbing (already covered by AttachmentsPanel.spec.js).
describe('Documents/Editor — garde de navigation vs AttachmentsPanel (retro Epic 3, item 7)', () => {
    async function mountDirtyEditor() {
        const wrapper = mount(Editor, {
            props: {
                document: { id: 1, title: 'Titre initial', content_html: '', tags: [], attachments: [] },
            },
            global: { stubs: globalStubs },
        });

        await wrapper.find('#document-title').setValue('Titre modifié');

        return wrapper;
    }

    function latestBeforeGuard() {
        const call = router.on.mock.calls.filter(([event]) => event === 'before').at(-1);

        return call[1];
    }

    it('shows the confirmation when a real navigation is attempted while dirty (sanity baseline)', async () => {
        window.confirm = vi.fn(() => true);
        await mountDirtyEditor();

        latestBeforeGuard()({ preventDefault: vi.fn() });

        expect(window.confirm).toHaveBeenCalledTimes(1);
    });

    it('does not show the confirmation while an AttachmentsPanel immediate-mode request is in flight', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountDirtyEditor();
        const beforeGuard = latestBeforeGuard();

        wrapper.findComponent(AttachmentsPanel).vm.$emit('before-request');
        beforeGuard({ preventDefault: vi.fn() });

        expect(window.confirm).not.toHaveBeenCalled();
    });

    it('re-arms the confirmation once the AttachmentsPanel request completes', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountDirtyEditor();
        const beforeGuard = latestBeforeGuard();
        const attachmentsPanel = wrapper.findComponent(AttachmentsPanel);

        attachmentsPanel.vm.$emit('before-request');
        attachmentsPanel.vm.$emit('after-request');
        beforeGuard({ preventDefault: vi.fn() });

        expect(window.confirm).toHaveBeenCalledTimes(1);
    });
});

describe('Documents/Editor — champ Tags visible sans révélation en deux temps (spec-fix-multi-tag-selection)', () => {
    beforeEach(() => {
        formPostMock.mockClear();
        formPatchMock.mockClear();
    });

    it('renders the Tags field immediately on a brand-new document, with no prior click on "Enregistrer"', () => {
        const wrapper = mountEditor();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(true);
    });

    // review_loop_iteration 1, finding verification-gap: the two-step
    // "Enregistrer" gesture (reveal the tag selector on the first click,
    // submit only on the second) is gone — a brand-new document (no
    // `document` prop) must submit directly on the very first click.
    it('submits directly to /documents/create on the first click on "Enregistrer" for a brand-new document', async () => {
        const wrapper = mountEditor();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock.mock.calls[0][0]).toBe('/documents/create');
        expect(formPatchMock).not.toHaveBeenCalled();
    });
});
