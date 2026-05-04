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
import PetBondMeter from '../components/PetBondMeter.vue'
import RankBadge from '../components/RankBadge.vue'
import { usePet } from '../composables/usePet'
import { useTone } from '../composables/useTone'
import { useGameDepth } from '../composables/useGameDepth'
import { useOnboardingTour } from '../composables/useOnboardingTour'

const { t } = useTone()
const router = useRouter()
const gameDepth = useGameDepth()
const tour = useOnboardingTour()
const data = ref<JourneyData | null>(null)
const achievements = ref<AchievementRow[]>([])
const outfits = ref<OutfitRow[]>([])
const equippedCode = ref<string>('none')
const loading = ref(true)
const { pet } = usePet()
const user = getStoredUser()

// === Preview state（2026-05-03 新增）===
// previewCode 設了之後 Character 立刻換上該 outfit 顯示，但不真的 equip。
// null = 沒在預覽，使用 equippedCode 顯示。
const previewCode = ref<string | null>(null)
const detailAchievement = ref<AchievementRow | null>(null)

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

// Character 上實際展示的 outfit code：preview > equipped > pet 預設
const displayedOutfitCode = computed(
  () => previewCode.value ?? (equippedCode.value as string) ?? pet.value.outfit,
)

const previewOutfit = computed<OutfitRow | null>(() =>
  previewCode.value ? outfits.value.find((o) => o.code === previewCode.value) ?? null : null,
)

async function load() {
  loading.value = true
  // allSettled：任一 API 5xx 不會炸整頁，改個別 fallback
  try {
    const [j, a, o] = await Promise.allSettled([
      JourneyApi.show(),
      AchievementsApi.list(),
      OutfitsApi.list(),
    ])
    if (j.status === 'fulfilled') data.value = j.value.data.data
    if (a.status === 'fulfilled') achievements.value = a.value.data.data.achievements
    if (o.status === 'fulfilled') {
      outfits.value = o.value.data.data.outfits
      equippedCode.value = o.value.data.data.equipped
    }
  } finally {
    loading.value = false
  }
}
onMounted(() => {
  load()
  gameDepth.refreshRank().catch(() => {})
  tour.startIfNew('first_journey')
})

function preview(code: string) {
  // 點同一件 = 取消預覽
  previewCode.value = previewCode.value === code ? null : code
}

function cancelPreview() {
  previewCode.value = null
}

async function confirmEquip() {
  if (!previewCode.value) return
  const code = previewCode.value
  const target = outfits.value.find((o) => o.code === code)
  if (!target || !target.unlocked) {
    alert(t('journey_outfit_locked_msg'))
    return
  }
  try {
    await OutfitsApi.equip(code)
    equippedCode.value = code
    outfits.value = outfits.value.map((o) => ({ ...o, equipped: o.unlocked && o.code === code }))
    previewCode.value = null
  } catch (e: any) {
    alert(e?.response?.data?.error === 'outfit_locked' ? t('journey_outfit_locked_msg') : t('journey_outfit_equip_failed'))
  }
}

async function takeOff() {
  try {
    await OutfitsApi.equip('none')
    equippedCode.value = 'none'
    outfits.value = outfits.value.map((o) => ({ ...o, equipped: false }))
    previewCode.value = null
  } catch {
    alert(t('journey_outfit_equip_failed'))
  }
}

function openAchievement(a: AchievementRow) {
  detailAchievement.value = a
}
function closeAchievement() {
  detailAchievement.value = null
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
      <!-- 寵物 + Lv 進度（外加 preview 效果）-->
      <Card tone="cream" class="text-center space-y-3" data-tour="pet-character">
        <div class="flex justify-center">
          <Character
            :species="pet.species"
            :level="data.level"
            :outfit="(displayedOutfitCode as any) || pet.outfit"
            mood="proud"
            :size="120"
            :show-halo="true"
            :floaty="true"
            data-test="journey-character"
          />
        </div>

        <!-- Preview banner：在預覽中顯示確認 / 取消按鈕 -->
        <div
          v-if="previewOutfit"
          class="bg-white/80 border border-peach-200 rounded-2xl p-3 mx-2 flex items-center gap-3 text-left"
          data-test="outfit-preview-banner"
        >
          <span class="text-2xl">{{ previewOutfit.icon }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-zen text-[12px] text-stone-700 font-bold truncate">
              {{ t('journey_preview_trying', { name: previewOutfit.name }) }}
            </p>
            <p class="font-zen text-[10px]" :class="previewOutfit.unlocked ? 'text-stone-500' : 'text-rose-500'">
              {{ previewOutfit.unlocked ? t('journey_preview_can_equip') : t('journey_preview_still_locked', { hint: previewOutfit.hint }) }}
            </p>
          </div>
          <div class="flex flex-col gap-1">
            <button
              v-if="previewOutfit.unlocked"
              @click="confirmEquip"
              class="bg-peach-500 text-white text-[11px] font-bold rounded-full px-3 py-1 active:scale-95"
              data-test="outfit-preview-confirm"
            >{{ t('journey_preview_confirm') }}</button>
            <button
              @click="cancelPreview"
              class="bg-stone-100 text-stone-500 text-[11px] font-bold rounded-full px-3 py-1 active:scale-95"
              data-test="outfit-preview-cancel"
            >{{ t('journey_preview_cancel') }}</button>
          </div>
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

      <!-- 寵物羈絆條 -->
      <div data-tour="bond-meter">
        <PetBondMeter :show-pet-head-button="true" data-test="journey-bond-meter" />
      </div>

      <!-- 段位 + game-depth quick cards -->
      <div class="grid grid-cols-3 gap-2.5" data-test="journey-quick-links" data-tour="quick-links">
        <button
          @click="router.push('/me/rank')"
          class="rounded-2xl bg-white p-3 shadow-soft text-center active:scale-95 transition-transform"
          data-test="journey-link-rank"
        >
          <RankBadge
            v-if="gameDepth.rank.value"
            :state="gameDepth.rank.value"
            :size="28"
            variant="compact"
            class="justify-center"
          />
          <span v-else class="text-2xl block" aria-hidden="true">⭐</span>
          <p class="font-zen text-[11px] text-stone-600 mt-1">{{ t('journey_quick_rank') }}</p>
        </button>
        <button
          @click="router.push('/me/body-dex')"
          class="rounded-2xl bg-white p-3 shadow-soft text-center active:scale-95 transition-transform"
          data-test="journey-link-bodydex"
        >
          <span class="text-2xl block" aria-hidden="true">📔</span>
          <p class="font-zen text-[11px] text-stone-600 mt-1">{{ t('journey_quick_bodydex') }}</p>
        </button>
        <button
          @click="router.push('/me/stories')"
          class="rounded-2xl bg-white p-3 shadow-soft text-center active:scale-95 transition-transform"
          data-test="journey-link-stories"
        >
          <span class="text-2xl block" aria-hidden="true">📖</span>
          <p class="font-zen text-[11px] text-stone-600 mt-1">{{ t('journey_quick_stories') }}</p>
        </button>
      </div>

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
      <Card tone="plain" class="space-y-3" data-tour="achievements-section">
        <div class="flex items-baseline justify-between">
          <h3 class="font-display font-bold text-peach-500 text-base">{{ t('journey_section_achievements') }}</h3>
          <p class="font-zen text-[11px] text-stone-500">{{ unlockedAchievements.length }} / {{ achievements.length }}</p>
        </div>

        <!-- 已解鎖（彩色） -->
        <div v-if="unlockedAchievements.length" class="grid grid-cols-3 gap-3">
          <button
            v-for="a in unlockedAchievements"
            :key="a.key"
            @click="openAchievement(a)"
            :data-test="`achievement-${a.key}`"
            class="flex flex-col items-center gap-1 active:scale-95 transition-transform"
          >
            <div class="w-16 h-16 flex items-center justify-center">
              <img :src="a.badge_url" :alt="a.name" class="w-full h-full object-contain drop-shadow-sm" />
            </div>
            <p class="font-zen text-[11px] text-stone-700 text-center font-bold">{{ a.name }}</p>
            <p class="font-zen text-[9px]" :class="TIER_COLOR[a.tier]">{{ a.tier.toUpperCase() }}</p>
          </button>
        </div>

        <!-- 待解（灰階 + 進度提示） -->
        <details v-if="lockedAchievements.length" class="pt-2 border-t border-cream-100">
          <summary class="font-zen text-[12px] text-stone-500 cursor-pointer">{{ t('journey_locked_count_a', { n: lockedAchievements.length }) }}</summary>
          <div class="mt-3 grid grid-cols-3 gap-3">
            <button
              v-for="a in lockedAchievements"
              :key="a.key"
              @click="openAchievement(a)"
              class="flex flex-col items-center gap-1 opacity-40 active:opacity-70 transition-opacity"
            >
              <div class="w-16 h-16 flex items-center justify-center grayscale">
                <img :src="a.badge_url" :alt="a.name" class="w-full h-full object-contain" />
              </div>
              <p class="font-zen text-[11px] text-stone-600 text-center">{{ a.name }}</p>
              <p class="font-zen text-[9px] text-stone-400">{{ a.hint }}</p>
            </button>
          </div>
        </details>
      </Card>

      <!-- Outfit 解鎖牆 — 兩段式（已解鎖 / 待解），都可點預覽 -->
      <Card tone="plain" class="space-y-3" data-tour="outfits-section">
        <div class="flex items-baseline justify-between">
          <h3 class="font-display font-bold text-peach-500 text-base">{{ t('journey_section_outfits') }}</h3>
          <p class="font-zen text-[11px] text-stone-500">{{ unlockedOutfits.length }} / {{ outfits.length }}</p>
        </div>

        <p class="font-zen text-[11px] text-stone-400">{{ t('journey_outfit_preview_hint') }}</p>

        <div v-if="unlockedOutfits.length" class="grid grid-cols-2 gap-2">
          <button
            v-for="o in unlockedOutfits"
            :key="o.code"
            @click="preview(o.code)"
            :data-test="`outfit-${o.code}`"
            :class="[
              RARITY_COLOR[o.rarity] || 'border-stone-200 bg-white',
              o.equipped ? 'ring-2 ring-peach-400' : '',
              previewCode === o.code ? 'ring-2 ring-lavender-400' : '',
              'border rounded-2xl p-3 flex items-center gap-2 transition-all active:scale-95'
            ]"
          >
            <span class="text-2xl">{{ o.icon }}</span>
            <div class="flex-1 text-left min-w-0">
              <p class="font-zen text-[12px] text-stone-700 truncate font-bold">{{ o.name }}</p>
              <p class="font-zen text-[10px] text-stone-400">{{ RARITY_LABEL[o.rarity] }}</p>
            </div>
            <span v-if="o.equipped" class="text-[10px] text-peach-500 font-bold">{{ t('journey_outfit_equipped') }}</span>
            <span v-else-if="previewCode === o.code" class="text-[10px] text-lavender-600 font-bold">{{ t('journey_outfit_previewing') }}</span>
          </button>
          <button
            v-if="equippedCode !== 'none'"
            @click="takeOff"
            class="border border-stone-200 bg-cream-50 rounded-2xl p-3 text-stone-500 font-zen text-xs hover:bg-cream-100"
          >
            {{ t('journey_outfit_take_off') }}
          </button>
        </div>

        <details v-if="lockedOutfits.length" class="pt-2 border-t border-cream-100" open>
          <summary class="font-zen text-[12px] text-stone-500 cursor-pointer">{{ t('journey_locked_count_o', { n: lockedOutfits.length }) }}</summary>
          <div class="mt-3 grid grid-cols-2 gap-2">
            <button
              v-for="o in lockedOutfits"
              :key="o.code"
              @click="preview(o.code)"
              :data-test="`outfit-locked-${o.code}`"
              :class="[
                RARITY_COLOR[o.rarity] || 'border-stone-200 bg-white',
                previewCode === o.code ? 'ring-2 ring-lavender-400 opacity-90' : 'opacity-50',
                'border rounded-2xl p-3 flex items-center gap-2 transition-all active:scale-95 text-left'
              ]"
            >
              <span class="text-2xl" :class="previewCode === o.code ? '' : 'grayscale'">{{ o.icon }}</span>
              <div class="flex-1 text-left min-w-0">
                <p class="font-zen text-[12px] text-stone-600 truncate">{{ o.name }}</p>
                <p class="font-zen text-[10px] text-stone-400">{{ o.hint }}</p>
              </div>
              <span class="text-[14px]">🔒</span>
            </button>
          </div>
        </details>
      </Card>
    </template>
  </div>

  <!-- Achievement 詳情 modal -->
  <div
    v-if="detailAchievement"
    class="fixed inset-0 bg-stone-900/40 z-50 flex items-end md:items-center justify-center p-4"
    @click.self="closeAchievement"
    data-test="achievement-detail-modal"
  >
    <div class="bg-white rounded-3xl p-6 max-w-sm w-full space-y-3 shadow-xl">
      <div class="flex justify-center">
        <img
          :src="detailAchievement.badge_url"
          :alt="detailAchievement.name"
          class="w-24 h-24 object-contain"
          :class="detailAchievement.unlocked ? 'drop-shadow' : 'grayscale opacity-50'"
        />
      </div>
      <h3 class="font-display text-xl font-bold text-peach-500 text-center">{{ detailAchievement.name }}</h3>
      <p class="font-zen text-[11px] text-center" :class="TIER_COLOR[detailAchievement.tier]">
        {{ detailAchievement.tier.toUpperCase() }} · +{{ detailAchievement.xp ?? 0 }} XP
      </p>
      <p class="font-zen text-sm text-stone-600 text-center">{{ detailAchievement.hint }}</p>
      <p
        v-if="detailAchievement.unlocked && detailAchievement.unlocked_at"
        class="font-zen text-[11px] text-stone-400 text-center"
      >{{ t('journey_achievement_unlocked_on', { date: String(detailAchievement.unlocked_at).slice(0, 10) }) }}</p>
      <p v-else class="font-zen text-[11px] text-stone-400 text-center">{{ t('journey_achievement_still_locked') }}</p>
      <button
        @click="closeAchievement"
        class="w-full bg-peach-500 text-white font-zen rounded-full py-2 active:scale-95"
      >{{ t('common_close') }}</button>
    </div>
  </div>
</template>
