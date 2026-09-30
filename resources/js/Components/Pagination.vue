<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Page links of a Laravel paginator (`links`: previous, numbered pages,
// next), shared by the Documents list and the Recherche
// (spec-recherche-bornes-pagination). Hidden when there is only one page
// (previous + page 1 + next). The paginator's own first/last labels are
// English ("Previous"/"Next"), so those two are rendered by position with a
// French label and a chevron; numbered pages and ellipses keep their label.
//
// `compact` is for narrow containers (the Documents menu): previous/next
// shrink to icon buttons (label kept for screen readers and as tooltip) and
// the numbered pages collapse into a single "Page X sur Y" indicator between
// them, so the bar always fits on one line.
const props = defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
    // Optional paginator totals: when `total` is given, a "4–6 sur 27
    // documents" line (shown range out of all existing documents) tops the bar.
    total: {
        type: Number,
        default: null,
    },
    from: {
        type: Number,
        default: null,
    },
    to: {
        type: Number,
        default: null,
    },
});

const countLabel = computed(() => {
    if (props.total === null) {
        return '';
    }

    const noun = props.total > 1 ? 'documents' : 'document';

    if (props.from === null || props.to === null || (props.from === 1 && props.to === props.total)) {
        return `${props.total} ${noun}`;
    }

    return `${props.from}–${props.to} sur ${props.total} ${noun}`;
});

const BASE_CLASSES = 'inline-flex min-w-9 items-center justify-center gap-1 rounded-md border px-3 py-1.5 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background';
const LINK_CLASSES = `${BASE_CLASSES} border-border text-foreground hover:bg-surface-alt`;
const ACTIVE_CLASSES = `${BASE_CLASSES} border-primary bg-primary font-semibold text-primary-foreground`;
const DISABLED_CLASSES = `${BASE_CLASSES} border-transparent text-muted opacity-50`;

const lastIndex = computed(() => props.links.length - 1);

// Only the previous/next buttons (first and last link) in compact mode.
const visibleLinks = computed(() => props.links
    .map((link, index) => ({ link, index }))
    .filter(({ index }) => !props.compact || index === 0 || index === lastIndex.value));

const currentPage = computed(() => props.links.find((link) => link.active)?.label ?? '1');
const lastPage = computed(() => props.links[lastIndex.value - 1]?.label ?? '1');
</script>

<template>
    <nav
        v-if="links && links.length > 3"
        class="flex flex-wrap items-center gap-1"
        :class="[compact ? 'mt-3 justify-between' : 'mt-4 justify-center']"
        aria-label="Pagination"
    >
        <template v-for="{ link, index } in visibleLinks" :key="index">
            <span
                v-if="compact && index === lastIndex"
                class="flex-1 whitespace-nowrap text-center text-sm text-muted"
                data-testid="pagination-status"
            >
                Page {{ currentPage }} sur {{ lastPage }}
            </span>
            <component
                :is="link.url ? Link : 'span'"
                v-bind="link.url ? { href: link.url, preserveScroll: true } : {}"
                :class="[
                    link.active ? ACTIVE_CLASSES : (link.url ? LINK_CLASSES : DISABLED_CLASSES),
                ]"
                :title="compact && (index === 0 || index === lastIndex) ? (index === 0 ? 'Page précédente' : 'Page suivante') : undefined"
                :aria-current="link.active ? 'page' : undefined"
                :aria-label="index === 0 ? 'Page précédente' : (index === lastIndex ? 'Page suivante' : undefined)"
            >
                <template v-if="index === 0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    <span :class="{ 'sr-only': compact }">Précédent</span>
                </template>
                <template v-else-if="index === lastIndex">
                    <span :class="{ 'sr-only': compact }">Suivant</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </template>
                <span v-else v-html="link.label" />
            </component>
        </template>
        <p
            v-if="countLabel"
            class="mt-1 w-full text-center text-xs text-muted"
            data-testid="pagination-count"
        >
            {{ countLabel }}
        </p>
    </nav>
</template>
