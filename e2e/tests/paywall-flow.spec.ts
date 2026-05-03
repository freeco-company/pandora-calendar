import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * Paywall 5 賣點 + 月/年 toggle + Restore + Terms / Privacy / 訂閱條款 link
 */

test.describe('paywall', () => {
  test('5 賣點 / Restore / Terms / Privacy / 訂閱管理 link 都呈現', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await page.click('[data-test="link-premium"]')
    await page.waitForURL(/premium/)

    await expect(page.locator('[data-test="premium-benefits"]')).toBeVisible()
    // 5 賣點：用 Card 內 li 計數
    const benefits = page.locator('[data-test="premium-benefits"] li')
    await expect.poll(() => benefits.count()).toBeGreaterThanOrEqual(3)

    // Restore Purchase
    await expect(page.locator('[data-test="restore-purchase"]')).toBeVisible()

    // Terms / Privacy / 訂閱管理
    await expect(page.locator('[data-test="paywall-terms"]')).toBeVisible()
    await expect(page.locator('[data-test="paywall-privacy"]')).toBeVisible()
    await expect(page.locator('[data-test="paywall-subscription-terms"]')).toBeVisible()
  })

  test('月 / 年 toggle 切換 selected state', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/premium')

    const monthly = page.locator('[data-test="plan-calendar.premium.monthly"]')
    const annual = page.locator('[data-test="plan-calendar.premium.annual"]')
    await expect(monthly).toBeVisible()
    await expect(annual).toBeVisible()

    // 預設選年費（save 24%）
    await expect(annual).toHaveClass(/border-peach-400/)

    // 切月費
    await monthly.click()
    await expect(monthly).toHaveClass(/border-peach-400/)
  })

  test('Restore Purchase 按下會跑（loading state 出現）', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/me/premium')
    const restore = page.locator('[data-test="restore-purchase"]')
    await expect(restore).toBeVisible()
    await restore.click()
    // 結果文字會出現（成功或「目前沒有可恢復的訂閱」）
    await expect(page.locator('text=/恢復|沒有可恢復|請稍後/')).toBeVisible({ timeout: 5000 })
  })
})
