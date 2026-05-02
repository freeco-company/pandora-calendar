<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PremiumApi, type PmsPattern } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'

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
  <div class="px-5 pt-8 pb-6 max-w-md mx-auto space-y-4">
    <button @click="router.push('/me')" class="font-zen text-sm text-peach-500 hover:text-peach-400">
      ← 回我的
    </button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Premium</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">PMS 模式分析</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">朵朵看見妳常見的經前訊號</p>
    </header>

    <Spinner v-if="loading" label="載入中..." />

    <Card v-else-if="blocked" tone="lavender" class="text-center space-y-3">
      <div class="text-4xl">💎</div>
      <p class="font-zen text-stone-700">PMS 模式分析是 Premium 功能</p>
      <Button variant="primary" sfx="ui_open" @click="router.push('/me/premium')">看看 Premium</Button>
    </Card>

    <EmptyState
      v-else-if="!pattern"
      :show-dodo="true"
      title="朵朵還在認識妳"
      subtitle="記錄滿 6 次以上經前症狀後就能算出妳的 pattern。"
    />

    <template v-else>
      <Card tone="cream" class="space-y-3">
        <p class="text-xs text-stone-500 font-zen">
          基於最近 {{ pattern.sample_cycles }} 個週期 · 信心度
          <span :class="pattern.confidence === 'high' ? 'text-sage-500' : 'text-peach-400'" class="font-semibold">
            {{ pattern.confidence === 'high' ? '高' : '低' }}
          </span>
        </p>
        <h3 class="font-display font-bold text-peach-500 text-base">妳經前最常出現的訊號</h3>
        <ul class="space-y-2.5">
          <li
            v-for="t in pattern.top_symptoms"
            :key="t"
            class="flex justify-between items-center text-sm font-zen"
          >
            <span class="text-stone-700">{{ tagLabels[t] || t }}</span>
            <span class="text-[11px] bg-peach-100 text-peach-500 font-semibold px-2.5 py-1 rounded-full">
              出現 {{ pattern.symptom_counts[t] }} 次
            </span>
          </li>
        </ul>
      </Card>
      <p class="text-xs text-stone-400 text-center font-zen">朵朵會在下次經前提早提醒妳照顧自己。</p>
    </template>
  </div>
</template>
