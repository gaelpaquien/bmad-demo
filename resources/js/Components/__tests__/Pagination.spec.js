import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Pagination from '@/Components/Pagination.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

const links = [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/?page=1', label: '1', active: true },
    { url: '/?page=2', label: '2', active: false },
    { url: '/?page=2', label: 'Next &raquo;', active: false },
];

describe('Pagination', () => {
    it('translates previous/next into French with a chevron icon each', () => {
        const items = mount(Pagination, { props: { links } }).find('nav').findAll(':scope > *');

        expect(items[0].text()).toBe('Précédent');
        expect(items[0].attributes('aria-label')).toBe('Page précédente');
        expect(items[0].find('svg').exists()).toBe(true);
        expect(items[3].text()).toBe('Suivant');
        expect(items[3].attributes('aria-label')).toBe('Page suivante');
        expect(items[3].find('svg').exists()).toBe(true);
    });

    it('renders a disabled previous as a non-link and styles the active page', () => {
        const items = mount(Pagination, { props: { links } }).find('nav').findAll(':scope > *');

        expect(items[0].element.tagName).toBe('SPAN');
        expect(items[1].attributes('aria-current')).toBe('page');
        expect(items[1].classes()).toContain('bg-primary');
        expect(items[2].element.tagName).toBe('A');
        expect(items[2].classes()).toContain('border');
    });

    it('collapses to previous, "Page X sur Y" and next in compact mode', () => {
        const wrapper = mount(Pagination, {
            props: {
                compact: true,
                links: [
                    { url: '/?page=1', label: '&laquo; Previous', active: false },
                    { url: '/?page=1', label: '1', active: false },
                    { url: '/?page=2', label: '2', active: true },
                    { url: null, label: '...', active: false },
                    { url: '/?page=9', label: '9', active: false },
                    { url: '/?page=3', label: 'Next &raquo;', active: false },
                ],
            },
        });
        const items = wrapper.find('nav').findAll(':scope > *');

        expect(items.length).toBe(3);
        expect(items[0].attributes('aria-label')).toBe('Page précédente');
        expect(items[0].find('.sr-only').text()).toBe('Précédent');
        expect(items[1].text()).toBe('Page 2 sur 9');
        expect(items[2].attributes('aria-label')).toBe('Page suivante');
        expect(items[2].attributes('href')).toBe('/?page=3');
    });

    it.each([
        [{ total: 27, from: 4, to: 6 }, '4–6 sur 27 documents'],
        [{ total: 1, from: 1, to: 1 }, '1 document'],
        [{ total: 3, from: 1, to: 3 }, '3 documents'],
    ])('shows the count line for %o', (totals, expected) => {
        const wrapper = mount(Pagination, { props: { links, ...totals } });

        expect(wrapper.find('[data-testid="pagination-count"]').text()).toBe(expected);
    });

    it('shows no count line without totals', () => {
        expect(mount(Pagination, { props: { links } }).find('[data-testid="pagination-count"]').exists()).toBe(false);
    });

    it('renders nothing when there is a single page', () => {
        const wrapper = mount(Pagination, {
            props: { links: [links[0], links[1], { url: null, label: 'Next &raquo;', active: false }] },
        });

        expect(wrapper.find('nav').exists()).toBe(false);
    });
});
