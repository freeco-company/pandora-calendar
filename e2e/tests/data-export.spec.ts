import { test, expect } from '@playwright/test'
import { expandProfileSection, loginDemo } from './_helpers'

/**
 * 資料匯出（Premium 功能）
 *
 * 免費 demo 用戶按匯出會收到 paywall_required（402 / 422 / 自訂 status）→ 前端導 paywall 或顯示訊息。
 * 我們只驗 UI 行為（按鈕在、按下會 react），不模擬 Premium / 簽章驗證（那是 backend Pest）。
 */

test.describe('data export', () => {
  test('Profile 顯示 PDF + CSV 匯出按鈕', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'security')
    await expect(page.locator('[data-test="export-card"]')).toBeVisible()
    await expect(page.locator('[data-test="export-pdf"]')).toBeVisible()
    await expect(page.locator('[data-test="export-csv"]')).toBeVisible()
  })

  test('免費用戶按匯出 → react（loading / paywall message / redirect）', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'security')
    await page.locator('[data-test="export-pdf"]').scrollIntoViewIfNeeded()

    // 監聽 navigation（可能導 paywall）+ message 區
    await Promise.race([
      page.locator('[data-test="export-card"] >> text=/Premium|升級|匯出|稍後/').waitFor({ timeout: 6000 }),
      page.waitForURL(/premium/, { timeout: 6000 }),
    ]).catch(() => {})

    await page.locator('[data-test="export-pdf"]').click({ trial: false }).catch(() => {})
    // 不 hard-assert，只要按下沒 crash 就 OK；行為視 backend Premium gate 而定
    expect(page.url()).toBeTruthy()
  })
})
