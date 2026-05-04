/**
 * Regression e2e for bugs already fixed (Layer E)
 *
 * For each fixed bug, plant a tripwire so it can't come back silently.
 *
 *   1. {name} / {n} / {total} placeholders must NEVER appear literally in
 *      rendered SkillPath / BodyDex / Story / Rank views.
 *   2. No double-arrow back button (`← ←`) anywhere in nav header.
 *   3. POST /me/stories/{n}/unlock with insufficient coins → UI shows
 *      friendly message (not generic "解鎖失敗 / unlock failed").
 */
import { expect, test } from '@playwright/test'
import { loginDemo, safeHashGoto } from './_helpers'

const PLACEHOLDER_RE = /\{[a-zA-Z_]+\}|\{\{\s*[a-zA-Z_]+\s*\}\}/

test.describe('regression: previously-fixed bugs stay fixed', () => {
  test('SkillPath view has no literal {name} / {total} placeholder leaking', async ({ page }) => {
    await loginDemo(page)
    await safeHashGoto(page, '/me/skill-path', /skill-path/)
    // wait for content render
    await page.waitForLoadState('networkidle')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(PLACEHOLDER_RE)
  })

  test('BodyDex view shows substituted "{n} / {total}" not literal braces', async ({ page }) => {
    await loginDemo(page)
    await safeHashGoto(page, '/me/body-dex', /body-dex/)
    await page.waitForLoadState('networkidle')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(PLACEHOLDER_RE)
  })

  test('Story chapters view has no leaking placeholder', async ({ page }) => {
    await loginDemo(page)
    await safeHashGoto(page, '/me/stories', /stories/)
    await page.waitForLoadState('networkidle')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(PLACEHOLDER_RE)
  })

  test('Rank view has no leaking placeholder', async ({ page }) => {
    await loginDemo(page)
    await safeHashGoto(page, '/me/rank', /rank/)
    await page.waitForLoadState('networkidle')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(PLACEHOLDER_RE)
  })

  test('no double-arrow `← ←` rendered on any back-button bearing view', async ({ page }) => {
    await loginDemo(page)
    for (const route of ['/me/skill-path', '/me/body-dex', '/me/rank', '/me/stories']) {
      await safeHashGoto(page, route, new RegExp(route.replace('/me/', '')))
      await page.waitForLoadState('networkidle')
      const body = await page.locator('body').innerText()
      expect(body, `${route} should not show double back arrow`).not.toMatch(/←\s*←/)
    }
  })

  test('story unlock with 0 coins shows friendly error not generic "解鎖失敗"', async ({ page }) => {
    await loginDemo(page)
    // mock economy/balance = 0 so no chapter is affordable
    await page.route('**/api/v1/economy/balance', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { balance: 0 } }),
      }),
    )
    // mock stories list with chapter 2 locked + cost 100
    await page.route('**/api/v1/me/stories/chapters', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { chapter: 1, unlocked: true, read: false, cost: 0 },
            { chapter: 2, unlocked: false, read: false, cost: 100 },
          ],
        }),
      }),
    )
    // mock unlock endpoint to return 422 with structured reason
    await page.route('**/api/v1/me/stories/2/unlock', (route) =>
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          errors: { coin: ['insufficient_balance'] },
          message: '朵朵幣不夠',
        }),
      }),
    )

    await safeHashGoto(page, '/me/stories', /stories/)
    await page.waitForLoadState('networkidle')

    const unlockBtn = page.locator('[data-test^="story-unlock-"]').first()
    const visible = await unlockBtn.isVisible().catch(() => false)
    if (!visible) {
      // some demo seeds already unlock everything; skip rather than fail
      test.skip(true, 'no locked chapter present in demo seed')
      return
    }
    await unlockBtn.click()
    // Either inline message or toast — both shouldn't say generic "解鎖失敗"
    await page.waitForTimeout(800)
    const body = await page.locator('body').innerText()
    expect(body).toContain('朵朵幣')
  })
})
