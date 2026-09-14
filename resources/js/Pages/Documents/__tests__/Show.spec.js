import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Show from '@/Pages/Documents/Show.vue';
import TagSelector from '@/Components/TagSelector.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock shape as Editor.spec.js/Index.spec.js/TagSelector.spec.js.
// `router.patch` is a bare `vi.fn()` that is never auto-resolved: Show.vue's
// own `onFinish` callback (passed to `router.patch`'s options) only flips
// `isSavingTags` back to `false` once *we* invoke it from a captured call,
// which lets this file control exactly when the simulated write "finishes".
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({
        props: {
            tags: [
                { id: 1, name: 'Contrats' },
                { id: 2, name: 'Factures' },
                { id: 3, name: 'Comptes rendus' },
                { id: 4, name: 'Devis' },
            ],
        },
    });

    return {
        usePage: () => pageState,
        router: {
            patch: vi.fn(),
            delete: vi.fn(),
        },
        Link: {
            name: 'Link',
            props: ['href'],
            template: '<a :href="href"><slot /></a>',
        },
    };
});

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

async function settle() {
    await nextTick();
    await nextTick();
}

function mountShow(overrides = {}) {
    return mount(Show, {
        props: {
            document: {
                id: 7,
                title: 'Document de test',
                created_at: '2026-01-01T00:00:00Z',
                source: 'imported',
                mime_type: null,
                content_html: null,
                attachments: [],
                // 2 existing tags, 2 fewer than the shared `tags` list above
                // (id 3 and 4 left unselected) — enough headroom for both
                // selections exercised below.
                tags: [
                    { id: 1, name: 'Contrats' },
                    { id: 2, name: 'Factures' },
                ],
                ...overrides,
            },
        },
        global: { stubs: globalStubs },
    });
}

// spec-fix-multi-tag-selection, review_loop_iteration 1 (second pass):
// TagSelector.spec.js only ever exercises the `disabled` prop contract in
// isolation via direct `setProps`, simulating what Show.vue does. No test
// mounts the real Show.vue -> TagSelector wiring end-to-end. This file
// proves the real `onTagsChange`/`isSavingTags` wiring — not just the
// isolated component contract — keeps the suggestion list usable across an
// immediate-write PATCH, with no reclick/refocus required.
describe('Documents/Show — sélection de tags en écriture immédiate (spec-fix-multi-tag-selection)', () => {
    beforeEach(() => {
        router.patch.mockClear();
    });

    it('persists a tag selection via PATCH /documents/{id}/tags with the accumulated tag_ids, keeps the list open (inertly) while that write is in flight, and lets a second selection through without reclick/refocus once it finishes', async () => {
        const wrapper = mountShow();

        const tagSelector = wrapper.findComponent(TagSelector);
        expect(tagSelector.exists()).toBe(true);

        await tagSelector.find('input').trigger('focus');
        await settle();

        // Document already has tags 1 and 2 — 3 (Comptes rendus) and 4
        // (Devis) are the two unselected suggestions left available.
        let options = tagSelector.findAll('li[role="option"]');
        expect(options.map((option) => option.text())).toEqual(['Comptes rendus', 'Devis']);

        await options[0].trigger('mousedown');
        await settle();

        // Real Show.vue wiring: onTagsChange -> router.patch with the
        // accumulated tag_ids, not a call the isolated TagSelector.spec.js
        // contract test can ever exercise (it never mounts Show.vue).
        expect(router.patch).toHaveBeenCalledTimes(1);
        expect(router.patch.mock.calls[0][0]).toBe('/documents/7/tags');
        expect(router.patch.mock.calls[0][1]).toEqual({ tag_ids: [1, 2, 3] });

        // Show.vue set `isSavingTags = true` synchronously right after that
        // first selection and never called its own `onFinish` yet (we
        // haven't invoked the mocked call's callback) — the write is still
        // "in flight". Per the frozen invariant, the list stays open but
        // fully inert while disabled: no reclick/refocus happened, yet a
        // further selection attempt must not produce a second PATCH.
        expect(tagSelector.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
        expect(tagSelector.find('input').attributes('readonly')).toBeDefined();

        options = tagSelector.findAll('li[role="option"]');
        expect(options.map((option) => option.text())).toEqual(['Devis']);
        await options[0].trigger('mousedown');
        await settle();

        expect(router.patch).toHaveBeenCalledTimes(1);

        // The PATCH now finishes — Show.vue's own `onFinish` callback (the
        // third argument's `onFinish`, captured from the first real
        // `router.patch` call above) flips `isSavingTags` back to `false`.
        router.patch.mock.calls[0][2].onFinish();
        await settle();

        expect(tagSelector.find('input').attributes('readonly')).toBeUndefined();
        // Still open, still showing Devis — no reclick/refocus was needed
        // to see it again once interactive.
        expect(tagSelector.find('input[role="combobox"]').attributes('aria-expanded')).toBe('true');
        options = tagSelector.findAll('li[role="option"]');
        expect(options.map((option) => option.text())).toEqual(['Devis']);

        await options[0].trigger('mousedown');
        await settle();

        expect(router.patch).toHaveBeenCalledTimes(2);
        expect(router.patch.mock.calls[1][0]).toBe('/documents/7/tags');
        expect(router.patch.mock.calls[1][1]).toEqual({ tag_ids: [1, 2, 3, 4] });
    });
});
