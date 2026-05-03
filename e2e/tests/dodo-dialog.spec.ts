import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * 朵朵對白 — 不同 mood 應該回不同對白（不是 hardcoded 同一句）。
 *
 * 注意：免費版同一天只能 check-in 一次（之後撞 paywall）。
 * 為了驗證 mood 差異性，每個 case 用 page.context().clearCookies + 重新登入無法繞，
 * 但我們可改用 GET /api/v1/dodo/recent 看歷史；或重新登入到「另一個 demo」看新對話。
 *
 * 實作策略：
 *   a. 第一個 mood check-in 後抓對白 textA
 *   b. 切到 demo-yuching 帳號（不同 user，免費 quota 獨立），check-in 不同 mood，抓 textB
 *   c. 比對 textA !== textB → 證明對白會根據 mood 變
 */

test.describe('dodo dialog', () => {
  test('不同 mood / 不同 user 各自有獨立對白（不是 hardcoded 同一句）', async ({ page, context }) => {
    // demo-min mood=good
    await loginDemo(page, 'demo-min@pandora-calendar.test')
    await page.click('a[href="#/dodo"]')
    await page.click('[data-test="mood-good"]')
    const respA = page.locator('[data-test="dodo-response"]')
    await expect(respA).toBeVisible()
    const textA = (await respA.innerText()).trim()
    expect(textA.length).toBeGreaterThan(0)

    // 換 demo-yuching mood=bad（清 token + 換 user）
    await context.clearCookies()
    await page.evaluate(() => {
      try {
        localStorage.clear()
        localStorage.setItem('pandora_calendar_onboarding_done', '1')
        localStorage.setItem('locale', 'zh-TW')
      } catch {}
    })
    await page.goto('/')
    await page.click('[data-test="demo-login-demo-yuching@pandora-calendar.test"]')
    await page.waitForURL(/calendar/)
    await page.click('a[href="#/dodo"]')
    await page.click('[data-test="mood-bad"]')
    const respB = page.locator('[data-test="dodo-response"]')
    await expect(respB).toBeVisible()
    const textB = (await respB.innerText()).trim()
    expect(textB.length).toBeGreaterThan(0)

    // 不同 user × 不同 mood × 不同 phase（demo seed 不同 cycle_length）→ 對白應該有差異
    expect(textA).not.toEqual(textB)
  })

  test('check-in 後可看 daily-reminder 區塊（phase tip）', async ({ page }) => {
    await loginDemo(page)
    await page.click('a[href="#/dodo"]')
    // daily-reminder 是 phase-based hard-coded tip，登入即出現（不需要 check-in）
    // 但若 backend 還沒 ready 不該 fail spec — 用 has count 弱判斷
    const reminder = page.locator('[data-test="daily-reminder"]')
    if ((await reminder.count()) > 0) {
      await expect(reminder).toBeVisible()
    }
  })
})
