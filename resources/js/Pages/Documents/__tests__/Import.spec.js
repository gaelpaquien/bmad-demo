import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Import from '@/Pages/Documents/Import.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import TagSelector from '@/Components/TagSelector.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as Editor.spec.js. The single `useForm()` instance
// is stashed on `formInstance` so a test can flip `processing`/`errors`
// directly — real Inertia sets them internally during a request and this
// mock never simulates the request lifecycle.
const { formPostMock, formState } = vi.hoisted(() => ({
    formPostMock: vi.fn(),
    formState: { instance: null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            on: vi.fn(() => vi.fn()),
            post: vi.fn(),
            delete: vi.fn(),
        },
        useForm: (initial) => {
            const instance = reactive({
                ...initial,
                processing: false,
                errors: {},
                post: formPostMock,
                clearErrors: vi.fn(),
            });
            formState.instance = instance;

            return instance;
        },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    };
});

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    TagSelector: true,
    AttachmentsPanel: true,
};

let mountedWrappers = [];

function mountImport() {
    const wrapper = mount(Import, { global: { stubs: globalStubs } });
    mountedWrappers.push(wrapper);

    return wrapper;
}

function pdfFile(name = 'Rapport.pdf') {
    return new File(['%PDF-1.4'], name, { type: 'application/pdf' });
}

async function chooseFile(wrapper, file) {
    const input = wrapper.find('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
}

function findButton(wrapper, label) {
    return wrapper.findAll('button').find((button) => button.text() === label);
}

function findCancelLink(wrapper) {
    return wrapper.findAll('a').find((link) => link.text() === 'Annuler');
}

beforeEach(() => {
    formPostMock.mockReset();
    router.post.mockClear();
    router.delete.mockClear();
    router.on.mockClear();
    window.confirm = vi.fn(() => true);
});

afterEach(() => {
    mountedWrappers.forEach((wrapper) => wrapper.unmount());
    mountedWrappers = [];
});

describe('Documents/Import — formulaire unique (spec-refonte-import-formulaire-unique)', () => {
    it('keeps a chosen file locally, shows its name and "Retirer", and sends no request', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());

        expect(wrapper.text()).toContain('Rapport.pdf');
        expect(findButton(wrapper, 'Retirer')).toBeDefined();
        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
        expect(formPostMock).not.toHaveBeenCalled();
        expect(router.post).not.toHaveBeenCalled();
    });

    it('keeps the title field empty and disabled until a file is chosen', () => {
        const wrapper = mountImport();
        const titleInput = wrapper.find('#document-title');

        expect(titleInput.element.value).toBe('');
        expect(titleInput.attributes('disabled')).toBeDefined();
    });

    it('prefills an editable title with the filename minus its final extension', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile('Rapport.v2.pdf'));

        const titleInput = wrapper.find('#document-title');
        expect(titleInput.attributes('disabled')).toBeUndefined();
        expect(titleInput.element.value).toBe('Rapport.v2');

        await titleInput.setValue('Rapport annuel');

        expect(formState.instance.title).toBe('Rapport annuel');
    });

    it('empties and disables the title again on "Retirer"', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await wrapper.find('#document-title').setValue('Rapport annuel');
        await findButton(wrapper, 'Retirer').trigger('click');

        const titleInput = wrapper.find('#document-title');
        expect(titleInput.element.value).toBe('');
        expect(titleInput.attributes('disabled')).toBeDefined();
    });

    it('disables "Enregistrer" when the title is cleared', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await wrapper.find('#document-title').setValue('  ');

        expect(findButton(wrapper, 'Enregistrer').attributes('disabled')).toBeDefined();
    });

    it('stays on the dropzone with the client error for an unsupported file', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, new File(['x'], 'notes.txt', { type: 'text/plain' }));

        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').text()).toContain('Formats acceptés');
        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('returns to the dropzone on "Retirer" while keeping tags and attachments', async () => {
        const wrapper = mountImport();
        const attachment = { filename: 'a.pdf', original_filename: 'Annexe.pdf' };

        await wrapper.findComponent(TagSelector).vm.$emit('update:modelValue', [1, 2]);
        await wrapper.findComponent(AttachmentsPanel).vm.$emit('update:attachments', [attachment]);
        await chooseFile(wrapper, pdfFile());
        await findButton(wrapper, 'Retirer').trigger('click');

        expect(wrapper.find('input[type="file"]').exists()).toBe(true);
        expect(formState.instance.file).toBeNull();
        expect(formState.instance.tag_ids).toEqual([1, 2]);
        expect(wrapper.findComponent(AttachmentsPanel).props('attachments')).toEqual([attachment]);
    });

    it('disables "Enregistrer" and sends nothing without a file', async () => {
        const wrapper = mountImport();
        const saveButton = findButton(wrapper, 'Enregistrer');

        expect(saveButton.attributes('disabled')).toBeDefined();

        await saveButton.trigger('click');

        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('sends file, tags, draft token and draft attachments in one POST /documents on "Enregistrer"', async () => {
        const wrapper = mountImport();
        const file = pdfFile();
        const panel = wrapper.findComponent(AttachmentsPanel);

        await chooseFile(wrapper, file);
        await wrapper.findComponent(TagSelector).vm.$emit('update:modelValue', [1, 2]);
        await panel.vm.$emit('update:attachments', [
            { filename: 'uuid.pdf', original_filename: 'Annexe.pdf', mime_type: 'application/pdf' },
        ]);
        await findButton(wrapper, 'Enregistrer').trigger('click');

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock).toHaveBeenCalledWith('/documents', expect.objectContaining({
            forceFormData: true,
            preserveState: true,
        }));
        expect(formState.instance.file).toBe(file);
        expect(formState.instance.title).toBe('Rapport');
        expect(formState.instance.tag_ids).toEqual([1, 2]);
        expect(formState.instance.draft_token).toBe(panel.props('draftToken'));
        expect(panel.props('mode')).toBe('draft');
        expect(formState.instance.draft_attachments).toEqual([
            { filename: 'uuid.pdf', original_filename: 'Annexe.pdf' },
        ]);
    });

    it('disables "Enregistrer" while an attachment upload is in flight', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await wrapper.findComponent(AttachmentsPanel).vm.$emit('update:uploading', true);

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Envoi de la pièce jointe…');
        expect(saveButton.attributes('disabled')).toBeDefined();

        await saveButton.trigger('click');

        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('keeps the form and shows the server error when the file is rejected', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await wrapper.findComponent(TagSelector).vm.$emit('update:modelValue', [3]);
        formState.instance.errors = { file: 'Fichier trop volumineux (20 Mo maximum).' };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Fichier trop volumineux');
        expect(wrapper.text()).toContain('Rapport.pdf');
        expect(formState.instance.tag_ids).toEqual([3]);
    });

    it('renders a per-attachment validation error returned as draft_attachments.0.filename', async () => {
        const wrapper = mountImport();

        formState.instance.errors = { 'draft_attachments.0.filename': 'Pièce jointe invalide.' };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Pièce jointe invalide.');
    });

    it('renders a per-tag validation error returned as tag_ids.0', async () => {
        const wrapper = mountImport();

        formState.instance.errors = { 'tag_ids.0': 'Tag invalide.' };
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Tag invalide.');
    });

    it('points "Annuler" to the library and sends no request or confirmation', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await findCancelLink(wrapper).trigger('click');

        expect(findCancelLink(wrapper).attributes('href')).toBe('/');
        expect(formPostMock).not.toHaveBeenCalled();
        expect(window.confirm).not.toHaveBeenCalled();
    });

    it('makes "Annuler" inert while the save is in flight', async () => {
        formPostMock.mockImplementation(function markProcessing() {
            this.processing = true;
        });
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());
        await findButton(wrapper, 'Enregistrer').trigger('click');

        expect(findCancelLink(wrapper)).toBeUndefined();
        expect(findButton(wrapper, 'Annuler').attributes('disabled')).toBeDefined();
    });

    it('makes "Annuler" inert while an attachment upload is in flight', async () => {
        const wrapper = mountImport();

        await wrapper.findComponent(AttachmentsPanel).vm.$emit('update:uploading', true);

        expect(findCancelLink(wrapper)).toBeUndefined();
        expect(findButton(wrapper, 'Annuler').attributes('disabled')).toBeDefined();
    });

    it('registers no navigation guard and no beforeunload prompt', async () => {
        const wrapper = mountImport();

        await chooseFile(wrapper, pdfFile());

        expect(router.on).not.toHaveBeenCalledWith('before', expect.anything());

        const unloadEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(unloadEvent);

        expect(unloadEvent.defaultPrevented).toBe(false);
    });
});
