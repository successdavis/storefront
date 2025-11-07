// app.ts
import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import { initializeTheme } from './composables/useAppearance'

import { ZiggyVue } from 'ziggy-js'  // ✅ plugin
import { Ziggy } from './ziggy'      // ✅ dynamic routes from @routes

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'
import DefaultLayoutFile from '@/layouts/AppLayout.vue'

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const page = resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        )
        page.then((m) => { m.default.layout = m.default.layout || DefaultLayoutFile })
        return page
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, Ziggy)   // ✅ provide the runtime Ziggy object
            .mount(el)
    },
    progress: { color: '#4B5563' },
})

initializeTheme()
