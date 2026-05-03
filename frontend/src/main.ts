import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { initSentry } from './lib/sentry'
import { vSound } from './directives/vSound'
import './style.css'

const app = createApp(App).use(createPinia()).use(router)
app.directive('sound', vSound)

// Sentry crash reporting — DSN 未設 / dev mode 會 noop
// health 路徑 + 敏感欄位 scrub 在 lib/sentry.ts 的 beforeSend / beforeBreadcrumb
initSentry(app, router)

app.mount('#app')
