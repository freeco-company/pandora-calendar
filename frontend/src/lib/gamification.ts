// 潘朵拉月曆 gamification toast bus.
//
// 三條全域 event 驅動 UI：
//   pandora:xp          → 右上 XpToast
//   pandora:level_up    → 全螢幕 LevelUpModal
//   pandora:achievement → 中央 AchievementToast
//
// Phase 4：先用 mock 觸發（Log.vue 在儲存週期成功後 dispatch），
// Track B 後端接通後改從 axios response 拿真實 XP / level_up payload，
// 不用改前端任何 listener。

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
