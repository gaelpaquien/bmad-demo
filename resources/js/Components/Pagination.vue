<script setup>
import { Link } from '@inertiajs/vue3';

// Page links of a Laravel paginator (`links`: previous, numbered pages,
// next), shared by the Bibliothèque and Recherche
// (spec-recherche-bornes-pagination). Hidden when there is only one page
// (previous + page 1 + next).
defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav
        v-if="links && links.length > 3"
        class="mt-4 flex flex-wrap items-center justify-center gap-1"
        aria-label="Pagination"
    >
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="!link.url"
                class="rounded-md px-3 py-1.5 text-sm text-muted opacity-50"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                class="rounded-md px-3 py-1.5 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="link.active
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
