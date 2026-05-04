<script setup lang="ts">
/**
 * SkillPathView (/me/skill-path) — 3 路徑卡 + quest 清單
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { SkillPathApi, type SkillPathState, type SkillQuest, type SkillPathKey } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Modal from '../components/ui/Modal.vue'
import DodoCoinDisplay from '../components/DodoCoinDisplay.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const state = ref<SkillPathState | null>(null)
const quests = ref<SkillQuest[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const switching = ref(false)
const confirmPath = ref<NonNullable<SkillPathKey> | null>(null)

const PATHS: Array<{ key: NonNullable<SkillPathKey>; emoji: string }> = [
  { key: 'fertility', emoji: '🌱' },
  { key: 'wellness', emoji: '💛' },
  { key: 'beauty', emoji: '✨' },
]

const completedQuests = computed(() => quests.value.filter((q) => q.is_completed).length)

async function load() {
  loading.value = true
  error.value = null
  try {
    const [s, q] = await Promise.allSettled([SkillPathApi.show(), SkillPathApi.quests()])
    if (s.status === 'fulfilled') state.value = s.value.data?.data ?? null
    if (q.status === 'fulfilled') quests.value = q.value.data?.data?.quests ?? []
  } catch {
    error.value = t('skill_path_load_failed')
  } finally {
    loading.value = false
  }
}

function askChoose(path: NonNullable<SkillPathKey>) {
  if (state.value?.path === path) return
  if (state.value?.path && !state.value.can_change) {
    error.value = t('skill_path_change_locked')
    return
  }
  confirmPath.value = path
}

async function confirmChoose() {
  if (!confirmPath.value || switching.value) return
  switching.value = true
  try {
    const r = await SkillPathApi.choose(confirmPath.value)
    state.value = r.data?.data ?? null
    confirmPath.value = null
    // reload quests for the new path
    const q = await SkillPathApi.quests().catch(() => null)
    if (q) quests.value = q.data?.data?.quests ?? []
  } catch {
    error.value = t('skill_path_change_failed')
  } finally {
    switching.value = false
  }
}

function cancelChoose() {
  confirmPath.value = null
}

import { useOnboardingTour } from '../composables/useOnboardingTour'
const tour = useOnboardingTour()
onMounted(() => {
  load()
  tour.startIfNew('first_skill_path')
})
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">
      {{ t('common_back') }}
    </button>

    <header class="flex items-start justify-between gap-3" data-tour="skill-path-intro">
      <div>
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">
          {{ t('skill_path_eyebrow') }}
        </p>
        <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">
          {{ t('skill_path_view_title') }}
        </h1>
        <p class="font-zen text-xs text-stone-500 mt-1">{{ t('skill_path_subtitle') }}</p>
      </div>
      <DodoCoinDisplay size="sm" />
    </header>

    <Spinner v-if="loading" :label="t('common_loading_dodo')" />

    <EmptyState
      v-else-if="error && !state"
      icon="🌸"
      :title="t('common_error_title')"
      :subtitle="error"
    />

    <template v-else>
      <!-- path selector -->
      <div class="grid grid-cols-3 gap-2.5" data-test="skill-path-selector" data-tour="skill-path-pick">
        <button
          v-for="p in PATHS"
          :key="p.key"
          type="button"
          @click="askChoose(p.key)"
          :class="[
            'rounded-2xl p-3 text-center transition-all active:scale-95',
            state?.path === p.key
              ? 'bg-gradient-to-br from-peach-100 to-sakura-100 ring-2 ring-peach-300 shadow-soft'
              : 'bg-white border border-stone-200',
          ]"
          :data-test="`path-${p.key}`"
        >
          <span class="text-3xl block" aria-hidden="true">{{ p.emoji }}</span>
          <p
            class="font-zen text-xs font-bold mt-1"
            :class="state?.path === p.key ? 'text-peach-600' : 'text-stone-600'"
          >
            {{ t('skill_path_name_' + p.key) }}
          </p>
          <p
            v-if="state?.path === p.key"
            class="font-zen text-[9px] text-peach-500 mt-0.5"
          >✓ {{ t('skill_path_active') }}</p>
        </button>
      </div>

      <!-- active path detail -->
      <Card v-if="state?.path" tone="cream" class="space-y-3" data-test="skill-path-active-card">
        <div>
          <p class="font-zen text-[10px] uppercase tracking-widest text-stone-500">
            {{ t('skill_path_current') }}
          </p>
          <p class="font-display font-bold text-peach-500 text-lg mt-0.5">
            {{ t('skill_path_name_' + state.path) }}
          </p>
          <p class="font-zen text-[12px] text-stone-600 leading-relaxed mt-1">
            {{ t('skill_path_desc_' + state.path) }}
          </p>
        </div>
        <p class="font-zen text-[11px] text-stone-500">
          {{ t('quest_progress_label', { done: completedQuests, total: quests.length }) }}
        </p>
        <p
          v-if="!state.can_change"
          class="font-zen text-[10px] text-stone-400 italic"
        >
          {{ t('skill_path_change_locked_hint') }}
        </p>
      </Card>

      <!-- quest list -->
      <ul v-if="state?.path" class="space-y-2.5" data-test="skill-quest-list" data-tour="skill-path-progress">
        <li
          v-for="q in quests"
          :key="q.key"
          class="rounded-2xl p-3.5 transition-all"
          :class="q.is_completed
            ? 'bg-sage-50 border border-sage-200'
            : 'bg-white border border-stone-200'"
        >
          <div class="flex items-start gap-2.5">
            <span
              class="shrink-0 mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
              :class="q.is_completed ? 'bg-sage-500 text-white' : 'bg-stone-200 text-stone-500'"
            >{{ q.is_completed ? '✓' : '·' }}</span>
            <div class="flex-1 min-w-0">
              <p
                class="font-display font-bold text-sm leading-tight"
                :class="q.is_completed ? 'text-sage-700 line-through' : 'text-stone-700'"
              >{{ q.title }}</p>
              <p class="font-zen text-[12px] text-stone-600 leading-relaxed mt-1">
                {{ q.description }}
              </p>
              <p class="font-zen text-[11px] text-peach-500 italic mt-1.5">
                🐣 {{ q.is_completed ? q.dodo_complete : q.dodo_intro }}
              </p>
              <div class="flex items-center justify-between mt-2 gap-2">
                <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-peach-300 to-sakura-400 transition-[width] duration-700"
                    :style="{
                      width: `${Math.min(100, Math.round((q.progress / Math.max(q.target, 1)) * 100))}%`,
                    }"
                  />
                </div>
                <span class="font-zen text-[10px] text-stone-500 tabular-nums shrink-0">
                  {{ q.progress }} / {{ q.target }}
                </span>
              </div>
              <div class="flex items-center gap-2 mt-1.5">
                <span class="font-zen text-[10px] text-peach-500">
                  +{{ q.reward_coin }} 朵朵幣
                </span>
                <span class="font-zen text-[10px] text-stone-500">+{{ q.reward_xp }} XP</span>
              </div>
            </div>
          </div>
        </li>
      </ul>

      <EmptyState
        v-if="state && !state.path"
        icon="🌱"
        :title="t('skill_path_pick_title')"
        :subtitle="t('skill_path_pick_subtitle')"
      />
    </template>

    <!-- confirm switch modal -->
    <Modal :open="!!confirmPath" @close="cancelChoose">
      <div v-if="confirmPath" class="space-y-3 text-center">
        <p class="font-display font-bold text-peach-500 text-lg">
          {{ t('skill_path_confirm_title') }}
        </p>
        <p class="font-zen text-sm text-stone-700">
          {{ t('skill_path_confirm_body', { name: t('skill_path_name_' + confirmPath) }) }}
        </p>
        <p class="font-zen text-[11px] text-stone-500">
          {{ t('skill_path_confirm_warn') }}
        </p>
        <div class="flex gap-2 justify-center pt-2">
          <button
            @click="cancelChoose"
            class="px-4 py-2 rounded-full bg-stone-100 text-stone-600 font-zen text-xs font-bold"
          >
            {{ t('common_cancel') }}
          </button>
          <button
            @click="confirmChoose"
            :disabled="switching"
            class="px-4 py-2 rounded-full bg-peach-500 text-white font-zen text-xs font-bold disabled:opacity-60"
          >
            {{ switching ? t('common_loading') : t('common_confirm') }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>
