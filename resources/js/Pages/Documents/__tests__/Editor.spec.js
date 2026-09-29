import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Editor from '@/Pages/Documents/Editor.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import TagSelector from '@/Components/TagSelector.vue';

// `@tiptap/vue-3` is mocked out entirely — mounting a real TipTap/ProseMirror
// instance in jsdom is unnecessary for this story (only the toolbar's
// `disabled` bindings and which TipTap command a click runs are in scope)
// and brittle. `useEditor()` returns a plain Vue ref wrapping a small
// stand-in editor whose `isActive`/`chain` are shared spies, so a test can
// both control what the toolbar sees and assert exactly which command ran —
// same reusable-mock approach as Configuration.spec.js's mocked `useForm`.
// `chain()` always returns the same fluent object so
// `chain().focus().insertTable(...).run()` / `chain().focus().deleteTable().run()`
// keep working however many links are chained.
const {
    isActiveMock, chainMock, insertTableMock, deleteTableMock, tableCommandMock, runMock, formPostMock, formPatchMock, editorState,
} = vi.hoisted(() => {
    const tableCommandMock = vi.fn();
    const insertTableMock = vi.fn();
    const deleteTableMock = vi.fn();
    const runMock = vi.fn();
    const formPostMock = vi.fn();
    const formPatchMock = vi.fn();

    const chainObj = {
        focus: () => chainObj,
        insertTable: (...args) => {
            insertTableMock(...args);
            return chainObj;
        },
        deleteTable: (...args) => {
            deleteTableMock(...args);
            return chainObj;
        },
        addColumnAfter: () => {
            tableCommandMock('addColumnAfter');
            return chainObj;
        },
        deleteColumn: () => {
            tableCommandMock('deleteColumn');
            return chainObj;
        },
        addRowAfter: () => {
            tableCommandMock('addRowAfter');
            return chainObj;
        },
        deleteRow: () => {
            tableCommandMock('deleteRow');
            return chainObj;
        },
        toggleHeading: () => chainObj,
        toggleBulletList: () => chainObj,
        toggleOrderedList: () => chainObj,
        setTextSelection: () => chainObj,
        setImage: () => chainObj,
        run: (...args) => {
            runMock(...args);
            return chainObj;
        },
    };

    return {
        isActiveMock: vi.fn(() => false),
        chainMock: vi.fn(() => chainObj),
        insertTableMock,
        deleteTableMock,
        tableCommandMock,
        runMock,
        formPostMock,
        formPatchMock,
        // Read by the mocked editor's `isEmpty` getter — lets a test put the
        // editor body in the empty state that keeps "Enregistrer" disabled.
        editorState: { isEmpty: false },
    };
});

vi.mock('@tiptap/vue-3', async () => {
    const { ref } = await import('vue');

    const mockEditor = {
        isActive: isActiveMock,
        chain: chainMock,
        getHTML: () => '',
        get isEmpty() {
            return editorState.isEmpty;
        },
        isEditable: true,
        setEditable: () => {},
        view: { posAtCoords: () => null },
    };

    return {
        // Invoking `onCreate` synchronously (as TipTap does in practice, same
        // tick) lets tests reach the fully loaded, editable state.
        useEditor: (options) => {
            options?.onCreate?.({ editor: mockEditor });

            return ref(mockEditor);
        },
        EditorContent: { name: 'EditorContent', template: '<div class="editor-content-stub" />' },
    };
});

// `@inertiajs/vue3` is mocked rather than imported for real — same
// reusable-mock approach as Configuration.spec.js/AttachmentsPanel.spec.js.
// Nothing here exercises navigation/save, so the spies only need to exist,
// not be asserted on.
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const pageState = reactive({ props: { flash: {} } });

    return {
        usePage: () => pageState,
        router: {
            on: vi.fn(() => vi.fn()),
            post: vi.fn(),
        },
        useForm: (initial) => reactive({
            ...initial,
            processing: false,
            errors: {},
            post: formPostMock,
            patch: formPatchMock,
        }),
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    };
});

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    AttachmentsPanel: true,
    TagSelector: true,
};

function mountEditor() {
    return mount(Editor, {
        global: { stubs: globalStubs },
    });
}

describe('Documents/Editor — tableaux imbriqués (spec-3-6)', () => {
    beforeEach(() => {
        insertTableMock.mockClear();
        deleteTableMock.mockClear();
        runMock.mockClear();
        chainMock.mockClear();
        isActiveMock.mockReset();
        isActiveMock.mockImplementation(() => false);
    });

    // --- "Insérer un tableau" ------------------------------------------------

    it('disables "Insérer un tableau" when the cursor is already inside a table', () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('enables "Insérer un tableau" and inserts a 3x3 table when the cursor is outside any table', async () => {
        isActiveMock.mockImplementation(() => false);

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');

        expect(insertTableMock).toHaveBeenCalledWith({ rows: 3, cols: 3, withHeaderRow: true });
        expect(runMock).toHaveBeenCalled();
    });

    // AC1 / Boundaries & Constraints: `insertTable()` itself checks
    // `editor.isActive('table')` before running the insertion chain — this
    // is the JS-level guard, kept as a defense-in-depth check independent of
    // the `disabled` attribute asserted above (which already stops a real
    // click from ever reaching the handler). The native `disabled` property
    // is cleared here so the click still dispatches, isolating the guard.
    it('never runs the insertion chain from insertTable() when the cursor is already inside a table, even if the click still dispatches', async () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Insérer un tableau"]');
        button.element.disabled = false;

        await button.trigger('click');

        expect(chainMock).not.toHaveBeenCalled();
        expect(insertTableMock).not.toHaveBeenCalled();
        expect(runMock).not.toHaveBeenCalled();
    });

    // --- "Supprimer le tableau" -----------------------------------------------

    it('disables "Supprimer le tableau" when the cursor is outside any table', () => {
        isActiveMock.mockImplementation(() => false);

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Supprimer le tableau"]');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('enables "Supprimer le tableau" and removes the table when the cursor is inside one', async () => {
        isActiveMock.mockImplementation((type) => type === 'table');

        const wrapper = mountEditor();
        const button = wrapper.find('button[aria-label="Supprimer le tableau"]');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');

        expect(deleteTableMock).toHaveBeenCalledTimes(1);
        expect(runMock).toHaveBeenCalled();
    });

    // --- Structure du tableau (lignes / colonnes) ---------------------------

    it('hides the row/column buttons when the cursor is outside any table', () => {
        const wrapper = mountEditor();

        expect(wrapper.find('button[aria-label="Ajouter une colonne à droite"]').exists()).toBe(false);
    });

    it.each([
        ['Ajouter une colonne à droite', 'addColumnAfter'],
        ['Supprimer la colonne', 'deleteColumn'],
        ['Ajouter une ligne en dessous', 'addRowAfter'],
        ['Supprimer la ligne', 'deleteRow'],
    ])('runs %s (%s) when the cursor is inside a table', async (ariaLabel, command) => {
        isActiveMock.mockImplementation((type) => type === 'table');
        tableCommandMock.mockClear();
        const wrapper = mountEditor();

        await wrapper.find(`button[aria-label="${ariaLabel}"]`).trigger('click');

        expect(tableCommandMock).toHaveBeenCalledExactlyOnceWith(command);
        expect(runMock).toHaveBeenCalled();
    });
});

// spec-ajustements-consultation-editeur: the unsaved-changes notion is gone
// entirely — no pastille, no label, no exit confirmation — and "Annuler"
// leaves without any request.
describe('Documents/Editor — Annuler et absence de garde de sortie', () => {
    const existingDocument = { id: 12, title: 'Titre initial', content_html: '', tags: [], attachments: [] };

    beforeEach(() => {
        router.on.mockClear();
        router.post.mockClear();
        formPostMock.mockReset();
        formPatchMock.mockReset();
        window.confirm = vi.fn(() => true);
    });

    function mountExistingEditor() {
        return mount(Editor, {
            props: { document: existingDocument },
            global: { stubs: globalStubs },
        });
    }

    function findCancelLink(wrapper) {
        return wrapper.findAll('a').find((link) => link.text() === 'Annuler');
    }

    it('shows neither the pastille nor the "Modifications non enregistrées" label after an edit', async () => {
        const wrapper = mountExistingEditor();

        await wrapper.find('#document-title').setValue('Titre modifié');

        expect(wrapper.text()).not.toContain('Modifications non enregistrées');
        expect(wrapper.find('.bg-amber-500').exists()).toBe(false);
    });

    it('registers no Inertia navigation guard and never asks for confirmation on leaving after an edit', async () => {
        const wrapper = mountExistingEditor();

        await wrapper.find('#document-title').setValue('Titre modifié');

        expect(router.on).not.toHaveBeenCalledWith('before', expect.anything());

        const unloadEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(unloadEvent);

        expect(unloadEvent.defaultPrevented).toBe(false);
        expect(window.confirm).not.toHaveBeenCalled();
    });

    it('points "Annuler" back to the document when editing an existing one', () => {
        const wrapper = mountExistingEditor();

        expect(findCancelLink(wrapper).attributes('href')).toBe('/documents/12');
    });

    it('points "Annuler" back to the list when drafting a new document', () => {
        const wrapper = mountEditor();

        expect(findCancelLink(wrapper).attributes('href')).toBe('/');
    });

    it('sends no request when clicking "Annuler"', async () => {
        const wrapper = mountExistingEditor();

        await wrapper.find('#document-title').setValue('Titre modifié');
        await findCancelLink(wrapper).trigger('click');

        expect(formPatchMock).not.toHaveBeenCalled();
        expect(formPostMock).not.toHaveBeenCalled();
        expect(router.post).not.toHaveBeenCalled();
        expect(window.confirm).not.toHaveBeenCalled();
    });

    it('makes "Annuler" inert while a save is in flight', async () => {
        formPatchMock.mockImplementation(function markProcessing() {
            this.processing = true;
        });
        const wrapper = mountExistingEditor();

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Enregistrer');
        await saveButton.trigger('click');

        expect(findCancelLink(wrapper)).toBeUndefined();
        const inertCancel = wrapper.findAll('button').find((button) => button.text() === 'Annuler');
        expect(inertCancel.attributes('disabled')).toBeDefined();
    });

    it('makes "Annuler" inert while an attachment upload is in flight', async () => {
        const wrapper = mountExistingEditor();

        await wrapper.findComponent(AttachmentsPanel).vm.$emit('update:uploading', true);
        await wrapper.vm.$nextTick();

        expect(findCancelLink(wrapper)).toBeUndefined();
        const inertCancel = wrapper.findAll('button').find((button) => button.text() === 'Annuler');
        expect(inertCancel.attributes('disabled')).toBeDefined();
    });
});

describe('Documents/Editor — champ Tags visible sans révélation en deux temps (spec-fix-multi-tag-selection)', () => {
    beforeEach(() => {
        formPostMock.mockClear();
        formPatchMock.mockClear();
    });

    it('renders the Tags field immediately on a brand-new document, with no prior click on "Enregistrer"', () => {
        const wrapper = mountEditor();

        expect(wrapper.findComponent(TagSelector).exists()).toBe(true);
    });

    // review_loop_iteration 1, finding verification-gap: the two-step
    // "Enregistrer" gesture (reveal the tag selector on the first click,
    // submit only on the second) is gone — a brand-new document (no
    // `document` prop) must submit directly on the very first click.
    it('submits directly to /documents/create on the first click on "Enregistrer" for a brand-new document', async () => {
        const wrapper = mountEditor();
        await wrapper.find('#document-title').setValue('Nouveau document');

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');

        expect(formPostMock).toHaveBeenCalledTimes(1);
        expect(formPostMock.mock.calls[0][0]).toBe('/documents/create');
        expect(formPatchMock).not.toHaveBeenCalled();
    });
});

describe('Documents/Editor — limite de pièces jointes (spec-limite-pieces-jointes)', () => {
    beforeEach(() => {
        formPostMock.mockReset();
    });

    it('shows a draft_attachments rejection from the server below the attachments panel', async () => {
        // The mocked useForm() is reactive and `post` is called as a
        // method, so `this` is the form itself — mimic Inertia filling
        // `errors` from a validation redirect.
        formPostMock.mockImplementationOnce(function () {
            this.errors = { draft_attachments: '10 pièces jointes maximum par document.' };
        });
        const wrapper = mountEditor();
        await wrapper.find('#document-title').setValue('Nouveau document');

        const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Enregistrer'));
        await saveButton.trigger('click');
        await nextTick();

        expect(wrapper.find('[role="alert"]').text()).toBe('10 pièces jointes maximum par document.');
    });
});

describe('Documents/Editor — "Enregistrer" bloqué tant que les champs requis sont vides', () => {
    beforeEach(() => {
        formPostMock.mockReset();
        editorState.isEmpty = false;
    });

    function findSaveButton(wrapper) {
        return wrapper.findAll('button').find((button) => button.text() === 'Enregistrer');
    }

    it('disables "Enregistrer" and sends nothing while the title is empty', async () => {
        const wrapper = mountEditor();

        await wrapper.find('#document-title').setValue('   ');
        await findSaveButton(wrapper).trigger('click');

        expect(findSaveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(formPostMock).not.toHaveBeenCalled();
    });

    it('disables "Enregistrer" while the content is empty, even with a title', async () => {
        editorState.isEmpty = true;
        const wrapper = mountEditor();

        await wrapper.find('#document-title').setValue('Nouveau document');

        expect(findSaveButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('enables "Enregistrer" once both the title and the content are filled', async () => {
        const wrapper = mountEditor();

        await wrapper.find('#document-title').setValue('Nouveau document');

        expect(findSaveButton(wrapper).attributes('disabled')).toBeUndefined();
    });
});
