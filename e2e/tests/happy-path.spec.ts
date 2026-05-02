import { test, expect } from '@playwright/test'

const DEMO_EMAIL = 'demo-min@pandora-calendar.test'

test('happy path: login → see calendar → check-in with 朵朵', async ({ page }) => {
  await page.goto('/')

  // Should redirect to /#/login because no token
  await expect(page).toHaveURL(/login/)

  // Pick the demo user
  await page.click(`[data-test="demo-login-${DEMO_EMAIL}"]`)

  // Lands on calendar with phase label rendered
  await expect(page).toHaveURL(/calendar/)
  await expect(page.locator('[data-test="phase-label"]')).toBeVisible()

  // Navigate to dodo, do a check-in
  await page.click('a[href="#/dodo"]')
  await expect(page).toHaveURL(/dodo/)

  await page.click('[data-test="mood-good"]')

  await expect(page.locator('[data-test="dodo-response"]')).toBeVisible()
  const text = await page.locator('[data-test="dodo-response"]').innerText()
  expect(text.length).toBeGreaterThan(5)

  // Logout from profile
  await page.click('a[href="#/me"]')
  await page.click('[data-test="logout"]')
  await expect(page).toHaveURL(/login/)
})

test('happy path: log a cycle then symptom', async ({ page }) => {
  await page.goto('/')
  await page.click(`[data-test="demo-login-${DEMO_EMAIL}"]`)
  await page.click('a[href="#/log"]')

  await page.fill('[data-test="cycle-start-date"]', new Date().toISOString().slice(0, 10))
  await page.click('[data-test="save-cycle"]')
  await expect(page.locator('[data-test="save-message"]')).toContainText('已記錄')

  await page.click('[data-test="tag-cramp"]')
  await page.click('[data-test="tag-fatigue"]')
  await page.click('[data-test="save-symptom"]')
  await expect(page.locator('[data-test="save-message"]')).toContainText('已記錄')
})
