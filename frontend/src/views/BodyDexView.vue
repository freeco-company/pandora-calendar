<script setup lang="ts">
/**
 * BodyDexView (/me/body-dex) — pokédex 風格 30 卡圖鑑
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BodyDexApi, type BodyDexEntry } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Modal from '../components/ui/Modal.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const collected = ref<BodyDexEntry[]>([])
const locked = ref<BodyDexEntry[]>([])
const total = ref(30)
const collectedCount = ref(0)
const loading = ref(true)
const error = ref<string | null>(null)
const detail = ref<BodyDexEntry | null>(null)

const allCells = computed<BodyDexEntry[]>(() => {
  const merged = [...collected.value, ...locked.value]
  return merged.slice(0, total.value)
})

const completionPct = computed(() =>
  total.value > 0 ? Math.round((collectedCount.value / total.value) * 100) : 0,
)
const isComplete = computed(() => collectedCount.value >= total.value && total.value > 0)

const RARITY_COLOR: Record<string, string> = {
  common: 'border-stone-200',
  rare: 'border-sage-300 bg-sage-50',
  epic: 'border-lavender-300 bg-lavender-50',
  legendary: 'border-peach-300 bg-peach-50 ring-2 ring-peach-200',
}
const RARITY_LABEL_KEY: Record<string, string> = {
  common: 'bodydex_rarity_common',
  rare: 'bodydex_rarity_rare',
  epic: 'bodydex_rarity_epic',
  legendary: 'bodydex_rarity_legendary',
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const r = await BodyDexApi.show()
    collected.value = r.data?.data?.collected ?? []
    locked.value = r.data?.data?.locked ?? []
    total.value = r.data?.data?.total ?? 30
    collectedCount.value = r.data?.data?.collected_count ?? collected.value.length
  } catch {
    error.value = t('bodydex_load_failed')
  } finally {
    loading.value = false
  }
}

function open(entry: BodyDexEntry) {
  if (!entry.collected) return
  detail.value = entry
}
function close() {
  detail.value = null
}

import { useOnboardingTour } from '../composables/useOnboardingTour'
const tour = useOnboardingTour()
onMounted(() => {
  load()
  tour.startIfNew('first_body_dex')
})
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-3xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">
      {{ t('common_back') }}
    </button>

    <header class="text-center" data-tour="body-dex-intro">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">
        {{ t('bodydex_eyebrow') }}
      </p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">
        {{ t('bodydex_view_title') }}
      </h1>
      <p class="font-zen text-xs text-stone-500 mt-1">{{ t('bodydex_view_subtitle') }}</p>
    </header>

    <Spinner v-if="loading" :label="t('common_loading_dodo')" />

    <EmptyState
      v-else-if="error"
      icon="🌸"
      :title="t('common_error_title')"
      :subtitle="error"
    />

    <template v-else>
      <Card tone="cream" class="text-center space-y-2">
        <p class="font-zen text-[11px] text-stone-500">
          {{ t('bodydex_count_label', { n: collectedCount, total }) }}
        </p>
        <div class="h-2 rounded-full bg-white overflow-hidden">
          <div
            class="h-full bg-gradient-to-r from-peach-400 to-sakura-400 transition-[width] duration-700"
            :style="{ width: `${completionPct}%` }"
          />
        </div>
        <p
          v-if="isComplete"
          class="font-display font-bold text-peach-500 text-sm pt-1"
          data-test="bodydex-completion"
        >
          🎉 {{ t('bodydex_complete_celebrate') }}
        </p>
      </Card>

      <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5" data-test="bodydex-grid" data-tour="body-dex-grid">
        <button
          v-for="entry in allCells"
          :key="entry.code"
          type="button"
          @click="open(entry)"
          :class="[
            'rounded-2xl p-3 text-center border-2 transition-all active:scale-95 min-h-[110px] flex flex-col items-center justify-center',
            entry.collected
              ? RARITY_COLOR[entry.rarity] || 'border-stone-200 bg-white'
              : 'border-dashed border-stone-200 bg-stone-50 cursor-default',
          ]"
          :disabled="!entry.collected"
          :data-test="entry.collected ? 'dex-cell-collected' : 'dex-cell-locked'"
        >
          <span
            class="text-3xl"
            :class="entry.collected ? '' : 'opacity-25 grayscale'"
            aria-hidden="true"
          >{{ entry.emoji }}</span>
          <p
            class="font-zen text-[11px] mt-1.5 truncate w-full"
            :class="entry.collected ? 'text-stone-700' : 'text-stone-400'"
          >
            {{ entry.collected ? entry.name : '???' }}
          </p>
          <p
            v-if="entry.collected && entry.count > 1"
            class="font-zen text-[10px] text-peach-500 mt-0.5"
          >
            ×{{ entry.count }}
          </p>
          <p
            v-else-if="!entry.collected"
            class="font-zen text-[9px] text-stone-400 mt-0.5"
          >
            {{ t('bodydex_locked_hint') }}
          </p>
        </button>
      </div>
    </template>

    <Modal :open="!!detail" @close="close">
      <div v-if="detail" class="space-y-3">
        <div class="text-center">
          <span class="text-5xl" aria-hidden="true">{{ detail.emoji }}</span>
          <p class="font-display font-bold text-peach-500 text-lg mt-1">{{ detail.name }}</p>
          <span
            class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-zen font-bold"
            :class="{
              'bg-stone-100 text-stone-600': detail.rarity === 'common',
              'bg-sage-100 text-sage-700': detail.rarity === 'rare',
              'bg-lavender-100 text-lavender-700': detail.rarity === 'epic',
              'bg-peach-100 text-peach-700 ring-1 ring-peach-300': detail.rarity === 'legendary',
            }"
          >
            {{ t(RARITY_LABEL_KEY[detail.rarity] || 'bodydex_rarity_common') }}
          </span>
        </div>
        <p class="font-zen text-sm text-stone-700 leading-relaxed">{{ detail.description }}</p>
        <div class="bg-cream-50 rounded-2xl p-3 space-y-1.5">
          <p class="font-zen text-[11px] text-stone-500 uppercase tracking-wider">
            {{ t('bodydex_why_label') }}
          </p>
          <p class="font-zen text-sm text-stone-700 leading-relaxed">{{ detail.why_text }}</p>
        </div>
        <div
          v-if="detail.comfort_action_keys.length"
          class="bg-sage-50 rounded-2xl p-3 space-y-1.5"
        >
          <p class="font-zen text-[11px] text-sage-700 uppercase tracking-wider">
            {{ t('bodydex_comfort_label') }}
          </p>
          <ul class="font-zen text-sm text-stone-700 space-y-1 list-disc pl-4">
            <li v-for="k in detail.comfort_action_keys" :key="k">{{ k }}</li>
          </ul>
        </div>
        <p class="font-zen text-xs text-peach-500 italic leading-relaxed border-l-2 border-peach-300 pl-3">
          🐣 {{ detail.dodo_companion }}
        </p>
      </div>
    </Modal>
  </div>
</template>
