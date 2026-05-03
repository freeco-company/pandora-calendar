import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * 訂閱取消挽留 4 步流程：
 * Step 1: 選 reason
 * Step 2: 依 offer_kind 顯示不同挽留（pause / discount / feedback / privacy / none）
 * Step 3: win-back（最後一個 pause 機會）
 * Step 4: 平台導向（itms-apps / market / web）
 *
 * 公開 endpoint：/api/v1/subscription/churn-intercept（不需 auth）
 */

test.describe('cancel intercept', () => {
  test('進入頁 → 看到 step1 + reasons 選項', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/subscription/cancel')

    await expect(page.locator('text=妳要離開了嗎')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('text=/第 1 \\/ 4 步/')).toBeVisible()
    // reasons 至少有一個按鈕
    const reasonBtns = page.locator('button:has-text("便宜"), button:has-text("功能"), button:has-text("隱私"), button:has-text("不用了")')
    await expect.poll(() => reasonBtns.count()).toBeGreaterThan(0)
  })

  test('選 reason → 下一步 → 看到挽留 offer', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/subscription/cancel')

    // 選第一個 reason
    const firstReason = page.locator('button[aria-pressed]').first()
    await firstReason.click()
    await page.click('button:has-text("下一步")')

    await expect(page.locator('text=/第 2 \\/ 4 步/')).toBeVisible()
    // 至少有「我還是要取消」按鈕（第 3 步入口）
    await expect(page.locator('button:has-text("我還是要取消")')).toBeVisible()
  })

  test('沒選 reason 直接按下一步 → 顯示提示', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/subscription/cancel')
    await page.click('button:has-text("下一步")')
    await expect(page.locator('text=先告訴朵朵一個原因')).toBeVisible()
  })

  test('一路走到 step3 → step4 → 看到平台導向 CTA', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/subscription/cancel')

    await page.locator('button[aria-pressed]').first().click()
    await page.click('button:has-text("下一步")')
    await page.click('button:has-text("我還是要取消")')

    // step3 win-back
    await expect(page.locator('text=/第 3 \\/ 4 步/')).toBeVisible()
    await page.click('button:has-text("不了，我要取消訂閱")')

    // step4 平台導向
    await expect(page.locator('text=/第 4 \\/ 4 步/')).toBeVisible()
    await expect(page.locator('button:has-text("前往訂閱設定取消")')).toBeVisible()
  })
})
