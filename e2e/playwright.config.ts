import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright config for Pandora Calendar e2e.
 *
 * - webServer: 自動起 Laravel backend (8000) + Vite dev (5174)。
 *   reuseExistingServer 在本機 dev 直接接已開的 process；CI 才強制重啟乾淨。
 * - globalSetup: 跑 migrate:fresh --seed 確保 demo 帳號 + 種子資料就位。
 * - 仍維持 fullyParallel:false / workers:1（資料庫共用，避免 race）。
 */
export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  // hash route + Vite + Capacitor App.vue mount race 偶爾讓 goto('/#/X') 不切 view；
  // 給 1 個 retry 吸收（重跑時 Vite warm，第二次都過）
  retries: 1,
  workers: 1,
  reporter: [['list']],
  globalSetup: './global-setup.ts',
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:5174',
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: [
    {
      command: 'cd ../backend && php artisan serve --host=127.0.0.1 --port=8000',
      url: 'http://127.0.0.1:8000/api/health',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
    {
      command: 'cd ../frontend && npm run dev -- --port 5174 --host',
      url: 'http://localhost:5174',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
      stdout: 'ignore',
      stderr: 'pipe',
    },
  ],
})
