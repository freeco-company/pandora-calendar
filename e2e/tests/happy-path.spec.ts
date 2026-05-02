import { test, expect } from '@playwright/test'

const DEMO_EMAIL = 'demo-min@pandora-calendar.test'

async function login(page: any) {
  await page.goto('/')
  await page.click(`[data-test="demo-login-${DEMO_EMAIL}"]`)
  await expect(page).toHaveURL(/calendar/)
}

test('happy path: login → see calendar → check-in with 朵朵', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/login/)

  await page.click(`[data-test="demo-login-${DEMO_EMAIL}"]`)
  await expect(page).toHaveURL(/calendar/)
  await expect(page.locator('[data-test="phase-label"]')).toBeVisible()

  await page.click('a[href="#/dodo"]')
  await expect(page).toHaveURL(/dodo/)

  await page.click('[data-test="mood-good"]')
  await expect(page.locator('[data-test="dodo-response"]')).toBeVisible()

  await page.click('a[href="#/me"]')
  await page.click('[data-test="logout"]')
  await expect(page).toHaveURL(/login/)
})

test('happy path: log a cycle then symptom', async ({ page }) => {
  await login(page)
  await page.click('a[href="#/log"]')

  await page.fill('[data-test="cycle-start-date"]', new Date().toISOString().slice(0, 10))
  await page.click('[data-test="save-cycle"]')
  await expect(page.locator('[data-test="save-message"]')).toContainText('已記錄')

  await page.click('[data-test="tag-cramp"]')
  await page.click('[data-test="tag-fatigue"]')
  await page.click('[data-test="save-symptom"]')
  await expect(page.locator('[data-test="save-message"]')).toContainText('已記錄')
})

test('paywall: free user sees upgrade prompt after second checkin same day', async ({ page }) => {
  await login(page)
  await page.click('a[href="#/dodo"]')

  // first checkin succeeds
  await page.click('[data-test="mood-good"]')
  await expect(page.locator('[data-test="dodo-response"]')).toBeVisible()

  // second checkin same day → upgrade prompt
  await page.click('[data-test="mood-bad"]')
  await expect(page.locator('[data-test="upgrade-prompt"]')).toBeVisible()
})

test('paywall: profile shows products and ecpay button', async ({ page }) => {
  await login(page)
  await page.click('a[href="#/me"]')
  await page.click('[data-test="link-premium"]')

  await expect(page).toHaveURL(/premium/)
  await expect(page.locator('[data-test="buy-calendar.premium.monthly"]')).toBeVisible()
  await expect(page.locator('[data-test="buy-calendar.premium.annual"]')).toBeVisible()
})

test('jerosse deep page: gate blocks free user', async ({ page }) => {
  await login(page)
  await page.click('a[href="#/me"]')
  await page.click('[data-test="link-jerosse"]')

  await expect(page).toHaveURL(/jerosse/)
  await expect(page.locator('text=這個區域目前對妳還沒開通')).toBeVisible()
})
