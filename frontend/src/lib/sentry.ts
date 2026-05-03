/**
 * Pandora Calendar — Sentry init (Capacitor + Vue 3).
 *
 * 為什麼有：crash reporting + perf。
 * 為什麼這麼小心：月經 / BBT / PMS / 孕期是 FTC 等級敏感資料（Flo 案）。
 *
 * 紅線：
 *   1. dev / test / DSN 未設 → noop，不裝任何 hook
 *   2. health 路徑 / health 欄位 → drop 整個 event
 *   3. user context 只送 anonymized id（uuid）
 *   4. request body 不送
 */

import * as Sentry from '@sentry/capacitor'
import * as SentryVue from '@sentry/vue'
import type { App } from 'vue'
import type { Router } from 'vue-router'

const HEALTH_PATH_SEGMENTS = [
  '/cycles',
  '/symptoms',
  '/symptom-tags',
  '/bbt',
  '/pms',
  '/pregnancy',
  '/body-rhythm',
  '/bodyrhythm',
  '/dodo/checkin',
  '/insights',
  '/onboarding',
]

const SENSITIVE_KEY_PARTS = [
  'email',
  'phone',
  'address',
  'password',
  'token',
  'authorization',
  'cookie',
  'cycle_start',
  'cycle_end',
  'flow',
  'symptom',
  'mood',
  'bbt',
  'temperature',
  'weight',
  'height',
  'pregnancy',
  'last_period',
  'period_length',
  'cycle_length',
]

function isHealthPath(url: unknown): boolean {
  if (typeof url !== 'string') return false
  const lower = url.toLowerCase()
  return HEALTH_PATH_SEGMENTS.some((seg) => lower.includes(seg))
}

function redactObject(input: unknown): unknown {
  if (!input || typeof input !== 'object') return input
  if (Array.isArray(input)) return input.map((v) => redactObject(v))
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(input as Record<string, unknown>)) {
    const lk = k.toLowerCase()
    if (SENSITIVE_KEY_PARTS.some((bad) => lk.includes(bad))) {
      out[k] = '[Filtered]'
      continue
    }
    out[k] = typeof v === 'object' && v !== null ? redactObject(v) : v
  }
  return out
}

/**
 * 在 main.ts createApp 後 router.use 之後呼叫一次。
 * router 為 optional — 若提供，會接 Vue Router instrumentation（trace 路由切換）。
 */
export function initSentry(app: App, router?: Router): void {
  const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined
  const env = (import.meta.env.MODE as string) || 'development'

  // dev / test / 沒設 DSN → noop
  if (!dsn || env === 'development' || env === 'test') {
    if (import.meta.env.DEV) {
      console.info('[sentry] noop (dev mode or VITE_SENTRY_DSN empty)')
    }
    return
  }

  Sentry.init(
    {
      dsn,
      environment: env,
      release: import.meta.env.VITE_APP_VERSION as string | undefined,
      sampleRate: 1.0,
      tracesSampleRate: 0.1,
      // 不送預設 PII（IP / cookie / user-agent）
      sendDefaultPii: false,
      // Capacitor v4 寫法：Vue-specific 透過 siblingOptions.vueOptions
      siblingOptions: {
        vueOptions: {
          app,
          // ⚠️ attachProps=false：不上報 component props（可能含 health 資料）
          attachProps: false,
          attachErrorHandler: true,
        },
      },
      integrations: router
        ? [SentryVue.browserTracingIntegration({ router })]
        : [],
      // event-level scrub：health 路徑直接 drop；其他欄位 redact
      beforeSend(event) {
        // request URL 命中 health → drop
        if (event.request?.url && isHealthPath(event.request.url)) {
          return null
        }
        // transaction 名稱命中 → drop
        if (event.transaction && isHealthPath(event.transaction)) {
          return null
        }
        // strip request body / cookies / headers 敏感欄位
        if (event.request) {
          delete event.request.data
          delete event.request.cookies
          if (event.request.headers) {
            event.request.headers = redactObject(event.request.headers) as Record<string, string>
          }
        }
        // user 只留 id
        if (event.user) {
          event.user = { id: event.user.id }
        }
        // extra / contexts redact
        if (event.extra) event.extra = redactObject(event.extra) as Record<string, unknown>
        if (event.contexts) {
          event.contexts = redactObject(event.contexts) as typeof event.contexts
        }
        // tags 也 redact key（不過 tag value 是 string，先做 key 過濾）
        if (event.tags) {
          for (const k of Object.keys(event.tags)) {
            const lk = k.toLowerCase()
            if (SENSITIVE_KEY_PARTS.some((bad) => lk.includes(bad))) {
              event.tags[k] = '[Filtered]'
            }
          }
        }
        return event
      },
      beforeBreadcrumb(crumb) {
        // navigation / fetch / xhr breadcrumb URL 命中 health → drop
        const url = crumb.data?.url ?? crumb.data?.to ?? crumb.data?.from
        if (isHealthPath(url)) return null
        // 其他 data redact
        if (crumb.data) {
          crumb.data = redactObject(crumb.data) as Record<string, unknown>
        }
        return crumb
      },
    },
    SentryVue.init,
  )
}
