import '../css/app.css';
import 'primeicons/primeicons.css';
 
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import PrimeVue from 'primevue/config';
import { initializeTheme } from './composables/useAppearance';
import Aura from '@primeuix/themes/aura'; 
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
  
import ToastService from 'primevue/toastservice' 

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) { 
        const app = createApp({ render: () => h(App, props) }) 
            .use(plugin) 
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        prefix: 'p',
                        darkModeSelector: 'system',
                        cssLayer: false
                    }
                },
                ripple: true
            })
            .use(ToastService)
            .use(ConfirmationService);

        app.directive('tooltip', Tooltip);
        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
}); 
initializeTheme();
