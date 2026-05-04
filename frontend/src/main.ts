import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { initSentry } from './lib/sentry'
import { vSound } from './directives/vSound'
import './style.css'

const app = createApp(App).use(createPinia()).use(router)
app.directive('sound', vSound)

// 全 app errorHandler — 任何 component render error 不該炸成白屏
// ErrorBoundary 是第一道防線（包住 RouterView），這個是最後 fallback
app.config.errorHandler = (err, _instance, info) => {
  console.error('[vue:errorHandler]', err, info)
  // PaywallRequiredError 是預期的（Premium gate），不報 Sentry
  if ((err as any)?.name !== 'PaywallRequiredError') {
    // Sentry 已在 lib/sentry.ts 補捉 unhandled，這裡 console 即可避免雙報
  }
}

// Sentry crash reporting — DSN 未設 / dev mode 會 noop
// health 路徑 + 敏感欄位 scrub 在 lib/sentry.ts 的 beforeSend / beforeBreadcrumb
initSentry(app, router)

app.mount('#app')
