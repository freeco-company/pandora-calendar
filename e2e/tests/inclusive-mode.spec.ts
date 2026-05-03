import { test, expect } from '@playwright/test'
import { expandProfileSection, loginDemo, pinLocaleZh } from './_helpers'

/**
 * Inclusive mode + locale switcher
 * - 開啟 inclusive：「妳」變「你」（檢查 Login subtitle 或 onboarding 標題）
 * - locale switch：zh-TW → en
 * - persist localStorage
 */

test.describe('inclusive + locale', () => {
  test('預設 zh-TW 看到「妳」字（Login subtitle）', async ({ page }) => {
    // Playwright chromium 預設 navigator.language=en-US，會 detect 成 'en'。
    // 此 spec 重點是 inclusive tone（妳 vs 你），需 pin 在 zh-TW 才驗得到。
    await pinLocaleZh(page)
    await page.goto('/#/login')
    await expect(page.locator('text=/妳的週期/')).toBeVisible()
  })

  test('開啟 inclusive toggle → 「妳」變「你」', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'personalize')
    const toggle = page.locator('[data-test="inclusive-toggle"]')
    await toggle.scrollIntoViewIfNeeded()
    await toggle.click()

    // 登出回 login 看 subtitle
    await page.locator('[data-test="logout"]').scrollIntoViewIfNeeded()
    await page.click('[data-test="logout"]')
    await page.waitForURL(/login/)
    await expect(page.locator('text=/你的週期/')).toBeVisible()
  })

  test('locale 切換 zh-TW → en，標語改英文', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'personalize')
    const select = page.locator('[data-test="locale-select"]')
    await select.scrollIntoViewIfNeeded()
    await select.selectOption('en')

    // 登出回 login 看英文 subtitle
    await page.locator('[data-test="logout"]').scrollIntoViewIfNeeded()
    await page.click('[data-test="logout"]')
    await page.waitForURL(/login/)
    await expect(page.locator('text=/Your cycle/i')).toBeVisible()
  })

  test('inclusive 偏好 persist localStorage', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/me"]')
    await expandProfileSection(page, 'personalize')
    const toggle = page.locator('[data-test="inclusive-toggle"]')
    await toggle.scrollIntoViewIfNeeded()
    await toggle.click()

    const stored = await page.evaluate(() => {
      // useInclusiveMode 通常存在 localStorage（key 視實作）
      return Object.keys(localStorage).filter((k) => k.includes('inclusive') || k.includes('tone'))
    })
    expect(stored.length).toBeGreaterThan(0)
  })
})
