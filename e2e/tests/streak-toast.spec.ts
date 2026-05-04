import { test, expect } from '@playwright/test'
import { loginDemo } from './_helpers'

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — daily login streak toast e2e.
 *
 * 第一次今日登入 → useStreakToast() 在 App.vue mount 時 fetch /api/streak/today
 * → middleware bump streak=1 (is_first_today=true, is_milestone=true since 1 is in MILESTONES)
 * → StreakToast.vue 顯示。
 *
 * Demo seed `migrate:fresh --seed --force` 已在 globalSetup 跑過 → demo 帳號是「全新」狀態，
 * `user_daily_streaks` 表沒有 row → 第一次登入必然 is_first_today=true。
 */
test('streak toast appears on first login of the day', async ({ page }) => {
  await loginDemo(page)
  await expect(page).toHaveURL(/calendar/)

  // toast 從 App.vue mount 後 fetchToday() resolve 才出現，給足 budget
  const toast = page.locator('[data-test="streak-toast"]')
  await expect(toast).toBeVisible({ timeout: 8000 })

  // 內文檢查（不死綁字串完整匹配，避免 emoji / 標點變動造成 flaky）
  await expect(toast).toContainText('連續')
})

test('streak milestone toast shows unlocks reveal at day 1 (cards)', async ({ page }) => {
  // Demo seed → fresh user → first login is streak=1, which is itself a milestone.
  // unlocks.cards_unlocked: [{ code: 'streak_1', label: '初心徽章' }]
  await loginDemo(page)
  await expect(page).toHaveURL(/calendar/)

  const toast = page.locator('[data-test="streak-toast"]')
  await expect(toast).toBeVisible({ timeout: 8000 })

  // Milestone variant attribute check (data-variant=milestone OR fullscreen)
  const variant = await toast.getAttribute('data-variant')
  expect(['milestone', 'fullscreen']).toContain(variant)

  // Card reveal item should be present (initial 初心徽章 card unlocked at streak=1)
  const unlocks = page.locator('[data-test="streak-unlocks"]')
  await expect(unlocks).toBeVisible()
  await expect(unlocks).toContainText('初心徽章')
})
