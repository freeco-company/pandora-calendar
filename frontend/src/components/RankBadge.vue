<script setup lang="ts">
/**
 * RankBadge — 段位徽章（6 漸層）+ 中文 tier 名 + 可選的進度條
 *
 * stone → cream → gold → rose → purple → indigo
 *
 * Compact mode: 小徽章只顯示 tier 名稱 + 漸層圓徽
 * Full mode:    含進度條 + 還差多少 XP
 */
import { computed } from 'vue'
import { useTone } from '../composables/useTone'
import type { RankTier, RankState } from '../api'

const props = defineProps<{
  state?: RankState | null
  variant?: 'compact' | 'full'
  size?: number
}>()

const { t } = useTone()

const TIER_GRADIENT: Record<RankTier, string> = {
  stone: 'from-stone-300 to-stone-500',
  cream: 'from-cream-200 to-peach-300',
  gold: 'from-yellow-300 to-amber-500',
  rose: 'from-rose-300 to-rose-500',
  purple: 'from-purple-300 to-purple-500',
  indigo: 'from-indigo-300 to-indigo-600',
}

const TIER_NAME_KEY: Record<RankTier, string> = {
  stone: 'rank_tier_stone',
  cream: 'rank_tier_cream',
  gold: 'rank_tier_gold',
  rose: 'rank_tier_rose',
  purple: 'rank_tier_purple',
  indigo: 'rank_tier_indigo',
}

const tier = computed<RankTier>(() => props.state?.tier ?? 'stone')
const gradient = computed(() => TIER_GRADIENT[tier.value])
const tierLabel = computed(() => t(TIER_NAME_KEY[tier.value]))
const sizePx = computed(() => props.size ?? 48)
</script>

<template>
  <div v-if="variant === 'full'" class="space-y-2" data-test="rank-badge-full">
    <div class="flex items-center gap-3">
      <div
        class="rounded-full shrink-0 bg-gradient-to-br shadow-soft flex items-center justify-center text-white font-display font-bold"
        :class="gradient"
        :style="{ width: sizePx + 'px', height: sizePx + 'px', fontSize: Math.round(sizePx * 0.4) + 'px' }"
        aria-hidden="true"
      >
        ★
      </div>
      <div class="min-w-0">
        <p class="font-zen text-[10px] uppercase tracking-widest text-stone-500">
          {{ t('rank_progress_label') }}
        </p>
        <p class="font-display font-bold text-peach-500 text-lg truncate">{{ tierLabel }}</p>
      </div>
    </div>
    <div v-if="state">
      <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
        <div
          class="h-full bg-gradient-to-r from-peach-400 to-sakura-400 transition-[width] duration-700"
          :style="{ width: `${state.progress_percent}%` }"
        />
      </div>
      <p class="font-zen text-[11px] text-stone-500 mt-1">
        {{ t('rank_next_tier_remaining', {
          remaining: Math.max(0, state.next_threshold - state.xp),
        }) }}
      </p>
    </div>
  </div>

  <div v-else class="inline-flex items-center gap-1.5" data-test="rank-badge-compact">
    <div
      class="rounded-full shrink-0 bg-gradient-to-br shadow-soft flex items-center justify-center text-white font-display font-bold"
      :class="gradient"
      :style="{ width: sizePx + 'px', height: sizePx + 'px', fontSize: Math.round(sizePx * 0.45) + 'px' }"
      aria-hidden="true"
    >
      ★
    </div>
    <span class="font-zen text-xs text-peach-500 font-bold whitespace-nowrap">{{ tierLabel }}</span>
  </div>
</template>
