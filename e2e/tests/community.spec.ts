import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * 社群 Q&A 板：
 * - 公開列表（已 published）
 * - 新用戶 gate（未滿 14 天 + 5 cycle）→ 撞 gate hint
 * - 紅線詞 → moderation hint
 * - 自殺敏感詞 → published（會 fallback dodo reply）— 這個透過後端 Feature test 比較準，e2e 只驗 UI
 *
 * 注意：demo 用戶 demo-min 已 seed 90 天歷史 → 通常 gate 通過。
 *      但 backend 的 community gate 可能也檢查 cycle 紀錄筆數（>= 5）；demo 滿足。
 */

test.describe('community', () => {
  test('社群列表頁正常 render（empty / list 都接受）', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/community')
    await expect(page.locator('h1:has-text("社群問板")')).toBeVisible()
    // sort tabs
    await expect(page.locator('button:has-text("最新")')).toBeVisible()
    await expect(page.locator('button:has-text("最熱")')).toBeVisible()
    await expect(page.locator('button:has-text("我的")')).toBeVisible()
  })

  test('FAB → 進 create 頁 → 看到 4 category', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/community')
    // FAB 是 fixed-position「＋」按鈕；用 router push 直接過去比 click 穩
    // （aria-label 動態 bind canPostHint ?? '發新貼文'，且 ＋ text 撞其他 UI）
    await page.goto('/#/community/new')
    await page.waitForURL(/community\/new/)
    // 用 .first() 避免 strict mode（「分享」「小撇步」可能在 guidelines li 也出現）
    await expect(page.locator('text=想問').first()).toBeVisible({ timeout: 8000 })
    await expect(page.locator('text=分享').first()).toBeVisible()
    await expect(page.locator('text=小撇步').first()).toBeVisible()
    await expect(page.locator('text=陪伴').first()).toBeVisible()
  })

  test('client-side soft warning：含「治療」會出現 soft hint', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/community/new')

    await page.waitForSelector('input#community-title', { timeout: 5000 })
    await page.fill('input#community-title', '我想分享治療經驗')
    await page.fill('textarea#community-body', '我之前用了某產品有療效，想推薦給大家治療。')
    // softWarning 區塊出現
    await expect(page.locator('text=/這類用字可能違反社群規範/')).toBeVisible({ timeout: 3000 })
  })

  test('送出全空白 → 送出按鈕 disabled', async ({ page }) => {
    await loginDemo(page)
    await page.goto('/#/community/new')
    await page.waitForSelector('input#community-title', { timeout: 8000 })
    // 送出按鈕在 header；用 .first() + 等更穩
    const submit = page.locator('button:has-text("送出")').first()
    await expect(submit).toBeDisabled({ timeout: 5000 })
  })

  test('送出含紅線詞 → 預期 422 + moderation 或 gate hint UI（看哪個先 trigger）', async ({ page }) => {
    await loginDemo(page)
    // 先進 list page 暖 view → 再 push /community/new（避免直接 goto race）
    await page.goto('/#/community')
    await page.waitForSelector('h1', { timeout: 5000 })
    await page.goto('/#/community/new')
    if (!page.url().includes('community/new')) await page.goto('/#/community/new')
    await page.waitForSelector('input#community-title', { timeout: 10_000 })

    await page.fill('input#community-title', '療效推薦')
    await page.fill('textarea#community-body', '這款產品有療效又能排毒，私訊 line id 加賴給我，限時優惠碼。')
    await page.click('button:has-text("送出")')

    // 任一 hint 出現（gate 也可能擋下；二擇一即可）
    await expect(
      page.locator('text=/這篇內容暫時無法發布|還不能發文|送出失敗|無法送出/').first(),
    ).toBeVisible({ timeout: 5000 })
  })
})
