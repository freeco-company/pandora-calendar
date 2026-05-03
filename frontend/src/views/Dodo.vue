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
    awardXp(3, '今天和朵朵聊了天')
    await loadRecent()
    setTimeout(() => { void consumeGamificationPending() }, 1500)
  } catch (e: any) {
    if (e?.response?.status === 402) {
      upgradePromptVisible.value = true
      error.value = e?.response?.data?.message ?? '免費版每天 1 次'
      sfx.play('notify')
    } else {
      error.value = e?.response?.data?.message ?? '失敗'
      sfx.play('wrong')
    }
  } finally {
    loading.value = false
  }
}

const phaseLabels: Record<string, string> = {
  menstrual: '經期',
  follicular: '濾泡期',
  ovulation: '排卵期',
  luteal: '黃體期',
  unknown: '',
}

const dodoMood = computed(() => moodForPhase(todayPhase.value))
</script>

<template>
  <div class="px-5 pt-10 pb-6 max-w-md mx-auto space-y-5">
    <!-- 朵朵 NPC 角色區 -->
    <header class="text-center space-y-2">
      <div class="flex justify-center">
        <Character
          species="dodo"
          :level="dodoLevel"
          :mood="dodoMood"
          outfit="ribbon"
          :size="160"
          :show-rarity="true"
          :floaty="true"
          :interactive="true"
        />
      </div>
      <h1 class="font-display text-2xl font-bold text-peach-500">朵朵 dodo</h1>
      <p class="font-zen text-sm text-stone-600">
        妳今天感覺如何？
      </p>
    </header>

    <Card tone="plain">
      <p class="font-zen text-xs text-stone-500 text-center mb-3">點一下，告訴朵朵</p>
      <div class="grid grid-cols-3 gap-2.5">
        <button
          v-for="m in [
            { v: 'good', e: '😊', label: '還不錯' },
            { v: 'okay', e: '😐', label: '普普' },
            { v: 'bad', e: '😞', label: '不太好' },
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

    <Spinner v-if="loading && !todayResponse" label="朵朵正在想..." />

    <Card
      v-if="todayResponse"
      tone="cream"
      data-test="dodo-response"
      class="animate-pop"
    >
      <p class="font-zen text-xs text-peach-500 mb-2 flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-peach-400 animate-sparkle" />
        朵朵說<span v-if="todayPhase"> · {{ phaseLabels[todayPhase] }}</span>
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
        升級 Premium 解鎖無限
      </Button>
    </Card>

    <p
      v-else-if="error"
      class="text-xs text-sakura-500 text-center font-zen"
    >
      {{ error }}
    </p>

    <!-- P1-6 朵朵每日提醒（基於 phase）-->
    <div
      v-if="reminder"
      :class="[TONE_BG[reminder.tone] || 'bg-cream-50', 'rounded-3xl shadow-soft p-4 space-y-1.5']"
      data-test="daily-reminder"
    >
      <p class="font-zen text-[11px] text-peach-500 flex items-center gap-1.5">
        <span class="text-base">{{ reminder.icon }}</span>
        <span class="font-bold">{{ reminder.title }}</span>
      </p>
      <p class="text-stone-700 text-[13px] font-zen leading-relaxed">{{ reminder.body }}</p>
    </div>

    <!-- P1-5 朵朵聊天歷史 timeline -->
    <Card tone="plain">
      <h2 class="font-display font-bold text-peach-500 text-base mb-3 flex items-center gap-2">
        <span class="text-xl">💬</span> 朵朵跟妳的對話
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
        title="還沒 check-in 過"
        subtitle="第一次點一下心情，朵朵就會回覆妳。"
      />
    </Card>
  </div>
</template>
