<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// Secondary menu of the Configuration surface (spec-refonte-layout-
// configuration): main menu | this menu | page content. Active state is
// route-aware via `usePage().component`, like Sidebar.vue.
const MENU_ITEMS = [
    { label: 'Tags', href: '/configuration/tags', component: 'Documents/Configuration' },
    { label: 'MCP', href: '/configuration/mcp', component: 'Documents/Mcp' },
    { label: 'Thèmes', href: '/configuration/themes', component: 'Documents/Themes' },
];

const page = usePage();
const activeComponent = computed(() => page.component);
</script>

<template>
    <AppLayout>
        <div class="flex min-h-screen">
            <nav
                class="sticky top-0 h-screen w-48 shrink-0 self-start overflow-y-auto border-r border-border bg-surface px-3 py-5"
                aria-label="Configuration"
                data-testid="configuration-menu"
            >
                <p class="mb-3 px-2.5 text-xs font-semibold uppercase tracking-wide text-muted">
                    Configuration
                </p>
                <div class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in MENU_ITEMS"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-2.5 py-2 text-sm leading-none transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :class="activeComponent === item.component
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'text-foreground hover:bg-surface-alt'"
                        :aria-current="activeComponent === item.component ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>
            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
