<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Collapsed (icons-only) mode, persisted like the theme choice under its
// own localStorage key. Labels stay in the DOM as `sr-only` so every item
// keeps its accessible name, with a `title` tooltip while collapsed.
const SIDEBAR_COLLAPSED_STORAGE_KEY = 'bmad-demo-sidebar-collapsed';

function readStoredCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY) === 'true';
    } catch (e) {
        return false;
    }
}

const isCollapsed = ref(readStoredCollapsed());

function toggleCollapsed() {
    isCollapsed.value = !isCollapsed.value;

    try {
        localStorage.setItem(SIDEBAR_COLLAPSED_STORAGE_KEY, isCollapsed.value ? 'true' : 'false');
    } catch (e) {
        // Private browsing or storage disabled: the state just won't persist across reloads.
    }
}

// Active state is route-aware via `usePage().component` (Boundaries &
// Constraints, spec-3-4) rather than a fixed constant: "Recherche" is
// active only on its own dedicated surface (`Documents/Search`), and
// "Bibliothèque" is active on every other existing surface — Library,
// Document Detail, Editor — same as before (AC1, spec-3-2).
//
// "Configuration" gets the same dedicated-surface treatment (Code Map,
// spec-3-5): active only on `Documents/Configuration`, with "Bibliothèque"
// redefined to exclude it too, alongside Recherche.
//
// "Bibliothèque" itself is an explicit whitelist of every `Documents/*`
// surface it actually covers, not "everything that isn't Recherche/
// Configuration" (retrospective Epic 3, action item 12) — an exclusion list
// silently lights up "Bibliothèque" for any future page nobody thought to
// add to it here; a whitelist instead defaults a new, unlisted surface to
// no nav item active at all.
const LIBRARY_SURFACES = ['Documents/Index', 'Documents/Editor', 'Documents/Show'];

// "Configuration" covers the three pages of its secondary menu
// (spec-refonte-layout-configuration): Tags, MCP and Thèmes.
const CONFIGURATION_SURFACES = ['Documents/Configuration', 'Documents/Mcp', 'Documents/Themes'];

const page = usePage();
const isSearchActive = computed(() => page.component === 'Documents/Search');
const isConfigActive = computed(() => CONFIGURATION_SURFACES.includes(page.component));

// "Créer un document" gets its own active state: `Documents/Editor` serves
// both create (`document` prop null/absent) and edit (prop present) — only
// the former is "Créer un document" itself, matching what the URL/action
// actually is.
const isCreateActive = computed(() => page.component === 'Documents/Editor' && !page.props.document);

// "Importer un document" gets its own active state, mirroring
// isCreateActive above (spec-import-document-page) — now a dedicated page
// (`Documents/Import`) reached via a plain `Link`, not a modal, so it needs
// the same route-aware active treatment as "Créer un document".
const isImportActive = computed(() => page.component === 'Documents/Import');

// `isLibraryActive` excludes the create route: without this, "Documents"
// and "Créer un document" would both light up lime at once on
// `/documents/create` (both cover `Documents/Editor`) — one nav item should
// read as "current" at a time, so create mode belongs to "Créer un
// document" alone, editing an existing document still to "Documents".
const isLibraryActive = computed(() => LIBRARY_SURFACES.includes(page.component) && !isCreateActive.value);

// "Créer un document"/"Importer" moved here from Documents/Index.vue (spec
// spec-sidebar-document-actions) so both actions are available on all 5
// surfaces, not just the Library page content. "Importer un document" now
// navigates to its own dedicated page (spec-import-document-page) instead
// of opening a modal — no more open-state ref to own here.
</script>

<template>
    <aside
        class="sticky top-0 flex h-screen shrink-0 flex-col overflow-y-auto overflow-x-hidden border-r border-border bg-surface-alt px-3 py-5 text-foreground transition-[width] duration-200"
        :class="isCollapsed ? 'w-16' : 'w-sidebar-width'"
    >
        <Link
            href="/"
            class="mb-5 flex items-center gap-2 rounded-md px-2.5 py-1 text-sm font-semibold tracking-tight transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
            data-testid="sidebar-brand"
            :title="isCollapsed ? 'BMAD Démo' : undefined"
        >
            <!-- Logo Laravel par défaut, à remplacer par le logo de l'app. En
            currentColor pour suivre le thème clair/sombre. -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 52" class="h-5 w-5 shrink-0" aria-hidden="true">
                <path fill="currentColor" d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068zm-1.574 10.718v-9.124l-3.363 1.936-4.646 2.675v9.124l8.01-4.611zm-9.61 16.505v-9.13l-4.57 2.61-13.05 7.448v9.216l17.62-10.144zM1.602 7.719v31.068L19.22 48.93v-9.214l-9.204-5.209-.003-.002-.004-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-.002-21.481L4.965 9.654 1.602 7.72zm8.81-5.994L2.405 6.334l8.005 4.609 8.006-4.61-8.006-4.608zm4.164 28.764l4.645-2.674V7.719l-3.363 1.936-4.646 2.675v20.096l3.364-1.937zM39.243 7.164l-8.006 4.609 8.006 4.609 8.005-4.61-8.005-4.608zm-.801 10.605l-4.646-2.675-3.363-1.936v9.124l4.645 2.674 3.364 1.937v-9.124zM20.02 38.33l11.743-6.704 5.87-3.35-8-4.606-9.211 5.303-8.395 4.833 7.993 4.524z" />
            </svg>
            <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">BMAD Démo</span>
        </Link>

        <hr class="mb-3 h-0.5 border-0 opacity-70 bg-[linear-gradient(to_right,transparent,var(--color-border)_20%,var(--color-border)_80%,transparent)]" />

        <div class="flex flex-col gap-0.5">
        <nav class="contents" aria-label="Navigation principale">
            <Link
                href="/"
                class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isLibraryActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isLibraryActive ? 'page' : undefined"
                :title="isCollapsed ? 'Documents' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5v-13Z" />
                    <path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5v-13Z" />
                </svg>
                <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Documents</span>
            </Link>
            <Link
                href="/documents/create"
                class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isCreateActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isCreateActive ? 'page' : undefined"
                :title="isCollapsed ? 'Créer un document' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                    <path d="M14 3v5h5" />
                    <line x1="9.5" y1="15" x2="14.5" y2="15" />
                    <line x1="12" y1="12.5" x2="12" y2="17.5" />
                </svg>
                <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Créer un document</span>
            </Link>
            <Link
                href="/documents/import"
                class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isImportActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isImportActive ? 'page' : undefined"
                :title="isCollapsed ? 'Importer un document' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <path d="M12 15V3" />
                    <path d="m7 8 5-5 5 5" />
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                </svg>
                <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Importer un document</span>
            </Link>
            <Link
                href="/recherche"
                class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isSearchActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isSearchActive ? 'page' : undefined"
                :title="isCollapsed ? 'Recherche' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m21 21-4.3-4.3" />
                </svg>
                <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Recherche</span>
            </Link>
            <Link
                href="/configuration/tags"
                class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                :class="isConfigActive
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : 'text-foreground hover:bg-surface'"
                :aria-current="isConfigActive ? 'page' : undefined"
                :title="isCollapsed ? 'Configuration' : undefined"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <line x1="4" y1="6" x2="20" y2="6" />
                    <circle cx="9" cy="6" r="2" />
                    <line x1="4" y1="12" x2="20" y2="12" />
                    <circle cx="15" cy="12" r="2" />
                    <line x1="4" y1="18" x2="20" y2="18" />
                    <circle cx="7" cy="18" r="2" />
                </svg>
                <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Configuration</span>
            </Link>
        </nav>

        </div>

        <div class="flex-1"></div>

        <button
            type="button"
            class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
            data-testid="sidebar-collapse-toggle"
            :aria-label="isCollapsed ? 'Déployer le menu' : 'Réduire le menu'"
            :aria-expanded="isCollapsed ? 'false' : 'true'"
            :title="isCollapsed ? 'Déployer le menu' : undefined"
            @click="toggleCollapsed"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 transition-transform" :class="{ 'rotate-180': isCollapsed }" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <path d="M9 3v18" />
                <path d="m16 15-3-3 3-3" />
            </svg>
            <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">Réduire le menu</span>
        </button>
    </aside>
</template>
