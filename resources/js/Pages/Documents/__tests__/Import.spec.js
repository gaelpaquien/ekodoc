import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router, usePage } from '@inertiajs/vue3';
import Import from '@/Pages/Documents/Import.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as Configuration.spec.js/Editor.spec.js.
// `useForm()` is called twice by Import.vue (the upload `form` and the
// step-2 `tagsForm`) — each call gets its own reactive instance so
// `form.processing` and `tagsForm.errors` can be manipulated independently,
// but both share the same `formPostMock`/`formPatchMock` spies so a test can
// assert on whichever one the component actually called. Each instance is
// also stashed on `formInstances` (keyed by which one it is, detected from
// its initial shape) so a test can reach into `form.processing` directly —
// there's no other way to flip it, since real Inertia sets it internally
// during a request and this mock never simulates the request lifecycle.
const { formPostMock, formPatchMock, routerDeleteMock, formInstances } = vi.hoisted(() => ({
    formPostMock: vi.fn(),
    formPatchMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    formInstances: { upload: null, tags: null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            on: vi.fn(() => vi.fn()),
            delete: routerDeleteMock,
        },
        useForm: (initial) => {
            const instance = reactive({
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
            });

            if ('file' in initial) {
                formInstances.upload = instance;
            } else {
                formInstances.tags = instance;
            }

            return instance;
        },
    };
});

const pageState = usePage();

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    DocumentTypeBadge: true,
    TagSelector: true,
};

const uploadedDocument = { id: 5, title: 'Rapport.pdf', mime_type: 'application/pdf' };

// Import.vue's beforeunload patch attaches a real `window.addEventListener`
// — a genuine global side effect, unlike the mocked `router.on()` above.
// Every mounted instance in this file must be unmounted afterwards so its
// listener is removed (onUnmounted), otherwise a stale instance from an
// earlier test keeps reacting to `window.dispatchEvent()` in a later one.
let mountedWrappers = [];

function mountImport() {
    const wrapper = mount(Import, { global: { stubs: globalStubs } });
    mountedWrappers.push(wrapper);

    return wrapper;
}

/** Mounts, then simulates the upload's redirect landing with
 * `flash.uploadedDocument` set — same technique Configuration.spec.js uses
 * for `flash.tagDeleted`. The watcher in Import.vue is not `immediate`, so
 * the flash prop must change *after* mount for it to flip the page to step 2.
 */
async function mountAtStep2() {
    const wrapper = mountImport();
    pageState.props.flash = { uploadedDocument };
    await wrapper.vm.$nextTick();

    return wrapper;
}

function latestBeforeGuard() {
    const call = router.on.mock.calls.filter(([event]) => event === 'before').at(-1);

    return call[1];
}

beforeEach(() => {
    pageState.props.flash = {};
    formPostMock.mockClear();
    formPatchMock.mockClear();
    routerDeleteMock.mockReset();
    router.on.mockClear();
});

afterEach(() => {
    mountedWrappers.forEach((wrapper) => wrapper.unmount());
    mountedWrappers = [];
});

describe('Documents/Import — étape 2 (spec-corrections-documents-ui)', () => {
    it('flips to the step-2 review UI once flash.uploadedDocument lands after a successful upload', async () => {
        const wrapper = mountImport();

        expect(wrapper.text()).not.toContain('Enregistrer');

        pageState.props.flash = { uploadedDocument };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Rapport.pdf');
        expect(wrapper.text()).toContain('Supprimer');
        expect(wrapper.text()).toContain('Enregistrer');
    });

    it('calls router.delete with ?redirect=import when "Supprimer" is clicked and confirmed', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Supprimer');
        await deleteButton.trigger('click');

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock.mock.calls[0][0]).toBe('/documents/5?redirect=import');
    });

    it('sends no request when the "Supprimer" confirmation is cancelled', async () => {
        window.confirm = vi.fn(() => false);
        const wrapper = await mountAtStep2();

        const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Supprimer');
        await deleteButton.trigger('click');

        expect(routerDeleteMock).not.toHaveBeenCalled();
    });

    it('calls the tags-patch endpoint with ?redirect=show when "Enregistrer" is clicked', async () => {
        const wrapper = await mountAtStep2();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        expect(formPatchMock).toHaveBeenCalledTimes(1);
        expect(formPatchMock.mock.calls[0][0]).toBe('/documents/5/tags?redirect=show');
    });
});

// Matrix Test Audit gap (I/O & Edge-Case Matrix, spec-corrections-documents-ui,
// row "Navigation quittée sans Enregistrer/Supprimer"): leaving the page
// while `uploadedDocument` is set and unsaved must trigger `window.confirm`
// via the `router.on('before')` guard — same approach as Editor.spec.js's
// "garde de navigation" tests (`router.on.mock.calls` / `latestBeforeGuard`).
describe('Documents/Import — garde de navigation (spec-corrections-documents-ui, I/O matrix ligne 4)', () => {
    it('confirms navigation once the document is uploaded but not yet saved/deleted (step 2)', async () => {
        window.confirm = vi.fn(() => true);
        await mountAtStep2();

        latestBeforeGuard()({ preventDefault: vi.fn() });

        expect(window.confirm).toHaveBeenCalledTimes(1);
    });

    // Pre-existing behavior (ImportModal.vue's old `close()` guard), kept
    // alongside the new step-2 check above rather than replaced by it.
    it('still confirms while the upload itself is in flight (form.processing)', () => {
        window.confirm = vi.fn(() => true);
        mountImport();

        formInstances.upload.processing = true;

        latestBeforeGuard()({ preventDefault: vi.fn() });

        expect(window.confirm).toHaveBeenCalledTimes(1);
    });

    it('does not add a second confirmation for its own delete-to-restart request (programmatic bypass)', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        // Mirrors Inertia's real dispatch order: `router.delete()` runs the
        // registered 'before' guard synchronously as part of starting the
        // visit, before anything async happens — so invoking it from inside
        // this mock's implementation reproduces the window in which
        // `programmaticNavigation` must still read true.
        routerDeleteMock.mockImplementationOnce(() => {
            latestBeforeGuard()({ preventDefault: vi.fn() });
        });

        const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Supprimer');
        await deleteButton.trigger('click');

        // Exactly one confirm: "Supprimer"'s own deletion dialog. The
        // navigation guard must see the bypass and add no second one.
        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/documents/5?redirect=import', expect.any(Object));
    });

    it('does not trigger the confirmation for its own save-tags request (programmatic bypass)', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        formPatchMock.mockImplementationOnce(() => {
            latestBeforeGuard()({ preventDefault: vi.fn() });
        });

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        // "Enregistrer" has no confirmation dialog of its own, so this stays
        // at zero unless the bypass fails.
        expect(window.confirm).not.toHaveBeenCalled();
        expect(formPatchMock).toHaveBeenCalledWith('/documents/5/tags?redirect=show', expect.any(Object));
    });

    // Code review fix: `programmaticNavigation` used to reset synchronously
    // right after calling `router.delete()`/`tagsForm.patch()`, i.e. before
    // the (async) request actually resolves — so it read `false` for the
    // entire in-flight window and a real navigation attempt during a delete
    // or save fell through to the generic guard. It must now stay bypassed
    // until each request's own `onFinish` fires.
    it('keeps the delete bypass active until the request finishes, then restores the guard', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Supprimer');
        await deleteButton.trigger('click');

        // Request is in flight (onFinish not yet called): still bypassed.
        latestBeforeGuard()({ preventDefault: vi.fn() });
        expect(window.confirm).toHaveBeenCalledTimes(1); // only "Supprimer"'s own dialog

        // The request settles.
        routerDeleteMock.mock.calls[0][1].onFinish();

        // uploadedDocument is still set (onSuccess was never invoked in this
        // test), so the guard must be live again and confirm this time.
        latestBeforeGuard()({ preventDefault: vi.fn() });
        expect(window.confirm).toHaveBeenCalledTimes(2);
    });

    it('keeps the save-tags bypass active until the request finishes, then restores the guard', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        // Request is in flight (onFinish not yet called): still bypassed.
        latestBeforeGuard()({ preventDefault: vi.fn() });
        expect(window.confirm).not.toHaveBeenCalled();

        // The request settles.
        formPatchMock.mock.calls[0][1].onFinish();

        // uploadedDocument is still set, so the guard must be live again.
        latestBeforeGuard()({ preventDefault: vi.fn() });
        expect(window.confirm).toHaveBeenCalledTimes(1);
    });
});

describe('Documents/Import — erreur générique sur "Enregistrer" (code review fix)', () => {
    it('shows a generic saveError when the tags patch fails without a tag_ids validation error', async () => {
        const wrapper = await mountAtStep2();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        // Simulate a non-validation failure (network error, 500, the document
        // having been concurrently deleted, …): no tag_ids error is set.
        formPatchMock.mock.calls[0][1].onError();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Impossible d'enregistrer les tags.");
    });

    it('does not show the generic saveError when the failure is a tag_ids validation error', async () => {
        const wrapper = await mountAtStep2();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        formInstances.tags.errors = { tag_ids: 'Sélection invalide.' };
        formPatchMock.mock.calls[0][1].onError();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain("Impossible d'enregistrer les tags.");
        expect(wrapper.text()).toContain('Sélection invalide.');
    });
});

describe('Documents/Import — avertissement beforeunload (code review fix)', () => {
    it('prevents an actual browser refresh/close while step 2 has an unsaved document', async () => {
        await mountAtStep2();

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('does not warn on beforeunload before any document has been uploaded (step 1)', () => {
        mountImport();

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('does not warn on beforeunload while the document is mid-deletion', async () => {
        window.confirm = vi.fn(() => true);
        const wrapper = await mountAtStep2();

        const deleteButton = wrapper.findAll('button').find((button) => button.text() === 'Supprimer');
        await deleteButton.trigger('click'); // sets isDeleting = true, request left in flight

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('does not warn on beforeunload while tags are being saved', async () => {
        const wrapper = await mountAtStep2();

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');
        formInstances.tags.processing = true;

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });
});
