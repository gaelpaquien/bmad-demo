<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TagChip from '@/Components/TagChip.vue';

// Secondary menu of the Documents surface (spec-refonte-layout-documents):
// main menu | paginated document list (this menu) | active document. The list
// comes from the shared `documents` paginator prop and the active row from the
// `document` prop of `Documents/Show`; its own width is fixed and does not
// follow the main menu's collapsed mode. Page links keep the active document
// (the paginator path is the current URL).
const page = usePage();
const documents = computed(() => page.props.documents ?? { data: [], links: [] });
const activeDocumentId = computed(() => page.props.document?.id ?? null);

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
                class="sticky top-0 flex h-screen w-80 shrink-0 flex-col overflow-y-auto overflow-x-hidden border-r border-border bg-surface px-3 py-5 text-foreground"
                aria-label="Liste des documents"
                data-testid="documents-menu"
            >
                <div
                    class="mb-5 flex items-center gap-2 px-2.5 py-1 text-sm font-semibold tracking-tight"
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

                <hr class="mb-3 h-0.5 border-0 opacity-70 bg-[linear-gradient(to_right,transparent,var(--color-border)_20%,var(--color-border)_80%,transparent)]" />

                <p v-if="documents.data.length === 0" class="px-2.5 py-6 text-center text-sm text-muted">
                    Aucun document pour l'instant.
                </p>

                <template v-else>
                    <ul class="flex flex-col gap-0.5">
                        <li v-for="document in documents.data" :key="document.id">
                            <Link
                                :href="`/documents/${document.id}${documents.current_page > 1 ? `?page=${documents.current_page}` : ''}`"
                                class="flex flex-col gap-1 rounded-md px-2.5 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                                :class="document.id === activeDocumentId
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : 'text-foreground hover:bg-surface-alt'"
                                :aria-current="document.id === activeDocumentId ? 'page' : undefined"
                                :title="document.title"
                            >
                                <span class="truncate leading-tight">{{ document.title }}</span>
                                <span class="flex flex-wrap items-center gap-1 text-xs font-normal" :class="document.id === activeDocumentId ? 'text-primary-foreground' : 'text-muted'">
                                    <span>{{ formatDate(document.created_at) }}</span>
                                    <template v-if="document.tags && document.tags.length > 0">
                                        <TagChip v-for="tag in document.tags" :key="tag.id" :name="tag.name" />
                                    </template>
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <Pagination :links="documents.links" />
                </template>
            </nav>
            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
