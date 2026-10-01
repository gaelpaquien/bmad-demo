<script setup>
import { TOAST_DURATION_MS, useToasts } from '@/Composables/useToasts';

const { toasts, dismissToast } = useToasts();
</script>

<template>
    <div role="status" aria-live="polite" class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col items-end gap-2">
        <!-- Persistent live region: announced content is inserted into an
             already-present element, which screen readers reliably pick up. -->
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                data-testid="toast"
                class="pointer-events-auto toast-surface relative flex w-full items-center gap-3 overflow-hidden rounded-md border border-success px-4 pb-4 pt-3 text-sm font-medium text-foreground shadow-lg"
            >
                <svg
                    class="h-5 w-5 shrink-0 text-success"
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
                    class="-mr-1.5 flex shrink-0 items-center justify-center rounded p-1.5 opacity-60 transition-opacity hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success"
                    aria-label="Fermer la notification"
                    @click="dismissToast(toast.id)"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
                <!-- Time left before the automatic dismissal: same duration as
                     the store's timer, so the bar runs out as the toast leaves. -->
                <span
                    data-testid="toast-progress"
                    class="toast-progress absolute inset-x-0 bottom-0 h-1 bg-success"
                    :style="{ '--toast-duration': `${TOAST_DURATION_MS}ms` }"
                    aria-hidden="true"
                ></span>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
/* Fond vert très léger mêlé à la couleur de fond du thème : reste lisible
   au-dessus du contenu, en clair comme en sombre. */
.toast-surface {
    background-color: color-mix(in srgb, var(--color-success) 18%, var(--color-background));
}

.toast-progress {
    transform-origin: left;
    animation: toast-progress var(--toast-duration) linear forwards;
}

@keyframes toast-progress {
    from {
        transform: scaleX(1);
    }

    to {
        transform: scaleX(0);
    }
}

.toast-enter-active,
.toast-leave-active,
.toast-move {
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(1.5rem);
}

/* A leaving toast leaves the flow so the others slide up smoothly. */
.toast-leave-active {
    position: absolute;
    right: 0;
}

@media (prefers-reduced-motion: reduce) {
    .toast-progress {
        animation: none;
    }

    .toast-enter-active,
    .toast-leave-active,
    .toast-move {
        transition: opacity 0.15s ease;
    }

    .toast-enter-from,
    .toast-leave-to {
        transform: none;
    }
}
</style>
