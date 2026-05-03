import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * Health 整合（HealthKit / Health Connect）
 * - Web 環境顯示「需要在 iOS / Android App 中使用」 banner
 * - toggles render（bbt / steps / sleep / menstrual_flow）
 */

test.describe('health integration', () => {
  test('Web 環境 banner 顯示需要 native', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/health-integration')
    if (!page.url().includes('health-integration')) {
      await page.goto('/#/me/health-integration')
    }
    // Capacitor platform detect 是 async（先 checking → 才顯示 web_only），timeout 給寬一點
    await expect(
      page.locator('text=/需要在 iOS \\/ Android App 中使用|此裝置不支援|手動記錄/').first(),
    ).toBeVisible({ timeout: 10_000 })
  })

  test('4 個 toggle 都 render（bbt / steps / sleep / menstrual_flow）', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/health-integration')
    await page.waitForURL(/health-integration/, { timeout: 5000 }).catch(() => {})
    // 偶爾路由 race 後仍在 calendar，reload 讓 view mount
    if (!page.url().includes('health-integration')) {
      await page.goto('/#/me/health-integration')
    }
    await expect(page.locator('text=基礎體溫').first()).toBeVisible({ timeout: 10_000 })
    await expect(page.locator('text=步數').first()).toBeVisible()
    await expect(page.locator('text=睡眠').first()).toBeVisible()
    await expect(page.locator('text=經期到 Apple Health').first()).toBeVisible()
  })

  test('Web 不可用時 sync 按鈕 disabled', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/health-integration')
    const sync = page.locator('button:has-text("立刻同步最近 7 天")')
    await expect(sync).toBeDisabled()
  })

  test('隱私說明區塊呈現', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/health-integration')
    // health_privacy_title = '🔒 隱私說明'；text= 找子串
    await expect(page.locator('text=隱私說明').first()).toBeVisible({ timeout: 10_000 })
    // wave 9 後改寫為「資料只在妳的裝置上處理...」（保留隱私精神，文案不再用「不賣資料 / 不放廣告」）
    await expect(
      page.locator('text=/裝置上處理|雲端只儲存|不會上傳|妳隨時可以|刪除/').first(),
    ).toBeVisible({ timeout: 5000 })
  })
})
