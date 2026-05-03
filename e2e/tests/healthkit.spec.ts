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
    await expect(page.locator('text=/需要在 iOS \\/ Android App 中使用|此裝置不支援/')).toBeVisible({
      timeout: 5000,
    })
  })

  test('4 個 toggle 都 render（bbt / steps / sleep / menstrual_flow）', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/health-integration')
    await expect(page.locator('text=基礎體溫')).toBeVisible()
    await expect(page.locator('text=步數')).toBeVisible()
    await expect(page.locator('text=睡眠')).toBeVisible()
    await expect(page.locator('text=經期到 Apple Health')).toBeVisible()
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
    await expect(page.locator('text=隱私說明')).toBeVisible()
    await expect(page.locator('text=/不賣資料|不放廣告/')).toBeVisible()
  })
})
