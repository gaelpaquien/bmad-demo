<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TagChip from '@/Components/TagChip.vue';

defineProps({
    documents: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

function formatDate(dateString) {
    if (!dateString) {
        return '';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(dateString));
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">
                    Documents
                </h1>
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div v-if="documents.data.length === 0" class="py-16 text-center">
                    <p class="text-muted">
                        Aucun document pour l'instant.
                    </p>
                </div>

                <template v-else>
                    <ul class="border-t border-border">
                        <li v-for="document in documents.data" :key="document.id">
                            <Link
                                :href="`/documents/${document.id}`"
                                class="flex items-center gap-3 border-b border-border px-2 py-3 transition hover:rounded-sm hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                            >
                                <span class="min-w-0 flex-1 truncate font-medium text-foreground">
                                    {{ document.title }}
                                </span>
                                <div v-if="document.tags && document.tags.length > 0" class="flex shrink-0 flex-wrap gap-1">
                                    <TagChip v-for="tag in document.tags" :key="tag.id" :name="tag.name" />
                                </div>
                                <span class="w-24 shrink-0 text-right text-xs text-muted">{{ formatDate(document.created_at) }}</span>
                            </Link>
                        </li>
                    </ul>

                    <Pagination :links="documents.links" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
