<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useFileDropZone } from '@/Composables/useFileDropZone';
import FieldRequirement from '@/Components/FieldRequirement.vue';

// Retractable side panel mounted in Editor.vue (spec-3-3, FR13) — never on
// Show.vue, which only ever renders a plain read-only list (Boundaries &
// Constraints: "pas d'ajout/retrait depuis Show.vue"). Two modes, chosen by
// the host (Editor.vue) from whether the document already has an id:
//
// - `immediate`: the document is already saved. Every add/remove hits the
//   server right away (AttachDocumentFileAction/DetachDocumentFileAction)
//   through a standard Inertia visit — `preserveState`/`preserveScroll`
//   throughout so the title/editor content being edited elsewhere on the
//   same page is never disturbed (ajout/retrait ne touche jamais
//   content_html). The
//   `attachments` prop itself is what refreshes after each request — this
//   component holds no server-authoritative local copy of it.
// - `draft`: the document doesn't exist yet. A file is uploaded to a
//   temporary, draft-token-keyed area (UploadDraftAttachmentAction) with no
//   row created yet — this component tracks the kept list itself via
//   `v-model:attachments` (`update:attachments`) so the host can include it
//   in the `draft_attachments` payload sent to "Enregistrer". Removing one
//   here is a pure local `splice` — no request at all (Boundaries &
//   Constraints: a removed draft file is deliberately left behind in
//   `tmp/{token}/attachments/`, same accepted gap as draft images).
//
// `v-model:uploading` (`update:uploading`) mirrors `isUploading` outward so
// the host can disable "Enregistrer" while a draft upload is still in
// flight (code review finding) — without it, a save mid-upload could
// silently exclude that file from `draft_attachments`.
const props = defineProps({
    attachments: {
        type: Array,
        default: () => [],
    },
    mode: {
        type: String,
        required: true,
        validator: (value) => ['immediate', 'draft'].includes(value),
    },
    // Required when mode === 'immediate' — the id every attach/detach/
    // preview/download URL is built from.
    documentId: {
        type: [Number, String],
        default: null,
    },
    // Required when mode === 'draft' — the same client-generated token
    // Editor.vue already uses for inline images, keying where a draft
    // attachment's file temporarily lands.
    draftToken: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:attachments', 'update:uploading']);

const ACCEPTED_LABEL = 'PDF, Word, Excel';

// Mirrors DocumentAttachment::MAX_PER_DOCUMENT — the server re-validates at
// every entry point, this only saves a round-trip.
const MAX_ATTACHMENTS = 10;

const MAX_FILE_SIZE_LABEL = '20 Mo';

const { isDragging, validationError, onDragover, onDragleave, filesFromDropEvent, filesFromInputEvent } = useFileDropZone({
    acceptedExtensions: ['pdf', 'docx', 'xlsx'],
    acceptedLabel: ACCEPTED_LABEL,
    maxFileSizeBytes: 20 * 1024 * 1024,
    maxFileSizeLabel: MAX_FILE_SIZE_LABEL,
});

// Expanded by default — the drop zone must be visible without an extra
// click. An empty list renders nothing at all below it.
// "Rétractable" describes the toggle affording collapse, not a
// collapsed-by-default starting state.
const isOpen = ref(true);
const panelId = `attachments-panel-${Math.random().toString(36).slice(2)}`;

function toggleOpen() {
    isOpen.value = !isOpen.value;
}

const fileInputRef = ref(null);

function openFilePicker() {
    fileInputRef.value?.click();
}

const clientErrors = ref([]);
const isUploading = ref(false);

// Surfaced to the host (Editor.vue, `v-model:uploading`) so "Enregistrer"
// can be disabled while a draft attachment upload is still in flight (code
// review finding) — without this, a save triggered mid-upload could exclude
// that file from `draft_attachments` with no error ever shown.
watch(isUploading, (value) => {
    emit('update:uploading', value);
});

// --- Mode immediate : useForm(), un attach/detach par requête réelle -------

const immediateForm = useForm({ file: null });

function attachImmediateFile(file) {
    immediateForm.file = file;
    immediateForm.post(`/documents/${props.documentId}/attachments`, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            immediateForm.reset();
        },
        onError: () => {
            clientErrors.value.push(`${file.name} : ${immediateForm.errors.file ?? 'Impossible de joindre ce fichier.'}`);
        },
        onCancel: () => abandonQueue(file),
        onFinish: uploadNextQueuedFile,
    });
}

const detachingAttachmentId = ref(null);

function detachImmediateAttachment(attachment) {
    if (detachingAttachmentId.value !== null) {
        return;
    }

    detachingAttachmentId.value = attachment.id;

    router.delete(`/documents/${props.documentId}/attachments/${attachment.id}`, {
        preserveState: true,
        preserveScroll: true,
        onError: () => {
            clientErrors.value = ['Impossible de retirer cette pièce jointe.'];
        },
        onFinish: () => {
            detachingAttachmentId.value = null;
        },
    });
}

// --- Mode draft : upload vers la zone temporaire, liste tenue localement ---

const page = usePage();

function uploadDraftFile(file) {
    router.post('/documents/create/attachments', {
        draft_token: props.draftToken,
        file,
    }, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            clientErrors.value.push(`${file.name} : ${errors.file ?? errors.draft_token ?? 'Impossible de joindre ce fichier.'}`);
        },
        onCancel: () => abandonQueue(file),
        onFinish: uploadNextQueuedFile,
    });
}

// Sole point where a draft-uploaded attachment actually reaches the local
// list — fired once the upload's Inertia redirect lands back on this same
// page and the shared flash.uploadedAttachment prop carries the freshly
// stored file's filename/original_filename/mime_type (AD-13, mirrors
// Editor.vue's own flash.uploadedImage watcher).
watch(
    () => page.props.flash?.uploadedAttachment,
    (uploadedAttachment) => {
        if (props.mode !== 'draft' || !uploadedAttachment) {
            return;
        }

        // Same cross-draft/cross-tab guard as flash.uploadedImage: ignore
        // an upload meant for a different draft sharing the same session.
        if (uploadedAttachment.draftToken !== props.draftToken) {
            return;
        }

        emit('update:attachments', [...props.attachments, {
            filename: uploadedAttachment.filename,
            original_filename: uploadedAttachment.original_filename,
            mime_type: uploadedAttachment.mime_type,
        }]);
    },
);

function removeDraftAttachment(attachment) {
    emit('update:attachments', props.attachments.filter((candidate) => candidate.filename !== attachment.filename));
}

// --- Entrée commune (bouton + glisser-déposer, un ou plusieurs fichiers) ----
//
// Every endpoint takes exactly one file per request, so a multi-file
// selection is sent as a queue, one upload after the other: each request's
// onFinish starts the next one. Sequential rather than parallel so draft
// mode's flash.uploadedAttachment watcher (one global slot per response)
// never sees two uploads land at once.
//
// Any other visit (a sidebar link, "Annuler"…) interrupts the upload in
// flight, and Inertia still fires that upload's onFinish: the queue is
// emptied on cancel so the next file never starts — its own visit would in
// turn interrupt the one the user just triggered.

const uploadQueue = [];

// The interrupted file may already have reached the server before the
// cancel, hence "peut-être" for it; the queued ones were never sent.
function abandonQueue(interruptedFile) {
    const neverSentNames = uploadQueue.map((file) => file.name);
    uploadQueue.length = 0;

    clientErrors.value.push(`Envoi interrompu : ${interruptedFile.name} n'a peut-être pas été joint, vérifiez la liste.`);

    if (neverSentNames.length > 0) {
        clientErrors.value.push(neverSentNames.length > 1
            ? `${neverSentNames.join(', ')} n'ont pas été envoyés.`
            : `${neverSentNames[0]} n'a pas été envoyé.`);
    }
}

onBeforeUnmount(() => {
    uploadQueue.length = 0;
});

function uploadNextQueuedFile() {
    const file = uploadQueue.shift();

    if (!file) {
        isUploading.value = false;
        return;
    }

    if (props.mode === 'immediate') {
        attachImmediateFile(file);
    } else {
        uploadDraftFile(file);
    }
}

function handleFiles(files) {
    // Re-entrancy guard (code review finding, mirrors
    // detachImmediateAttachment()'s own detachingAttachmentId check) — a
    // second drop/pick while a previous batch is still uploading is
    // silently ignored rather than firing overlapping requests.
    if (isUploading.value || files.length === 0) {
        return;
    }

    clientErrors.value = [];
    immediateForm.clearErrors('file');

    // An invalid file is reported by name and skipped; the valid ones of the
    // same selection are still sent.
    const validFiles = files.filter((file) => {
        const error = validationError(file);

        if (error) {
            clientErrors.value.push(files.length > 1 ? `${file.name} : ${error}` : error);
        }

        return !error;
    });

    if (validFiles.length === 0) {
        return;
    }

    // The valid files are refused as a whole rather than silently truncated
    // when they don't fit — the user then knows exactly how many can still
    // be added. Invalid files never count against the limit.
    const remainingSlots = MAX_ATTACHMENTS - props.attachments.length;

    if (validFiles.length > remainingSlots) {
        clientErrors.value.push(limitErrorFor(remainingSlots));
        return;
    }

    uploadQueue.push(...validFiles);
    isUploading.value = true;
    uploadNextQueuedFile();
}

function limitErrorFor(remainingSlots) {
    if (remainingSlots <= 0) {
        return `${MAX_ATTACHMENTS} pièces jointes maximum par document.`;
    }

    return remainingSlots === 1
        ? `${MAX_ATTACHMENTS} pièces jointes maximum par document : encore 1 possible.`
        : `${MAX_ATTACHMENTS} pièces jointes maximum par document : encore ${remainingSlots} possibles.`;
}

function onInputChange(event) {
    handleFiles(filesFromInputEvent(event));
}

function onDrop(event) {
    handleFiles(filesFromDropEvent(event));
}

function removeAttachment(attachment) {
    // A stale "10 pièces jointes maximum" (or any earlier rejection) must
    // not outlive the removal that brings the list back under the limit —
    // except while a batch is still uploading, whose per-file errors the
    // user may not have read yet.
    if (!isUploading.value) {
        clientErrors.value = [];
    }

    if (props.mode === 'immediate') {
        detachImmediateAttachment(attachment);
    } else {
        removeDraftAttachment(attachment);
    }
}

// In immediate mode a detach is an Inertia visit that would interrupt the
// upload in flight, so "Retirer" waits for the batch to finish. A draft
// removal is a local splice and stays available.
function isRemoveDisabled(attachment) {
    return props.mode === 'immediate' && (isUploading.value || detachingAttachmentId.value === attachment.id);
}

const previewUrl = (attachment) => `/documents/${props.documentId}/attachments/${attachment.id}/preview`;
const downloadUrl = (attachment) => `/documents/${props.documentId}/attachments/${attachment.id}/download`;

// Re-evaluated on every add/remove, so "Parcourir" re-enables itself as soon
// as a removal frees a slot.
const isLimitReached = computed(() => props.attachments.length >= MAX_ATTACHMENTS);

const attachmentCountLabel = computed(() => (props.attachments.length > 0 ? ` (${props.attachments.length})` : ''));
</script>

<template>
    <div class="rounded-md border border-border bg-surface-alt">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md px-4 py-3 text-left text-sm font-medium text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
            :aria-expanded="isOpen"
            :aria-controls="panelId"
            @click="toggleOpen"
        >
            <span>Pièces jointes{{ attachmentCountLabel }}<FieldRequirement /></span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="h-4 w-4 shrink-0 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            >
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <div v-if="isOpen" :id="panelId" class="border-t border-border px-4 py-4">
            <div
                class="flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-border bg-surface p-4 text-center"
                :class="{ 'border-primary bg-primary/10': isDragging }"
                @dragover.prevent="onDragover"
                @dragleave.prevent="onDragleave"
                @drop.prevent="onDrop"
            >
                <p class="text-xs text-muted">
                    Glissez-déposez un ou plusieurs fichiers ici, ou
                </p>
                <button
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground not-disabled:hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                    :disabled="isUploading || isLimitReached"
                    @click="openFilePicker"
                >
                    Parcourir
                </button>
                <input
                    ref="fileInputRef"
                    type="file"
                    class="sr-only"
                    accept=".pdf,.docx,.xlsx"
                    multiple
                    aria-label="Sélectionner une ou plusieurs pièces jointes"
                    :disabled="isUploading || isLimitReached"
                    @change="onInputChange"
                >
                <p class="text-xs text-muted">
                    Formats acceptés : {{ ACCEPTED_LABEL }}<br>
                    {{ MAX_ATTACHMENTS }} fichiers maximum<br>
                    {{ MAX_FILE_SIZE_LABEL }} maximum par fichier
                </p>
            </div>

            <p v-if="isUploading" class="mt-2 text-xs text-muted" role="status">
                Envoi en cours…
            </p>

            <p
                v-for="(error, index) in clientErrors"
                :key="index"
                class="mt-2 text-xs text-red-600 dark:text-red-400"
                role="alert"
            >
                {{ error }}
            </p>

            <ul v-if="attachments.length > 0" class="mt-3 flex flex-col gap-2">
                <li
                    v-for="attachment in attachments"
                    :key="attachment.id ?? attachment.filename"
                    class="flex items-center justify-between gap-2 rounded-md bg-surface px-3 py-2 text-sm text-foreground"
                >
                    <span class="truncate" :title="attachment.original_filename">
                        {{ attachment.original_filename }}
                    </span>

                    <span class="flex shrink-0 items-center gap-2">
                        <template v-if="mode === 'immediate'">
                            <a
                                :href="previewUrl(attachment)"
                                target="_blank"
                                rel="noopener"
                                class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            >
                                Aperçu
                            </a>
                            <a
                                :href="downloadUrl(attachment)"
                                class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            >
                                Télécharger
                            </a>
                        </template>
                        <button
                            type="button"
                            class="rounded-sm px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-600 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-500 dark:hover:bg-red-500 dark:hover:text-white"
                            :disabled="isRemoveDisabled(attachment)"
                            :aria-label="`Retirer la pièce jointe ${attachment.original_filename}`"
                            @click="removeAttachment(attachment)"
                        >
                            Retirer
                        </button>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
