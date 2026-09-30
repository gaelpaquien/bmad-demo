import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { handleFlash } from '@/Composables/useToasts';

// Success toasts flashed by the server (App\Support\Toast) — registered once
// for the app's lifetime, outside any layout, so a layout remount during the
// navigation that follows the redirect cannot drop it.
router.on('flash', handleFlash);

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
