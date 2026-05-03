import { test, expect } from '@playwright/test'
import { loginDemoFresh } from './_helpers'

/**
 * Onboarding 3 步流程 — 全新登入用戶第一次看到。
 * 隱私大字 + 不確定 option + cycle slider + goal 4 選 1。
 */

test.describe('onboarding', () => {
  test('全新登入 → 看到 onboarding 第一步 + 隱私大字', async ({ page }) => {
    await loginDemoFresh(page)
    await page.waitForURL(/onboarding/)
    await expect(page.locator('[data-test="onboarding-step-1"]')).toBeVisible()
    await expect(page.locator('[data-test="onboarding-privacy"]')).toBeVisible()
    await expect(page.locator('[data-test="onboarding-privacy"]')).toContainText(/資料只屬於/)
  })

  test('Step1 「不確定」option 也能往下走', async ({ page }) => {
    await loginDemoFresh(page)
    await page.waitForURL(/onboarding/)
    await page.click('[data-test="onboarding-unsure"]')
    await page.click('[data-test="onboarding-step-1-next"]')
    await expect(page.locator('[data-test="onboarding-step-2"]')).toBeVisible()
  })

  test('Step1 選日期 → Step2 cycle slider 21-45 → Step3 goal → 跳 calendar', async ({ page }) => {
    await loginDemoFresh(page)
    await page.waitForURL(/onboarding/)

    // Step1
    await page.fill('[data-test="onboarding-last-period"]', new Date(Date.now() - 7 * 86400_000).toISOString().slice(0, 10))
    await page.click('[data-test="onboarding-step-1-next"]')
    await expect(page.locator('[data-test="onboarding-step-2"]')).toBeVisible()

    // Step2 — cycle slider，min/max 範圍檢查
    const slider = page.locator('[data-test="onboarding-cycle-length"]')
    await expect(slider).toHaveAttribute('min', '21')
    await expect(slider).toHaveAttribute('max', '45')
    await slider.evaluate((el: HTMLInputElement) => {
      el.value = '30'
      el.dispatchEvent(new Event('input', { bubbles: true }))
      el.dispatchEvent(new Event('change', { bubbles: true }))
    })
    await page.click('[data-test="onboarding-step-2-next"]')
    await expect(page.locator('[data-test="onboarding-step-3"]')).toBeVisible()

    // Step3 — 4 個 goal 之一
    await page.click('[data-test="onboarding-goal-health"]')
    await page.click('[data-test="onboarding-submit"]')

    await page.waitForURL(/calendar/)
    await expect(page.locator('[data-test="phase-label"]')).toBeVisible()
  })

  test('Step3 4 個 goal 都 render', async ({ page }) => {
    await loginDemoFresh(page)
    await page.waitForURL(/onboarding/)
    await page.click('[data-test="onboarding-unsure"]')
    await page.click('[data-test="onboarding-step-1-next"]')
    await page.click('[data-test="onboarding-step-2-next"]')
    for (const goal of ['health', 'conceive', 'avoid', 'unsure']) {
      await expect(page.locator(`[data-test="onboarding-goal-${goal}"]`)).toBeVisible()
    }
  })
})
