<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CalendarApi, ReminderApi, DodoChatApi, type DodoCheckin, type DailyReminder } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
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
    await Promise.all([loadRecent(), loadReminder(), loadHistory()])
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
    // 樂觀 +XP（catalog: calendar.dodo_checkin = 3 XP, daily_cap=3）
    awardXp(3, t('dodo_xp_chatted'))
    await loadRecent()
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
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-6 max-w-md md:max-w-3xl lg:max-w-4xl mx-auto space-y-5">

    <!-- 第一次來：放大 hero 卡，講清楚朵朵是什麼 -->
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
      <h1 class="font-display text-2xl font-bold text-peach-500 leading-tight">
        {{ t('dodo_hero_title') }}
      </h1>
      <p class="font-zen text-sm text-stone-600 leading-relaxed max-w-xs mx-auto">
        {{ t('dodo_hero_subtitle') }}
      </p>
      <p class="font-zen text-[12px] text-peach-500 inline-flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-peach-400 animate-sparkle" />
        {{ t('dodo_hero_cta') }}
      </p>
    </section>

    <!-- 已有 history：精簡 header（小 character + 標題） -->
    <header v-else class="text-center space-y-2">
      <div class="flex justify-center">
        <Character
          species="dodo"
          :level="dodoLevel"
          :mood="dodoMood"
          outfit="ribbon"
          :size="120"
          :show-rarity="true"
          :floaty="true"
          :interactive="true"
        />
      </div>
      <h1 class="font-display text-xl font-bold text-peach-500">{{ t('dodo_view_title') }}</h1>
    </header>

    <!-- P1-6 今日 reminder（已有 history 才顯示，避免第一次資訊太多）-->
    <div
      v-if="reminder && !isFirstTime"
      :class="[TONE_BG[reminder.tone] || 'bg-cream-50', 'rounded-3xl shadow-soft p-4 space-y-1.5']"
      data-test="daily-reminder"
    >
      <p class="font-zen text-[11px] text-peach-500 flex items-center gap-1.5">
        <span class="text-base">{{ reminder.icon }}</span>
        <span class="font-bold">{{ reminder.title }}</span>
      </p>
      <p class="text-stone-700 text-[13px] font-zen leading-relaxed">{{ reminder.body }}</p>
    </div>

    <div class="md:grid md:grid-cols-2 md:gap-5 md:items-start space-y-5 md:space-y-0">

    <!-- 主動作區：mood checkin 永遠在 thumb-zone（最上） -->
    <Card tone="plain" data-test="mood-checkin">
      <h2 class="font-display text-lg font-bold text-peach-500 text-center mb-1">
        {{ t('dodo_today_section_title') }}
      </h2>
      <p class="font-zen text-xs text-stone-500 text-center mb-3">{{ t('dodo_tap_hint') }}</p>
      <div class="grid grid-cols-3 gap-2.5">
        <button
          v-for="m in [
            { v: 'good', e: '😊', label: t('dodo_mood_good') },
            { v: 'okay', e: '😐', label: t('dodo_mood_okay') },
            { v: 'bad', e: '😞', label: t('dodo_mood_bad') },
          ]"
          :key="m.v"
          :data-test="`mood-${m.v}`"
          :disabled="loading"
          @click="checkin(m.v as any)"
          class="bg-cream-50 hover:bg-peach-50 active:scale-95 disabled:opacity-50 border border-cream-200 rounded-2xl py-4 transition-all flex flex-col items-center gap-1"
        >
          <span class="text-3xl">{{ m.e }}</span>
          <span class="text-xs font-zen text-stone-600">{{ m.label }}</span>
        </button>
      </div>
    </Card>

    <Spinner v-if="loading && !todayResponse" :label="t('dodo_thinking_label')" />

    <!-- 朵朵的回應（mood checkin 後即時出現） -->
    <Card
      v-if="todayResponse"
      tone="cream"
      data-test="dodo-response"
      class="animate-pop"
    >
      <p class="font-zen text-xs text-peach-500 mb-2 flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-peach-400 animate-sparkle" />
        {{ t('dodo_say') }}<span v-if="todayPhase"> · {{ phaseLabels[todayPhase] }}</span>
      </p>
      <p class="text-stone-700 leading-relaxed font-zen">{{ todayResponse }}</p>
    </Card>

    <Card
      v-if="upgradePromptVisible"
      tone="lavender"
      data-test="upgrade-prompt"
      class="text-center space-y-3 animate-pop"
    >
      <div class="text-3xl">💎</div>
      <p class="text-sm text-stone-700 font-zen">{{ error }}</p>
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

    <!-- 長期承諾條（提醒朵朵會學習，鼓勵連續記錄）-->
    <p
      v-if="!isFirstTime"
      class="font-zen text-[12px] text-stone-500 text-center px-4 leading-relaxed"
    >
      <span class="inline-block mr-1">🌱</span>{{ t('dodo_commitment') }}
    </p>

    <!-- P1-5 朵朵聊天歷史 timeline（第一次來時隱藏整塊，避免空 timeline 干擾）-->
    <Card v-if="!isFirstTime" tone="plain">
      <h2 class="font-display font-bold text-peach-500 text-base mb-3 flex items-center gap-2">
        <span class="text-xl">💬</span> {{ t('dodo_chat_history') }}
      </h2>
      <Spinner v-if="initialLoading" size="sm" />
      <div v-else-if="history.length" class="space-y-3 font-zen text-sm">
        <div
          v-for="r in history.slice(0, 12)"
          :key="r.checked_on"
          class="flex gap-2.5 items-start"
        >
          <span class="text-xl shrink-0 mt-0.5">
            {{ r.mood === 'good' ? '😊' : r.mood === 'okay' ? '😐' : '😞' }}
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[10px] text-stone-400 mb-0.5">{{ r.checked_on }}</p>
            <div class="bg-cream-50 rounded-2xl px-3 py-2 text-stone-700 text-[13px] leading-relaxed">
              {{ r.dodo_response }}
            </div>
          </div>
        </div>
      </div>
      <EmptyState
        v-else
        icon="🐣"
        :title="t('dodo_history_empty_title')"
        :subtitle="t('dodo_silent')"
      />
    </Card>
    </div>
  </div>
</template>
