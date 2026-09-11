import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Editor from '@/Pages/Documents/Editor.vue';

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
const { isActiveMock, chainMock, insertTableMock, deleteTableMock, runMock } = vi.hoisted(() => {
    const insertTableMock = vi.fn();
    const deleteTableMock = vi.fn();
    const runMock = vi.fn();

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
        useEditor: () => ref(mockEditor),
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
            post: vi.fn(),
            patch: vi.fn(),
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
