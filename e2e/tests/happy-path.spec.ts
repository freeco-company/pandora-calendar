import { test, expect } from '@playwright/test'
import { DEMO_EMAIL, expandProfileSection, loginDemo } from './_helpers'

test('happy path: login → see calendar → check-in with 朵朵', async ({ page }) => {
  // 用 helper 才會 set onboarding-done flag + locale=zh-TW
  // （Playwright chromium 預設 navigator.language=en-US → i18n 偵測為 en → 中文 assertion 失效）
  await loginDemo(page)
  await expect(page).toHaveURL(/calendar/)
  await expect(page.locator('[data-test="phase-label"]')).toBeVisible()

  await page.click('a[href="#/dodo"]')
  await expect(page).toHaveURL(/dodo/)

  await page.click('[data-test="mood-good"]')
  await expect(page.locator('[data-test="dodo-response"]')).toBeVisible()

  await page.click('a[href="#/me"]')
  await page.click('[data-test="logout"]')
  await expect(page).toHaveURL(/login/)
})

test('happy path: log a cycle then symptom', async ({ page }) => {
  await loginDemo(page)
  await page.click('a[href="#/log"]')

  // demo seed 有 90 天歷史，最近一次約 7 天前 → 用今天會撞 too-close 拒絕。
  // 用很久之前（180 天前）— 確保跟 demo seed 任何 cycle 都離很遠
  const farPast = new Date(Date.now() - 180 * 86400_000).toISOString().slice(0, 10)
  await page.fill('[data-test="cycle-start-date"]', farPast)
  await page.click('[data-test="save-cycle"]')
  // save-message 會出現（success / 業務拒絕都算 API 回覆 ✓）；給寬一點 budget
  await expect(page.locator('[data-test="save-message"]')).toBeVisible({ timeout: 8000 })

  // wave 9 後 symptom tags 改 category accordion；但 body 預設 open（expandedCategories.body = true）
  // 所以 tag-cramp / tag-fatigue 直接 click 即可（不要再 click tag-cat-body 否則會 collapse）
  await page.click('[data-test="tag-cramp"]')
  await page.click('[data-test="tag-fatigue"]')
  await page.click('[data-test="save-symptom"]')
  await expect(page.locator('[data-test="save-message"]')).toContainText('已記錄')
})

test('paywall: free user sees upgrade prompt after second checkin same day', async ({ page }) => {
  await loginDemo(page)
  await page.click('a[href="#/dodo"]')

  // first checkin succeeds
  await page.click('[data-test="mood-good"]')
  await expect(page.locator('[data-test="dodo-response"]')).toBeVisible()

  // second checkin same day → upgrade prompt
  await page.click('[data-test="mood-bad"]')
  await expect(page.locator('[data-test="upgrade-prompt"]')).toBeVisible()
})

test('paywall: profile shows products and ecpay button', async ({ page }) => {
  await loginDemo(page)
  await page.click('a[href="#/me"]')

  // Profile 已從平鋪重排為 accordion。subscription section 預設關閉，要先展開。
  // 同時等 subStatus API 回來才會 render link-premium。
  await page.waitForSelector('[data-test="section-subscription"]', { timeout: 8000 }).catch(() => {})
  await expandProfileSection(page, 'subscription')
  const linkPremium = page.locator('[data-test="link-premium"]')
  await linkPremium.waitFor({ state: 'visible', timeout: 8000 })
  await linkPremium.click()

  await expect(page).toHaveURL(/premium/)
  // wave 9 後 selector 改 plan-* 前綴（從 buy-* 改名）
  await expect(page.locator('[data-test="plan-calendar.premium.monthly"]')).toBeVisible()
  await expect(page.locator('[data-test="plan-calendar.premium.annual"]')).toBeVisible()
})

test('jerosse deep page: gate blocks free user', async ({ page }) => {
  await loginDemo(page)
  await page.click('a[href="#/me"]')
  // link-jerosse 在 gamification section（預設展開），但 v-if="ecommerceGate.eligible.value"
  // 對 demo 用戶（未綁母艦）gate 不過 → link 不顯示 → 直接 goto 看 gate hint
  await page.goto('/#/me/jerosse')

  await expect(page).toHaveURL(/jerosse/)
  await expect(page.locator('text=這個區域目前對妳還沒開通')).toBeVisible()
})
