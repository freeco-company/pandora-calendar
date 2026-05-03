import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * 給朵朵的話（feedback）
 * - < 10 字驗證錯誤
 * - 送出成功 toast「謝謝妳的回饋」
 * - 不嘗試壓 rate limit 5+1 次（會打到 prod-style throttle，e2e 不適合；
 *   backend Pest 已驗 429）
 */

test.describe('feedback', () => {
  test('< 10 字按送出 → 顯示驗證錯誤', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/feedback')
    await page.fill('textarea#fb-message', '太短')
    const submit = page.locator('button:has-text("送給朵朵")')
    // < 10 字時 button disabled，所以驗證 disabled 狀態 + 提示
    await expect(submit).toBeDisabled()
    await expect(page.locator('text=/再多 \\d+ 個字/')).toBeVisible()
  })

  test('合法訊息送出 → 顯示成功卡片', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/feedback')

    await page.fill(
      'textarea#fb-message',
      '希望朵朵能加上經期 PMS 預測通知功能，謝謝你的努力。',
    )
    const submit = page.locator('button:has-text("送給朵朵")')
    await expect(submit).toBeEnabled()
    await submit.click()

    // 等 success card 出現
    await expect(page.locator('text=謝謝妳的回饋')).toBeVisible({ timeout: 5000 })
  })

  test('4 個 category 都呈現', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/feedback')
    await expect(page.locator('text=哪裡壞掉了')).toBeVisible()
    await expect(page.locator('text=想要新功能')).toBeVisible()
    await expect(page.locator('text=內容回饋')).toBeVisible()
    await expect(page.locator('text=其他')).toBeVisible()
  })
})
