<script setup>
import { router, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';
import AttachmentsPanel from '@/Components/AttachmentsPanel.vue';
import TextInput from '@/Components/TextInput.vue';
import FieldRequirement from '@/Components/FieldRequirement.vue';
import { useFileDropZone } from '@/Composables/useFileDropZone';

// Single-form import page (spec-refonte-import-formulaire-unique): choosing
// a file only keeps it locally — nothing is sent, no document exists yet.
// Tags and draft attachments are gathered alongside, and "Enregistrer" sends
// everything in one `POST /documents` (DocumentController::store()), which
// creates the document, assigns its tags and relocates its attachments in a
// single transaction before redirecting to the document's own page.
//
// No navigation guard, no `beforeunload`: nothing is persisted before
// "Enregistrer", so leaving loses nothing server-side (Boundaries &
// Constraints). "Annuler" is only made inert while a request is in flight,
// same as Editor.vue.

const ACCEPTED_LABEL = 'PDF, Word, Excel';

const MAX_FILE_SIZE_LABEL = '20 Mo';

// Generated once, client-side (same mechanism as Editor.vue, spec-2-2/3-3) —
// keys every draft attachment uploaded before the document exists, and
// travels along with "Enregistrer" so ImportDocumentAction knows which
// tmp/{token} directory to relocate.
const draftToken = crypto.randomUUID();

const form = useForm({
    file: null,
    // Disabled and empty until a file is chosen, then prefilled with the
    // file's name minus its final extension — still editable, and reset
    // along with the file on "Retirer".
    title: '',
    tag_ids: [],
    draft_token: draftToken,
    // Populated from `attachments` right before submit().
    draft_attachments: [],
});

// Deliberately its own ref, managed through AttachmentsPanel's
// `update:attachments` emit — mirrors Editor.vue's draft mode.
const attachments = ref([]);

// Mirrors AttachmentsPanel's own `isUploading` (`v-model:uploading`): an
// attachment still uploading when "Enregistrer" is clicked would otherwise
// be silently left out of `draft_attachments`.
const isAttachmentUploading = ref(false);

const clientError = ref('');
const fileInputRef = ref(null);

const { isDragging, validationError, onDragover, onDragleave, fileFromDropEvent, fileFromInputEvent } = useFileDropZone({
    acceptedExtensions: ['pdf', 'docx', 'xlsx'],
    acceptedLabel: ACCEPTED_LABEL,
    maxFileSizeBytes: 20 * 1024 * 1024,
    maxFileSizeLabel: MAX_FILE_SIZE_LABEL,
});

const fileErrorMessage = computed(() => clientError.value || form.errors.file || '');

// Any `draft_token`/`draft_attachments[.*]` rejection has no field of its
// own to sit under — surfaced once, below the attachments panel.
const attachmentsErrorMessage = computed(() => {
    const key = Object.keys(form.errors).find((field) => field === 'draft_token' || field.startsWith('draft_attachments'));

    return key ? form.errors[key] : '';
});

// Per-tag rejections come back as `tag_ids.0`, `tag_ids.1`… — surfaced
// once, below the TagSelector, alongside a whole-list `tag_ids` error.
const tagsErrorMessage = computed(() => {
    const key = Object.keys(form.errors).find((field) => field === 'tag_ids' || field.startsWith('tag_ids.'));

    return key ? form.errors[key] : '';
});

const isBusy = computed(() => form.processing || isAttachmentUploading.value);

const canSave = computed(() => !!form.file && form.title.trim() !== '' && !isBusy.value);

// "Annuler" always leads back to the library (same mechanism as Editor.vue).
const cancelUrl = '/';

// Deletes this session's own tmp/{draftToken} draft directory server-side
// (spec-nettoyage-fichiers-orphelins-tmp, P3 point 1) — fire-and-forget:
// the DELETE is never awaited before navigating away, since the app stays
// a SPA and the underlying fetch survives the following visit (Design
// Notes). useHttp() handles CSRF automatically via the XSRF-TOKEN cookie,
// same as useForm(). The `.catch()` is only there to silence an unhandled
// promise rejection (network error, CSRF mismatch, 500) — the delete is
// already best-effort server-side (DeleteDraftDirectoryAction), and this
// click must navigate regardless of how it resolves.
function onCancelClick() {
    useHttp({}).delete(`/documents/create/draft/${draftToken}`).catch(() => {});
    router.visit(cancelUrl);
}

// "Rapport.v2.pdf" → "Rapport.v2"; a name that is only an extension
// (".pdf") is kept as-is rather than yielding an empty title. Cut to the
// server's 255-character limit (`max:255` in ImportDocumentRequest):
// `maxlength` only stops typing, never a value set from a long filename.
// Cut by character (`Array.from`), not UTF-16 unit, so an emoji at the
// boundary is never split in half. Mirrored server-side by
// ImportDocumentAction::titleFromFilename() for a submission without title.
const MAX_TITLE_LENGTH = 255;

function titleFromFilename(filename) {
    const title = filename.replace(/\.[^.]+$/, '') || filename;

    return Array.from(title).slice(0, MAX_TITLE_LENGTH).join('');
}

function handleFile(file) {
    clientError.value = '';
    form.clearErrors('file');

    const error = validationError(file);

    if (error) {
        clientError.value = error;
        form.file = null;
        form.title = '';
        return;
    }

    form.file = file;
    form.title = titleFromFilename(file.name);
    form.clearErrors('title');
}

function onInputChange(event) {
    handleFile(fileFromInputEvent(event));
    // Lets the same file be picked again after "Retirer".
    event.target.value = '';
}

function onDrop(event) {
    handleFile(fileFromDropEvent(event));
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function removeFile() {
    if (form.processing) {
        return;
    }

    form.file = null;
    form.title = '';
    form.clearErrors('file', 'title');
    clientError.value = '';
}

function submit() {
    if (!canSave.value) {
        return;
    }

    form.draft_attachments = attachments.value.map((attachment) => ({
        filename: attachment.filename,
        original_filename: attachment.original_filename,
    }));

    // preserveState keeps the chosen file, tags and attachments on a
    // server-side validation error (I/O matrix: "saisie conservée") — the
    // message then renders from form.errors.
    form.post('/documents', {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <h1 class="sr-only">Importer un document</h1>

            <div class="mb-6">
                <label for="document-title" class="mb-1 block text-sm font-medium text-foreground">
                    Titre<FieldRequirement required />
                </label>
                <TextInput
                    id="document-title"
                    v-model="form.title"
                    aria-required="true"
                    aria-describedby="document-title-hint"
                    :maxlength="MAX_TITLE_LENGTH"
                    placeholder="Titre du document"
                    :disabled="!form.file || form.processing"
                />
                <p id="document-title-hint" class="mt-1 text-xs text-muted">
                    Rempli automatiquement à partir du nom du fichier une fois le document principal ajouté, puis modifiable.
                </p>
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ form.errors.title }}
                </p>
            </div>

            <!-- Same card shape as AttachmentsPanel (header + bordered body,
                 compact dropzone, file row) so the main document and its
                 attachments read as two sections of one form rather than a
                 duplicated widget. Not collapsible: the main file is required. -->
            <section class="rounded-md border border-border bg-surface-alt" aria-labelledby="main-document-heading">
                <h2 id="main-document-heading" class="px-4 py-3 text-sm font-medium text-foreground">
                    Document principal<FieldRequirement required />
                </h2>

                <div class="border-t border-border px-4 py-4">
                    <div
                        v-if="!form.file"
                        class="flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-border bg-surface p-4 text-center"
                        :class="{ 'border-primary bg-primary/10': isDragging }"
                        @dragover.prevent="onDragover"
                        @dragleave.prevent="onDragleave"
                        @drop.prevent="onDrop"
                    >
                        <p class="text-xs text-muted">
                            Glissez-déposez un fichier ici, ou
                        </p>
                        <button
                            type="button"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground not-disabled:hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            @click="openFilePicker"
                        >
                            Parcourir
                        </button>
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="sr-only"
                            accept=".pdf,.docx,.xlsx"
                            aria-label="Sélectionner le document principal"
                            aria-required="true"
                            @change="onInputChange"
                        />
                        <p class="text-xs text-muted">
                            Formats acceptés : {{ ACCEPTED_LABEL }}<br>
                            1 fichier maximum<br>
                            {{ MAX_FILE_SIZE_LABEL }} maximum
                        </p>
                    </div>

                    <div v-else class="flex items-center justify-between gap-2 rounded-md bg-surface px-3 py-2 text-sm text-foreground">
                        <span class="truncate" :title="form.file.name">
                            {{ form.file.name }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-sm px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:bg-red-950/30"
                            :disabled="form.processing"
                            :aria-label="`Retirer le document principal ${form.file.name}`"
                            @click="removeFile"
                        >
                            Retirer
                        </button>
                    </div>

                    <p v-if="fileErrorMessage" class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">
                        {{ fileErrorMessage }}
                    </p>
                </div>
            </section>

            <div class="mt-6">
                <TagSelector v-model="form.tag_ids" :disabled="form.processing" />
                <p v-if="tagsErrorMessage" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ tagsErrorMessage }}
                </p>
            </div>

            <div class="mt-6">
                <AttachmentsPanel
                    v-model:attachments="attachments"
                    v-model:uploading="isAttachmentUploading"
                    mode="draft"
                    :draft-token="draftToken"
                />
                <p v-if="attachmentsErrorMessage" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ attachmentsErrorMessage }}
                </p>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground not-disabled:hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                    :disabled="!canSave"
                    @click="submit"
                >
                    {{ form.processing ? 'Enregistrement…' : (isAttachmentUploading ? 'Envoi de la pièce jointe…' : 'Enregistrer') }}
                </button>
                <!-- Swapped for an inert, disabled button while a save or an
                     upload is in flight (same as Editor.vue) — leaving would
                     both abort that Inertia visit and race the in-flight
                     save against the draft-directory delete triggered by
                     onCancelClick(). -->
                <button
                    v-if="isBusy"
                    type="button"
                    disabled
                    class="cursor-not-allowed rounded-md border border-foreground/40 px-4 py-2 text-sm font-medium text-foreground opacity-50"
                >
                    Annuler
                </button>
                <!-- A plain <a>, not Inertia's <Link> or a <button> — its
                     native href keeps middle-click/ctrl-click "open in new
                     tab" and the hover status-bar URL preview working, same
                     as Editor.vue. `@click.prevent` only intercepts the
                     primary left-click. -->
                <a
                    v-else
                    :href="cancelUrl"
                    class="rounded-md border border-foreground/40 px-4 py-2 text-sm font-medium text-foreground hover:bg-foreground/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    @click.prevent="onCancelClick"
                >
                    Annuler
                </a>
            </div>
        </div>
    </AppLayout>
</template>
