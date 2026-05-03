import type { Page } from '@playwright/test'

export const DEMO_EMAIL = 'demo-min@pandora-calendar.test'
export const DEMO_EMAIL_2 = 'demo-yuching@pandora-calendar.test'

/**
 * 走 demo login 按鈕（避免 hard-code prod data；走 backend /api/demo/login route）。
 * 同時 mark onboarding done，避免被 router.beforeEach 導去 onboarding 把 spec 弄壞。
 */
export async function loginDemo(page: Page, email = DEMO_EMAIL) {
  await page.addInitScript(() => {
    try {
      localStorage.setItem('pandora_calendar_onboarding_done', '1')
    } catch {
      /* private mode */
    }
  })
  await page.goto('/')
  await page.click(`[data-test="demo-login-${email}"]`)
  await page.waitForURL(/calendar|onboarding/)
}

/**
 * 不 mark onboarding done — 給 onboarding spec 用。
 */
export async function loginDemoFresh(page: Page, email = DEMO_EMAIL) {
  // 清掉可能殘留的 onboarding flag
  await page.addInitScript(() => {
    try {
      localStorage.removeItem('pandora_calendar_onboarding_done')
    } catch {
      /* */
    }
  })
  await page.goto('/')
  await page.click(`[data-test="demo-login-${email}"]`)
}
