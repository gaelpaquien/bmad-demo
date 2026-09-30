import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Index from '@/Pages/Documents/Index.vue';

// The document list lives in DocumentsLayout (spec-refonte-layout-documents,
// covered by DocumentsLayout.spec.js): the layout is stubbed here, this file
// only exercises what Index.vue itself renders — the invitation shown while
// no document is active.
const globalStubs = {
    DocumentsLayout: { template: '<div><slot /></div>' },
};

describe('Documents/Index', () => {
    it('invites the user to pick a document from the list', () => {
        const wrapper = mount(Index, { global: { stubs: globalStubs } });

        expect(wrapper.find('[data-testid="documents-placeholder"]').text())
            .toBe('Sélectionnez un document dans la liste pour le consulter.');
    });
});
