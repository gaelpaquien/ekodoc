import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as TagSelector.spec.js/Sidebar.spec.js. `useForm()`
// returns a small reactive stand-in whose `post` is a shared spy
// (`formPostMock`) so a test can both assert on the call and manually
// invoke its `onStart`/`onSuccess`/`onError`/`onFinish` options to simulate
// a request completing. `vi.hoisted()` is required here (unlike
// TagSelector.spec.js's self-contained factory) since these mocks must also
// be reachable from the test bodies below, not just from inside the factory.
const { routerPostMock, routerDeleteMock, formPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    formPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            post: routerPostMock,
            delete: routerDeleteMock,
        },
        useForm: (initial) => reactive({
            ...initial,
            processing: false,
            errors: {},
            post: formPostMock,
            reset: vi.fn(),
            clearErrors: vi.fn(),
        }),
    };
});

const pageState = usePage();

function pdfFile(name = 'source.pdf') {
    return new File(['%PDF-1.4 fake'], name, { type: 'application/pdf' });
}

function pngFile(name = 'photo.png') {
    return new File(['fake'], name, { type: 'image/png' });
}

const immediateAttachments = [
    { id: 1, original_filename: 'contrat.pdf', mime_type: 'application/pdf' },
    { id: 2, original_filename: 'budget.xlsx', mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
];

const draftAttachments = [
    { filename: '11111111-1111-1111-1111-111111111111.pdf', original_filename: 'brouillon.pdf', mime_type: 'application/pdf' },
];

describe('AttachmentsPanel', () => {
    beforeEach(() => {
        routerPostMock.mockClear();
        routerDeleteMock.mockClear();
        formPostMock.mockClear();
        pageState.props.flash = {};
    });

    // --- État vide ("toujours affiché") -----------------------------------

    it('always shows "Aucune pièce jointe." when the list is empty, immediate mode', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        expect(wrapper.text()).toContain('Aucune pièce jointe.');
    });

    it('always shows "Aucune pièce jointe." when the list is empty, draft mode', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        expect(wrapper.text()).toContain('Aucune pièce jointe.');
    });

    // --- Rendu de la liste ---------------------------------------------------

    it('renders one row per attachment with preview/download links in immediate mode', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });

        expect(wrapper.text()).toContain('contrat.pdf');
        expect(wrapper.text()).toContain('budget.xlsx');

        const previewLinks = wrapper.findAll('a').filter((a) => a.text() === 'Aperçu');
        expect(previewLinks).toHaveLength(2);
        expect(previewLinks[0].attributes('href')).toBe('/documents/42/attachments/1/preview');

        const downloadLinks = wrapper.findAll('a').filter((a) => a.text() === 'Télécharger');
        expect(downloadLinks[0].attributes('href')).toBe('/documents/42/attachments/1/download');
    });

    it('renders draft attachments with no preview/download links', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftAttachments, mode: 'draft', draftToken: 'draft-token' },
        });

        expect(wrapper.text()).toContain('brouillon.pdf');
        expect(wrapper.findAll('a')).toHaveLength(0);
    });

    // --- Ouverture / fermeture du panneau -------------------------------------

    it('starts expanded, and the toggle button collapses/reopens the body', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        const toggle = wrapper.find('button[aria-controls]');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Aucune pièce jointe.');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.text()).not.toContain('Aucune pièce jointe.');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Aucune pièce jointe.');
    });

    // --- Format non supporté (client-side, pas d'envoi réseau) ----------------

    it('rejects an unsupported format client-side, sending no request', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pngFile()] } });

        expect(wrapper.text()).toContain('Format non supporté');
        expect(formPostMock).not.toHaveBeenCalled();
    });

    // --- Mode immediate : ajout ------------------------------------------------

    it('posts to /documents/{id}/attachments on a valid drop in immediate mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock.mock.calls[0][0]).toBe('/documents/42/attachments');
        expect(formPostMock.mock.calls[0][1].forceFormData).toBe(true);
        expect(formPostMock.mock.calls[0][1].preserveState).toBe(true);
    });

    // --- Réentrance : un deuxième drop pendant un envoi en cours est ignoré ----

    it('ignores a second drop while an immediate upload is still in flight', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('first.pdf')] } });
        expect(formPostMock).toHaveBeenCalledTimes(1);

        // Simulates the in-flight state Inertia would set via onStart —
        // formPostMock itself is a bare spy, so onStart is never invoked
        // automatically.
        formPostMock.mock.calls[0][1].onStart();

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('second.pdf')] } });
        expect(formPostMock).toHaveBeenCalledTimes(1);
    });

    it('ignores a second drop while a draft upload is still in flight', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('first.pdf')] } });
        expect(routerPostMock).toHaveBeenCalledTimes(1);

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('second.pdf')] } });
        expect(routerPostMock).toHaveBeenCalledTimes(1);
    });

    // --- v-model:uploading -------------------------------------------------------

    it('emits update:uploading true then false around an immediate upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        const { onStart, onFinish } = formPostMock.mock.calls[0][1];
        onStart();
        await wrapper.vm.$nextTick();
        onFinish();
        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:uploading');
        expect(emitted.at(-2)).toEqual([true]);
        expect(emitted.at(-1)).toEqual([false]);
    });

    it('emits update:uploading true then false around a draft upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });
        await wrapper.vm.$nextTick();

        const { onFinish } = routerPostMock.mock.calls[0][2];
        onFinish();
        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:uploading');
        expect(emitted.at(-2)).toEqual([true]);
        expect(emitted.at(-1)).toEqual([false]);
    });

    // --- Mode immediate : retrait -----------------------------------------------

    it('deletes /documents/{id}/attachments/{attachment} on Retirer in immediate mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('button[aria-label="Retirer la pièce jointe contrat.pdf"]').trigger('click');

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock.mock.calls[0][0]).toBe('/documents/42/attachments/1');
        expect(routerDeleteMock.mock.calls[0][1].preserveState).toBe(true);
    });

    // --- Mode draft : ajout via upload + flash ----------------------------------

    it('posts to /documents/create/attachments on a valid drop in draft mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][0]).toBe('/documents/create/attachments');
        expect(routerPostMock.mock.calls[0][1].draft_token).toBe('draft-token');
    });

    it('emits update:attachments when flash.uploadedAttachment matches this draft token', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        pageState.props.flash.uploadedAttachment = {
            filename: '22222222-2222-2222-2222-222222222222.pdf',
            original_filename: 'nouveau.pdf',
            mime_type: 'application/pdf',
            draftToken: 'draft-token',
        };

        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:attachments');
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toEqual([{
            filename: '22222222-2222-2222-2222-222222222222.pdf',
            original_filename: 'nouveau.pdf',
            mime_type: 'application/pdf',
        }]);
    });

    it('ignores flash.uploadedAttachment meant for a different draft token', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        pageState.props.flash.uploadedAttachment = {
            filename: '33333333-3333-3333-3333-333333333333.pdf',
            original_filename: 'autre.pdf',
            mime_type: 'application/pdf',
            draftToken: 'other-draft-token',
        };

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:attachments')).toBeUndefined();
    });

    // --- Mode draft : retrait local, sans requête réseau ------------------------

    it('removes a draft attachment locally on Retirer, with no request sent', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftAttachments, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('button[aria-label="Retirer la pièce jointe brouillon.pdf"]').trigger('click');

        expect(wrapper.emitted('update:attachments')).toEqual([[[]]]);
        expect(routerDeleteMock).not.toHaveBeenCalled();
        expect(routerPostMock).not.toHaveBeenCalled();
    });
});
