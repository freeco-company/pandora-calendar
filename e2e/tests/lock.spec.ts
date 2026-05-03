import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * App Lock（生物辨識）
 *
 * 限制：
 *   - lock-toggle 只在 Capacitor.isNativePlatform() === true 時顯示（web 環境隱藏 security-card）
 *   - 真實 biometric 無法在 Playwright web 測 → 我們用 localStorage 直接設 lock_enabled + sessionStorage app_locked，
 *     確認 App.vue overlay 出現
 */

test.describe('app lock', () => {
  test('Web 環境 security-card 隱藏（isNative=false）', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    // security-card 只在 isNative 才 render
    await expect(page.locator('[data-test="security-card"]')).toHaveCount(0)
  })

  test('localStorage lock_enabled=1 + 進 App → overlay 出現', async ({ page }) => {
    // 先正常登入拿 token
    await loginDemo(page)

    // 設 lock_enabled，然後 reload，App.vue onMounted 應該 lock()
    await page.evaluate(() => {
      localStorage.setItem('pandora_calendar_lock_enabled', '1')
    })
    await page.goto('/#/calendar')

    // overlay 出現（即使 verify 會失敗 — web 沒 biometric plugin）
    await expect(page.locator('[data-test="app-lock-screen"]')).toBeVisible({ timeout: 5000 })
  })

  test('鎖定畫面有 unlock 按鈕 + 退出按鈕', async ({ page }) => {
    await loginDemo(page)
    await page.evaluate(() => {
      localStorage.setItem('pandora_calendar_lock_enabled', '1')
    })
    await page.goto('/#/calendar')

    await expect(page.locator('[data-test="app-lock-unlock"]')).toBeVisible()
    await expect(page.locator('[data-test="app-lock-exit"]')).toBeVisible()
  })

  test('按退出 → 登出回 login', async ({ page }) => {
    await loginDemo(page)
    await page.evaluate(() => {
      localStorage.setItem('pandora_calendar_lock_enabled', '1')
    })
    await page.goto('/#/calendar')
    await expect(page.locator('[data-test="app-lock-screen"]')).toBeVisible({ timeout: 5000 })
    await page.click('[data-test="app-lock-exit"]')
    await page.waitForURL(/login/)
  })
})
