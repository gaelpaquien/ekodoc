import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppLayout from '@/Layouts/AppLayout.vue';
import Sidebar from '@/Components/Sidebar.vue';

// No test covered AppLayout.vue before this story — this is a guard-rail
// against a future regression where the layout would silently stop
// rendering the sidebar (Tasks & Acceptance, spec-3-2). `@inertiajs/vue3` is
// mocked (Link stubbed, usePage/router no-ops) so Sidebar can mount for
// real without a full Inertia app — same reusable mock shape as
// Sidebar.spec.js/Index.spec.js. ExtractionTasksPanel is stubbed: its own
// behaviour is out of scope here, only that AppLayout mounts Sidebar.
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({ props: {} }),
    router: { reload: vi.fn(), get: vi.fn(), on: vi.fn(() => () => {}) },
}));

describe('AppLayout', () => {
    it('mounts Sidebar', () => {
        const wrapper = mount(AppLayout, {
            global: {
                stubs: { ExtractionTasksPanel: true },
            },
        });

        expect(wrapper.findComponent(Sidebar).exists()).toBe(true);
    });

    it('renders the default slot content inside <main>', () => {
        const wrapper = mount(AppLayout, {
            slots: { default: '<p>Contenu de page</p>' },
            global: {
                stubs: { ExtractionTasksPanel: true },
            },
        });

        expect(wrapper.find('main').text()).toContain('Contenu de page');
    });
});
