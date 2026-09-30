<script setup>
import { useToasts } from '@/Composables/useToasts';

const { toasts, dismissToast } = useToasts();
</script>

<template>
    <div role="status" aria-live="polite" class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col items-end gap-2">
        <!-- Persistent live region: announced content is inserted into an
             already-present element, which screen readers reliably pick up. -->
        <div
            v-for="toast in toasts"
            :key="toast.id"
            data-testid="toast"
            class="pointer-events-auto flex w-full items-start gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background shadow-lg"
        >
            <svg
                class="mt-0.5 h-4 w-4 shrink-0"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M5 13l4 4L19 7" />
            </svg>
            <span class="min-w-0 flex-1">{{ toast.message }}</span>
            <button
                type="button"
                class="-mr-1 shrink-0 rounded p-0.5 opacity-70 hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                aria-label="Fermer la notification"
                @click="dismissToast(toast.id)"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </div>
</template>
