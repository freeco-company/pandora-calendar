<script setup lang="ts">
/**
 * StoryChaptersView (/me/stories) — 25 章 timeline + StoryUnlockModal
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { StoryApi, type StoryChapter } from '../api'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import StoryUnlockModal from '../components/StoryUnlockModal.vue'
import DodoCoinDisplay from '../components/DodoCoinDisplay.vue'
import { useTone } from '../composables/useTone'
import { useEconomy } from '../composables/useEconomy'

const { t } = useTone()
const router = useRouter()
const economy = useEconomy()

const chapters = ref<StoryChapter[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const activeChapter = ref<StoryChapter | null>(null)
const modalOpen = ref(false)
const unlocking = ref<number | null>(null)

const unlockedCount = computed(() => chapters.value.filter((c) => c.unlocked).length)

async function load() {
  loading.value = true
  error.value = null
  try {
    const r = await StoryApi.chapters()
    chapters.value = r.data?.data?.chapters ?? []
  } catch {
    error.value = t('story_load_failed')
  } finally {
    loading.value = false
  }
}

async function openChapter(c: StoryChapter) {
  if (!c.unlocked) return
  // ensure we have dialog
  if (!c.dialog || c.dialog.length === 0) {
    try {
      const r = await StoryApi.unlock(c.chapter)
      const fresh = r.data?.data?.chapter_data
      if (fresh) {
        c.dialog = fresh.dialog
      }
    } catch {
      // graceful — show empty dialog state
    }
  }
  activeChapter.value = c
  modalOpen.value = true
}

async function payToUnlock(c: StoryChapter) {
  if (c.unlocked || unlocking.value === c.chapter) return
  unlocking.value = c.chapter
  try {
    const r = await StoryApi.unlock(c.chapter)
    const fresh = r.data?.data?.chapter_data
    if (fresh) {
      // splice in
      const idx = chapters.value.findIndex((x) => x.chapter === c.chapter)
      if (idx >= 0) chapters.value[idx] = fresh
    }
    economy.refresh()
    if (fresh && fresh.unlocked) openChapter(fresh)
  } catch (e: any) {
    // backend 回 friendly message + reason — 顯示具體原因（朵朵幣不夠 / 已解鎖 / 找不到）
    error.value = e?.response?.data?.message ?? t('story_unlock_failed')
  } finally {
    unlocking.value = null
  }
}

async function onFinished() {
  if (activeChapter.value) {
    try {
      await StoryApi.read(activeChapter.value.chapter)
      activeChapter.value.read_at = new Date().toISOString()
    } catch {
      /* graceful */
    }
  }
}

function onClose() {
  modalOpen.value = false
}

import { useOnboardingTour } from '../composables/useOnboardingTour'
const tour = useOnboardingTour()
onMounted(() => {
  load()
  tour.startIfNew('first_stories')
})
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">
      {{ t('common_back') }}
    </button>

    <header class="flex items-start justify-between gap-3" data-tour="stories-intro">
      <div>
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">
          {{ t('story_eyebrow') }}
        </p>
        <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">
          {{ t('story_chapters_title') }}
        </h1>
        <p class="font-zen text-xs text-stone-500 mt-1">
          {{ t('story_progress_label', { n: unlockedCount, total: chapters.length }) }}
        </p>
      </div>
      <DodoCoinDisplay size="sm" />
    </header>

    <Spinner v-if="loading" :label="t('common_loading_dodo')" />

    <EmptyState
      v-else-if="error"
      icon="🌸"
      :title="t('common_error_title')"
      :subtitle="error"
    />
    <EmptyState
      v-else-if="!chapters.length"
      icon="📖"
      :title="t('story_empty_title')"
      :subtitle="t('story_empty_subtitle')"
    />

    <div v-else class="relative" data-test="story-timeline" data-tour="stories-list">
      <!-- vertical guide line -->
      <div class="absolute left-5 top-2 bottom-2 w-0.5 bg-cream-200" aria-hidden="true" />
      <ul class="space-y-3">
        <li
          v-for="c in chapters"
          :key="c.chapter"
          class="relative pl-12"
          :data-test="c.unlocked ? 'story-chapter-unlocked' : 'story-chapter-locked'"
        >
          <span
            class="absolute left-3 top-3 w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold"
            :class="c.unlocked ? 'bg-peach-500 text-white' : 'bg-stone-200 text-stone-500'"
          >
            {{ c.chapter }}
          </span>
          <button
            type="button"
            @click="c.unlocked ? openChapter(c) : payToUnlock(c)"
            :disabled="!c.unlocked && c.unlock_cost_coin === null"
            class="w-full text-left rounded-2xl p-3.5 transition-all active:scale-[0.98] disabled:cursor-not-allowed"
            :class="c.unlocked
              ? 'bg-gradient-to-br from-peach-50 to-sakura-50 shadow-soft'
              : 'bg-stone-50 backdrop-blur'"
          >
            <div class="flex items-start gap-2">
              <span
                class="text-xl shrink-0"
                :class="c.unlocked ? '' : 'opacity-30 blur-[1px]'"
                aria-hidden="true"
              >{{ c.emoji }}</span>
              <div class="flex-1 min-w-0">
                <p
                  class="font-display font-bold text-sm leading-tight"
                  :class="c.unlocked ? 'text-peach-500' : 'text-stone-400'"
                >
                  <template v-if="c.unlocked">{{ c.title }}</template>
                  <template v-else>🔒 {{ t('story_locked_chapter') }}</template>
                </p>
                <p
                  v-if="!c.unlocked && c.unlock_hint"
                  class="font-zen text-[11px] text-stone-500 mt-1 leading-relaxed"
                >
                  {{ c.unlock_hint }}
                </p>
                <p
                  v-if="c.unlocked && c.read_at"
                  class="font-zen text-[10px] text-stone-400 mt-1"
                >
                  ✓ {{ t('story_already_read') }}
                </p>
                <div v-if="!c.unlocked && c.unlock_cost_coin" class="mt-2">
                  <span
                    class="inline-block bg-peach-500 text-white font-zen text-[11px] font-bold px-3 py-1 rounded-full"
                  >
                    {{ unlocking === c.chapter
                      ? t('common_loading')
                      : t('story_unlock_with_coin', { coin: c.unlock_cost_coin }) }}
                  </span>
                </div>
                <span
                  v-else-if="c.unlocked && !c.read_at"
                  class="inline-block mt-1.5 font-zen text-[10px] text-peach-500"
                >
                  → {{ t('story_read_btn') }}
                </span>
              </div>
            </div>
          </button>
        </li>
      </ul>
    </div>

    <StoryUnlockModal
      :chapter="activeChapter"
      :open="modalOpen"
      @close="onClose"
      @finished="onFinished"
    />
  </div>
</template>
