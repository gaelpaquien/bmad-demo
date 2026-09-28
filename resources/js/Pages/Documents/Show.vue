<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagChip from '@/Components/TagChip.vue';
import TagSelector from '@/Components/TagSelector.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    sourceMissing: {
        type: Boolean,
        default: false,
    },
});

// The page is read-only by default (spec-refonte-page-consultation-document):
// an imported document's tags only become editable after an explicit
// "Modifier", and are then held as a local draft until "Enregistrer" sends
// them in one PATCH /documents/{id}/tags (sync semantics) or "Annuler"
// drops them. A created document never enters this mode — its "Modifier"
// opens the editor, which already handles tags.
const isEditing = ref(false);
const draftTagIds = ref([]);
const isSavingTags = ref(false);
const tagsError = ref('');

const documentTags = computed(() => props.document.tags ?? []);

// Each mode switch removes the button that triggered it from the DOM, so
// focus is moved explicitly rather than falling back to <body>: into the
// tag input on entry, back onto "Modifier" on exit.
const tagsEditorRef = ref(null);
const editButtonRef = ref(null);

async function startEditing() {
    draftTagIds.value = documentTags.value.map((tag) => tag.id);
    tagsError.value = '';
    isEditing.value = true;
    await nextTick();
    tagsEditorRef.value?.querySelector('input')?.focus();
}

async function leaveEditing() {
    isEditing.value = false;
    draftTagIds.value = [];
    tagsError.value = '';
    await nextTick();
    editButtonRef.value?.focus();
}

function cancelEditing() {
    if (isSavingTags.value) {
        return;
    }

    leaveEditing();
}

function saveTags() {
    // A rapid double-click can fire before Vue re-renders `:disabled` onto
    // the button — guard here too so a second click never sends a second
    // PATCH.
    if (isSavingTags.value) {
        return;
    }

    isSavingTags.value = true;
    tagsError.value = '';

    router.patch(`/documents/${props.document.id}/tags`, { tag_ids: [...draftTagIds.value] }, {
        preserveScroll: true,
        preserveState: true,
        // updateTags() answers with back(), so props.document.tags is
        // already refreshed by the time the page returns to consultation.
        onSuccess: () => {
            leaveEditing();
        },
        // Stay in edit mode with the local selection intact so the user
        // can retry or cancel.
        onError: (errors) => {
            tagsError.value = errors.tag_ids ?? 'Impossible de mettre à jour les tags.';
        },
        onFinish: () => {
            isSavingTags.value = false;
        },
    });
}

// Inertia can reuse this component instance across a <Link> navigation
// from one document to another — always land back in consultation mode on
// the new document rather than keeping the previous one's draft.
watch(() => props.document.id, () => {
    isEditing.value = false;
    draftTagIds.value = [];
    tagsError.value = '';
});

const formattedDate = computed(() => {
    if (!props.document.created_at) {
        return '';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(props.document.created_at));
});

const previewUrl = computed(() => `/documents/${props.document.id}/preview`);
const downloadUrl = computed(() => `/documents/${props.document.id}/download`);
const exportPdfUrl = computed(() => `/documents/${props.document.id}/export/pdf`);

// Read-only list (spec-3-3, FR13; Boundaries & Constraints: "pas d'ajout/
// retrait depuis Show.vue", UX-DR16) — each attachment's own preview/
// download links, same shape as the document's own downloadUrl above, just
// scoped to one attachment.
const attachments = computed(() => props.document.attachments ?? []);
const attachmentPreviewUrl = (attachment) => `/documents/${props.document.id}/attachments/${attachment.id}/preview`;
const attachmentDownloadUrl = (attachment) => `/documents/${props.document.id}/attachments/${attachment.id}/download`;

// A created document (spec-2-1) has no original file on disk — `file_path`
// is deliberately null (AD-9) — so it is never subject to the
// file-missing/download flow below; its content lives in `content_html`
// and renders directly instead of through the file preview/iframe path.
const isCreated = computed(() => props.document.source === 'created');

// FR11/spec-2-4: a fetch() rather than a plain <a href> so success/failure
// can be told apart from the HTTP status (UX-DR20) — a bare <a> would hide
// a 422 behind Laravel's default error page instead of surfacing it here.
// The button itself always stays visible/enabled outside of an in-flight
// request (UX-DR11) so a failed export can always be retried immediately.
const isExportingPdf = ref(false);
const exportPdfError = ref('');
const showExportPdfToast = ref(false);
let exportPdfToastTimer = null;

// Same-origin request — the Content-Disposition header set by
// DocumentController::exportPdf() is readable from fetch() without any
// CORS exposure list, so the already-slugified filename it carries (with
// its own `document-{id}` fallback for an empty title, Str::slug()) is
// parsed from there rather than re-derived from props.document.title on
// the client, which could diverge (different empty-title fallback, no
// character sanitization).
function filenameFromContentDisposition(header) {
    if (!header) {
        return null;
    }

    const match = /filename="?([^";]+)"?/i.exec(header);

    return match ? match[1] : null;
}

async function exportToPdf() {
    if (isExportingPdf.value) {
        return;
    }

    isExportingPdf.value = true;
    exportPdfError.value = '';

    try {
        const response = await fetch(exportPdfUrl.value, {
            headers: { Accept: 'application/pdf' },
        });

        if (!response.ok) {
            exportPdfError.value = 'Export PDF impossible pour l\'instant, merci de réessayer.';
            return;
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const filename = filenameFromContentDisposition(response.headers.get('content-disposition'))
            ?? `${props.document.title || 'document'}.pdf`;

        // Success is delivered as a blob (not a navigation), so the
        // download is triggered manually via a temporary anchor rather
        // than letting the browser handle Content-Disposition itself
        // (Design Notes, spec-2-4).
        const link = window.document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        window.document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);

        triggerExportPdfToast();
    } catch (error) {
        exportPdfError.value = 'Export PDF impossible pour l\'instant, merci de réessayer.';
    } finally {
        isExportingPdf.value = false;
    }
}

function triggerExportPdfToast() {
    showExportPdfToast.value = true;

    if (exportPdfToastTimer) {
        clearTimeout(exportPdfToastTimer);
    }

    exportPdfToastTimer = setTimeout(() => {
        showExportPdfToast.value = false;
        exportPdfToastTimer = null;
    }, 3000);
}

// Deletion always requires explicit confirmation (UX-DR21, AD-15) — no
// undo/SoftDeletes, so the dialog is the only guard against an accidental
// destructive request. Standard modal accessibility pattern: role="dialog",
// a focus trap, Escape to cancel, and focus restored to the trigger on close.
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const deleteError = ref('');
const deleteDialogRef = ref(null);
const cancelDeleteButtonRef = ref(null);
let deleteTriggerElement = null;

async function openDeleteDialog(event) {
    deleteTriggerElement = event?.currentTarget ?? document.activeElement;
    deleteError.value = '';
    isDeleteDialogOpen.value = true;
    await nextTick();
    // Default focus lands on "Annuler", not the destructive action itself —
    // a stray Enter press right after opening must never confirm deletion.
    cancelDeleteButtonRef.value?.focus();
}

function closeDeleteDialog() {
    if (isDeleting.value) {
        // A delete request is in flight: ignore the close request rather
        // than letting the user believe they cancelled while the deletion
        // still completes underneath them.
        return;
    }

    isDeleteDialogOpen.value = false;
    deleteError.value = '';

    if (deleteTriggerElement instanceof HTMLElement) {
        deleteTriggerElement.focus();
    }
}

function confirmDelete() {
    if (isDeleting.value) {
        // A rapid double-click can fire before Vue re-renders the
        // `:disabled` attribute onto the Confirm button — guard here too
        // so a second click never sends a second DELETE request.
        return;
    }

    isDeleting.value = true;
    deleteError.value = '';

    router.delete(`/documents/${props.document.id}`, {
        onError: () => {
            deleteError.value = 'Impossible de supprimer le document.';
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function onDeleteDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeDeleteDialog();
        return;
    }

    if (event.key === 'Tab') {
        trapDeleteDialogFocus(event);
    }
}

function trapDeleteDialogFocus(event) {
    const focusable = deleteDialogRef.value?.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (!focusable || focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

const isPdf = computed(() => props.document.mime_type === 'application/pdf');
const isOfficeDocument = computed(() => [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
].includes(props.document.mime_type));

// Word/Excel go through fetch() (a binary request, not an Inertia visit) so
// "converting" and "conversion failed" can be told apart, which a bare
// <iframe src> can't do: success (2xx, application/pdf) builds a blob URL
// for the iframe; failure (non-2xx) shows the message outside the iframe.
const officePreviewState = ref('loading'); // 'loading' | 'ready' | 'error'
const officePreviewBlobUrl = ref(null);

// Inertia can reuse this component instance across a <Link> navigation from
// one document to another (same page component, new props, no remount) —
// a bare onMounted() would then keep showing the previous document's
// preview. requestSequence guards both that case and a slower one: it's
// bumped every time a load starts (by loadOfficePreview itself, or by the
// watcher below on navigation/unmount), so a fetch()/blob() continuation
// that resolves after it's been superseded — by a newer load, or by unmount
// — is detected and never touches officePreviewState or creates a
// never-released blob URL.
let requestSequence = 0;

function revokeOfficePreviewBlobUrl() {
    if (officePreviewBlobUrl.value) {
        URL.revokeObjectURL(officePreviewBlobUrl.value);
        officePreviewBlobUrl.value = null;
    }
}

async function loadOfficePreview() {
    const requestId = ++requestSequence;

    revokeOfficePreviewBlobUrl();
    officePreviewState.value = 'loading';

    try {
        const response = await fetch(previewUrl.value, {
            headers: { Accept: 'application/pdf' },
        });

        if (requestId !== requestSequence) {
            return;
        }

        if (!response.ok || !(response.headers.get('content-type') || '').includes('application/pdf')) {
            officePreviewState.value = 'error';
            return;
        }

        const blob = await response.blob();

        if (requestId !== requestSequence) {
            return;
        }

        officePreviewBlobUrl.value = URL.createObjectURL(blob);
        officePreviewState.value = 'ready';
    } catch (error) {
        if (requestId === requestSequence) {
            officePreviewState.value = 'error';
        }
    }
}

function refreshPreview() {
    if (!props.sourceMissing && isOfficeDocument.value) {
        loadOfficePreview();
    } else {
        // Not (or no longer) an Office document under preview — invalidate
        // any load still in flight for the previous document and drop its
        // blob URL rather than leaving it cached but unreferenced.
        requestSequence += 1;
        revokeOfficePreviewBlobUrl();
        officePreviewState.value = 'loading';
    }
}

onMounted(refreshPreview);

// Re-run whenever the component is reused for a different document (Inertia
// navigating Show -> Show without unmounting).
watch(() => props.document.id, refreshPreview);

onBeforeUnmount(() => {
    requestSequence += 1;
    revokeOfficePreviewBlobUrl();

    if (exportPdfToastTimer) {
        clearTimeout(exportPdfToastTimer);
    }
});
</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <!-- Title and actions share one line: the title truncates
                 (full text in `title`), the actions never shrink. Full
                 width below xl so the actions still fit, 3/4 above: wide
                 enough for a readable PDF preview without stretching the
                 page edge to edge (same container on every page). -->
            <div class="flex items-center gap-4">
                <h1 class="min-w-0 flex-1 truncate text-2xl font-semibold text-foreground" :title="document.title">
                    {{ document.title }}
                </h1>

                <div v-if="isEditing" class="flex shrink-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSavingTags"
                        @click="saveTags"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        {{ isSavingTags ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-foreground/40 px-4 py-2 text-sm font-medium text-foreground hover:bg-foreground/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSavingTags"
                        @click="cancelEditing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                        Annuler
                    </button>
                </div>

                <!-- Same actions, same order for both document types:
                     Télécharger · Modifier · Supprimer. -->
                <div v-else class="flex shrink-0 items-center gap-3">
                    <!-- A created document has no original file (AD-9):
                         "Télécharger" exports its content_html to PDF
                         (FR11/spec-2-4). Only :disabled changes while
                         exporting or after a failure (UX-DR11), so a retry
                         never requires a page reload. -->
                    <button
                        v-if="isCreated"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isExportingPdf"
                        @click="exportToPdf"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M12 3v12" />
                            <path d="m7 10 5 5 5-5" />
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        </svg>
                        {{ isExportingPdf ? 'Téléchargement…' : 'Télécharger' }}
                    </button>
                    <a
                        v-else-if="!sourceMissing"
                        :href="downloadUrl"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M12 3v12" />
                            <path d="m7 10 5 5 5-5" />
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        </svg>
                        Télécharger
                    </a>
                    <button
                        v-else
                        type="button"
                        disabled
                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-md bg-surface-alt px-4 py-2 text-sm font-medium text-muted"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M12 3v12" />
                            <path d="m7 10 5 5 5-5" />
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        </svg>
                        Télécharger
                    </button>

                    <!-- One "Modifier" per document: a created document
                         reopens the editor (which already handles tags,
                         spec-2-3); an imported one switches this page into
                         tag edit mode. -->
                    <Link
                        v-if="isCreated"
                        :href="`/documents/${document.id}/edit`"
                        class="inline-flex items-center gap-2 rounded-md border border-foreground/40 px-4 py-2 text-sm font-medium text-foreground hover:bg-foreground/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                        Modifier
                    </Link>
                    <button
                        v-else
                        ref="editButtonRef"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-foreground/40 px-4 py-2 text-sm font-medium text-foreground hover:bg-foreground/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        @click="startEditing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                        Modifier
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-red-600 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-950/30"
                        @click="openDeleteDialog"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M3 6h18" />
                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        </svg>
                        Supprimer
                    </button>
                </div>
            </div>

            <p v-if="exportPdfError" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">
                {{ exportPdfError }}
            </p>

            <dl class="mt-6 space-y-2 text-sm text-foreground">
                <div class="flex gap-2">
                    <dt class="font-medium">Ajouté le :</dt>
                    <dd>{{ formattedDate }}</dd>
                </div>
                <!-- Empty rows are hidden entirely, except Tags while its
                     edit mode is open (the TagSelector lives there). -->
                <div v-if="documentTags.length > 0 || isEditing" class="flex items-start gap-2">
                    <dt class="font-medium" :class="{ 'mt-2': isEditing }">Tags :</dt>
                    <dd v-if="isEditing" ref="tagsEditorRef" class="w-full max-w-md">
                        <TagSelector
                            v-model="draftTagIds"
                            :disabled="isSavingTags"
                            :show-label="false"
                            placeholder="Ajouter un tag…"
                        />
                        <p v-if="tagsError" class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">
                            {{ tagsError }}
                        </p>
                    </dd>
                    <dd v-else class="flex flex-wrap gap-2">
                        <TagChip v-for="tag in documentTags" :key="tag.id" :name="tag.name" />
                    </dd>
                </div>
                <div v-if="attachments.length > 0" class="flex items-start gap-2">
                    <dt class="mt-2 font-medium">Pièces jointes :</dt>
                    <dd class="w-full max-w-xs">
                        <ul class="mt-2 flex flex-col gap-2">
                            <li
                                v-for="attachment in attachments"
                                :key="attachment.id"
                                class="flex items-center justify-between gap-2 rounded-md bg-surface-alt px-3 py-2 text-sm text-foreground"
                            >
                                <span class="truncate" :title="attachment.original_filename">
                                    {{ attachment.original_filename }}
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <a
                                        :href="attachmentPreviewUrl(attachment)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                                    >
                                        Aperçu
                                    </a>
                                    <a
                                        :href="attachmentDownloadUrl(attachment)"
                                        class="text-xs text-muted hover:text-foreground hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                                    >
                                        Télécharger
                                    </a>
                                </span>
                            </li>
                        </ul>
                    </dd>
                </div>
            </dl>

            <div class="mt-8">
                <!-- eslint-disable-next-line vue/no-v-html -- content authored by the same local user in the app's own WYSIWYG editor (spec-2-1); no auth boundary exists in v1 (NFR3). -->
                <div
                    v-if="isCreated"
                    class="tiptap-content min-h-[200px] rounded-md border border-border bg-background px-4 py-3 text-sm text-foreground"
                    v-html="document.content_html"
                ></div>

                <div
                    v-else-if="sourceMissing"
                    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    Fichier source introuvable ou illisible. La prévisualisation n'est pas disponible.
                </div>

                <iframe
                    v-else-if="isPdf"
                    :src="previewUrl"
                    class="h-[75vh] w-full rounded-md border border-border"
                    title="Aperçu du document"
                ></iframe>

                <template v-else-if="isOfficeDocument">
                    <div
                        v-if="officePreviewState === 'loading'"
                        class="flex items-center gap-2 rounded-md border border-border p-4 text-sm text-muted"
                    >
                        Conversion de l'aperçu en cours…
                    </div>
                    <div
                        v-else-if="officePreviewState === 'error'"
                        class="rounded-md border border-border p-4 text-sm text-muted"
                    >
                        Aperçu indisponible pour ce fichier.
                    </div>
                    <iframe
                        v-else
                        :src="officePreviewBlobUrl"
                        class="h-[75vh] w-full rounded-md border border-border"
                        title="Aperçu du document"
                    ></iframe>
                </template>

                <div
                    v-else
                    class="rounded-md border border-border p-4 text-sm text-muted"
                >
                    Aperçu indisponible pour ce type de document.
                </div>
            </div>
        </div>

        <div
            v-if="isDeleteDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown="onDeleteDialogKeydown"
        >
            <div
                ref="deleteDialogRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-dialog-title"
                aria-describedby="delete-dialog-description"
                class="w-full max-w-md rounded-lg bg-surface p-6 shadow-xl"
            >
                <h2 id="delete-dialog-title" class="text-lg font-semibold text-foreground">
                    Supprimer ce document ?
                </h2>
                <p id="delete-dialog-description" class="mt-2 text-sm text-muted">
                    « {{ document.title }} » sera supprimé définitivement, avec son fichier et son aperçu. Cette action est irréversible.
                </p>

                <p v-if="deleteError" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert">
                    {{ deleteError }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        ref="cancelDeleteButtonRef"
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-foreground hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-background"
                        :disabled="isDeleting"
                        @click="closeDeleteDialog"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-500 dark:hover:bg-red-400 dark:focus-visible:ring-background"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        {{ isDeleting ? 'Suppression…' : 'Supprimer' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Minimal, purpose-built toast (spec-2-4 Design Notes: no
             existing toast component in the project) — auto-dismisses via
             triggerExportPdfToast()'s timer, never blocks interaction. -->
        <div class="fixed inset-x-0 bottom-4 z-40 flex flex-col items-center gap-2 px-4">
            <div
                v-if="showExportPdfToast"
                role="status"
                aria-live="polite"
                class="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background shadow-lg"
            >
                Export PDF généré.
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Mirrors Editor.vue's tiptap-content rules so a created document's
   headings/lists/tables render the same way they looked while drafting. */
:deep(.tiptap-content h1) {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0.75rem 0 0.5rem;
}

:deep(.tiptap-content p) {
    margin: 0.5rem 0;
}

:deep(.tiptap-content img) {
    max-width: 100%;
    height: auto;
    margin: 0.75rem 0;
    border-radius: 0.25rem;
}

:deep(.tiptap-content ul) {
    list-style: disc;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

:deep(.tiptap-content ol) {
    list-style: decimal;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

:deep(.tiptap-content table) {
    border-collapse: collapse;
    width: 100%;
    margin: 0.75rem 0;
}

:deep(.tiptap-content table td),
:deep(.tiptap-content table th) {
    border: 1px solid #d4d4d4;
    padding: 0.375rem 0.5rem;
}

:deep(.tiptap-content table th) {
    background-color: #f5f5f5;
    font-weight: 600;
    text-align: left;
}

:global(.dark) :deep(.tiptap-content table td),
:global(.dark) :deep(.tiptap-content table th) {
    border-color: #404040;
}

:global(.dark) :deep(.tiptap-content table th) {
    background-color: #262626;
}
</style>
