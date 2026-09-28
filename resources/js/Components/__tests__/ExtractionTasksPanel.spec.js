import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import ExtractionTasksPanel from '@/Components/ExtractionTasksPanel.vue';

// Same self-contained `@inertiajs/vue3` mock shape as TagSelector.spec.js —
// the panel only reads the shared `pendingExtractions` prop and polls it
// through `router.reload()`.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { pendingExtractions: [] } });

    return {
        usePage: () => pageState,
        router: { reload: vi.fn() },
    };
});

const pageState = usePage();

describe('ExtractionTasksPanel', () => {
    afterEach(() => {
        pageState.props.pendingExtractions = [];
    });

    it('lists an attachment extraction alongside documents, naming the document it belongs to', async () => {
        pageState.props.pendingExtractions = [
            { type: 'document', id: 1, title: 'Contrat.pdf', document_title: null, extraction_status: 'processing' },
            { type: 'attachment', id: 1, title: 'Annexe.docx', document_title: 'Contrat.pdf', extraction_status: 'pending' },
        ];

        const wrapper = mount(ExtractionTasksPanel);

        expect(wrapper.text()).toContain('Extraction de texte : 2 en cours');

        await wrapper.find('button').trigger('click');

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].text()).not.toContain('Pièce jointe');
        expect(items[1].text()).toContain('Annexe.docx');
        expect(items[1].text()).toContain('Pièce jointe de « Contrat.pdf »');

        wrapper.unmount();
    });
});
