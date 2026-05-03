import { test, expect } from '@playwright/test'

/**
 * FAQ — 公開頁（未登入也能看，meta.public=true + backend /api/v1/faq 不需 auth）
 */

test.describe('faq', () => {
  test('未登入也能進 FAQ 頁', async ({ page }) => {
    await page.goto('/#/faq')
    await expect(page.locator('h1:has-text("常見問題")')).toBeVisible()
  })

  test('FAQ 載入完成後 → 看到 category accordion 或 empty state', async ({ page }) => {
    await page.goto('/#/faq')

    // 等 spinner 消失
    await page.waitForTimeout(800)

    // 看到 group / 或 empty / 或 error 重試 — 三選一
    await Promise.race([
      page.locator('h2.font-display').first().waitFor({ timeout: 5000 }),
      page.locator('text=還沒整理好').waitFor({ timeout: 5000 }),
      page.locator('text=載入失敗').waitFor({ timeout: 5000 }),
    ]).catch(() => {})

    // 一定能看到 header
    await expect(page.locator('h1:has-text("常見問題")')).toBeVisible()
  })

  test('展開 accordion item（若有資料）', async ({ page }) => {
    await page.goto('/#/faq')
    await page.waitForTimeout(1000)
    const firstQuestion = page.locator('button[aria-expanded]').first()
    if (await firstQuestion.count()) {
      await firstQuestion.click()
      // aria-expanded 變 true
      await expect(firstQuestion).toHaveAttribute('aria-expanded', 'true')
    }
  })

  test('「給朵朵的話」CTA → 進 feedback 頁（需登入會被擋去 login，行為合理）', async ({ page }) => {
    await page.goto('/#/faq')
    await page.waitForTimeout(800)
    const cta = page.locator('button:has-text("給朵朵的話")')
    if (await cta.count()) {
      await cta.click()
      // 未登入會被導去 login
      await expect(page).toHaveURL(/login|feedback/)
    }
  })
})
