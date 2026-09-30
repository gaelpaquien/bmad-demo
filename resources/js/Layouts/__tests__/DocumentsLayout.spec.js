import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DocumentsLayout from '@/Layouts/DocumentsLayout.vue';

const pageState = vi.hoisted(() => ({ component: 'Documents/Index', props: {} }));
const routerHandlers = vi.hoisted(() => ({}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => pageState,
    router: {
        on: (event, handler) => {
            routerHandlers[event] = handler;

            return () => {};
        },
    },
}));

const visitTo = (path, only = []) => ({ detail: { visit: { url: new URL(path, 'http://localhost'), only } } });

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
        total: 27,
        from: 1,
        to: 2,
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

    it('lays each row out on three lines: bold title, tags, then date (no tag line without tags)', () => {
        const wrapper = mountLayout();
        const tagged = wrapper.find('a[href="/documents/42"]');
        const untagged = wrapper.find('a[href="/documents/43"]');

        expect(tagged.findAll(':scope > *').map((line) => line.attributes('data-testid') ?? 'title'))
            .toEqual(['title', 'documents-row-tags', 'documents-row-date']);
        expect(tagged.find(':scope > span').classes()).toContain('font-semibold');
        expect(tagged.find('[data-testid="documents-row-tags"]').classes()).not.toContain('font-semibold');
        expect(tagged.classes()).not.toContain('font-semibold');
        expect(tagged.find('[data-testid="documents-row-tags"]').text()).toContain('Finance');
        expect(untagged.find('[data-testid="documents-row-tags"]').exists()).toBe(false);
    });

    it('shows at most two tags per row, then a "+X" chip counting the others and naming them in its tooltip', () => {
        pageState.props = {
            documents: makeDocuments({
                data: [
                    {
                        id: 44,
                        title: 'Très étiqueté',
                        created_at: '2026-01-13T10:30:00Z',
                        tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }, { id: 3, name: 'Juridique' }, { id: 4, name: 'Qualité' }],
                    },
                    { id: 45, title: 'Deux tags', created_at: '2026-01-12T10:30:00Z', tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }] },
                ],
            }),
        };
        const wrapper = mountLayout();
        const many = wrapper.find('a[href="/documents/44"]').find('[data-testid="documents-row-tags"]');
        const two = wrapper.find('a[href="/documents/45"]').find('[data-testid="documents-row-tags"]');

        expect(many.text()).toContain('Finance');
        expect(many.text()).toContain('RH');
        expect(many.text()).not.toContain('Juridique');
        expect(many.find('[data-testid="documents-row-tags-more"]').text()).toBe('+2');
        expect(many.find('[data-testid="documents-row-tags-more"]').attributes('title')).toBe('Juridique, Qualité');
        expect(two.find('[data-testid="documents-row-tags-more"]').exists()).toBe(false);
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

    it('keeps the skeleton at least 400 ms even when the response is instant, then restores the rows', async () => {
        vi.useFakeTimers();

        try {
            pageState.props = { documents: makeDocuments({ per_page: 3 }) };
            const wrapper = mountLayout();

            const visit = visitTo('/?page=2');
            routerHandlers.start(visit);
            await wrapper.vm.$nextTick();

            expect(wrapper.find('[data-testid="documents-skeleton"]').findAll('li').length).toBe(3);
            wrapper.find('[data-testid="documents-skeleton"]').findAll('li').forEach((row) => {
                expect(row.findAll(':scope > div').length).toBe(3);
            });
            expect(wrapper.findAll('[data-testid="documents-menu"] li a').length).toBe(0);

            routerHandlers.finish(visit);
            await vi.advanceTimersByTimeAsync(399);
            expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(true);

            await vi.advanceTimersByTimeAsync(1);
            expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(false);
            expect(wrapper.findAll('[data-testid="documents-menu"] li a').length).toBe(2);
        } finally {
            vi.useRealTimers();
        }
    });

    it('does not extend the skeleton when the response already took longer than the minimum', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountLayout();

            const visit = visitTo('/?page=2');
            routerHandlers.start(visit);
            await vi.advanceTimersByTimeAsync(700);
            routerHandlers.finish(visit);
            await vi.advanceTimersByTimeAsync(0);

            expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(false);
        } finally {
            vi.useRealTimers();
        }
    });

    it('never shows the skeleton for a partial reload (extraction polling) nor for a URL without page', async () => {
        const wrapper = mountLayout();

        routerHandlers.start(visitTo('/documents/42?page=2', ['pendingExtractions']));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(false);

        routerHandlers.start(visitTo('/documents/42'));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(false);
    });

    it('is not cut short by the end of another visit', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountLayout();
            const pageVisit = visitTo('/?page=2');

            routerHandlers.start(pageVisit);
            routerHandlers.finish(visitTo('/?page=1', ['pendingExtractions']));
            await vi.advanceTimersByTimeAsync(1000);

            expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(true);
        } finally {
            vi.useRealTimers();
        }
    });

    it('keeps the rows while opening a document from the current list page', async () => {
        pageState.props = { documents: makeDocuments({ current_page: 2 }) };
        const wrapper = mountLayout();

        routerHandlers.start(visitTo('/documents/43?page=2'));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="documents-skeleton"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="documents-menu"] li a').length).toBe(2);
    });

    it('shows the empty-library message when there are no documents', () => {
        pageState.props = { documents: makeDocuments({ data: [] }) };
        const wrapper = mountLayout();

        expect(wrapper.text()).toContain("Aucun document pour l'instant.");
        expect(wrapper.findAll('li').length).toBe(0);
    });

    it('renders a compact pagination (previous, page indicator, next), a disabled link never being an anchor', () => {
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

        expect(nav.findAll('a').length).toBe(1);
        expect(nav.find('a[href="/documents/42?page=2"]').exists()).toBe(true);
        expect(nav.text()).toContain('Précédent');
        expect(nav.find('[data-testid="pagination-status"]').text()).toBe('Page 1 sur 2');
    });

    it('shows the shown range out of the total, with a light separator above the pagination', () => {
        pageState.props = {
            documents: makeDocuments({
                total: 27,
                from: 4,
                to: 5,
                links: [
                    { url: '/?page=1', label: '&laquo; Previous', active: false },
                    { url: '/?page=1', label: '1', active: false },
                    { url: null, label: '2', active: true },
                    { url: '/?page=3', label: 'Next &raquo;', active: false },
                ],
            }),
        };
        const wrapper = mountLayout();

        expect(wrapper.find('[data-testid="pagination-count"]').text()).toBe('4–5 sur 27 documents');
        expect(wrapper.find('[data-testid="documents-pagination-separator"]').exists()).toBe(true);
    });

    it('shows no pagination separator when there is a single page', () => {
        pageState.props = { documents: makeDocuments({ links: [] }) };

        expect(mountLayout().find('[data-testid="documents-pagination-separator"]').exists()).toBe(false);
    });
});
