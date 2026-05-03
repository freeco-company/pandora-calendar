import { execSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// ESM 環境補 __dirname
const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

/**
 * 在跑 e2e 前確保 backend 已遷移 + demo seed 完成。
 *
 * 跑兩次會被 reuseExistingServer 共享同個 sqlite，所以 migrate:fresh 是必要的，
 * 但只有沒設 PANDORA_E2E_SKIP_SEED=1 時會跑（debug / 連續開發時可跳過保留 state）。
 */
export default async function globalSetup() {
  if (process.env.PANDORA_E2E_SKIP_SEED === '1') {
    console.log('[e2e:setup] PANDORA_E2E_SKIP_SEED=1 → 跳過 migrate:fresh + seed')
    return
  }

  const backendDir = path.resolve(__dirname, '..', 'backend')
  if (!existsSync(backendDir)) {
    throw new Error(`[e2e:setup] backend 目錄找不到：${backendDir}`)
  }

  console.log('[e2e:setup] 跑 backend migrate:fresh --seed --force ...')
  try {
    execSync('php artisan migrate:fresh --seed --force', {
      cwd: backendDir,
      stdio: 'inherit',
      env: { ...process.env, APP_ENV: 'local' },
    })
  } catch (err) {
    console.error('[e2e:setup] migrate:fresh 失敗，e2e 可能會看到舊資料 / 缺 demo 帳號')
    throw err
  }
}
