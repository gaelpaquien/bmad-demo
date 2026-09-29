import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePage } from '@inertiajs/vue3';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as TagSelector.spec.js/Sidebar.spec.js. `useForm()`
// returns a small reactive stand-in whose `post` is a shared spy
// (`formPostMock`) so a test can both assert on the call and manually
// invoke its `onStart`/`onSuccess`/`onError`/`onFinish` options to simulate
// a request completing. `vi.hoisted()` is required here (unlike
// TagSelector.spec.js's self-contained factory) since these mocks must also
// be reachable from the test bodies below, not just from inside the factory.
const { routerPostMock, routerDeleteMock, formPostMock, formState } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    formPostMock: vi.fn(),
    // Last useForm() instance, so a test can set the server errors it exposes.
    formState: { instance: null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            post: routerPostMock,
            delete: routerDeleteMock,
        },
        useForm: (initial) => {
            formState.instance = reactive({
                ...initial,
                processing: false,
                errors: {},
                post: formPostMock,
                reset: vi.fn(),
                clearErrors: vi.fn(),
            });

            return formState.instance;
        },
    };
});

const pageState = usePage();

function pdfFile(name = 'source.pdf') {
    return new File(['%PDF-1.4 fake'], name, { type: 'application/pdf' });
}

function pngFile(name = 'photo.png') {
    return new File(['fake'], name, { type: 'image/png' });
}

const immediateAttachments = [
    { id: 1, original_filename: 'contrat.pdf', mime_type: 'application/pdf' },
    { id: 2, original_filename: 'budget.xlsx', mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
];

const draftAttachments = [
    { filename: '11111111-1111-1111-1111-111111111111.pdf', original_filename: 'brouillon.pdf', mime_type: 'application/pdf' },
];

describe('AttachmentsPanel', () => {
    beforeEach(() => {
        routerPostMock.mockClear();
        routerDeleteMock.mockClear();
        formPostMock.mockClear();
        pageState.props.flash = {};
    });

    // --- État vide : aucun message, aucune liste -----------------------------

    it('renders no list and no empty-state message when there is no attachment', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        expect(wrapper.find('ul').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Aucune pièce jointe');
    });

    it('shows the accepted formats, the file count limit and the per-file size limit', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        expect(wrapper.text()).toContain('Formats acceptés : PDF, Word, Excel');
        expect(wrapper.text()).toContain('10 fichiers maximum');
        expect(wrapper.text()).toContain('20 Mo maximum par fichier');
    });

    // --- Rendu de la liste ---------------------------------------------------

    it('renders one row per attachment with preview/download links in immediate mode', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });

        expect(wrapper.text()).toContain('contrat.pdf');
        expect(wrapper.text()).toContain('budget.xlsx');

        const previewLinks = wrapper.findAll('a').filter((a) => a.text() === 'Aperçu');
        expect(previewLinks).toHaveLength(2);
        expect(previewLinks[0].attributes('href')).toBe('/documents/42/attachments/1/preview');

        const downloadLinks = wrapper.findAll('a').filter((a) => a.text() === 'Télécharger');
        expect(downloadLinks[0].attributes('href')).toBe('/documents/42/attachments/1/download');
    });

    it('renders draft attachments with no preview/download links', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftAttachments, mode: 'draft', draftToken: 'draft-token' },
        });

        expect(wrapper.text()).toContain('brouillon.pdf');
        expect(wrapper.findAll('a')).toHaveLength(0);
    });

    // --- Ouverture / fermeture du panneau -------------------------------------

    it('starts expanded, and the toggle button collapses/reopens the body', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        const toggle = wrapper.find('button[aria-controls]');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Glissez-déposez un ou plusieurs fichiers ici');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.text()).not.toContain('Glissez-déposez un ou plusieurs fichiers ici');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.text()).toContain('Glissez-déposez un ou plusieurs fichiers ici');
    });

    // --- Format non supporté (client-side, pas d'envoi réseau) ----------------

    it('rejects an unsupported format client-side, sending no request', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pngFile()] } });

        expect(wrapper.text()).toContain('Format non supporté');
        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('rejects a file over 20 Mo client-side, sending no request', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });
        const bigFile = pdfFile('gros.pdf');
        Object.defineProperty(bigFile, 'size', { value: 20 * 1024 * 1024 + 1 });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [bigFile] } });

        expect(wrapper.text()).toContain('Fichier trop volumineux (20 Mo maximum)');
        expect(routerPostMock).not.toHaveBeenCalled();
    });

    // --- Limite de 10 pièces jointes (client-side, pas d'envoi réseau) --------

    function attachmentsOfCount(count) {
        return Array.from({ length: count }, (_, index) => ({ id: index + 1, original_filename: `annexe-${index + 1}.pdf` }));
    }

    it.each([
        ['immediate', { documentId: 42 }],
        ['draft', { draftToken: 'draft-token' }],
    ])('refuses an 11th attachment in %s mode, sending no request', async (mode, extraProps) => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: attachmentsOfCount(10), mode, ...extraProps },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(wrapper.text()).toContain('10 pièces jointes maximum par document.');
        expect(formPostMock).not.toHaveBeenCalled();
        expect(routerPostMock).not.toHaveBeenCalled();
    });

    it('disables "Parcourir" at 10 attachments and re-enables it once one is removed', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: attachmentsOfCount(10), mode: 'immediate', documentId: 42 },
        });
        const browseButton = () => wrapper.findAll('button').find((button) => button.text() === 'Parcourir');

        expect(browseButton().attributes('disabled')).toBeDefined();
        expect(wrapper.find('input[type="file"]').attributes('disabled')).toBeDefined();

        await wrapper.setProps({ attachments: attachmentsOfCount(9) });

        expect(browseButton().attributes('disabled')).toBeUndefined();
        expect(wrapper.find('input[type="file"]').attributes('disabled')).toBeUndefined();
    });

    it('clears the limit error on removal and accepts a new attachment once back under the limit', async () => {
        const draftList = attachmentsOfCount(10).map((attachment) => ({ ...attachment, filename: `${attachment.id}.pdf` }));
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftList, mode: 'draft', draftToken: 'draft-token' },
        });
        const dropZone = wrapper.find('.border-dashed');

        await dropZone.trigger('drop', { dataTransfer: { files: [pdfFile()] } });
        expect(wrapper.text()).toContain('10 pièces jointes maximum par document.');

        await wrapper.findAll('button').find((button) => button.text() === 'Retirer').trigger('click');
        expect(wrapper.text()).not.toContain('pièces jointes maximum');

        await wrapper.setProps({ attachments: wrapper.emitted('update:attachments').at(-1)[0] });
        await dropZone.trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(wrapper.text()).not.toContain('pièces jointes maximum');
        expect(routerPostMock).toHaveBeenCalledTimes(1);
    });

    // --- Sélection multiple : envoi en file, un fichier après l'autre ----------

    it('accepts several files through a multiple file input', () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        expect(wrapper.find('input[type="file"]').attributes('multiple')).toBeDefined();
    });

    it.each([
        ['immediate', { documentId: 42 }, () => formPostMock, 1],
        ['draft', { draftToken: 'draft-token' }, () => routerPostMock, 2],
    ])('uploads a multi-file drop one file after the other in %s mode', async (mode, extraProps, postMockOf, optionsIndex) => {
        const postMock = postMockOf();
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode, ...extraProps },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        expect(postMock).toHaveBeenCalledTimes(1);

        postMock.mock.calls[0][optionsIndex].onFinish();
        expect(postMock).toHaveBeenCalledTimes(2);

        postMock.mock.calls[1][optionsIndex].onFinish();
        await wrapper.vm.$nextTick();

        expect(postMock).toHaveBeenCalledTimes(2);
        expect(wrapper.emitted('update:uploading').at(-1)).toEqual([false]);
    });

    it('refuses the whole selection when it exceeds the remaining slots, sending no request', async () => {
        const draftList = attachmentsOfCount(9).map((attachment) => ({ ...attachment, filename: `${attachment.id}.pdf` }));
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftList, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });

        expect(wrapper.text()).toContain('10 pièces jointes maximum par document : encore 1 possible.');
        expect(routerPostMock).not.toHaveBeenCalled();
    });

    it('uses the plural for several remaining slots', async () => {
        const draftList = attachmentsOfCount(8).map((attachment) => ({ ...attachment, filename: `${attachment.id}.pdf` }));
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftList, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf'), pdfFile('c.pdf')] } });

        expect(wrapper.text()).toContain('10 pièces jointes maximum par document : encore 2 possibles.');
    });

    it('does not count invalid files against the limit', async () => {
        const draftList = attachmentsOfCount(9).map((attachment) => ({ ...attachment, filename: `${attachment.id}.pdf` }));
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftList, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pngFile('photo.png'), pdfFile('ok.pdf')] } });

        expect(wrapper.text()).toContain('photo.png : Format non supporté');
        expect(wrapper.text()).not.toContain('pièces jointes maximum');
        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1].file.name).toBe('ok.pdf');
    });

    it('renders every error even when two messages are identical', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pngFile('photo.png'), pngFile('photo.png')] } });

        const alerts = wrapper.findAll('[role="alert"]').map((alert) => alert.text());
        expect(alerts).toHaveLength(2);
        expect(alerts[0]).toBe(alerts[1]);
    });

    it.each([
        // Immediate mode reads the message off its useForm() errors, draft
        // mode off the errors bag handed to onError.
        ['immediate', { documentId: 42 }, () => formPostMock, 1, (options, message) => {
            formState.instance.errors = { file: message };
            options.onError();
        }],
        ['draft', { draftToken: 'draft-token' }, () => routerPostMock, 2, (options, message) => options.onError({ file: message })],
    ])('reports a server rejection by file name and still sends the rest of the queue in %s mode', async (mode, extraProps, postMockOf, optionsIndex, rejectWith) => {
        const postMock = postMockOf();
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode, ...extraProps },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        rejectWith(postMock.mock.calls[0][optionsIndex], 'Le fichier a est invalide.');
        postMock.mock.calls[0][optionsIndex].onFinish();
        rejectWith(postMock.mock.calls[1][optionsIndex], 'Le fichier b est invalide.');
        postMock.mock.calls[1][optionsIndex].onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('[role="alert"]').map((alert) => alert.text())).toEqual([
            'a.pdf : Le fichier a est invalide.',
            'b.pdf : Le fichier b est invalide.',
        ]);
        expect(postMock).toHaveBeenCalledTimes(2);
    });

    it.each([
        ['immediate', { documentId: 42 }, () => formPostMock, 1],
        ['draft', { draftToken: 'draft-token' }, () => routerPostMock, 2],
    ])('stops the queue when the upload in flight is cancelled by another visit in %s mode', async (mode, extraProps, postMockOf, optionsIndex) => {
        const postMock = postMockOf();
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode, ...extraProps },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf'), pdfFile('c.pdf')] } });
        postMock.mock.calls[0][optionsIndex].onCancel();
        postMock.mock.calls[0][optionsIndex].onFinish();
        await wrapper.vm.$nextTick();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('[role="alert"]').map((alert) => alert.text())).toEqual([
            'Envoi interrompu : a.pdf n\'a peut-être pas été joint, vérifiez la liste.',
            'b.pdf, c.pdf n\'ont pas été envoyés.',
        ]);
        expect(wrapper.emitted('update:uploading').at(-1)).toEqual([false]);
    });

    it('uses the singular when a single queued file was never sent', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        routerPostMock.mock.calls[0][2].onCancel();
        routerPostMock.mock.calls[0][2].onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('b.pdf n\'a pas été envoyé.');
    });

    it('reports only the interrupted file when nothing else was queued', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf')] } });
        routerPostMock.mock.calls[0][2].onCancel();
        routerPostMock.mock.calls[0][2].onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('[role="alert"]').map((alert) => alert.text())).toEqual([
            'Envoi interrompu : a.pdf n\'a peut-être pas été joint, vérifiez la liste.',
        ]);
    });

    it('keeps Retirer available and the batch errors shown when removing a draft attachment mid-upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftAttachments, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        routerPostMock.mock.calls[0][2].onError({ file: 'Le fichier est invalide.' });
        await wrapper.vm.$nextTick();
        const removeButton = wrapper.find('button[aria-label="Retirer la pièce jointe brouillon.pdf"]');

        expect(removeButton.attributes('disabled')).toBeUndefined();

        await removeButton.trigger('click');

        expect(wrapper.emitted('update:attachments')).toEqual([[[]]]);
        expect(wrapper.text()).toContain('a.pdf : Le fichier est invalide.');
    });

    it('sends nothing more once unmounted mid-queue', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        const { onFinish } = routerPostMock.mock.calls[0][2];
        wrapper.unmount();
        onFinish();

        expect(routerPostMock).toHaveBeenCalledTimes(1);
    });

    it('keeps every file of a multi-file draft upload, one flash after the other', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: {
                attachments: [],
                mode: 'draft',
                draftToken: 'draft-token',
                'onUpdate:attachments': (attachments) => wrapper.setProps({ attachments }),
            },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('a.pdf'), pdfFile('b.pdf')] } });
        pageState.props.flash = { uploadedAttachment: { filename: 'a-uuid.pdf', original_filename: 'a.pdf', mime_type: 'application/pdf', draftToken: 'draft-token' } };
        routerPostMock.mock.calls[0][2].onFinish();
        await wrapper.vm.$nextTick();
        pageState.props.flash = { uploadedAttachment: { filename: 'b-uuid.pdf', original_filename: 'b.pdf', mime_type: 'application/pdf', draftToken: 'draft-token' } };
        routerPostMock.mock.calls[1][2].onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.props('attachments').map((attachment) => attachment.original_filename)).toEqual(['a.pdf', 'b.pdf']);
    });

    it('skips an invalid file of a multi-file selection, naming it, and still sends the valid ones', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pngFile('photo.png'), pdfFile('ok.pdf')] } });

        expect(wrapper.text()).toContain('photo.png : Format non supporté');
        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1].file.name).toBe('ok.pdf');
    });

    // --- Mode immediate : ajout ------------------------------------------------

    it('posts to /documents/{id}/attachments on a valid drop in immediate mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock.mock.calls[0][0]).toBe('/documents/42/attachments');
        expect(formPostMock.mock.calls[0][1].forceFormData).toBe(true);
        expect(formPostMock.mock.calls[0][1].preserveState).toBe(true);
    });

    // --- Réentrance : un deuxième drop pendant un envoi en cours est ignoré ----

    it('ignores a second drop while an immediate upload is still in flight', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('first.pdf')] } });
        expect(formPostMock).toHaveBeenCalledTimes(1);

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('second.pdf')] } });
        expect(formPostMock).toHaveBeenCalledTimes(1);
    });

    it('ignores a second drop while a draft upload is still in flight', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('first.pdf')] } });
        expect(routerPostMock).toHaveBeenCalledTimes(1);

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile('second.pdf')] } });
        expect(routerPostMock).toHaveBeenCalledTimes(1);
    });

    // --- v-model:uploading -------------------------------------------------------

    it('emits update:uploading true then false around an immediate upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        await wrapper.vm.$nextTick();
        formPostMock.mock.calls[0][1].onFinish();
        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:uploading');
        expect(emitted.at(-2)).toEqual([true]);
        expect(emitted.at(-1)).toEqual([false]);
    });

    it('emits update:uploading true then false around a draft upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });
        await wrapper.vm.$nextTick();

        const { onFinish } = routerPostMock.mock.calls[0][2];
        onFinish();
        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:uploading');
        expect(emitted.at(-2)).toEqual([true]);
        expect(emitted.at(-1)).toEqual([false]);
    });

    // --- Mode immediate : retrait -----------------------------------------------

    it('deletes /documents/{id}/attachments/{attachment} on Retirer in immediate mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('button[aria-label="Retirer la pièce jointe contrat.pdf"]').trigger('click');

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock.mock.calls[0][0]).toBe('/documents/42/attachments/1');
        expect(routerDeleteMock.mock.calls[0][1].preserveState).toBe(true);
    });

    it('disables Retirer in immediate mode while an upload is in flight', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });
        const removeButton = () => wrapper.find('button[aria-label="Retirer la pièce jointe contrat.pdf"]');

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(removeButton().attributes('disabled')).toBeDefined();

        formPostMock.mock.calls[0][1].onFinish();
        await wrapper.vm.$nextTick();

        expect(removeButton().attributes('disabled')).toBeUndefined();
    });

    // --- before-request/after-request (retro Epic 3, item 7) --------------------
    // Editor.vue relies on these to bracket its own unsaved-changes navigation
    // guard around this panel's own immediate-mode requests — asserted here on
    // the real component, since Editor.spec.js only exercises a stub.

    it('emits before-request then after-request around an immediate attach', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(wrapper.emitted('before-request')).toHaveLength(1);
        expect(wrapper.emitted('after-request')).toHaveLength(1);
    });

    it('emits before-request then after-request around an immediate detach', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: immediateAttachments, mode: 'immediate', documentId: 42 },
        });

        await wrapper.find('button[aria-label="Retirer la pièce jointe contrat.pdf"]').trigger('click');

        expect(wrapper.emitted('before-request')).toHaveLength(1);
        expect(wrapper.emitted('after-request')).toHaveLength(1);
    });

    it('never emits before-request/after-request during a draft-mode upload', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(wrapper.emitted('before-request')).toBeUndefined();
        expect(wrapper.emitted('after-request')).toBeUndefined();
    });

    // --- Mode draft : ajout via upload + flash ----------------------------------

    it('posts to /documents/create/attachments on a valid drop in draft mode', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('.border-dashed').trigger('drop', { dataTransfer: { files: [pdfFile()] } });

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][0]).toBe('/documents/create/attachments');
        expect(routerPostMock.mock.calls[0][1].draft_token).toBe('draft-token');
    });

    it('emits update:attachments when flash.uploadedAttachment matches this draft token', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        pageState.props.flash.uploadedAttachment = {
            filename: '22222222-2222-2222-2222-222222222222.pdf',
            original_filename: 'nouveau.pdf',
            mime_type: 'application/pdf',
            draftToken: 'draft-token',
        };

        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:attachments');
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toEqual([{
            filename: '22222222-2222-2222-2222-222222222222.pdf',
            original_filename: 'nouveau.pdf',
            mime_type: 'application/pdf',
        }]);
    });

    it('ignores flash.uploadedAttachment meant for a different draft token', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: [], mode: 'draft', draftToken: 'draft-token' },
        });

        pageState.props.flash.uploadedAttachment = {
            filename: '33333333-3333-3333-3333-333333333333.pdf',
            original_filename: 'autre.pdf',
            mime_type: 'application/pdf',
            draftToken: 'other-draft-token',
        };

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:attachments')).toBeUndefined();
    });

    // --- Mode draft : retrait local, sans requête réseau ------------------------

    it('removes a draft attachment locally on Retirer, with no request sent', async () => {
        const wrapper = mount(AttachmentsPanel, {
            props: { attachments: draftAttachments, mode: 'draft', draftToken: 'draft-token' },
        });

        await wrapper.find('button[aria-label="Retirer la pièce jointe brouillon.pdf"]').trigger('click');

        expect(wrapper.emitted('update:attachments')).toEqual([[[]]]);
        expect(routerDeleteMock).not.toHaveBeenCalled();
        expect(routerPostMock).not.toHaveBeenCalled();
    });
});
