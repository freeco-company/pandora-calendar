<script setup lang="ts">
/**
 * CalendarGamificationStrip — 月曆首頁 above-the-fold 遊戲化條
 *
 * 4 區塊：Pet (clickable) / Streak / Today's quest / Next milestone progress
 * - 直接打 /me/journey 拉 streak / level / progress（既有 API）
 * - quest 用 useDailyQuest 抽
 * - sm+ 一橫 4 欄；< sm 2x2 grid
 */
import { computed, onActivated, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ActionApi, JourneyApi, type DailyAction, type JourneyData } from '../api'
import Character from './Character.vue'
import { getPet, moodForPhase } from '../lib/character'
import { useTone } from '../composables/useTone'
import { useDailyQuest } from '../composables/useDailyQuest'

const props = defineProps<{
  phase?: string | null
}>()

const { t } = useTone()
const router = useRouter()
const pet = ref(getPet())
const journey = ref<JourneyData | null>(null)
const loading = ref(true)
const { current: quest, isCompleted, markCompleted, refresh } = useDailyQuest()

// 個人化 daily action — 若 backend 有資料則優先顯示，沒有 fallback 既有 quest pool
const personalizedAction = ref<DailyAction | null>(null)

async function load() {
  loading.value = true
  try {
    const r = await JourneyApi.show()
    journey.value = r.data.data
  } catch {
    journey.value = null
  } finally {
    loading.value = false
  }
}

async function loadPersonalizedAction() {
  try {
    const r = await ActionApi.today()
    personalizedAction.value = r.data.data
  } catch {
    personalizedAction.value = null
  }
}

onMounted(() => {
  load()
  loadPersonalizedAction()
})
onActivated(() => {
  refresh()
  load()
  loadPersonalizedAction()
})

// 是否走個人化 action 模式
const usePersonalizedAction = computed(() => !!personalizedAction.value)
const personalizedDone = computed(() => !!personalizedAction.value?.is_completed)

const todayMood = computed(() => moodForPhase(props.phase ?? null))
const streakDays = computed(() => journey.value?.streak_days ?? 0)
const level = computed(() => journey.value?.level ?? pet.value.level ?? 1)

// streak milestone tone — 7 / 14 / 30
const streakTier = computed<'fresh' | 'good' | 'fire' | 'legend'>(() => {
  const d = streakDays.value
  if (d >= 30) return 'legend'
  if (d >= 14) return 'fire'
  if (d >= 7) return 'good'
  return 'fresh'
})

const streakClasses = computed(() => {
  switch (streakTier.value) {
    case 'legend':
      return 'bg-gradient-to-br from-peach-100 to-sakura-100 text-peach-600 ring-2 ring-peach-300'
    case 'fire':
      return 'bg-gradient-to-br from-peach-50 to-cream-100 text-peach-500 ring-1 ring-peach-200'
    case 'good':
      return 'bg-cream-100 text-peach-500'
    default:
      return 'bg-stone-50 text-stone-600'
  }
})

const streakIcon = computed(() => {
  switch (streakTier.value) {
    case 'legend':
      return '✨'
    case 'fire':
      return '🔥'
    case 'good':
      return '🔥'
    default:
      return '🌱'
  }
})

// milestone progress（用 journey 既有資料：progress_in_level / need_for_next_level）
const progressPct = computed(() => {
  if (!journey.value || journey.value.need_for_next_level <= 0) return 0
  return Math.min(100, Math.round((journey.value.progress_in_level / journey.value.need_for_next_level) * 100))
})

const xpRemaining = computed(() => {
  if (!journey.value) return 0
  return Math.max(0, journey.value.need_for_next_level - journey.value.progress_in_level)
})

function goPet() {
  router.push('/me/journey')
}

function goQuest() {
  // 有個人化 action → 跳 DailyActionView 完成
  if (usePersonalizedAction.value) {
    router.push('/me/action-today')
    return
  }
  // mark completed on click — user actually engaged with the suggested action.
  // 真正 XP 仍由目的 view（Log / Dodo / BBT）走既有 publisher 發放。
  markCompleted()
  router.push(quest.value.cta_route)
}
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-4" data-test="gam-strip">
    <!-- 1. Pet -->
    <button
      type="button"
      @click="goPet"
      class="rounded-2xl p-3 bg-white shadow-soft active:scale-95 transition-transform flex items-center gap-2.5 text-left"
      :title="`${pet.nickname}, Lv ${level}`"
      data-test="gam-strip-pet"
    >
      <div class="shrink-0">
        <Character
          :species="pet.species"
          :level="level"
          :outfit="pet.outfit"
          :mood="todayMood"
          :size="48"
          :show-halo="false"
          :floaty="true"
          :show-rarity="false"
        />
      </div>
      <div class="min-w-0">
        <p class="font-zen text-[11px] text-stone-500 truncate">{{ pet.nickname }}</p>
        <p class="font-display font-bold text-peach-500 text-sm leading-tight">
          {{ t('gam_strip_pet_lv', { lv: level }) }}
        </p>
      </div>
    </button>

    <!-- 2. Streak -->
    <div
      class="rounded-2xl p-3 shadow-soft flex items-center gap-2.5"
      :class="streakClasses"
      data-test="gam-strip-streak"
    >
      <span class="text-2xl shrink-0" aria-hidden="true">{{ streakIcon }}</span>
      <div class="min-w-0">
        <p class="font-display font-bold text-xl leading-none">{{ streakDays }}</p>
        <p class="font-zen text-[11px] mt-0.5 truncate">{{ t('gam_strip_streak_label') }}</p>
      </div>
    </div>

    <!-- 3. Today's quest（personalized action 優先，無則 fallback quest pool） -->
    <button
      type="button"
      @click="goQuest"
      :disabled="usePersonalizedAction ? personalizedDone : isCompleted"
      class="rounded-2xl p-3 shadow-soft text-left active:scale-95 transition-transform flex flex-col justify-between min-h-[72px]"
      :class="(usePersonalizedAction ? personalizedDone : isCompleted)
        ? 'bg-stone-100 text-stone-400 cursor-default'
        : 'bg-gradient-to-br from-sage-50 to-cream-50 text-stone-700'"
      data-test="gam-strip-quest"
    >
      <p class="font-zen text-[10px] uppercase tracking-wider text-peach-500/80 flex items-center gap-1">
        <span>{{ t('gam_strip_quest_today') }}</span>
        <span v-if="usePersonalizedAction ? personalizedDone : isCompleted" class="text-sage-500" aria-hidden="true">✓</span>
      </p>
      <p
        class="font-zen text-[12px] leading-tight mt-0.5 line-clamp-2"
        :class="(usePersonalizedAction ? personalizedDone : isCompleted) && 'line-through'"
      >
        {{ usePersonalizedAction ? personalizedAction!.title : t(quest.title_key) }}
      </p>
      <p
        v-if="!(usePersonalizedAction ? personalizedDone : isCompleted)"
        class="font-zen text-[10px] text-sage-500 mt-1"
      >
        <template v-if="usePersonalizedAction">→</template>
        <template v-else>+{{ quest.xp_reward }} XP</template>
      </p>
      <p
        v-else
        class="font-zen text-[10px] mt-1"
      >
        {{ t('quest_done') }}
      </p>
    </button>

    <!-- 4. Next milestone progress -->
    <div
      class="rounded-2xl p-3 bg-white shadow-soft flex flex-col justify-between min-h-[72px]"
      data-test="gam-strip-milestone"
    >
      <p class="font-zen text-[10px] uppercase tracking-wider text-stone-500">
        {{ t('gam_strip_milestone_label') }}
      </p>
      <div>
        <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden mt-1">
          <div
            class="h-full bg-gradient-to-r from-peach-400 to-sakura-400 transition-[width] duration-500"
            :style="{ width: `${progressPct}%` }"
          />
        </div>
        <p class="font-zen text-[11px] text-stone-600 mt-1.5 leading-none">
          <span class="font-bold text-peach-500">{{ progressPct }}%</span>
          <span class="text-stone-400 ml-1.5">
            {{ t('gam_strip_milestone_remaining', { xp: xpRemaining }) }}
          </span>
        </p>
      </div>
    </div>
  </div>
</template>
