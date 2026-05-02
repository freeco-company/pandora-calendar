<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PremiumApi, type PmsPattern } from '../api'

const router = useRouter()
const pattern = ref<PmsPattern | null>(null)
const loading = ref(true)
const blocked = ref(false)

const tagLabels: Record<string, string> = {
  cramp: '經痛', headache: '頭痛', fatigue: '疲倦', bloating: '腹脹',
  breast_tender: '胸脹', acne: '冒痘', mood_swing: '情緒起伏',
  craving_sweet: '想吃甜', insomnia: '失眠', back_pain: '腰痠',
}

onMounted(async () => {
  try {
    const res = await PremiumApi.pms()
    pattern.value = res.data.data
  } catch (e: any) {
    if (e?.response?.status === 402) blocked.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <button @click="router.push('/me')" class="text-sm text-brand-600">← 回我的</button>

    <header>
      <h1 class="text-2xl font-bold text-brand-700">PMS 模式分析</h1>
      <p class="text-sm text-stone-500">朵朵看見妳常見的經前訊號</p>
    </header>

    <div v-if="loading" class="text-center py-8 text-stone-400">載入中...</div>

    <div v-else-if="blocked" class="bg-brand-50 rounded-3xl p-6 text-center space-y-3">
      <div class="text-4xl">💎</div>
      <p>PMS 模式分析是 Premium 功能</p>
      <button @click="router.push('/me/premium')" class="px-5 py-2 bg-brand-600 text-white rounded-full">看看 Premium</button>
    </div>

    <div v-else-if="!pattern" class="bg-white rounded-3xl p-6 text-center text-sm text-stone-500">
      <p>朵朵還在認識妳的週期。記錄滿 6 次以上經前症狀後就能算出 pattern 了。</p>
    </div>

    <template v-else>
      <div class="bg-white rounded-3xl shadow-sm p-5 space-y-3">
        <p class="text-xs text-stone-500">基於最近 {{ pattern.sample_cycles }} 個週期 · 信心度 {{ pattern.confidence === 'high' ? '高' : '低' }}</p>
        <h3 class="font-bold text-brand-700">妳經前最常出現的訊號</h3>
        <ul class="space-y-2">
          <li v-for="t in pattern.top_symptoms" :key="t" class="flex justify-between items-center text-sm">
            <span class="text-stone-700">{{ tagLabels[t] || t }}</span>
            <span class="text-xs bg-brand-50 text-brand-700 px-2 py-1 rounded-full">出現 {{ pattern.symptom_counts[t] }} 次</span>
          </li>
        </ul>
      </div>
      <p class="text-xs text-stone-400 text-center">朵朵會在下次經前提早提醒妳照顧自己。</p>
    </template>
  </div>
</template>
