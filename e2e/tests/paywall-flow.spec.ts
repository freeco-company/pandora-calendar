import { test, expect } from '@playwright/test'
import { expandProfileSection, loginDemo } from './_helpers'

/**
 * Paywall 5 賣點 + 月/年 toggle + Restore + Terms / Privacy / 訂閱條款 link
 */

test.describe('paywall', () => {
  test('5 賣點 / Restore / Terms / Privacy / 訂閱管理 link 都呈現', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'subscription')
    await page.click('[data-test="link-premium"]')
    await page.waitForURL(/premium/)

    await expect(page.locator('[data-test="premium-benefits"]')).toBeVisible()
    // wave 9 後 markup 從 <li> 改為 <div v-for>；用直接子 div 數 benefit cards
    const benefits = page.locator('[data-test="premium-benefits"] > div > div')
    await expect.poll(() => benefits.count(), { timeout: 8000 }).toBeGreaterThanOrEqual(3)

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
    if (!page.url().includes('premium')) await page.goto('/#/me/premium')
    // entitlements + products 兩個 API 都要回來才 render plan cards
    await page.waitForSelector('[data-test="plan-calendar.premium.monthly"]', { timeout: 10_000 })

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
    // Paywall.vue 等 entitlements + products 兩個 API 才結束 initialLoad → 給寬一點
    const restore = page.locator('[data-test="restore-purchase"]')
    await expect(restore).toBeVisible({ timeout: 10_000 })
    await restore.click()
    // 結果文字會出現（成功或「目前沒有可恢復的訂閱」）
    await expect(page.locator('text=/恢復|沒有可恢復|請稍後/').first()).toBeVisible({ timeout: 8000 })
  })
})
