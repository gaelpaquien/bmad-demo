<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TagChip from '@/Components/TagChip.vue';

// Secondary menu of the Documents surface (spec-refonte-layout-documents):
// main menu | paginated document list (this menu) | active document. The list
// comes from the shared `documents` paginator prop and the active row from the
// `document` prop of `Documents/Show`; its own width is fixed and does not
// follow the main menu's collapsed mode. Page links keep the active document
// (the paginator path is the current URL).
// Beyond this many tags a row shows a "+X" chip (X = the hidden tags), its
// tooltip naming them, so a heavily tagged document stays compact.
const MAX_VISIBLE_TAGS = 2;

const page = usePage();
const documents = computed(() => page.props.documents ?? { data: [], links: [] });
const activeDocumentId = computed(() => page.props.document?.id ?? null);

// While a visit heads to another page of the list (pagination click), the
// rows are replaced by placeholders so the change is softer than a sudden
// swap. A row click keeps the same list page, so it never triggers it.
// The response usually lands within a few ms, which would make the skeleton
// flash: once shown, it stays at least SKELETON_MIN_DURATION_MS.
const SKELETON_MIN_DURATION_MS = 400;
const isListLoading = ref(false);
const skeletonRowCount = computed(() => Math.max(documents.value.per_page ?? documents.value.data.length, 1));
let skeletonShownAt = 0;
let skeletonHideTimer = null;
let trackedVisit = null;

const stopListeningStart = router.on('start', (event) => {
    const { url, only } = event.detail.visit;
    const requestedPage = Number(url.searchParams.get('page'));

    // Only a visit that explicitly asks for another list page counts: the
    // extraction polling (partial `router.reload`) and any URL without `page`
    // (a document opened on a page computed server-side) must not flash it.
    if (only?.length > 0 || !requestedPage || requestedPage === (documents.value.current_page ?? 1)) {
        return;
    }

    clearTimeout(skeletonHideTimer);
    trackedVisit = event.detail.visit;
    skeletonShownAt = Date.now();
    isListLoading.value = true;
});
// Only the finish of the visit that showed the skeleton hides it: a
// background visit ending meanwhile (extraction polling) must not cut it short.
const stopListeningFinish = router.on('finish', (event) => {
    if (!isListLoading.value || event.detail.visit !== trackedVisit) {
        return;
    }

    clearTimeout(skeletonHideTimer);
    skeletonHideTimer = setTimeout(() => {
        isListLoading.value = false;
    }, Math.max(0, SKELETON_MIN_DURATION_MS - (Date.now() - skeletonShownAt)));
});

onBeforeUnmount(() => {
    stopListeningStart();
    stopListeningFinish();
    clearTimeout(skeletonHideTimer);
});

function formatDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(dateString));
}
</script>

<template>
    <AppLayout>
        <div class="flex min-h-screen">
            <nav
                class="sticky top-0 flex h-screen w-80 shrink-0 flex-col overflow-y-auto overflow-x-hidden themed-scrollbar border-r border-border bg-surface px-3 py-5 text-foreground"
                aria-label="Liste des documents"
                data-testid="documents-menu"
            >
                <div
                    class="mb-5 flex shrink-0 items-center gap-2 px-2.5 py-1 text-sm font-semibold tracking-tight"
                    data-testid="documents-menu-title"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                        <path d="M14 3v5h5" />
                        <line x1="9" y1="13" x2="15" y2="13" />
                        <line x1="9" y1="17" x2="15" y2="17" />
                    </svg>
                    <span class="whitespace-nowrap">Documents</span>
                </div>

                <hr class="mb-3 h-0.5 shrink-0 border-0 opacity-70 bg-[linear-gradient(to_right,var(--color-border)_60%,transparent)]" />

                <ul v-if="isListLoading" class="flex flex-col gap-0.5" aria-busy="true" data-testid="documents-skeleton">
                    <li v-for="row in skeletonRowCount" :key="row" class="flex animate-pulse flex-col gap-1.5 rounded-md px-2.5 py-2">
                        <div class="h-3.5 w-3/4 rounded bg-border"></div>
                        <div class="h-4 w-1/2 rounded-sm bg-border opacity-70"></div>
                        <div class="h-3 w-1/3 rounded bg-border opacity-70"></div>
                    </li>
                </ul>

                <p v-else-if="documents.data.length === 0" class="px-2.5 py-6 text-center text-sm text-muted">
                    Aucun document pour l'instant.
                </p>

                <ul v-else class="flex flex-col gap-0.5">
                    <li v-for="(document, index) in documents.data" :key="document.id">
                        <!-- Light gradient separator between two rows. -->
                        <hr
                            v-if="index > 0"
                            class="mx-2.5 my-0.5 h-px border-0 opacity-70 bg-[linear-gradient(to_right,transparent,var(--color-border)_20%,var(--color-border)_80%,transparent)]"
                            data-testid="documents-row-separator"
                        />
                        <Link
                            :href="`/documents/${document.id}${documents.current_page > 1 ? `?page=${documents.current_page}` : ''}`"
                            class="flex flex-col gap-1 rounded-md px-2.5 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            :class="document.id === activeDocumentId
                                ? 'bg-primary text-primary-foreground'
                                : 'text-foreground hover:bg-border/70'"
                            :aria-current="document.id === activeDocumentId ? 'page' : undefined"
                            :title="document.title"
                        >
                            <span class="truncate font-semibold leading-tight">{{ document.title }}</span>
                            <span v-if="document.tags && document.tags.length > 0" class="flex flex-wrap items-center gap-1" data-testid="documents-row-tags">
                                <TagChip v-for="tag in document.tags.slice(0, MAX_VISIBLE_TAGS)" :key="tag.id" :name="tag.name" size="small" />
                                <TagChip
                                    v-if="document.tags.length > MAX_VISIBLE_TAGS"
                                    :name="`+${document.tags.length - MAX_VISIBLE_TAGS}`"
                                    size="small"
                                    :title="document.tags.slice(MAX_VISIBLE_TAGS).map((tag) => tag.name).join(', ')"
                                    data-testid="documents-row-tags-more"
                                />
                            </span>
                            <span class="text-xs" :class="document.id === activeDocumentId ? 'text-primary-foreground' : 'text-muted'" data-testid="documents-row-date">
                                {{ formatDate(document.created_at) }}
                            </span>
                        </Link>
                    </li>
                </ul>

                <!-- Separator + pagination stay in place while the skeleton
                     shows, so the menu does not jump. -->
                <template v-if="documents.data.length > 0 && documents.links.length > 3">
                    <hr
                        class="mt-4 h-px shrink-0 border-0 opacity-70 bg-[linear-gradient(to_right,transparent,var(--color-border)_20%,var(--color-border)_80%,transparent)]"
                        data-testid="documents-pagination-separator"
                    />
                    <Pagination
                        :links="documents.links"
                        :total="documents.total"
                        :from="documents.from"
                        :to="documents.to"
                        compact
                    />
                </template>
            </nav>
            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
