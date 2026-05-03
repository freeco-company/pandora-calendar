<script setup lang="ts">
/**
 * Dodo view — 重新定位為「朵朵 × 妳的對話日誌」timeline view（tab 標籤已改名「日誌」）。
 *
 * 資訊架構（上 → 下）：
 *   1. Hero（pet + level + streak）— 把成長軌跡放在最顯眼處
 *   2. Daily reminder（phase tip）— 已有
 *   3. 第一次來：放大 hero 卡（onboarding 引導）
 *   4. 對話 timeline（主視覺）— history + 今日朵朵回應
 *   5. Mood check-in card（副，下移到 timeline 後）— 功能完整保留，給「想再 check-in 一次」
 *      （主要進入點是 Calendar 主頁的 TodayActionCard）
 *
 * 不改 endpoint / data-test 屬性 — e2e（mood-good / dodo-response / upgrade-prompt / daily-reminder）保留。
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CalendarApi, ReminderApi, DodoChatApi, type DodoCheckin, type DailyReminder } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingSkeleton from '../components/ui/LoadingSkeleton.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
import Icon from '../components/icons/Icon.vue'
import { useSfx } from '../lib/sound'
import { moodForPhase } from '../lib/character'
import { getCurrentLevel, awardXp, consumeGamificationPending } from '../lib/gamification'
import { useTone } from '../composables/useTone'

const { t } = useTone()

const router = useRouter()
const ent = useEntitlementsStore()
const sfx = useSfx()
const todayResponse = ref<string | null>(null)
const todayPhase = ref<string | null>(null)
const recent = ref<DodoCheckin[]>([])
const loading = ref(false)
const initialLoading = ref(true)
const error = ref<string | null>(null)
const upgradePromptVisible = ref(false)
const dodoLevel = ref(getCurrentLevel())
const reminder = ref<DailyReminder | null>(null)
const history = ref<DodoCheckin[]>([])

const TONE_BG: Record<string, string> = {
  sakura: 'bg-gradient-to-br from-sakura-50 to-cream-100',
  lavender: 'bg-gradient-to-br from-lavender-50 to-cream-100',
  peach: 'bg-gradient-to-br from-peach-50 to-cream-100',
  cream: 'bg-cream-50',
  sage: 'bg-gradient-to-br from-sage-50 to-cream-100',
}

const MOOD_EMOJI: Record<string, string> = { good: '😊', okay: '😐', bad: '😞' } // legacy fallback (timeline plain text)
// (MOOD_ICON map removed — buttons inline icon names directly)

async function loadRecent() {
  const res = await CalendarApi.dodoRecent()
  recent.value = res.data.data
  if (
    recent.value.length &&
    recent.value[0].checked_on === new Date().toISOString().slice(0, 10)
  ) {
    todayResponse.value = recent.value[0].dodo_response
    todayPhase.value = recent.value[0].phase
  }
}

async function loadReminder() {
  try {
    const r = await ReminderApi.today()
    reminder.value = r.data.data
  } catch {/* silent */}
}

async function loadHistory() {
  try {
    const r = await DodoChatApi.history(20)
    history.value = r.data.data as any
  } catch {/* silent */}
}

onMounted(async () => {
  ent.load()
  try {
    await Promise.allSettled([loadRecent(), loadReminder(), loadHistory()])
  } finally {
    initialLoading.value = false
  }
})

async function checkin(mood: 'good' | 'okay' | 'bad') {
  loading.value = true
  error.value = null
  upgradePromptVisible.value = false
  try {
    const res = await CalendarApi.dodoCheckin(mood)
    todayResponse.value = res.data.data.dodo_response
    todayPhase.value = res.data.data.phase
    sfx.play('correct')
    awardXp(3, t('dodo_xp_chatted'))
    await Promise.all([loadRecent(), loadHistory()])
    setTimeout(() => { void consumeGamificationPending() }, 1500)
  } catch (e: any) {
    if (e?.response?.status === 402) {
      upgradePromptVisible.value = true
      error.value = e?.response?.data?.message ?? t('dodo_error_rate_limited')
      sfx.play('notify')
    } else {
      error.value = e?.response?.data?.message ?? t('dodo_error_generic')
      sfx.play('wrong')
    }
  } finally {
    loading.value = false
  }
}

const phaseLabels = computed<Record<string, string>>(() => ({
  menstrual: t('calendar_phase_menstrual'),
  follicular: t('calendar_phase_follicular'),
  ovulation: t('calendar_phase_ovulation'),
  luteal: t('calendar_phase_luteal'),
  unknown: '',
}))

const dodoMood = computed(() => moodForPhase(todayPhase.value))

// First-time hero: 沒有任何歷史 + 沒有今天回應 + 已載入完成
const isFirstTime = computed(() =>
  !initialLoading.value && history.value.length === 0 && !todayResponse.value
)

// streak 計算：history 連到今天的連續天數
const streakDays = computed(() => {
  if (!history.value.length) return 0
  const dates = new Set(history.value.map((h) => h.checked_on))
  let count = 0
  const cursor = new Date()
  for (let i = 0; i < 60; i++) {
    const iso = cursor.toISOString().slice(0, 10)
    if (!dates.has(iso)) break
    count += 1
    cursor.setDate(cursor.getDate() - 1)
  }
  return count
})

const todayIso = new Date().toISOString().slice(0, 10)

// 把 history 整理成 timeline view（最新在上；今日 entry 標 highlight）
interface TimelineEntry {
  date: string
  isToday: boolean
  mood: string | null
  response: string
  phase: string | null
}
const timeline = computed<TimelineEntry[]>(() =>
  history.value.slice(0, 20).map((r) => ({
    date: r.checked_on,
    isToday: r.checked_on === todayIso,
    mood: r.mood ?? null,
    response: r.dodo_response,
    phase: r.phase ?? null,
  })),
)
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-8 max-w-md md:max-w-3xl lg:max-w-4xl mx-auto space-y-5">

    <!-- ============================================================
         A. 第一次來：放大 hero 卡（onboarding 引導）
         ============================================================ -->
    <section
      v-if="isFirstTime"
      data-test="dodo-hero"
      class="surface-card text-center px-6 py-8 space-y-4 animate-pop"
    >
      <div class="flex justify-center">
        <Character
          species="dodo"
          :level="dodoLevel"
          :mood="dodoMood"
          outfit="ribbon"
          :size="180"
          :show-rarity="true"
          :floaty="true"
          :interactive="true"
        />
      </div>
      <h1 class="heading-1">{{ t('dodo_hero_title') }}</h1>
      <p class="text-body max-w-xs mx-auto">
        {{ t('dodo_hero_subtitle') }}
      </p>
      <p class="font-zen text-[12px] text-peach-500 inline-flex items-center gap-1.5">
        <span class="status-dot bg-peach-400 animate-sparkle" />
        {{ t('dodo_hero_cta') }}
      </p>

      <!-- First-time 直接放 mood 入口（避免空 timeline + 主動作消失） -->
      <div class="grid grid-cols-3 gap-2.5 pt-2" data-test="mood-checkin">
        <button
          v-for="m in [
            { v: 'good', icon: 'heart' as const, label: t('dodo_mood_good') },
            { v: 'okay', icon: 'face-neutral' as const, label: t('dodo_mood_okay') },
            { v: 'bad',  icon: 'rain-cloud' as const,  label: t('dodo_mood_bad') },
          ]"
          :key="m.v"
          :data-test="`mood-${m.v}`"
          :disabled="loading"
          @click="checkin(m.v as any)"
          class="bg-cream-50 hover:bg-peach-50 active:scale-95 disabled:opacity-50 border border-cream-200 rounded-2xl py-4 transition-all flex flex-col items-center gap-1"
        >
          <Icon :name="m.icon" :size="32" decorative />
          <span class="text-xs font-zen text-stone-600">{{ m.label }}</span>
        </button>
      </div>
    </section>

    <!-- ============================================================
         B. 已有 history：Hero（pet + level + streak）
         成長軌跡放最上，比舊版只 character 更有訊息密度
         ============================================================ -->
    <section
      v-else
      class="rounded-3xl bg-gradient-to-br from-cream-50 via-peach-50 to-sakura-50 shadow-soft px-5 py-5 flex items-center gap-4"
      data-test="dodo-tab-hero"
    >
      <Character
        species="dodo"
        :level="dodoLevel"
        :mood="dodoMood"
        outfit="ribbon"
        :size="96"
        :show-rarity="false"
        :floaty="true"
        :interactive="true"
      />
      <div class="flex-1 min-w-0">
        <p class="eyebrow">{{ t('dodo_view_title') }}</p>
        <h1 class="heading-2 mt-0.5">朵朵 × 妳的日誌</h1>
        <div class="mt-2 flex items-center gap-3 text-[12px] font-zen">
          <span class="inline-flex items-center gap-1 text-peach-500 font-bold">
            <span aria-hidden="true">⭐</span>
            <span>Lv.{{ dodoLevel }}</span>
          </span>
          <span
            class="inline-flex items-center gap-1"
            :class="streakDays >= 7 ? 'text-peach-500 font-bold' : 'text-stone-500'"
          >
            <Icon :name="streakDays >= 7 ? 'flame' : 'sprout-small'" :size="16" :animated="streakDays >= 7" decorative />
            <span>連續 {{ streakDays }} 天</span>
          </span>
        </div>
        <p class="text-hint mt-1.5 leading-relaxed">
          <Icon name="sprout-small" :size="14" decorative class="inline-block mr-1 align-middle" />{{ t('dodo_commitment') }}
        </p>
      </div>
    </section>

    <!-- ============================================================
         C. Daily reminder（phase tip）
         ============================================================ -->
    <div
      v-if="reminder && !isFirstTime"
      :class="[TONE_BG[reminder.tone] || 'bg-cream-50', 'rounded-3xl shadow-soft p-4 space-y-1.5']"
      data-test="daily-reminder"
    >
      <p class="font-zen text-[11px] text-peach-500 flex items-center gap-1.5">
        <span class="text-base" aria-hidden="true">{{ reminder.icon }}</span>
        <span class="font-bold">{{ reminder.title }}</span>
      </p>
      <p class="text-stone-700 text-[13px] font-zen leading-relaxed">{{ reminder.body }}</p>
    </div>

    <!-- ============================================================
         D. Loading skeleton（對齊 design system，取代散用 Spinner）
         ============================================================ -->
    <Card v-if="initialLoading && !isFirstTime" tone="plain">
      <LoadingSkeleton variant="card" />
    </Card>

    <!-- ============================================================
         E. 對話 timeline（主視覺）
         每筆 entry：日期 + mood emoji + 朵朵回應氣泡
         今日 entry 用 ring + sparkle 標 highlight
         ============================================================ -->
    <Card v-else-if="!isFirstTime" tone="plain" data-test="dodo-timeline">
      <header class="flex items-center justify-between mb-3">
        <h2 class="heading-3 flex items-center gap-2">
          <span class="text-xl" aria-hidden="true">💬</span>
          <span>{{ t('dodo_chat_history') }}</span>
        </h2>
        <span v-if="timeline.length" class="text-hint">{{ timeline.length }} 筆</span>
      </header>

      <Spinner v-if="loading && !todayResponse" :label="t('dodo_thinking_label')" size="sm" />

      <ol v-if="timeline.length" class="space-y-4 font-zen relative">
        <!-- vertical timeline guide -->
        <div class="absolute left-[15px] top-2 bottom-2 w-px bg-cream-200" aria-hidden="true" />

        <li
          v-for="entry in timeline"
          :key="entry.date"
          class="relative flex gap-3 items-start pl-0"
          :data-test="entry.isToday ? 'dodo-response' : undefined"
        >
          <!-- mood node on timeline -->
          <span
            class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-soft"
            :class="entry.isToday
              ? 'bg-peach-gradient ring-2 ring-peach-300 animate-pulse-slow'
              : 'bg-white border border-cream-200'"
            aria-hidden="true"
          >
            <span class="text-base">{{ entry.mood ? MOOD_EMOJI[entry.mood] : '·' }}</span>
          </span>

          <div class="flex-1 min-w-0">
            <p class="text-[10px] text-stone-400 mb-1 flex items-center gap-1.5">
              <span>{{ entry.date }}</span>
              <span v-if="entry.isToday" class="text-peach-500 font-bold">· 今天</span>
              <span v-if="entry.phase && phaseLabels[entry.phase]" class="text-stone-500">
                · {{ phaseLabels[entry.phase] }}
              </span>
            </p>
            <div
              class="rounded-2xl px-3.5 py-2.5 text-stone-700 text-[13px] leading-relaxed"
              :class="entry.isToday ? 'bg-cream-50 ring-1 ring-peach-200' : 'bg-cream-50/60'"
            >
              {{ entry.response }}
            </div>
          </div>
        </li>
      </ol>

      <EmptyState
        v-else
        icon="🐣"
        :title="t('dodo_history_empty_title')"
        :subtitle="t('dodo_silent')"
      />
    </Card>

    <!-- ============================================================
         F. 升級提示（mood checkin 撞 paywall）
         ============================================================ -->
    <Card
      v-if="upgradePromptVisible"
      tone="lavender"
      data-test="upgrade-prompt"
      class="text-center space-y-3 animate-pop"
    >
      <div class="flex justify-center" aria-hidden="true"><Icon name="gem" :size="36" animated decorative /></div>
      <p class="text-body">{{ error }}</p>
      <Button variant="primary" sfx="ui_open" @click="router.push('/me/premium')">
        {{ t('dodo_upgrade_btn') }}
      </Button>
    </Card>

    <p
      v-else-if="error"
      class="text-xs text-sakura-500 text-center font-zen"
    >
      {{ error }}
    </p>

    <!-- ============================================================
         G. Mood check-in（副，下移）—— 功能保留給「想再 check-in」
         主要進入點是 Calendar 主頁的 TodayActionCard，所以這裡 framing 改成
         「再跟朵朵聊兩句」（不是「今天怎麼樣？」當主動作）
         data-test="mood-checkin" 保留給 e2e
         ============================================================ -->
    <Card v-if="!isFirstTime" tone="plain" data-test="mood-checkin">
      <header class="text-center mb-3">
        <p class="eyebrow">{{ t('dodo_today_section_title') }}</p>
        <h3 class="heading-3 mt-1">再跟朵朵聊兩句</h3>
        <p class="text-caption mt-1">{{ t('dodo_tap_hint') }}</p>
      </header>
      <div class="grid grid-cols-3 gap-2.5">
        <button
          v-for="m in [
            { v: 'good', icon: 'heart' as const, label: t('dodo_mood_good') },
            { v: 'okay', icon: 'face-neutral' as const, label: t('dodo_mood_okay') },
            { v: 'bad',  icon: 'rain-cloud' as const,  label: t('dodo_mood_bad') },
          ]"
          :key="m.v"
          :data-test="`mood-${m.v}`"
          :disabled="loading"
          @click="checkin(m.v as any)"
          class="bg-cream-50 hover:bg-peach-50 active:scale-95 disabled:opacity-50 border border-cream-200 rounded-2xl py-4 transition-all flex flex-col items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-peach-300"
        >
          <Icon :name="m.icon" :size="32" decorative />
          <span class="text-xs font-zen text-stone-600">{{ m.label }}</span>
        </button>
      </div>
    </Card>
  </div>
</template>
