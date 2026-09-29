<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';
import TagSelector from '@/Components/TagSelector.vue';
import TagChip from '@/Components/TagChip.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: '',
    },
    tagFilters: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const allTags = computed(() => page.props.tags ?? []);

const searchTerm = ref(props.search);
const selectedTagIds = ref([...props.tagFilters]);
const searchInputRef = ref(null);

// 500ms rather than the initial 300ms: a slower typist pausing mid-word
// otherwise fired a search on almost every letter. Enter skips the wait.
const SEARCH_DEBOUNCE_MS = 500;
// The spinner only shows once a search has been pending this long, so a
// fast response never flashes it.
const SPINNER_DELAY_MS = 300;

const isSearching = ref(false);

let debounceTimer = null;
// Identifies the most recent navigation: Inertia cancels a still-pending
// visit when a newer one starts, and the cancelled visit's `onFinish` must
// not hide the spinner the newer one is still waiting on.
let latestVisitId = 0;
// Set right before a programmatic (non-typed) write to `searchTerm`/the tag
// selection so the watchers below can tell it apart from an actual user
// edit and skip re-navigating — otherwise syncing from server props (e.g. a
// browser back/forward restoring a different `?search=` or `?tag_id[]=`)
// would itself trigger a redundant `router.get` that clobbers the history
// entry navigation just restored. Two independent flags because a single
// response can update `search` and `tagFilters` together, and each side
// must consume only its own signal.
let isSyncingSearchFromProps = false;
let isSyncingFiltersFromProps = false;

// Keeps the local input in sync when the server-provided `search` prop
// changes from outside this component's own typing (e.g. browser
// back/forward navigation restoring a different `?search=`).
watch(
    () => props.search,
    (value) => {
        if (value !== searchTerm.value) {
            isSyncingSearchFromProps = true;
            searchTerm.value = value;
        }
    },
);

// Same back/forward sync as `search`, for the tag filter.
watch(
    () => props.tagFilters,
    (value) => {
        isSyncingFiltersFromProps = true;
        selectedTagIds.value = [...value];
    },
);

// One shared navigation call for search + tag filter, no pagination on this
// surface (Boundaries & Constraints, spec-3-4). Clearing any pending
// debounced search navigation here avoids a redundant duplicate request
// when the tag selection (immediate, no debounce) changes while a
// search-term debounce is still pending.
function navigate() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    const params = {};

    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    if (selectedTagIds.value.length > 0) {
        params.tag_id = selectedTagIds.value;
    }

    const visitId = ++latestVisitId;
    let spinnerTimer = null;

    router.get(
        '/recherche',
        params,
        {
            preserveState: true,
            replace: true,
            only: ['documents', 'search', 'tagFilters', 'tags'],
            onStart: () => {
                spinnerTimer = setTimeout(() => {
                    if (visitId === latestVisitId) {
                        isSearching.value = true;
                    }
                }, SPINNER_DELAY_MS);
            },
            onFinish: () => {
                clearTimeout(spinnerTimer);

                if (visitId === latestVisitId) {
                    isSearching.value = false;
                }
            },
        },
    );
}

watch(searchTerm, () => {
    if (isSyncingSearchFromProps) {
        isSyncingSearchFromProps = false;
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(navigate, SEARCH_DEBOUNCE_MS);
});

// Filters are a discrete selection, not free typing — no debounce,
// navigate immediately (Design Notes, spec-1-7).
watch(selectedTagIds, () => {
    if (isSyncingFiltersFromProps) {
        isSyncingFiltersFromProps = false;
        return;
    }

    navigate();
});

// A whitespace-only term is treated as empty by the server (it `trim()`s
// before deciding whether to filter), so the empty-results messaging must
// agree: otherwise a search box containing only spaces would wrongly show
// "no results" instead of the neutral, no-results-yet state (AC2).
const trimmedSearchTerm = computed(() => searchTerm.value.trim());

function removeTagFilter(tagId) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
}

function tagName(tagId) {
    return allTags.value.find((tag) => tag.id === tagId)?.name ?? 'Tag';
}

onMounted(() => {
    // AC2: the field carries focus as soon as the surface loads, before any
    // term has been typed.
    searchInputRef.value?.focus();
});

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
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
            <h1 class="mb-6 text-2xl font-semibold text-foreground">
                Recherche
            </h1>

            <section
                aria-labelledby="search-help-title"
                class="mb-4 rounded-lg border border-border bg-surface p-4 text-sm text-muted"
            >
                <h2 id="search-help-title" class="mb-1 font-medium text-foreground">
                    Comment fonctionne la recherche
                </h2>
                <p class="mb-3">
                    Les mots-clés sont cherchés dans le titre, le contenu des documents et celui de leurs pièces jointes,
                    sans tenir compte des majuscules. Les résultats sont classés en plaçant d'abord les documents dont le
                    titre contient les mots-clés, puis ceux qui en contiennent le plus ; à pertinence égale, les plus
                    récents apparaissent en premier.
                </p>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-[max-content_1fr]">
                    <dt><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs text-foreground">cubiscan speed</code></dt>
                    <dd>au moins un des mots (OU)</dd>
                    <dt><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs text-foreground">+cubiscan +speed</code></dt>
                    <dd>les deux mots obligatoires (ET)</dd>
                    <dt><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs text-foreground">cubiscan -speed</code></dt>
                    <dd>« cubiscan », mais sans « speed »</dd>
                    <dt><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs text-foreground">"cubiscan speed"</code></dt>
                    <dd>l'expression exacte, dans cet ordre</dd>
                </dl>
            </section>

            <div class="relative mb-6">
                <label for="search-input" class="sr-only">
                    Rechercher un document
                </label>
                <TextInput
                    id="search-input"
                    ref="searchInputRef"
                    v-model="searchTerm"
                    type="search"
                    placeholder="Rechercher dans les titres, contenus et pièces jointes… (ex. : cubiscan +speed)"
                    @keydown.enter.prevent="navigate"
                />
            </div>

            <div class="mb-6 flex flex-col gap-3 rounded-lg border border-border p-4">
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-foreground">
                        Filtrer par tag
                    </legend>
                    <TagSelector v-model="selectedTagIds" :show-label="false" />
                </fieldset>

                <div v-if="selectedTagIds.length > 0" class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="text-sm text-muted">Filtres actifs :</span>
                    <button
                        v-for="tagId in selectedTagIds"
                        :key="`tag-${tagId}`"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground not-disabled:hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-label="`Retirer le filtre tag ${tagName(tagId)}`"
                        @click="removeTagFilter(tagId)"
                    >
                        {{ tagName(tagId) }}
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            </div>

            <div aria-live="polite" aria-atomic="true">
                <div
                    v-if="isSearching"
                    role="status"
                    class="flex items-center justify-center gap-3 py-16 text-muted"
                >
                    <svg class="h-5 w-5 animate-spin text-foreground dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <p>Recherche en cours…</p>
                </div>

                <div v-else-if="documents.length === 0 && trimmedSearchTerm" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document ne correspond à votre recherche.
                    </p>
                </div>

                <ul v-else-if="documents.length > 0" class="border-t border-border">
                    <li v-for="document in documents" :key="document.id">
                        <Link
                            :href="`/documents/${document.id}`"
                            class="flex items-center gap-3 border-b border-border px-2 py-3 transition hover:rounded-sm hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        >
                            <DocumentTypeBadge class="shrink-0" :mime-type="document.mime_type" :source="document.source" />
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
            </div>
        </div>
    </AppLayout>
</template>
