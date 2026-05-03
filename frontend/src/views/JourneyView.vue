<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  JourneyApi, AchievementsApi, OutfitsApi,
  type JourneyData, type AchievementRow, type OutfitRow,
  getStoredUser,
} from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
import { usePet } from '../composables/usePet'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const data = ref<JourneyData | null>(null)
const achievements = ref<AchievementRow[]>([])
const outfits = ref<OutfitRow[]>([])
const equippedCode = ref<string>('none')
const loading = ref(true)
const { pet } = usePet()
const user = getStoredUser()

const RARITY_COLOR: Record<string, string> = {
  common: 'border-stone-200 bg-white',
  rare: 'border-sage-200 bg-sage-50',
  epic: 'border-lavender-200 bg-lavender-50',
  legendary: 'border-peach-300 bg-peach-50 ring-2 ring-peach-200',
}
const RARITY_LABEL = computed<Record<string, string>>(() => ({
  common: t('journey_rarity_common'), rare: t('journey_rarity_rare'), epic: t('journey_rarity_epic'), legendary: t('journey_rarity_legendary'),
}))
const TIER_COLOR: Record<string, string> = {
  bronze: 'text-amber-700',
  silver: 'text-stone-500',
  gold: 'text-yellow-600',
}

const progressPct = computed(() => {
  if (!data.value) return 0
  return Math.min(100, Math.round((data.value.progress_in_level / data.value.need_for_next_level) * 100))
})

const unlockedAchievements = computed(() => achievements.value.filter((a) => a.unlocked))
const lockedAchievements = computed(() => achievements.value.filter((a) => !a.unlocked))
const unlockedOutfits = computed(() => outfits.value.filter((o) => o.unlocked))
const lockedOutfits = computed(() => outfits.value.filter((o) => !o.unlocked))

async function load() {
  loading.value = true
  try {
    const [j, a, o] = await Promise.all([
      JourneyApi.show(),
      AchievementsApi.list(),
      OutfitsApi.list(),
    ])
    data.value = j.data.data
    achievements.value = a.data.data.achievements
    outfits.value = o.data.data.outfits
    equippedCode.value = o.data.data.equipped
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function equip(code: string) {
  try {
    await OutfitsApi.equip(code)
    equippedCode.value = code
    outfits.value = outfits.value.map((o) => ({ ...o, equipped: o.unlocked && o.code === code }))
  } catch (e: any) {
    alert(e?.response?.data?.error === 'outfit_locked' ? t('journey_outfit_locked_msg') : t('journey_outfit_equip_failed'))
  }
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-3xl lg:max-w-4xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">{{ t('common_back') }}</button>

    <header class="text-center">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('journey_eyebrow') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">
        {{ t('journey_title', { name: user?.display_name ?? user?.name ?? t('profile_greeting_default') }) }}
      </h1>
    </header>

    <Spinner v-if="loading" :label="t('common_loading_dodo')" />

    <template v-else-if="data">
      <!-- 寵物 + Lv 進度 -->
      <Card tone="cream" class="text-center space-y-3">
        <div class="flex justify-center">
          <Character
            :species="pet.species"
            :level="data.level"
            :outfit="(equippedCode as any) || pet.outfit"
            mood="proud"
            :size="120"
            :show-halo="true"
            :floaty="true"
          />
        </div>
        <p class="font-display text-3xl font-bold text-peach-500">Lv {{ data.level }}</p>
        <div class="bg-white rounded-full h-3 overflow-hidden mx-2">
          <div
            class="h-full bg-peach-gradient transition-all"
            :style="{ width: progressPct + '%' }"
            data-test="xp-progress"
          />
        </div>
        <p class="font-zen text-[12px] text-stone-500">
          {{ t('journey_total_xp', { cur: data.progress_in_level, need: data.need_for_next_level, total: data.total_xp }) }}
        </p>
      </Card>

      <!-- 連勝 + 30 天統計 -->
      <Card tone="plain" class="space-y-2.5">
        <h3 class="font-display font-bold text-peach-500 text-base flex items-center gap-2">
          <span>🔥</span>
          <span>{{ t('journey_streak_title') }}</span>
          <span class="font-display text-2xl text-sakura-500">{{ data.streak_days }}</span>
          <span class="font-zen text-[12px] text-stone-500 self-end pb-1">{{ t('journey_streak_unit') }}</span>
        </h3>
        <div class="grid grid-cols-3 gap-2 text-center text-sm font-zen">
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.cycles_logged }}</p>
            <p class="text-[11px] text-stone-500">{{ t('journey_stat_cycles') }}</p>
          </div>
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.symptoms_logged }}</p>
            <p class="text-[11px] text-stone-500">{{ t('journey_stat_symptoms') }}</p>
          </div>
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.dodo_checkins }}</p>
            <p class="text-[11px] text-stone-500">{{ t('journey_stat_checkins') }}</p>
          </div>
        </div>
      </Card>

      <!-- 成就（badge SVG） -->
      <Card tone="plain" class="space-y-3">
        <div class="flex items-baseline justify-between">
          <h3 class="font-display font-bold text-peach-500 text-base">{{ t('journey_section_achievements') }}</h3>
          <p class="font-zen text-[11px] text-stone-500">{{ unlockedAchievements.length }} / {{ achievements.length }}</p>
        </div>

        <!-- 已解鎖（彩色） -->
        <div v-if="unlockedAchievements.length" class="grid grid-cols-3 gap-3">
          <div
            v-for="a in unlockedAchievements"
            :key="a.key"
            class="flex flex-col items-center gap-1"
          >
            <div class="w-16 h-16 flex items-center justify-center">
              <img :src="a.badge_url" :alt="a.name" class="w-full h-full object-contain drop-shadow-sm" />
            </div>
            <p class="font-zen text-[11px] text-stone-700 text-center font-bold">{{ a.name }}</p>
            <p class="font-zen text-[9px]" :class="TIER_COLOR[a.tier]">{{ a.tier.toUpperCase() }}</p>
          </div>
        </div>

        <!-- 待解（灰階 + 進度提示） -->
        <details v-if="lockedAchievements.length" class="pt-2 border-t border-cream-100">
          <summary class="font-zen text-[12px] text-stone-500 cursor-pointer">{{ t('journey_locked_count_a', { n: lockedAchievements.length }) }}</summary>
          <div class="mt-3 grid grid-cols-3 gap-3">
            <div
              v-for="a in lockedAchievements"
              :key="a.key"
              class="flex flex-col items-center gap-1 opacity-40"
            >
              <div class="w-16 h-16 flex items-center justify-center grayscale">
                <img :src="a.badge_url" :alt="a.name" class="w-full h-full object-contain" />
              </div>
              <p class="font-zen text-[11px] text-stone-600 text-center">{{ a.name }}</p>
              <p class="font-zen text-[9px] text-stone-400">{{ a.hint }}</p>
            </div>
          </div>
        </details>
      </Card>

      <!-- Outfit 解鎖牆 -->
      <Card tone="plain" class="space-y-3">
        <div class="flex items-baseline justify-between">
          <h3 class="font-display font-bold text-peach-500 text-base">{{ t('journey_section_outfits') }}</h3>
          <p class="font-zen text-[11px] text-stone-500">{{ unlockedOutfits.length }} / {{ outfits.length }}</p>
        </div>

        <div v-if="unlockedOutfits.length" class="grid grid-cols-2 gap-2">
          <button
            v-for="o in unlockedOutfits"
            :key="o.code"
            @click="equip(o.code)"
            :data-test="`outfit-${o.code}`"
            :class="[
              RARITY_COLOR[o.rarity] || 'border-stone-200 bg-white',
              o.equipped ? 'ring-2 ring-peach-400' : '',
              'border rounded-2xl p-3 flex items-center gap-2 transition-all active:scale-95'
            ]"
          >
            <span class="text-2xl">{{ o.icon }}</span>
            <div class="flex-1 text-left min-w-0">
              <p class="font-zen text-[12px] text-stone-700 truncate font-bold">{{ o.name }}</p>
              <p class="font-zen text-[10px] text-stone-400">{{ RARITY_LABEL[o.rarity] }}</p>
            </div>
            <span v-if="o.equipped" class="text-[10px] text-peach-500 font-bold">{{ t('journey_outfit_equipped') }}</span>
          </button>
          <button
            v-if="equippedCode !== 'none'"
            @click="equip('none')"
            class="border border-stone-200 bg-cream-50 rounded-2xl p-3 text-stone-500 font-zen text-xs hover:bg-cream-100"
          >
            {{ t('journey_outfit_take_off') }}
          </button>
        </div>

        <details v-if="lockedOutfits.length" class="pt-2 border-t border-cream-100">
          <summary class="font-zen text-[12px] text-stone-500 cursor-pointer">{{ t('journey_locked_count_o', { n: lockedOutfits.length }) }}</summary>
          <div class="mt-3 grid grid-cols-2 gap-2">
            <div
              v-for="o in lockedOutfits"
              :key="o.code"
              :class="[
                RARITY_COLOR[o.rarity] || 'border-stone-200 bg-white',
                'border rounded-2xl p-3 flex items-center gap-2 opacity-50'
              ]"
            >
              <span class="text-2xl grayscale">{{ o.icon }}</span>
              <div class="flex-1 text-left min-w-0">
                <p class="font-zen text-[12px] text-stone-600 truncate">{{ o.name }}</p>
                <p class="font-zen text-[10px] text-stone-400">{{ o.hint }}</p>
              </div>
              <span class="text-[14px]">🔒</span>
            </div>
          </div>
        </details>
      </Card>
    </template>
  </div>
</template>
