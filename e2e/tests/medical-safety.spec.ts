import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * 身體狀況自我評估（醫療安全決策樹）
 * - period_late 14d / 60d 走不同 urgency
 * - disclaimer 大字「朵朵不是醫師」必呈現
 * - find_doctor_url（衛福部就醫地圖）link 可點
 */

test.describe('medical safety', () => {
  test('disclaimer 大字必出現', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/health-check')
    await expect(page.locator('text=朵朵不是醫師')).toBeVisible()
  })

  test('5 個 context 選項都 render', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/health-check')
    await expect(page.locator('text=經期延遲')).toBeVisible()
    await expect(page.locator('text=經血過多')).toBeVisible()
    await expect(page.locator('text=嚴重經痛')).toBeVisible()
    await expect(page.locator('text=經期不規律')).toBeVisible()
    await expect(page.locator('text=經期間出血')).toBeVisible()
  })

  test('經期延遲 14 天 → suggest_test 驗孕（UI 出現「驗孕試紙」字眼）', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/health-check')
    await page.click('button:has-text("經期延遲")')

    // slider 拉到 14
    const slider = page.locator('input[type="range"]')
    await slider.evaluate((el: HTMLInputElement) => {
      el.value = '14'
      el.dispatchEvent(new Event('input', { bubbles: true }))
      el.dispatchEvent(new Event('change', { bubbles: true }))
    })
    await page.click('button:has-text("請朵朵幫我看看")')

    // 14 天通常 medium + suggest_test=true
    await expect(page.locator('text=/驗孕|建議使用驗孕試紙/')).toBeVisible({ timeout: 6000 })
  })

  test('經期延遲 60 天 → high urgency + 衛福部 link', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/health-check')
    await page.click('button:has-text("經期延遲")')

    const slider = page.locator('input[type="range"]')
    await slider.evaluate((el: HTMLInputElement) => {
      el.value = '60'
      el.dispatchEvent(new Event('input', { bubbles: true }))
      el.dispatchEvent(new Event('change', { bubbles: true }))
    })
    await page.click('button:has-text("請朵朵幫我看看")')

    // 衛福部就醫地圖 link
    const link = page.locator('a:has-text("衛福部就醫地圖")')
    await expect(link).toBeVisible({ timeout: 6000 })
    await expect(link).toHaveAttribute('href', /^https?:\/\//)
    await expect(link).toHaveAttribute('target', '_blank')
  })
})
