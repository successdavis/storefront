// app.ts
import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import { initializeTheme } from './composables/useAppearance'
import { bootStorefrontAnalytics } from './composables/useStorefrontAnalytics'
import './lib/echarts'

import { ZiggyVue, type Config as ZiggyConfig } from 'ziggy-js'  // ✅ plugin
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
        // ziggy.js is generated at build time and bakes in whatever APP_URL
        // the builder had (e.g. http://store-front.test on a dev box). In the
        // browser we always know the real origin, so use it — otherwise every
        // route() link points at the wrong host when the file is stale.
        const ziggyConfig = { ...Ziggy, url: window.location.origin, port: null } as ZiggyConfig

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig)   // ✅ provide the runtime Ziggy object
            .mount(el)

        bootStorefrontAnalytics(props.initialPage)
    },
    progress: { color: '#4B5563' },
})

initializeTheme()
