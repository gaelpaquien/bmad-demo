import { ref } from 'vue';

// Collapsed (icons-only) mode shared by the main menu and the Configuration
// secondary menu: one module-level ref, so collapsing either collapses both.
// Persisted under its own localStorage key, like the theme choice.
export const SIDEBAR_COLLAPSED_STORAGE_KEY = 'bmad-demo-sidebar-collapsed';

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

export function useSidebarCollapsed() {
    return { isCollapsed, toggleCollapsed };
}
