<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { CalendarApi, type CycleRecord, type SymptomRecord } from '../api'

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
    await load()
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? '存檔失敗'
  } finally {
    saving.value = false
  }
}

function toggleTag(t: string) {
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
    selectedTags.value = []
    await load()
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? '存檔失敗'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <h1 class="text-2xl font-bold text-brand-700">記錄</h1>

    <section class="bg-white rounded-3xl shadow-sm p-5 space-y-3">
      <h2 class="font-bold text-brand-700">📅 經期記錄</h2>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <label class="block">
          <span class="text-stone-500">開始日</span>
          <input
            v-model="startDate" type="date" data-test="cycle-start-date"
            class="mt-1 w-full px-3 py-2 rounded-lg border border-brand-100 focus:outline-none focus:border-brand-500"
          />
        </label>
        <label class="block">
          <span class="text-stone-500">結束日（選填）</span>
          <input
            v-model="endDate" type="date" data-test="cycle-end-date"
            class="mt-1 w-full px-3 py-2 rounded-lg border border-brand-100 focus:outline-none focus:border-brand-500"
          />
        </label>
      </div>
      <label class="block text-sm">
        <span class="text-stone-500">流量（1 最少 · 5 最多）</span>
        <input v-model.number="peakFlow" type="range" min="1" max="5" class="w-full mt-2" />
        <span class="text-brand-600 font-semibold">{{ peakFlow }}</span>
      </label>
      <button
        @click="saveCycle"
        :disabled="saving"
        data-test="save-cycle"
        class="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white font-medium"
      >記下這次經期</button>
    </section>

    <section class="bg-white rounded-3xl shadow-sm p-5 space-y-3">
      <h2 class="font-bold text-brand-700">🌸 今日身體狀態</h2>
      <label class="block text-sm">
        <span class="text-stone-500">日期</span>
        <input v-model="symptomDate" type="date" class="mt-1 w-full px-3 py-2 rounded-lg border border-brand-100" />
      </label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="t in tags"
          :key="t.v"
          @click="toggleTag(t.v)"
          :data-test="`tag-${t.v}`"
          class="px-3 py-1.5 rounded-full text-xs border transition"
          :class="selectedTags.includes(t.v)
            ? 'bg-brand-600 text-white border-brand-600'
            : 'bg-brand-50 text-brand-700 border-brand-100'"
        >{{ t.label }}</button>
      </div>
      <div class="flex gap-2">
        <button
          v-for="m in [{v:'good', e:'😊'}, {v:'okay', e:'😐'}, {v:'bad', e:'😞'}]"
          :key="m.v"
          @click="symptomMood = m.v"
          class="flex-1 py-2 rounded-xl border"
          :class="symptomMood === m.v ? 'bg-brand-100 border-brand-500' : 'bg-white border-brand-100'"
        >{{ m.e }}</button>
      </div>
      <button
        @click="saveSymptom"
        :disabled="saving"
        data-test="save-symptom"
        class="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white font-medium"
      >存今日狀態</button>
    </section>

    <p v-if="message" data-test="save-message" class="text-center text-sm text-brand-700">{{ message }}</p>

    <section class="bg-white rounded-3xl shadow-sm p-5">
      <h2 class="font-bold text-brand-700 mb-2">📚 最近的經期</h2>
      <ul class="text-sm divide-y divide-brand-50">
        <li v-for="c in cycles.slice(0, 6)" :key="c.id" class="py-2 flex justify-between text-stone-600">
          <span>{{ c.start_date }}</span>
          <span class="text-stone-400">{{ c.length_days ?? '進行中' }}{{ c.length_days ? ' 天' : '' }}</span>
        </li>
        <li v-if="!cycles.length" class="py-3 text-stone-400 text-center">還沒有記錄</li>
      </ul>
    </section>
  </div>
</template>
