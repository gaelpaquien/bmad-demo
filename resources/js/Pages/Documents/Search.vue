<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DocumentTypeBadge from '@/Components/DocumentTypeBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import TagSelector from '@/Components/TagSelector.vue';
import TagChip from '@/Components/TagChip.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    // Laravel paginator, 10 results per page (spec-recherche-bornes-pagination).
    documents: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
    search: {
        type: String,
        default: '',
    },
    tagFilters: {
        type: Array,
        default: () => [],
    },
    // True when the term holds more distinct keywords than the server
    // applies (only the first 20 are).
    keywordLimitReached: {
        type: Boolean,
        default: false,
    },
});

const MAX_SEARCH_LENGTH = 255;

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

// One shared navigation call for search + tag filter. It never sends `page`,
// so every new search (typing, Enter, tag) starts again from page 1; only
// the pagination links move between pages (spec-recherche-bornes-
// pagination). Clearing any pending
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
            only: ['documents', 'search', 'tagFilters', 'keywordLimitReached', 'tags'],
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

// Enter also confirms an IME composition (accented or Asian characters):
// that keypress belongs to the input method, not a request to search, so it
// is neither prevented nor turned into a search. Safari fires it after
// `compositionend`, with `isComposing` false but keyCode 229.
function searchNowUnlessComposing(event) {
    if (event.isComposing || event.keyCode === 229) {
        return;
    }

    event.preventDefault();
    navigate();
}

// The help box is folded by default so the search field stays near the
// top; the reader's choice is remembered like the sidebar's.
const SEARCH_HELP_OPEN_STORAGE_KEY = 'bmad-demo-search-help-open';

function readStoredHelpOpen() {
    try {
        return localStorage.getItem(SEARCH_HELP_OPEN_STORAGE_KEY) === 'true';
    } catch (e) {
        return false;
    }
}

const isHelpOpen = ref(readStoredHelpOpen());

function toggleHelp() {
    isHelpOpen.value = !isHelpOpen.value;

    try {
        localStorage.setItem(SEARCH_HELP_OPEN_STORAGE_KEY, isHelpOpen.value ? 'true' : 'false');
    } catch (e) {
        // Private browsing or storage disabled: the state just won't persist across reloads.
    }
}

const codeClass = 'rounded bg-background px-1.5 py-0.5 font-mono text-xs text-foreground';

// Help box operator list: each explanation is a lowercase fragment with no
// final period; `{ code }` parts render as inline code.
const operatorExamples = [
    { code: 'cubiscan speed', explanation: ['au moins un des mots (OU)'] },
    { code: '+cubiscan +speed', explanation: ['tous les mots précédés de ', { code: '+' }, ' (ET)'] },
    {
        code: '+cubiscan speed',
        explanation: ['« cubiscan » obligatoire, « speed » fait seulement remonter les documents qui le contiennent'],
    },
    {
        code: 'cubiscan -speed',
        explanation: ['« cubiscan », sauf les documents contenant « speed » (pièces jointes comprises)'],
    },
    {
        code: '"cubiscan speed"',
        explanation: [
            'cette suite exacte, espace compris, sans retour à la ligne entre les mots ; accepte ',
            { code: '+' },
            ' et ',
            { code: '-' },
            ' (',
            { code: '-"mode test"' },
            ')',
        ],
    },
    {
        code: 'speed -speed',
        explanation: [
            'le plus strict l\'emporte (',
            { code: '-' },
            ', puis ',
            { code: '+' },
            ', puis sans opérateur) : ici aucun résultat',
        ],
    },
];

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

// The empty-results message follows what the server actually searched
// (`search` comes back trimmed, `tagFilters` validated) rather than the
// local input: a whitespace-only term stays neutral (AC2), and a tag just
// picked never flashes "no results" before its documents arrive.
const hasServerCriteria = computed(() => props.search !== '' || props.tagFilters.length > 0);

const resultCountLabel = computed(() => (props.documents.total === 1
    ? '1 document trouvé'
    : `${props.documents.total} documents trouvés`));

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
            <h1 class="sr-only">Recherche</h1>

            <section class="mb-4 rounded-lg border border-border bg-surface text-sm text-muted">
                <h2 class="text-base font-semibold text-foreground">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-expanded="isHelpOpen"
                        aria-controls="search-help"
                        @click="toggleHelp"
                    >
                        <span>Comment fonctionne la recherche ?</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-4 w-4 shrink-0 transition-transform duration-200 motion-reduce:transition-none"
                            :class="{ 'rotate-180': isHelpOpen }"
                            aria-hidden="true"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                </h2>

                <!-- Always rendered so the height can animate (0fr ↔ 1fr, like
                     the sidebar's 200 ms width transition); `inert` keeps the
                     folded content out of the tab order and the accessibility
                     tree. -->
                <div
                    id="search-help"
                    class="grid transition-[grid-template-rows] duration-200 motion-reduce:transition-none"
                    :class="isHelpOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                    :inert="isHelpOpen ? undefined : true"
                    :aria-hidden="isHelpOpen ? undefined : 'true'"
                >
                    <div class="overflow-hidden">
                        <div class="border-t border-border px-4 py-4">
                            <h3 class="mb-1 text-sm font-medium text-foreground">
                                Où et comment les mots sont cherchés ?
                            </h3>
                            <ul class="mb-4 list-disc space-y-1 pl-5">
                                <li>
                                    Dans le titre, le contenu du document et le contenu de ses pièces jointes. Les tags ne
                                    sont jamais cherchés : ils servent de filtre (voir plus bas).
                                </li>
                                <li>
                                    Seul le texte est cherché : un PDF scanné, qui ne contient que des images, n'a aucun
                                    texte à trouver.
                                </li>
                                <li>Sans tenir compte des majuscules ni des accents : « ete » trouve « Été ».</li>
                                <li>
                                    Un mot ou une expression est aussi trouvé à l'intérieur d'un mot plus long : « port »
                                    trouve « rapport » et « portail ».
                                </li>
                                <li>
                                    Les mots sont séparés par des espaces. Les autres caractères (<code :class="codeClass">%</code>,
                                    <code :class="codeClass">_</code>, ponctuation…) sont cherchés tels quels, sauf les
                                    guillemets <code :class="codeClass">"</code> : ils délimitent une expression, et placés
                                    au début ou à la fin d'un mot, ils ne sont pas cherchés.
                                </li>
                                <li>Un mot répété, même avec d'autres majuscules ou accents, ne compte qu'une seule fois.</li>
                            </ul>

                            <h3 class="mb-1 text-sm font-medium text-foreground">
                                Opérateurs
                            </h3>
                            <dl class="mb-4 grid grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-[max-content_1fr]">
                                <template v-for="operator in operatorExamples" :key="operator.code">
                                    <dt class="flex items-center gap-3 sm:justify-between">
                                        <code :class="codeClass">{{ operator.code }}</code>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="hidden h-4 w-4 shrink-0 sm:block"
                                            aria-hidden="true"
                                        >
                                            <path d="M5 12h14" />
                                            <path d="m12 5 7 7-7 7" />
                                        </svg>
                                    </dt>
                                    <dd>
                                        <template v-for="(part, index) in operator.explanation" :key="index">
                                            <code v-if="typeof part === 'object'" :class="codeClass">{{ part.code }}</code>
                                            <template v-else>{{ part }}</template>
                                        </template>
                                    </dd>
                                </template>
                            </dl>

                            <h3 class="mb-1 text-sm font-medium text-foreground">
                                Cas particuliers
                            </h3>
                            <ul class="mb-4 list-disc space-y-1 pl-5">
                                <li>
                                    Une recherche faite uniquement d'exclusions (<code :class="codeClass">-speed</code>) ne
                                    renvoie rien, même avec un tag : ajoutez au moins un mot à trouver.
                                </li>
                                <li>
                                    Un <code :class="codeClass">+</code> ou un <code :class="codeClass">-</code> isolé, suivi
                                    d'une espace, est ignoré : collez-le au mot (<code :class="codeClass">+speed</code>).
                                </li>
                                <li>
                                    Seul le premier signe d'un mot est un opérateur : <code :class="codeClass">++speed</code>
                                    rend obligatoire « +speed », signe compris.
                                </li>
                                <li>
                                    Pour chercher un mot qui commence par <code :class="codeClass">+</code> ou
                                    <code :class="codeClass">-</code> (« -5 », « +33 »), mettez-le entre guillemets
                                    (<code :class="codeClass">"-5"</code>).
                                </li>
                                <li>Un guillemet non fermé court jusqu'à la fin de la saisie.</li>
                                <li>
                                    La saisie est limitée à 255 caractères, et seuls les 20 premiers mots-clés différents
                                    sont pris en compte (un mot répété ne compte qu'une fois) : les suivants sont ignorés.
                                </li>
                            </ul>

                            <h3 class="mb-1 text-sm font-medium text-foreground">
                                Comment filtrer par tag ?
                            </h3>
                            <ul class="mb-4 list-disc space-y-1 pl-5">
                                <li>Avec plusieurs tags sélectionnés, un document doit porter au moins un de ces tags (OU).</li>
                                <li>
                                    Le filtre s'ajoute aux mots-clés (ET) : un document doit correspondre aux mots-clés et
                                    porter un des tags.
                                </li>
                                <li>Sans mot-clé, les documents portant un des tags sont affichés, du plus récent au plus ancien.</li>
                                <li>Choisir ou retirer un tag relance la recherche aussitôt.</li>
                            </ul>

                            <h3 class="mb-1 text-sm font-medium text-foreground">
                                Classement et lancement
                            </h3>
                            <ul class="list-disc space-y-1 pl-5">
                                <li>
                                    D'abord les documents dont le titre contient le plus de mots-clés différents
                                    (obligatoires et facultatifs), puis ceux qui en contiennent le plus au total. Un mot
                                    présent plusieurs fois dans un document ne compte qu'une fois. À égalité, les documents
                                    créés ou importés le plus récemment viennent en premier.
                                </li>
                                <li>
                                    Les résultats s'affichent par pages de 10, avec le nombre total de documents trouvés.
                                    Une nouvelle recherche repart de la première page.
                                </li>
                                <li>La recherche part une demi-seconde après la dernière frappe, ou tout de suite avec Entrée.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mb-6 flex flex-col gap-4 rounded-lg border border-border bg-surface p-4">
                <div>
                    <label for="search-input" class="mb-1 block text-sm font-medium text-foreground">
                        Rechercher par mots-clés
                    </label>
                    <TextInput
                        id="search-input"
                        ref="searchInputRef"
                        v-model="searchTerm"
                        type="search"
                        :maxlength="MAX_SEARCH_LENGTH"
                        placeholder="Rechercher dans les titres, contenus et pièces jointes… (ex. : cubiscan +speed)"
                        :aria-describedby="keywordLimitReached ? 'search-keyword-limit' : undefined"
                        @keydown.enter="searchNowUnlessComposing"
                    />
                    <!-- Always mounted so the message is announced when a
                         search brings it in. -->
                    <div aria-live="polite">
                        <p
                            v-if="keywordLimitReached"
                            id="search-keyword-limit"
                            data-testid="search-keyword-limit"
                            class="mt-1 text-sm text-muted"
                        >
                            Seuls les 20 premiers mots-clés sont pris en compte.
                        </p>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-1 text-sm font-medium text-foreground">
                        Filtrer par tag
                    </legend>
                    <TagSelector v-model="selectedTagIds" :show-label="false" :show-selected="false" />
                </fieldset>

                <div v-if="selectedTagIds.length > 0" class="flex flex-wrap items-center gap-2 border-t border-border pt-3">
                    <span class="text-sm text-muted">Filtres par tag actifs :</span>
                    <TagChip
                        v-for="tagId in selectedTagIds"
                        :key="`tag-${tagId}`"
                        :name="tagName(tagId)"
                        active
                        :remove-label="`Retirer le filtre tag ${tagName(tagId)}`"
                        @remove="removeTagFilter(tagId)"
                    />
                </div>
            </div>

            <!-- The live region stays mounted so every state change is
                 announced; the states inside carry no role of their own, which
                 would announce the loading message a second time. -->
            <div aria-live="polite" aria-atomic="true">
                <div
                    v-if="isSearching"
                    data-testid="search-loading"
                    class="flex items-center justify-center gap-3 py-16 text-muted"
                >
                    <svg class="h-5 w-5 animate-spin text-foreground dark:text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <p>Recherche en cours…</p>
                </div>

                <!-- A page beyond the last one (stale bookmark, results
                     shrunk since) still has matches: say so and offer the
                     page links instead of "no document". -->
                <div v-else-if="documents.data.length === 0 && documents.total > 0" data-testid="search-page-out-of-range" class="py-16 text-center">
                    <p class="text-muted">
                        Cette page n'existe pas : {{ resultCountLabel }}.
                    </p>
                    <Pagination :links="documents.links" />
                </div>

                <div v-else-if="documents.data.length === 0 && hasServerCriteria" class="flex flex-col items-center gap-4 py-16 text-center">
                    <p class="text-muted">
                        Aucun document ne correspond à votre recherche.
                    </p>
                </div>

                <div v-else-if="documents.data.length > 0">
                    <p data-testid="search-count" class="mb-2 text-sm text-muted">
                        {{ resultCountLabel }}
                    </p>
                    <ul data-testid="search-results" class="border-t border-border">
                        <li v-for="document in documents.data" :key="document.id">
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

                    <Pagination :links="documents.links" />
                </div>

                <div
                    v-else-if="!hasServerCriteria"
                    data-testid="search-idle"
                    class="flex flex-col items-center gap-2 py-16 text-center text-muted"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="mb-2 h-10 w-10"
                        aria-hidden="true"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    <p class="font-medium text-foreground">
                        Aucune recherche en cours
                    </p>
                    <p class="text-sm">
                        Saisissez des mots-clés ou choisissez un tag pour afficher les documents correspondants.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
