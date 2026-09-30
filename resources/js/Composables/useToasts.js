import { reactive } from 'vue';

// Success toasts shared by the whole app: one module-level list, so a toast
// pushed just before an Inertia navigation is still there once the next page
// (and its layout) has mounted. Fed by the server's flash data (`toast`, see
// App\Support\Toast) through `handleFlash` (registered once in app.js), or
// directly by a client-side action (e.g. the PDF export).
export const TOAST_DURATION_MS = 4000;

const toasts = reactive([]);
const timers = new Map();
let nextId = 1;

export function dismissToast(id) {
    clearTimeout(timers.get(id));
    timers.delete(id);

    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

// No de-duplication: two identical actions in a row are two distinct toasts.
export function notifySuccess(message) {
    const id = nextId++;

    toasts.push({ id, type: 'success', message });
    timers.set(id, setTimeout(() => dismissToast(id), TOAST_DURATION_MS));

    return id;
}

// Listener of Inertia's global `flash` event: turns the server's
// `flash.toast` into a client toast; any other flash payload is ignored.
export function handleFlash(event) {
    const message = event.detail?.flash?.toast?.message;

    if (typeof message === 'string' && message !== '') {
        notifySuccess(message);
    }
}

export function useToasts() {
    return { toasts, dismissToast };
}
