<script setup lang="ts">
/**
 * RankView (/me/rank) — 大段位徽章 + philosophy + 進度 + 6 段位 timeline
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { RankApi, type RankState, type RankTier } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import RankBadge from '../components/RankBadge.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const state = ref<RankState | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const TIERS: RankTier[] = ['stone', 'cream', 'gold', 'rose', 'purple', 'indigo']
const TIER_NAME_KEY: Record<RankTier, string> = {
  stone: 'rank_tier_stone',
  cream: 'rank_tier_cream',
  gold: 'rank_tier_gold',
  rose: 'rank_tier_rose',
  purple: 'rank_tier_purple',
  indigo: 'rank_tier_indigo',
}
const TIER_PHILOSOPHY_KEY: Record<RankTier, string> = {
  stone: 'rank_philosophy_stone',
  cream: 'rank_philosophy_cream',
  gold: 'rank_philosophy_gold',
  rose: 'rank_philosophy_rose',
  purple: 'rank_philosophy_purple',
  indigo: 'rank_philosophy_indigo',
}

const currentIndex = computed(() => state.value?.tier_index ?? 0)

async function load() {
  loading.value = true
  error.value = null
  try {
    const r = await RankApi.show()
    state.value = r.data?.data ?? null
  } catch {
    error.value = t('rank_load_failed')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">
      ← {{ t('common_back') }}
    </button>

    <header class="text-center">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">
        {{ t('rank_eyebrow') }}
      </p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">
        {{ t('rank_view_title') }}
      </h1>
    </header>

    <Spinner v-if="loading" :label="t('common_loading_dodo')" />

    <EmptyState
      v-else-if="error || !state"
      icon="🌸"
      :title="t('common_error_title')"
      :subtitle="error ?? t('rank_empty_subtitle')"
    />

    <template v-else>
      <Card tone="cream" class="text-center space-y-3" data-test="rank-hero">
        <div class="flex justify-center">
          <RankBadge :state="state" :size="96" />
        </div>
        <p class="font-display font-bold text-peach-500 text-xl">
          {{ t(TIER_NAME_KEY[state.tier]) }}
        </p>
        <p class="font-zen text-sm text-stone-600 italic leading-relaxed px-2">
          「{{ state.philosophy ?? t(TIER_PHILOSOPHY_KEY[state.tier]) }}」
        </p>
        <div class="space-y-1.5">
          <div class="h-2 rounded-full bg-white overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-peach-400 to-sakura-400 transition-[width] duration-700"
              :style="{ width: `${state.progress_percent}%` }"
            />
          </div>
          <p class="font-zen text-[11px] text-stone-500">
            {{ t('rank_xp_progress', { xp: state.xp, total: state.next_threshold }) }}
          </p>
          <p class="font-zen text-xs text-peach-500 font-bold">
            {{ t('rank_next_tier_remaining', {
              remaining: Math.max(0, state.next_threshold - state.xp),
            }) }}
          </p>
        </div>
      </Card>

      <!-- timeline -->
      <Card tone="plain" class="space-y-2" data-test="rank-timeline">
        <p class="font-display font-bold text-peach-500 text-sm">
          {{ t('rank_timeline_title') }}
        </p>
        <ul class="space-y-2.5 mt-2">
          <li
            v-for="(tier, i) in TIERS"
            :key="tier"
            class="flex items-center gap-3 rounded-2xl p-2.5 transition-all"
            :class="{
              'bg-cream-50': i === currentIndex,
              'opacity-50': i > currentIndex,
            }"
          >
            <RankBadge :state="{ ...state, tier, tier_index: i }" :size="36" variant="compact" />
            <div class="flex-1 min-w-0">
              <p class="font-zen text-xs text-stone-700 truncate">
                {{ t(TIER_PHILOSOPHY_KEY[tier]) }}
              </p>
            </div>
            <span
              v-if="i < currentIndex"
              class="text-sage-500 text-sm shrink-0"
              :aria-label="t('rank_passed')"
            >✓</span>
            <span
              v-else-if="i === currentIndex"
              class="font-zen text-[10px] text-peach-500 font-bold shrink-0"
            >{{ t('rank_current_label') }}</span>
          </li>
        </ul>
      </Card>
    </template>
  </div>
</template>
