import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ConfigurationLayout from '@/Layouts/ConfigurationLayout.vue';

const pageState = vi.hoisted(() => ({ component: 'Documents/Configuration', props: {} }));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => pageState,
    router: { reload: vi.fn(), get: vi.fn(), on: vi.fn(() => () => {}) },
}));

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

describe('ConfigurationLayout', () => {
    it('renders the secondary menu with Tags, MCP and Thèmes, then the page content', () => {
        const wrapper = mount(ConfigurationLayout, {
            slots: { default: '<p>Contenu de page</p>' },
            global: { stubs: globalStubs },
        });
        const links = wrapper.findAll('[data-testid="configuration-menu"] a');

        expect(links.map((link) => link.text())).toEqual(['Tags', 'MCP', 'Thèmes']);
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/configuration/tags',
            '/configuration/mcp',
            '/configuration/themes',
        ]);
        expect(wrapper.text()).toContain('Contenu de page');
    });

    it.each([
        ['Documents/Configuration', 0],
        ['Documents/Mcp', 1],
        ['Documents/Themes', 2],
    ])('marks only the entry of %s as active', (component, activeIndex) => {
        pageState.component = component;
        const wrapper = mount(ConfigurationLayout, { global: { stubs: globalStubs } });
        const links = wrapper.findAll('[data-testid="configuration-menu"] a');

        links.forEach((link, index) => {
            expect(link.attributes('aria-current')).toBe(index === activeIndex ? 'page' : undefined);
            expect(link.classes().includes('bg-primary')).toBe(index === activeIndex);
        });
    });
});
