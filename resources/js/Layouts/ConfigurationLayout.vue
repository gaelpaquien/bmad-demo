<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSidebarCollapsed } from '@/Composables/useSidebarCollapsed';

// Secondary menu of the Configuration surface (spec-refonte-layout-
// configuration): main menu | this menu | page content. Same width, margins,
// separator and item styling as Sidebar.vue, and it collapses to icons only
// together with it (shared state). Active state is route-aware via
// `usePage().component`, like Sidebar.vue.
const MENU_ITEMS = [
    { label: 'Tags', href: '/configuration/tags', component: 'Documents/Configuration', icon: 'tag' },
    { label: 'MCP', href: '/configuration/mcp', component: 'Documents/Mcp', icon: 'server' },
    { label: 'Thèmes', href: '/configuration/themes', component: 'Documents/Themes', icon: 'palette' },
];

const page = usePage();
const activeComponent = computed(() => page.component);
const { isCollapsed } = useSidebarCollapsed();
</script>

<template>
    <AppLayout>
        <div class="flex min-h-screen">
            <nav
                class="sticky top-0 flex h-screen shrink-0 flex-col overflow-y-auto overflow-x-hidden border-r border-border bg-surface px-3 py-5 text-foreground transition-[width] duration-200"
                :class="isCollapsed ? 'w-16' : 'w-sidebar-width'"
                aria-label="Configuration"
                data-testid="configuration-menu"
            >
                <div
                    class="mb-5 flex items-center gap-2 px-2.5 py-1 text-sm font-semibold tracking-tight"
                    data-testid="configuration-menu-title"
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
                </div>

                <hr class="mb-3 h-0.5 border-0 opacity-70 bg-[linear-gradient(to_right,var(--color-border)_60%,transparent)]" />

                <div class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in MENU_ITEMS"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :class="activeComponent === item.component
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-foreground hover:bg-surface-alt'"
                        :aria-current="activeComponent === item.component ? 'page' : undefined"
                        :title="isCollapsed ? item.label : undefined"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                            <template v-if="item.icon === 'tag'">
                                <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z" />
                                <circle cx="7.5" cy="7.5" r="1" />
                            </template>
                            <template v-else-if="item.icon === 'server'">
                                <rect x="3" y="4" width="18" height="6" rx="1.5" />
                                <rect x="3" y="14" width="18" height="6" rx="1.5" />
                                <line x1="7" y1="7" x2="7.01" y2="7" />
                                <line x1="7" y1="17" x2="7.01" y2="17" />
                            </template>
                            <template v-else>
                                <path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2H18a3 3 0 0 0 3-3c0-4.4-4-8.4-9-8.4Z" />
                                <circle cx="7.5" cy="11" r="1" />
                                <circle cx="10" cy="7" r="1" />
                                <circle cx="15" cy="7" r="1" />
                            </template>
                        </svg>
                        <span class="whitespace-nowrap" :class="{ 'sr-only': isCollapsed }">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
