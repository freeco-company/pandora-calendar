<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { JourneyApi, type JourneyData, getStoredUser } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
import { getPet } from '../lib/character'
import { OUTFITS } from '../lib/character'

const router = useRouter()
const data = ref<JourneyData | null>(null)
const loading = ref(true)
const pet = ref(getPet())
const user = getStoredUser()

const ALL_OUTFITS = OUTFITS
const ownedSet = computed(() => new Set(data.value?.outfit_owned ?? []))
const progressPct = computed(() => {
  if (!data.value) return 0
  return Math.min(100, Math.round((data.value.progress_in_level / data.value.need_for_next_level) * 100))
})

onMounted(async () => {
  try {
    const r = await JourneyApi.show()
    data.value = r.data.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="px-5 pt-10 pb-12 max-w-md mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">← 返回</button>

    <header class="text-center">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Journey</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ user?.display_name ?? user?.name ?? '朋友' }} 的旅程</h1>
    </header>

    <Spinner v-if="loading" label="朵朵在算..." />

    <template v-else-if="data">
      <!-- 寵物 + Lv 進度 -->
      <Card tone="cream" class="text-center space-y-3">
        <div class="flex justify-center">
          <Character
            :species="pet.species"
            :level="data.level"
            :outfit="pet.outfit"
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
          {{ data.progress_in_level }} / {{ data.need_for_next_level }} XP
          · 累積 {{ data.total_xp }} XP
        </p>
      </Card>

      <!-- 連勝 + 30 天統計 -->
      <Card tone="plain" class="space-y-2.5">
        <h3 class="font-display font-bold text-peach-500 text-base flex items-center gap-2">
          <span>🔥</span>
          <span>連勝</span>
          <span class="text-2xl text-sakura-500 font-display">{{ data.streak_days }}</span>
          <span class="font-zen text-[12px] text-stone-500 self-end pb-1">天</span>
        </h3>
        <p class="font-zen text-[12px] text-stone-500">過去 30 天</p>
        <div class="grid grid-cols-3 gap-2 text-center text-sm font-zen">
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.cycles_logged }}</p>
            <p class="text-[11px] text-stone-500">經期記錄</p>
          </div>
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.symptoms_logged }}</p>
            <p class="text-[11px] text-stone-500">症狀記錄</p>
          </div>
          <div class="bg-cream-50 rounded-2xl py-3">
            <p class="font-display text-lg text-peach-500 font-bold">{{ data.last_30_days.dodo_checkins }}</p>
            <p class="text-[11px] text-stone-500">朵朵 check-in</p>
          </div>
        </div>
      </Card>

      <!-- 里程碑 -->
      <Card tone="plain" class="space-y-3">
        <h3 class="font-display font-bold text-peach-500 text-base">里程碑</h3>
        <div class="space-y-2">
          <div
            v-for="m in data.milestones"
            :key="m.code"
            class="flex items-center gap-3 p-2.5 rounded-2xl"
            :class="m.unlocked ? 'bg-peach-50' : 'bg-cream-50/50'"
          >
            <span class="text-2xl" :class="{ 'opacity-30': !m.unlocked }">{{ m.icon }}</span>
            <div class="flex-1">
              <p class="font-zen text-sm" :class="m.unlocked ? 'text-peach-500 font-bold' : 'text-stone-500'">
                {{ m.name }}
              </p>
              <p
                v-if="!m.unlocked && m.target"
                class="font-zen text-[11px] text-stone-400 mt-0.5"
              >
                {{ m.progress ?? 0 }} / {{ m.target }}
              </p>
            </div>
            <span v-if="m.unlocked" class="text-sage-500 font-zen text-xs">✓ 已解鎖</span>
          </div>
        </div>
      </Card>

      <!-- Outfit 解鎖預告 -->
      <Card tone="plain" class="space-y-3">
        <h3 class="font-display font-bold text-peach-500 text-base">寵物裝扮</h3>
        <p class="font-zen text-[11px] text-stone-500">
          已擁有 {{ data.outfit_owned.length }} 件 · 連勝、達成成就會解鎖更多。
        </p>
        <div class="grid grid-cols-4 gap-2">
          <div
            v-for="o in ALL_OUTFITS.slice(0, 12)"
            :key="o"
            class="aspect-square rounded-2xl flex items-center justify-center text-3xl"
            :class="ownedSet.has(o) ? 'bg-peach-50 border border-peach-200' : 'bg-cream-50 border border-cream-200 opacity-30'"
            :title="o"
          >
            <span v-if="ownedSet.has(o)">🎀</span>
            <span v-else>🔒</span>
          </div>
        </div>
      </Card>
    </template>
  </div>
</template>
