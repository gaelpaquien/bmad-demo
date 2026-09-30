import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DocumentsLayout from '@/Layouts/DocumentsLayout.vue';

const pageState = vi.hoisted(() => ({ component: 'Documents/Index', props: {} }));

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

function makeDocuments(overrides = {}) {
    return {
        data: [
            {
                id: 42,
                title: 'Contrat prestataire',
                created_at: '2026-01-15T10:30:00Z',
                tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
            },
            { id: 43, title: 'Note interne', created_at: '2026-01-14T10:30:00Z', tags: [] },
        ],
        current_page: 1,
        links: [],
        ...overrides,
    };
}

function mountLayout() {
    return mount(DocumentsLayout, {
        slots: { default: '<p>Contenu du document</p>' },
        global: { stubs: globalStubs },
    });
}

describe('DocumentsLayout', () => {
    beforeEach(() => {
        pageState.props = { documents: makeDocuments() };
    });

    it('renders the document list, then the page content', () => {
        const wrapper = mountLayout();
        const menu = wrapper.find('[data-testid="documents-menu"]');

        expect(menu.find('[data-testid="documents-menu-title"]').text()).toBe('Documents');
        expect(menu.findAll('li a').map((link) => link.attributes('href'))).toEqual(['/documents/42', '/documents/43']);
        expect(wrapper.text()).toContain('Contenu du document');
    });

    it('renders title, tags and date on each row', () => {
        const row = mountLayout().find('a[href="/documents/42"]');

        expect(row.text()).toContain('Contrat prestataire');
        expect(row.text()).toContain('Finance');
        expect(row.text()).toContain('RH');
        expect(row.text()).toContain('2026');
    });

    it('marks only the active document row', () => {
        pageState.props = { documents: makeDocuments(), document: { id: 43 } };
        const links = mountLayout().findAll('[data-testid="documents-menu"] li a');

        expect(links.map((link) => link.attributes('aria-current'))).toEqual([undefined, 'page']);
        expect(links[1].classes()).toContain('bg-primary');
        expect(links[0].classes()).not.toContain('bg-primary');
    });

    it('keeps the current list page in the row links beyond page 1', () => {
        pageState.props = { documents: makeDocuments({ current_page: 3 }) };
        const links = mountLayout().findAll('[data-testid="documents-menu"] li a');

        expect(links.map((link) => link.attributes('href'))).toEqual(['/documents/42?page=3', '/documents/43?page=3']);
    });

    it('shows the empty-library message when there are no documents', () => {
        pageState.props = { documents: makeDocuments({ data: [] }) };
        const wrapper = mountLayout();

        expect(wrapper.text()).toContain("Aucun document pour l'instant.");
        expect(wrapper.findAll('li').length).toBe(0);
    });

    it('renders pagination links, a disabled link never being an anchor', () => {
        pageState.props = {
            documents: makeDocuments({
                links: [
                    { url: null, label: '&laquo; Précédent', active: false },
                    { url: '/documents/42?page=1', label: '1', active: true },
                    { url: '/documents/42?page=2', label: '2', active: false },
                    { url: '/documents/42?page=2', label: 'Suivant &raquo;', active: false },
                ],
            }),
        };
        const nav = mountLayout().find('nav[aria-label="Pagination"]');

        expect(nav.findAll('a').length).toBe(3);
        expect(nav.find('a[href="/documents/42?page=2"]').exists()).toBe(true);
        expect(nav.text()).toContain('Précédent');
    });
});
