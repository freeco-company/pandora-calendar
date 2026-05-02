<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { CalendarApi, type CycleRecord, type SymptomRecord } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useSfx } from '../lib/sound'
import { awardXp, emitAchievement } from '../lib/gamification'

const sfx = useSfx()
const startDate = ref(new Date().toISOString().slice(0, 10))
const endDate = ref('')
const peakFlow = ref(3)
const symptomDate = ref(new Date().toISOString().slice(0, 10))
const selectedTags = ref<string[]>([])
const symptomMood = ref<string>('okay')
const cycles = ref<CycleRecord[]>([])
const symptoms = ref<SymptomRecord[]>([])
const saving = ref(false)
const message = ref<string | null>(null)

const tags = [
  { v: 'cramp', label: '經痛' },
  { v: 'headache', label: '頭痛' },
  { v: 'fatigue', label: '疲倦' },
  { v: 'bloating', label: '腹脹' },
  { v: 'breast_tender', label: '胸脹' },
  { v: 'acne', label: '冒痘' },
  { v: 'mood_swing', label: '情緒起伏' },
  { v: 'craving_sweet', label: '想吃甜' },
  { v: 'insomnia', label: '失眠' },
  { v: 'back_pain', label: '腰痠' },
]

async function load() {
  const [c, s] = await Promise.all([CalendarApi.cycles(), CalendarApi.symptoms()])
  cycles.value = c.data.data
  symptoms.value = s.data.data
}
onMounted(load)

async function saveCycle() {
  saving.value = true
  message.value = null
  try {
    await CalendarApi.storeCycle({
      start_date: startDate.value,
      end_date: endDate.value || undefined,
      peak_flow: peakFlow.value,
    })
    message.value = '已記錄這次經期 ✓'
    sfx.play('cycle_logged')
    // Phase 4 mock：成功儲存週期 → 5 XP toast。Track B 接通後改吃 API response 的 xp 欄位。
    awardXp(5, '記錄了今天的週期')
    // 第一次記錄時觸發成就（cycle 列表 0 → 1）
    if (cycles.value.length === 0) {
      setTimeout(() => {
        emitAchievement({
          code: 'first_cycle',
          title: '第一次記錄',
          description: '謝謝妳願意分享，朵朵會陪著妳。',
          icon: '🌸',
        })
      }, 700)
    }
    await load()
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? '存檔失敗'
    sfx.play('wrong')
  } finally {
    saving.value = false
  }
}

function toggleTag(t: string) {
  sfx.play('choice_select')
  const idx = selectedTags.value.indexOf(t)
  if (idx >= 0) selectedTags.value.splice(idx, 1)
  else selectedTags.value.push(t)
}

async function saveSymptom() {
  saving.value = true
  message.value = null
  try {
    await CalendarApi.storeSymptom({
      logged_on: symptomDate.value,
      tags: selectedTags.value,
      mood: symptomMood.value,
    })
    message.value = '已記錄今日狀態 ✓'
    sfx.play('meal_logged')
    awardXp(2, '記錄了今天的身體狀態')
    selectedTags.value = []
    await load()
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? '存檔失敗'
    sfx.play('wrong')
  } finally {
    saving.value = false
  }
}

function pickMood(v: string) {
  sfx.play('choice_select')
  symptomMood.value = v
}
</script>

<template>
  <div class="px-5 pt-10 pb-6 max-w-md mx-auto space-y-5">
    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Log</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">今天的記錄</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">越記越懂自己 · 朵朵會跟著一起學</p>
    </header>

    <Card tone="plain" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">🌙</span>
        <h2 class="font-display font-bold text-peach-500 text-base">經期記錄</h2>
      </div>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <label class="block">
          <span class="text-stone-500 font-zen text-xs">開始日</span>
          <input
            v-model="startDate"
            type="date"
            data-test="cycle-start-date"
            class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
          />
        </label>
        <label class="block">
          <span class="text-stone-500 font-zen text-xs">結束日（選填）</span>
          <input
            v-model="endDate"
            type="date"
            data-test="cycle-end-date"
            class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
          />
        </label>
      </div>
      <label class="block text-sm">
        <span class="text-stone-500 font-zen text-xs">流量（1 最少 · 5 最多）</span>
        <div class="flex items-center gap-3 mt-2">
          <input
            v-model.number="peakFlow"
            type="range"
            min="1"
            max="5"
            class="flex-1 accent-peach-400"
          />
          <span class="text-peach-500 font-display font-bold text-lg w-6 text-center">{{ peakFlow }}</span>
        </div>
      </label>
      <Button
        full
        variant="primary"
        :loading="saving"
        data-test="save-cycle"
        sfx="cycle_logged"
        @click="saveCycle"
      >
        記下這次經期
      </Button>
    </Card>

    <Card tone="plain" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">🌸</span>
        <h2 class="font-display font-bold text-peach-500 text-base">今日身體狀態</h2>
      </div>
      <label class="block text-sm">
        <span class="text-stone-500 font-zen text-xs">日期</span>
        <input
          v-model="symptomDate"
          type="date"
          class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
        />
      </label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="t in tags"
          :key="t.v"
          @click="toggleTag(t.v)"
          :data-test="`tag-${t.v}`"
          class="px-3.5 py-1.5 rounded-full text-xs font-zen border transition-all active:scale-95"
          :class="
            selectedTags.includes(t.v)
              ? 'bg-peach-gradient text-white border-transparent shadow-soft'
              : 'bg-cream-50 text-peach-500 border-cream-200 hover:bg-peach-50'
          "
        >
          {{ t.label }}
        </button>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="m in [
            { v: 'good', e: '😊', label: '還不錯' },
            { v: 'okay', e: '😐', label: '普普' },
            { v: 'bad', e: '😞', label: '不太好' },
          ]"
          :key="m.v"
          @click="pickMood(m.v)"
          class="flex flex-col items-center gap-1 py-3 rounded-2xl border transition-all active:scale-95"
          :class="
            symptomMood === m.v
              ? 'bg-peach-50 border-peach-300 shadow-soft'
              : 'bg-white border-cream-200 hover:bg-cream-50'
          "
        >
          <span class="text-2xl">{{ m.e }}</span>
          <span class="text-[11px] font-zen text-stone-600">{{ m.label }}</span>
        </button>
      </div>
      <Button
        full
        variant="secondary"
        :loading="saving"
        data-test="save-symptom"
        sfx="meal_logged"
        @click="saveSymptom"
      >
        存今日狀態
      </Button>
    </Card>

    <p
      v-if="message"
      data-test="save-message"
      class="text-center text-sm text-peach-500 font-zen animate-fadein"
    >
      {{ message }}
    </p>

    <Card tone="plain">
      <h2 class="font-display font-bold text-peach-500 text-base mb-3 flex items-center gap-2">
        <span class="text-xl">📚</span> 最近的經期
      </h2>
      <ul v-if="cycles.length" class="text-sm divide-y divide-cream-200 font-zen">
        <li
          v-for="c in cycles.slice(0, 6)"
          :key="c.id"
          class="py-2.5 flex justify-between text-stone-600"
        >
          <span>{{ c.start_date }}</span>
          <span class="text-stone-400">{{ c.length_days ?? '進行中' }}{{ c.length_days ? ' 天' : '' }}</span>
        </li>
      </ul>
      <EmptyState
        v-else
        icon="🌱"
        title="還沒有記錄"
        subtitle="第一次記錄就由朵朵陪妳開始。"
      />
    </Card>
  </div>
</template>
