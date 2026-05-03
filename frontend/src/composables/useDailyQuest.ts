/**
 * useDailyQuest — 每日輕量任務引擎
 *
 * - 從 quest pool 中根據 (user_id + YYYY-MM-DD) hash 抽一個 quest，同用戶同天恆定，跨天變
 * - 完成狀態存 localStorage `daily_quest_completed:YYYY-MM-DD:<key>`
 * - 不打 API：純 client-side gamification 包裝；XP 實際發放仍由各 view（Log / Dodo）走原本 publisher
 *
 * 文案 key 對齊 4 dict（zh-TW / zh-TW-inclusive / en / en-inclusive）
 */
import { computed, ref } from 'vue'
import { getStoredUser } from '../api'

export interface DailyQuest {
  key: string
  title_key: string
  cta_label_key: string
  cta_route: string
  xp_reward: number
}

const POOL: DailyQuest[] = [
  { key: 'log_mood', title_key: 'quest_log_mood', cta_label_key: 'quest_cta_log', cta_route: '/log', xp_reward: 10 },
  { key: 'log_symptom', title_key: 'quest_log_symptom', cta_label_key: 'quest_cta_log', cta_route: '/log', xp_reward: 10 },
  { key: 'record_bbt', title_key: 'quest_record_bbt', cta_label_key: 'quest_cta_record', cta_route: '/me/bbt', xp_reward: 15 },
  { key: 'chat_dodo', title_key: 'quest_chat_dodo', cta_label_key: 'quest_cta_chat', cta_route: '/dodo', xp_reward: 10 },
  { key: 'read_insight', title_key: 'quest_read_insight', cta_label_key: 'quest_cta_read', cta_route: '/me/pms', xp_reward: 5 },
  { key: 'checkin_streak', title_key: 'quest_checkin_streak', cta_label_key: 'quest_cta_checkin', cta_route: '/dodo', xp_reward: 10 },
  { key: 'log_period', title_key: 'quest_log_period', cta_label_key: 'quest_cta_log', cta_route: '/log', xp_reward: 15 },
  { key: 'open_year_review', title_key: 'quest_open_year_review', cta_label_key: 'quest_cta_view', cta_route: '/me/journey', xp_reward: 5 },
]

function hashStr(s: string): number {
  let h = 5381
  for (let i = 0; i < s.length; i++) {
    h = ((h << 5) + h + s.charCodeAt(i)) | 0
  }
  return Math.abs(h)
}

function todayIso(): string {
  const d = new Date()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${m}-${day}`
}

function lsKey(date: string, questKey: string): string {
  return `daily_quest_completed:${date}:${questKey}`
}

export function useDailyQuest() {
  const user = getStoredUser()
  const userId = (user as any)?.id ?? (user as any)?.uuid ?? 'anon'
  const date = ref(todayIso())

  const current = computed<DailyQuest>(() => {
    const seed = hashStr(`${userId}:${date.value}`)
    return POOL[seed % POOL.length]
  })

  // reactive completion flag
  const completionTick = ref(0)
  const isCompleted = computed<boolean>(() => {
    void completionTick.value
    try {
      return localStorage.getItem(lsKey(date.value, current.value.key)) === '1'
    } catch {
      return false
    }
  })

  function markCompleted(key?: string) {
    const k = key ?? current.value.key
    try {
      localStorage.setItem(lsKey(date.value, k), '1')
      completionTick.value++
    } catch {
      /* ignore */
    }
  }

  function refresh() {
    date.value = todayIso()
    completionTick.value++
  }

  return { current, isCompleted, markCompleted, refresh }
}
