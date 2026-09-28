import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Show from '@/Pages/Documents/Show.vue';
import TagSelector from '@/Components/TagSelector.vue';
import TagChip from '@/Components/TagChip.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock shape as Editor.spec.js/Index.spec.js/TagSelector.spec.js.
// `router.patch` is a bare `vi.fn()` that is never auto-resolved: the test
// invokes the captured `onSuccess`/`onError`/`onFinish` callbacks itself to
// control exactly when and how the simulated write "finishes".
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

function makeDocument(overrides = {}) {
    return {
        id: 7,
        title: 'Document de test',
        created_at: '2026-01-01T00:00:00Z',
        source: 'imported',
        mime_type: null,
        content_html: null,
        attachments: [],
        tags: [
            { id: 1, name: 'Contrats' },
            { id: 2, name: 'Factures' },
        ],
        ...overrides,
    };
}

function mountShow(overrides = {}) {
    return mount(Show, {
        props: { document: makeDocument(overrides) },
        global: { stubs: globalStubs },
    });
}

function findButton(wrapper, text) {
    return wrapper.findAll('button').find((button) => button.text() === text);
}

function chipNames(wrapper) {
    return wrapper.findAllComponents(TagChip).map((chip) => chip.text());
}

async function enterEditMode(wrapper) {
    await findButton(wrapper, 'Modifier').trigger('click');
    await settle();
}

async function selectSuggestions(wrapper, names) {
    const tagSelector = wrapper.findComponent(TagSelector);
    await tagSelector.find('input').trigger('focus');
    await settle();

    for (const name of names) {
        const option = tagSelector.findAll('li[role="option"]').find((item) => item.text() === name);
        expect(option, `suggestion "${name}" not found`).toBeDefined();
        await option.trigger('mousedown');
        await settle();
    }
}

describe('Documents/Show — consultation', () => {
    it('renders read-only tag chips with no tag input and no "Type" field, actions in the header next to a truncated title', () => {
        const wrapper = mountShow();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Type :');
        expect(chipNames(wrapper)).toEqual(['Contrats', 'Factures']);

        const title = wrapper.find('h1');
        expect(title.classes()).toEqual(expect.arrayContaining(['truncate', 'min-w-0']));
        expect(title.attributes('title')).toBe('Document de test');

        const header = title.element.parentElement;
        const headerButtons = Array.from(header.querySelectorAll('button, a')).map((element) => element.textContent.trim());
        expect(headerButtons).toEqual(['Télécharger', 'Modifier', 'Supprimer']);
    });

    it('lists a created document\'s header actions in order: Modifier (link), exports, Supprimer', () => {
        const wrapper = mountShow({ source: 'created', content_html: '<p>x</p>' });

        const header = wrapper.find('h1').element.parentElement;
        const headerActions = Array.from(header.querySelectorAll('button, a')).map((element) => [
            element.tagName,
            element.textContent.trim(),
        ]);
        expect(headerActions).toEqual([
            ['A', 'Modifier'],
            ['BUTTON', 'Exporter en PDF'],
            ['BUTTON', 'Exporter en Word'],
            ['BUTTON', 'Supprimer'],
        ]);
    });

    it('shows "Aucun tag." when the document has no tags', () => {
        const wrapper = mountShow({ tags: [] });

        expect(wrapper.text()).toContain('Aucun tag.');
        expect(wrapper.findAllComponents(TagChip)).toHaveLength(0);
    });

    it('keeps "Modifier" as a link to the editor for a created document, with no in-page edit mode', async () => {
        const wrapper = mountShow({ source: 'created', content_html: '<p>Bonjour</p>' });

        const editLinks = wrapper.findAll('a').filter((link) => link.text() === 'Modifier');
        expect(editLinks).toHaveLength(1);
        expect(editLinks[0].attributes('href')).toBe('/documents/7/edit');
        expect(findButton(wrapper, 'Modifier')).toBeUndefined();
    });
});

describe('Documents/Show — mode modification des tags', () => {
    beforeEach(() => {
        router.patch.mockClear();
    });

    it('holds several selections locally, then sends them in a single PATCH on "Enregistrer" and returns to consultation', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);

        const tagSelector = wrapper.findComponent(TagSelector);
        expect(tagSelector.exists()).toBe(true);
        expect(tagSelector.find('input').attributes('placeholder')).toBe('Ajouter un tag…');
        expect(findButton(wrapper, 'Supprimer')).toBeUndefined();

        await selectSuggestions(wrapper, ['Comptes rendus', 'Devis']);
        expect(router.patch).not.toHaveBeenCalled();

        await findButton(wrapper, 'Enregistrer').trigger('click');
        await settle();

        expect(router.patch).toHaveBeenCalledTimes(1);
        expect(router.patch.mock.calls[0][0]).toBe('/documents/7/tags');
        expect(router.patch.mock.calls[0][1]).toEqual({ tag_ids: [1, 2, 3, 4] });

        // updateTags() answers with back(): the refreshed document prop
        // arrives, then Inertia calls onSuccess/onFinish.
        await wrapper.setProps({
            document: makeDocument({
                tags: [
                    { id: 1, name: 'Contrats' },
                    { id: 2, name: 'Factures' },
                    { id: 3, name: 'Comptes rendus' },
                    { id: 4, name: 'Devis' },
                ],
            }),
        });
        router.patch.mock.calls[0][2].onSuccess();
        router.patch.mock.calls[0][2].onFinish();
        await settle();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(false);
        expect(chipNames(wrapper)).toEqual(['Contrats', 'Factures', 'Comptes rendus', 'Devis']);
    });

    it('stays in edit mode with the local selection and an alert when the PATCH fails', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);
        await selectSuggestions(wrapper, ['Devis']);

        await findButton(wrapper, 'Enregistrer').trigger('click');
        router.patch.mock.calls[0][2].onError({ tag_ids: 'Le tag sélectionné est invalide.' });
        router.patch.mock.calls[0][2].onFinish();
        await settle();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(true);
        expect(wrapper.findComponent(TagSelector).props('modelValue')).toEqual([1, 2, 4]);
        expect(wrapper.find('[role="alert"]').text()).toBe('Le tag sélectionné est invalide.');
        expect(findButton(wrapper, 'Enregistrer').attributes('disabled')).toBeUndefined();
    });

    it('falls back to a generic message when the error carries no tag_ids entry', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);

        await findButton(wrapper, 'Enregistrer').trigger('click');
        router.patch.mock.calls[0][2].onError({});
        router.patch.mock.calls[0][2].onFinish();
        await settle();

        expect(wrapper.find('[role="alert"]').text()).toBe('Impossible de mettre à jour les tags.');
    });

    it('discards local changes without any PATCH on "Annuler"', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);
        await selectSuggestions(wrapper, ['Devis']);

        await findButton(wrapper, 'Annuler').trigger('click');
        await settle();

        expect(router.patch).not.toHaveBeenCalled();
        expect(wrapper.findComponent(TagSelector).exists()).toBe(false);
        expect(chipNames(wrapper)).toEqual(['Contrats', 'Factures']);

        // Re-entering starts again from document.tags, not the dropped draft.
        await enterEditMode(wrapper);
        expect(wrapper.findComponent(TagSelector).props('modelValue')).toEqual([1, 2]);
    });

    it('never sends a second PATCH on a double click while the first is in flight', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);

        const saveButton = findButton(wrapper, 'Enregistrer');
        // Both clicks dispatched before Vue re-renders `:disabled`, so the
        // in-function guard is what is exercised here.
        saveButton.trigger('click');
        saveButton.trigger('click');
        await settle();

        expect(router.patch).toHaveBeenCalledTimes(1);
        expect(findButton(wrapper, 'Enregistrement…').attributes('disabled')).toBeDefined();
        expect(findButton(wrapper, 'Annuler').attributes('disabled')).toBeDefined();
    });

    it('moves focus into the tag input on entering edit mode, and back onto "Modifier" after cancelling or saving', async () => {
        const wrapper = mount(Show, {
            props: { document: makeDocument() },
            global: { stubs: globalStubs },
            attachTo: document.body,
        });

        try {
            await enterEditMode(wrapper);
            expect(document.activeElement).toBe(wrapper.findComponent(TagSelector).find('input').element);

            await findButton(wrapper, 'Annuler').trigger('click');
            await settle();
            expect(document.activeElement).toBe(findButton(wrapper, 'Modifier').element);

            await enterEditMode(wrapper);
            await findButton(wrapper, 'Enregistrer').trigger('click');
            router.patch.mock.calls[0][2].onSuccess();
            router.patch.mock.calls[0][2].onFinish();
            await settle();
            expect(document.activeElement).toBe(findButton(wrapper, 'Modifier').element);
        } finally {
            wrapper.unmount();
        }
    });

    it('returns to consultation with the new document tags when navigating to another document', async () => {
        const wrapper = mountShow();
        await enterEditMode(wrapper);
        await selectSuggestions(wrapper, ['Devis']);

        await wrapper.setProps({
            document: makeDocument({
                id: 8,
                title: 'Autre document',
                tags: [{ id: 3, name: 'Comptes rendus' }],
            }),
        });
        await settle();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(false);
        expect(chipNames(wrapper)).toEqual(['Comptes rendus']);
        expect(router.patch).not.toHaveBeenCalled();
    });
});
