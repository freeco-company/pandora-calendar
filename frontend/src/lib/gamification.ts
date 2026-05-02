// 潘朵拉月曆 gamification toast bus.
//
// 三條全域 event 驅動 UI：
//   pandora:xp          → 右上 XpToast
//   pandora:level_up    → 全螢幕 LevelUpModal
//   pandora:achievement → 中央 AchievementToast
//
// 雙軌：
//   - 樂觀 awardXp()：使用者動作後立刻 +XP toast（catalog 對齊的數字）
//     LS XP 只是 UI cache；真實 source of truth 是 py-service ledger
//   - consumePending()：呼叫 /v1/me/gamification/pending 拉 webhook 收到的
//     level_up / achievement_unlocked / outfit_unlocked，dispatch 給同一條 bus

export interface XpDetail {
  amount: number
  reason?: string
  total?: number
  level?: number
}
export interface LevelUpDetail {
  level: number
  prev_level?: number
  outfit_unlocked?: string
  cheer?: string
}
export interface AchievementDetail {
  code: string
  title: string
  description?: string
  icon?: string
}

export type GamificationEvent =
  | { type: 'pandora:xp'; detail: XpDetail }
  | { type: 'pandora:level_up'; detail: LevelUpDetail }
  | { type: 'pandora:achievement'; detail: AchievementDetail }

export function emitXp(detail: XpDetail) {
  window.dispatchEvent(new CustomEvent('pandora:xp', { detail }))
}
export function emitLevelUp(detail: LevelUpDetail) {
  window.dispatchEvent(new CustomEvent('pandora:level_up', { detail }))
}
export function emitAchievement(detail: AchievementDetail) {
  window.dispatchEvent(new CustomEvent('pandora:achievement', { detail }))
}

// 既有 LS xp store，phase 4 mock 用
const XP_KEY = 'pandora_calendar_xp_total'
const LEVEL_KEY = 'pandora_calendar_level'

function xpForLevel(level: number) {
  // 簡單線性公式：LV2 需 50, LV3 需 120, ...
  return Math.floor(50 * level + 25 * level * level)
}

export function awardXp(amount: number, reason: string) {
  const totalBefore = parseInt(localStorage.getItem(XP_KEY) || '0', 10) || 0
  const levelBefore = parseInt(localStorage.getItem(LEVEL_KEY) || '1', 10) || 1
  const totalAfter = totalBefore + amount

  let levelAfter = levelBefore
  while (totalAfter >= xpForLevel(levelAfter)) levelAfter++

  localStorage.setItem(XP_KEY, String(totalAfter))
  localStorage.setItem(LEVEL_KEY, String(levelAfter))

  emitXp({ amount, reason, total: totalAfter, level: levelAfter })

  if (levelAfter > levelBefore) {
    emitLevelUp({
      level: levelAfter,
      prev_level: levelBefore,
      cheer: '妳又前進了一步，朵朵很開心。',
    })
  }
}

export function getCurrentLevel(): number {
  return parseInt(localStorage.getItem(LEVEL_KEY) || '1', 10) || 1
}
export function getCurrentXp(): number {
  return parseInt(localStorage.getItem(XP_KEY) || '0', 10) || 0
}

/**
 * 拉 /v1/me/gamification/pending 一次（pull-then-clear）。
 * Webhook receiver 收到 py-service level_up / achievement_awarded / outfit_unlocked
 * 後 cache 在 server 端，前端在關鍵動作後（save / checkin）呼叫此函數消化。
 *
 * import 在實際呼叫處才動態載入，避免 gamification.ts ↔ api.ts 互相 import 循環。
 */
export async function consumeGamificationPending(): Promise<void> {
  try {
    const { GamificationApi } = await import('../api')
    const { data } = await GamificationApi.pending()
    const pending = data?.data
    if (!pending) return

    if (pending.kind === 'level_up') {
      // 用 server 真實值校正 LS cache
      localStorage.setItem(XP_KEY, String(pending.total_xp))
      localStorage.setItem(LEVEL_KEY, String(pending.level))
      emitLevelUp({
        level: pending.level,
        cheer: '妳又前進了一步，朵朵很開心。',
      })
    } else if (pending.kind === 'achievement_unlocked') {
      emitAchievement({
        code: pending.code,
        title: pending.name || pending.code,
        description: pending.tier ? `${pending.tier.toUpperCase()} 成就` : undefined,
        icon: '🏆',
      })
    } else if (pending.kind === 'outfit_unlocked') {
      // outfit unlock 用 achievement toast 呈現
      emitAchievement({
        code: 'outfit_' + pending.codes.join('_'),
        title: '解鎖新裝扮',
        description: pending.codes.join(', '),
        icon: '🎀',
      })
    }
  } catch {
    // pending fetch 失敗 silent — 不阻斷主流程
  }
}
