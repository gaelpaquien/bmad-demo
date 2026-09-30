<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Page links of a Laravel paginator (`links`: previous, numbered pages,
// next), shared by the Documents list and the Recherche
// (spec-recherche-bornes-pagination). Hidden when there is only one page
// (previous + page 1 + next). The paginator's own first/last labels are
// English ("Previous"/"Next"), so those two are rendered by position as
// chevron icon buttons (French label kept for screen readers and as tooltip).
//
// `compact` is for narrow containers (the Documents menu): the numbered
// pages collapse into a single "Page X sur Y" indicator between the two
// buttons, so the bar always fits on one line. Otherwise the numbered pages
// are windowed client-side, whatever the paginator sent: with many pages only
// 1 to 5 then the last two show, an ellipsis standing for the rest (the page
// in progress and its neighbours replace them when it is further along).
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

const WINDOW_EDGE_PAGES = 5;

const currentPage = computed(() => props.links.find((link) => link.active)?.label ?? '1');
const lastPage = computed(() => props.links[props.links.length - 2]?.label ?? '1');

// Any page's URL, derived from a numbered link of the paginator (same path
// and query, only `page` differs).
function urlForPage(page) {
    const template = props.links.slice(1, -1).find((link) => link.url)?.url;

    if (!template) {
        return null;
    }

    const url = new URL(template, 'http://localhost');
    url.searchParams.set('page', String(page));

    return `${url.pathname}${url.search}`;
}

function pageNumbersToShow(current, last) {
    if (last <= WINDOW_EDGE_PAGES + 2) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    if (current <= WINDOW_EDGE_PAGES) {
        return [1, 2, 3, 4, 5, null, last - 1, last];
    }

    if (current >= last - WINDOW_EDGE_PAGES + 1) {
        return [1, 2, null, ...Array.from({ length: WINDOW_EDGE_PAGES }, (_, i) => last - WINDOW_EDGE_PAGES + 1 + i)];
    }

    return [1, 2, null, current - 1, current, current + 1, null, last - 1, last];
}

// Links actually rendered: the paginator's own in compact mode (only the
// previous/next are drawn); previous + windowed pages + next otherwise.
const barLinks = computed(() => {
    if (props.compact || props.links.length <= 3) {
        return props.links;
    }

    const current = Number(currentPage.value);
    const last = Number(lastPage.value);

    // Nothing active (a page beyond the last one): keep the paginator's own links.
    if (!props.links.some((link) => link.active) || !urlForPage(current)) {
        return props.links;
    }

    const pages = pageNumbersToShow(current, last).map((page) => (page === null
        ? { url: null, label: '…', active: false }
        : { url: urlForPage(page), label: String(page), active: page === current }));

    return [props.links[0], ...pages, props.links[props.links.length - 1]];
});

const lastIndex = computed(() => barLinks.value.length - 1);

// Only the previous/next buttons (first and last link) in compact mode.
const visibleLinks = computed(() => barLinks.value
    .map((link, index) => ({ link, index }))
    .filter(({ index }) => !props.compact || index === 0 || index === lastIndex.value));
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
                :title="index === 0 ? 'Page précédente' : (index === lastIndex ? 'Page suivante' : undefined)"
                :aria-current="link.active ? 'page' : undefined"
                :aria-label="index === 0 ? 'Page précédente' : (index === lastIndex ? 'Page suivante' : undefined)"
            >
                <template v-if="index === 0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    <span class="sr-only">Précédent</span>
                </template>
                <template v-else-if="index === lastIndex">
                    <span class="sr-only">Suivant</span>
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
