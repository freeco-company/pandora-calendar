import type { Page } from '@playwright/test'

export const DEMO_EMAIL = 'demo-min@pandora-calendar.test'
export const DEMO_EMAIL_2 = 'demo-yuching@pandora-calendar.test'

/**
 * Wave 9-12 後 PetOnboardingModal 自動在 `pet_onboarded_at === null` 的 demo 用戶開啟，
 * 以 z-80 fixed overlay 攔截所有點擊 → 大量 spec 死在「a[href=#/me] intercepted by pet-onboarding-modal」。
 * 解法：route mock GET /v1/me/pet 直接回 onboarded=true，讓 modal 不開。
 *
 * 此函式對 page 設定 route，必須在 page.goto 前呼叫（addInitScript 同階段）。
 */
async function stubPetOnboarded(page: Page) {
  await page.route('**/api/v1/me/pet', async (route) => {
    if (route.request().method() === 'GET') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            species: 'cat',
            nickname: '小貓',
            level: 1,
            xp: 0,
            outfit: 'default',
            outfit_state: {},
            available_species: ['cat', 'rabbit', 'dog', 'fox', 'bear', 'penguin', 'pig', 'sheep', 'dinosaur', 'tiger', 'robot'],
            onboarded: true,
          },
        }),
      })
    } else {
      await route.continue()
    }
  })
}

/**
 * 走 demo login 按鈕。
 *
 * 同時：
 *  - mark onboarding done（避免被 router.beforeEach 導去 onboarding 弄壞 spec）
 *  - force locale = zh-TW（Playwright chromium 預設 navigator.language=en-US，
 *    會讓 i18n 偵測為 'en' → 大量 spec 對中文文案的 assertion 全部失效）
 */
export async function loginDemo(page: Page, email = DEMO_EMAIL) {
  await stubPetOnboarded(page)
  await page.addInitScript(() => {
    try {
      localStorage.setItem('pandora_calendar_onboarding_done', '1')
      // 只有「使用者還沒選過 locale」才幫他塞 zh-TW（不要覆蓋 user 在 spec 中切換的偏好）
      if (!localStorage.getItem('locale')) {
        localStorage.setItem('locale', 'zh-TW')
      }
    } catch {
      /* private mode */
    }
  })
  await page.goto('/')
  // demo-login 按鈕只在 import.meta.env.DEV 才出現；給時間讓它 mount
  await page.waitForSelector(`[data-test="demo-login-${email}"]`, { timeout: 8000 })
  await page.click(`[data-test="demo-login-${email}"]`)
  await page.waitForURL(/calendar|onboarding/, { timeout: 8000 })
}

/**
 * 不 mark onboarding done — 給 onboarding spec 用。
 * 仍 force locale = zh-TW（onboarding spec 也吃中文文案）。
 */
export async function loginDemoFresh(page: Page, email = DEMO_EMAIL) {
  await stubPetOnboarded(page)
  await page.addInitScript(() => {
    try {
      localStorage.removeItem('pandora_calendar_onboarding_done')
      if (!localStorage.getItem('locale')) {
        localStorage.setItem('locale', 'zh-TW')
      }
    } catch {
      /* */
    }
  })
  await page.goto('/')
  await page.waitForSelector(`[data-test="demo-login-${email}"]`, { timeout: 8000 })
  await page.click(`[data-test="demo-login-${email}"]`)
}

/**
 * 對於沒登入的 spec（例如 FAQ public route），仍要鎖 locale 才能對中文文案。
 */
/**
 * 處理 hash route 的 goto race：loginDemo 後直接 page.goto('/#/X') 偶爾不觸發
 * Vue Router push（Capacitor App.vue mount + lock check + 多 watcher 同時 race）。
 * 解法：goto + waitForURL；若 URL 沒切過去就 reload + 再 goto。
 */
export async function safeHashGoto(page: Page, path: string, urlPattern?: RegExp) {
  const target = path.startsWith('#') ? path : `/#${path.startsWith('/') ? path : '/' + path}`
  await page.goto(target)
  const expected = urlPattern || new RegExp(path.replace(/^\/?#?\/?/, ''))
  try {
    await page.waitForURL(expected, { timeout: 3000 })
  } catch {
    // race：reload + 再試一次
    await page.goto(target)
    await page.waitForURL(expected, { timeout: 5000 })
  }
}

/**
 * Wave 9 之後 Profile.vue 從平鋪 → accordion；只有 gamification 預設展開。
 * 測試要點哪一段就先展開哪一段。
 *
 * Section keys: gamification | subscription | health | personalize | security | help | about
 * Logout 不在 accordion 內，永遠可見。
 */
export async function expandProfileSection(page: Page, key: string) {
  // Section header is the button right inside [data-test="section-{key}"]
  const header = page.locator(`[data-test="section-${key}"] button[aria-expanded]`).first()
  // 若已展開（aria-expanded=true）就不重複點
  const expanded = await header.getAttribute('aria-expanded').catch(() => 'false')
  if (expanded !== 'true') {
    await header.click()
  }
}

export async function pinLocaleZh(page: Page) {
  await stubPetOnboarded(page)
  await page.addInitScript(() => {
    try {
      if (!localStorage.getItem('locale')) {
        localStorage.setItem('locale', 'zh-TW')
      }
    } catch {
      /* */
    }
  })
}
