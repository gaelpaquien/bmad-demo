import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Search from '@/Pages/Documents/Search.vue';

// `@inertiajs/vue3` is mocked rather than imported for real (same reusable
// shape as Index.spec.js/Sidebar.spec.js): `Link` is stubbed as a plain
// anchor, `usePage()` returns a fixed `tags` prop set, and `router` is a
// no-op stub — no navigation is actually triggered by the I/O-matrix lines
// this file covers (neutral empty state, no-results message, document-row
// rendering).
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({
        props: {
            tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
        },
    }),
    router: { get: vi.fn(), on: vi.fn(() => () => {}) },
}));

// AppLayout is stubbed: this file exercises Search.vue's own template
// (neutral state, no-results messaging, document-row markup), not the
// layout chrome.
const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

// `documents` is a Laravel paginator (spec-recherche-bornes-pagination).
function pageOf(data, { total = data.length, links = [] } = {}) {
    return { data, links, total };
}

function emptyPage() {
    return pageOf([]);
}

function documentRow(id) {
    return { id, title: `Doc ${id}`, mime_type: 'application/pdf', source: 'imported', created_at: '2026-01-15T10:30:00Z', tags: [] };
}

describe('Documents/Search', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    // AC2: no term typed yet ⇒ neutral state, no result rows, no
    // "no results" message either (Recherche never shows the whole
    // library) — only an invitation to type a keyword or pick a tag.
    it('shows the idle invitation, no rows and no no-results message when search is empty', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.findAll('[data-testid="search-results"] li').length).toBe(0);
        expect(wrapper.text()).not.toContain('Aucun document ne correspond');
        expect(wrapper.find('[data-testid="search-idle"]').exists()).toBe(true);
    });

    // I/O matrix "Terme sans résultat".
    it('shows the no-results message when a search term is active but matches nothing', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: 'xyz123', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Aucun document ne correspond à votre recherche.');
    });

    // I/O matrix "Ligne de document" — badge + title + tags + date, whole
    // row clickable to the Document Detail — same shape as Index.vue's row.
    it('renders a document-row with the type badge, title, its tags and the date, the whole row linking to the document', () => {
        const wrapper = mount(Search, {
            props: {
                documents: pageOf([
                    {
                        id: 42,
                        title: 'Contrat prestataire',
                        mime_type: 'application/pdf',
                        source: 'imported',
                        created_at: '2026-01-15T10:30:00Z',
                        tags: [{ id: 1, name: 'Finance' }, { id: 2, name: 'RH' }],
                    },
                ]),
                search: 'contrat',
                tagFilters: [],
            },
            global: { stubs: globalStubs },
        });

        const row = wrapper.find('a[href="/documents/42"]');

        expect(row.exists()).toBe(true);
        expect(row.text()).toContain('PDF');
        expect(row.text()).toContain('Contrat prestataire');
        expect(row.text()).toContain('Finance');
        expect(row.text()).toContain('RH');
        expect(row.text()).toContain('2026');
    });

    // AC2: focus lands on the search field as soon as the surface loads.
    it('focuses the search input on mount', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        expect(document.activeElement).toBe(wrapper.find('input[type="search"]').element);

        wrapper.unmount();
    });

    // I/O matrix "Tag sans document" (spec-recherche-aide-et-filtre-tag-seul):
    // a tag filter alone now lists that tag's documents, so an empty result
    // shows the no-results message rather than the neutral state.
    it('shows the no-results message when only a tag filter is active and it matches nothing', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [1] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.findAll('[data-testid="search-results"] li').length).toBe(0);
        expect(wrapper.text()).toContain('Aucun document ne correspond à votre recherche.');
    });

    // I/O matrix "Tags sélectionnés" (spec-recherche-chips-tag-sans-doublon):
    // each selected tag shows once, as the lime active-filter chip —
    // TagSelector's own grey chip is hidden on this page.
    it('shows each selected tag once, as an active-filter chip under the field', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [1, 2] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).toContain('Filtres par tag actifs');
        expect(wrapper.findAll('button[aria-label^="Retirer le filtre tag"]').map((chip) => chip.attributes('aria-label')))
            .toEqual(['Retirer le filtre tag Finance', 'Retirer le filtre tag RH']);
        expect(wrapper.findAll('button[aria-label^="Retirer le tag"]')).toHaveLength(0);
    });

    it('removes the tag and searches again right away when its active-filter chip is clicked', async () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [1, 2] },
            global: { stubs: globalStubs },
        });

        await wrapper.find('button[aria-label="Retirer le filtre tag Finance"]').trigger('click');

        expect(wrapper.find('button[aria-label="Retirer le filtre tag Finance"]').exists()).toBe(false);
        expect(router.get).toHaveBeenLastCalledWith('/recherche', { tag_id: [2] }, expect.any(Object));
    });

    // I/O matrix "Aucun tag": neither the label nor any chip.
    it('shows no active-filter row when no tag is selected', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).not.toContain('Filtres par tag actifs');
        expect(wrapper.findAll('button[aria-label^="Retirer le filtre tag"]')).toHaveLength(0);
    });

    // Retro Epic 3, item 9: a filter-triggered partial reload must also
    // refresh the shared `tags` prop, or a tag renamed/deleted elsewhere
    // (e.g. via Configuration) stays stale in Recherche's own TagSelector.
    it('includes tags in the partial reload once the debounced search fires', async () => {
        vi.useFakeTimers();

        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        await wrapper.find('input[type="search"]').setValue('contrat');
        vi.advanceTimersByTime(500);

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get.mock.calls[0][2].only).toContain('tags');
    });

    // The keyword-limit notice must follow each live search, not only a full load.
    it('includes keywordLimitReached in the partial reload', async () => {
        vi.useFakeTimers();
        router.get.mockClear();

        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        await wrapper.find('input[type="search"]').setValue('contrat');
        vi.advanceTimersByTime(500);

        expect(router.get.mock.calls.at(-1)[2].only).toContain('keywordLimitReached');
    });
});

// Bounds and pagination (spec-recherche-bornes-pagination).
describe('Documents/Search bounds and pagination', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the total number of documents found, singular or plural', () => {
        const single = mount(Search, {
            props: { documents: pageOf([documentRow(1)]), search: 'contrat', tagFilters: [] },
            global: { stubs: globalStubs },
        });
        const several = mount(Search, {
            props: { documents: pageOf([documentRow(1), documentRow(2)], { total: 25 }), search: 'contrat', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(single.find('[data-testid="search-count"]').text()).toBe('1 document trouvé');
        expect(several.find('[data-testid="search-count"]').text()).toBe('25 documents trouvés');
    });

    it('renders the page links under the results', () => {
        const wrapper = mount(Search, {
            props: {
                documents: pageOf([documentRow(1)], {
                    total: 25,
                    links: [
                        { url: null, label: '&laquo; Précédent', active: false },
                        { url: '/recherche?search=contrat&page=1', label: '1', active: true },
                        { url: '/recherche?search=contrat&page=2', label: '2', active: false },
                        { url: '/recherche?search=contrat&page=3', label: '3', active: false },
                        { url: '/recherche?search=contrat&page=2', label: 'Suivant &raquo;', active: false },
                    ],
                }),
                search: 'contrat',
                tagFilters: [],
            },
            global: { stubs: globalStubs },
        });

        const nav = wrapper.find('nav[aria-label="Pagination"]');

        expect(nav.exists()).toBe(true);
        expect(nav.find('a[href="/recherche?search=contrat&page=3"]').exists()).toBe(true);
    });

    // AC: a new search from page 2 never sends `page`, so it shows page 1.
    it('starts a new search from page 1 even when a later page is displayed', async () => {
        vi.useFakeTimers();
        router.get.mockClear();

        const wrapper = mount(Search, {
            props: { documents: pageOf([documentRow(11)], { total: 25 }), search: 'contrat', tagFilters: [1] },
            global: { stubs: globalStubs },
        });

        await wrapper.find('input[type="search"]').setValue('facture');
        vi.advanceTimersByTime(500);

        expect(router.get).toHaveBeenLastCalledWith('/recherche', { search: 'facture', tag_id: [1] }, expect.any(Object));
    });

    it('offers the page links instead of "no document" on a page beyond the last one', () => {
        const wrapper = mount(Search, {
            props: {
                documents: pageOf([], {
                    total: 25,
                    links: [
                        { url: '/recherche?search=contrat&page=98', label: '&laquo; Précédent', active: false },
                        { url: '/recherche?search=contrat&page=1', label: '1', active: false },
                        { url: '/recherche?search=contrat&page=2', label: '2', active: false },
                        { url: null, label: 'Suivant &raquo;', active: false },
                    ],
                }),
                search: 'contrat',
                tagFilters: [],
            },
            global: { stubs: globalStubs },
        });

        expect(wrapper.text()).not.toContain('Aucun document ne correspond');
        expect(wrapper.find('[data-testid="search-page-out-of-range"]').text()).toContain('25 documents trouvés');
        expect(wrapper.find('a[href="/recherche?search=contrat&page=1"]').exists()).toBe(true);
    });

    it('limits the search field to 255 characters', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.find('input[type="search"]').attributes('maxlength')).toBe('255');
    });

    it('explains under the field, linked to it, that only the first 20 keywords are used', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: 'm1 m2', tagFilters: [], keywordLimitReached: true },
            global: { stubs: globalStubs },
        });

        const message = wrapper.find('[data-testid="search-keyword-limit"]');

        expect(message.text()).toBe('Seuls les 20 premiers mots-clés sont pris en compte.');
        expect(wrapper.find('input[type="search"]').attributes('aria-describedby')).toBe(message.attributes('id'));
    });

    it('shows no keyword-limit message when every keyword is used', () => {
        const wrapper = mount(Search, {
            props: { documents: emptyPage(), search: 'm1 m2', tagFilters: [] },
            global: { stubs: globalStubs },
        });

        expect(wrapper.find('[data-testid="search-keyword-limit"]').exists()).toBe(false);
        expect(wrapper.find('input[type="search"]').attributes('aria-describedby')).toBeUndefined();
    });
});

// Typing delay and loading feedback: the debounced search waits 500ms
// (enough for a slower typist pausing mid-word), Enter skips the wait, and
// a spinner appears only once a search has been pending for 300ms. The
// spied `router.get`'s `onStart`/`onFinish` options are invoked by hand to
// simulate a request's lifecycle.
describe('Documents/Search typing delay and spinner', () => {
    let wrapper;

    function mountSearch() {
        wrapper = mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        return wrapper;
    }

    function lastVisitOptions() {
        return router.get.mock.calls.at(-1)[2];
    }

    async function searchNow(term) {
        const input = wrapper.find('input[type="search"]');

        await input.setValue(term);
        await input.trigger('keydown', { key: 'Enter' });
    }

    beforeEach(() => {
        vi.useFakeTimers();
        router.get.mockClear();
    });

    afterEach(() => {
        wrapper?.unmount();
        vi.useRealTimers();
    });

    it('waits 500ms after the last keystroke before searching', async () => {
        const input = mountSearch().find('input[type="search"]');

        await input.setValue('cub');
        vi.advanceTimersByTime(400);
        await input.setValue('cubiscan');
        vi.advanceTimersByTime(400);

        expect(router.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(100);

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(router.get.mock.calls[0][1]).toEqual({ search: 'cubiscan' });
    });

    it('searches immediately on Enter, without a second search once the delay elapses', async () => {
        mountSearch();

        await searchNow('cubiscan');

        expect(router.get).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(1000);

        expect(router.get).toHaveBeenCalledTimes(1);
    });

    it.each([
        ['flagged as composing', { isComposing: true }],
        ['fired after compositionend (Safari)', { isComposing: false, keyCode: 229 }],
    ])('does not search on the Enter that confirms an IME composition, %s', async (_, eventInit) => {
        const input = mountSearch().find('input[type="search"]');

        await input.setValue('été');
        await input.trigger('keydown', { key: 'Enter', ...eventInit });

        expect(router.get).not.toHaveBeenCalled();
    });

    it('shows the spinner only once a search has been pending for 300ms, and hides it when it finishes', async () => {
        mountSearch();

        await searchNow('cubiscan');
        lastVisitOptions().onStart();

        vi.advanceTimersByTime(299);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="search-loading"]').exists()).toBe(false);

        vi.advanceTimersByTime(1);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="search-loading"]').text()).toContain('Recherche en cours');

        lastVisitOptions().onFinish();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="search-loading"]').exists()).toBe(false);
    });

    it('replaces the previous results with the in-progress message while the search is pending', async () => {
        wrapper = mount(Search, {
            props: {
                documents: pageOf([{ id: 42, title: 'Contrat prestataire', mime_type: 'application/pdf', source: 'imported', created_at: '2026-01-15T10:30:00Z', tags: [] }]),
                search: 'contrat',
                tagFilters: [],
            },
            attachTo: document.body,
            global: { stubs: globalStubs },
        });

        await searchNow('cubiscan');
        lastVisitOptions().onStart();
        vi.advanceTimersByTime(300);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('a[href="/documents/42"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="search-loading"]').text()).toBe('Recherche en cours…');
    });

    it('never shows the spinner for a search answered in under 300ms', async () => {
        mountSearch();

        await searchNow('cubiscan');
        lastVisitOptions().onStart();
        vi.advanceTimersByTime(200);
        lastVisitOptions().onFinish();

        vi.advanceTimersByTime(500);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="search-loading"]').exists()).toBe(false);
    });

    it('keeps the spinner while a newer search is pending, when the older cancelled one finishes', async () => {
        mountSearch();

        await searchNow('cubiscan');
        const olderVisit = lastVisitOptions();
        olderVisit.onStart();

        await searchNow('cubiscan speed');
        const newerVisit = lastVisitOptions();
        newerVisit.onStart();

        olderVisit.onFinish();
        vi.advanceTimersByTime(300);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="search-loading"]').exists()).toBe(true);

        newerVisit.onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="search-loading"]').exists()).toBe(false);
    });
});

// The help box starts folded so the search field stays near the top; the
// reader's choice is remembered across visits, like the sidebar's.
describe('Documents/Search help box', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    function mountSearch() {
        return mount(Search, {
            props: { documents: emptyPage(), search: '', tagFilters: [] },
            global: { stubs: globalStubs },
        });
    }

    function helpToggle(wrapper) {
        return wrapper.find('button[aria-controls="search-help"]');
    }

    it('starts folded, and its toggle unfolds it', async () => {
        const wrapper = mountSearch();

        expect(helpToggle(wrapper).attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('#search-help').attributes('inert')).toBeDefined();
        expect(wrapper.find('#search-help').attributes('aria-hidden')).toBe('true');

        await helpToggle(wrapper).trigger('click');

        expect(helpToggle(wrapper).attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('#search-help').attributes('inert')).toBeUndefined();
        expect(wrapper.find('#search-help').attributes('aria-hidden')).toBeUndefined();
        expect(wrapper.find('#search-help').text()).toContain('Opérateurs');
    });

    it('remembers an unfolded help box on the next visit', async () => {
        await helpToggle(mountSearch()).trigger('click');

        const nextVisit = mountSearch();

        expect(helpToggle(nextVisit).attributes('aria-expanded')).toBe('true');
        expect(nextVisit.find('#search-help').attributes('inert')).toBeUndefined();
    });
});
