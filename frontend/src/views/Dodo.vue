<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { CalendarApi, type DodoCheckin } from '../api'

const todayResponse = ref<string | null>(null)
const todayPhase = ref<string | null>(null)
const recent = ref<DodoCheckin[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function loadRecent() {
  const res = await CalendarApi.dodoRecent()
  recent.value = res.data.data
  if (recent.value.length && recent.value[0].checked_on === new Date().toISOString().slice(0, 10)) {
    todayResponse.value = recent.value[0].dodo_response
    todayPhase.value = recent.value[0].phase
  }
}
onMounted(loadRecent)

async function checkin(mood: 'good' | 'okay' | 'bad') {
  loading.value = true
  error.value = null
  try {
    const res = await CalendarApi.dodoCheckin(mood)
    todayResponse.value = res.data.data.dodo_response
    todayPhase.value = res.data.data.phase
    await loadRecent()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '失敗'
  } finally {
    loading.value = false
  }
}

const phaseLabels: Record<string, string> = {
  menstrual: '經期', follicular: '濾泡期', ovulation: '排卵期', luteal: '黃體期', unknown: '',
}
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <header class="text-center mb-2">
      <div class="text-7xl">🐣</div>
      <h1 class="text-xl font-bold text-brand-700 mt-1">朵朵</h1>
      <p class="text-sm text-brand-600">妳今天感覺如何？</p>
    </header>

    <div class="grid grid-cols-3 gap-3">
      <button
        v-for="m in [
          { v: 'good', label: '😊 還不錯' },
          { v: 'okay', label: '😐 普普' },
          { v: 'bad', label: '😞 不太好' },
        ]"
        :key="m.v"
        :data-test="`mood-${m.v}`"
        :disabled="loading"
        @click="checkin(m.v as any)"
        class="bg-white border border-brand-100 rounded-2xl py-4 hover:bg-brand-50 disabled:opacity-50 transition text-sm"
      >{{ m.label }}</button>
    </div>

    <div v-if="todayResponse" data-test="dodo-response" class="bg-brand-50 rounded-3xl p-5 text-stone-700 leading-relaxed">
      <p class="text-xs text-brand-600 mb-1">朵朵說 · {{ todayPhase ? phaseLabels[todayPhase] : '' }}</p>
      <p>{{ todayResponse }}</p>
    </div>

    <p v-if="error" class="text-xs text-red-500 text-center">{{ error }}</p>

    <section class="bg-white rounded-3xl shadow-sm p-5">
      <h2 class="font-bold text-brand-700 mb-2">最近 check-in</h2>
      <ul class="text-sm divide-y divide-brand-50">
        <li v-for="r in recent.slice(0, 7)" :key="r.checked_on" class="py-2 flex gap-3">
          <span class="text-stone-400 w-24 shrink-0">{{ r.checked_on }}</span>
          <span class="text-stone-700 truncate">{{ r.dodo_response }}</span>
        </li>
        <li v-if="!recent.length" class="py-3 text-stone-400 text-center">還沒 check-in 過</li>
      </ul>
    </section>
  </div>
</template>
