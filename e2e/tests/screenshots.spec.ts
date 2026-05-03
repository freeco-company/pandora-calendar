import { test } from '@playwright/test'
import { loginDemo } from './_helpers'

const OUT = '/tmp/pandora-calendar-screenshots'

test.use({ viewport: { width: 414, height: 896 } })

test('capture 5 view screenshots', async ({ page }) => {
  await loginDemo(page)
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}/01-login-after.png`, fullPage: true })
  await page.screenshot({ path: `${OUT}/02-calendar.png`, fullPage: true })

  await page.click('a[href="#/log"]')
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}/03-log.png`, fullPage: true })

  await page.click('a[href="#/dodo"]')
  await page.waitForTimeout(800)
  await page.click('[data-test="mood-good"]')
  await page.waitForTimeout(1200)
  await page.screenshot({ path: `${OUT}/04-dodo.png`, fullPage: true })

  await page.click('a[href="#/me"]')
  await page.waitForTimeout(800)
  await page.screenshot({ path: `${OUT}/05-profile.png`, fullPage: true })
})
