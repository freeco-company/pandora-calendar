<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PremiumApi, type PmsPattern } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()
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
      {{ t('common_back_to_me') }}
    </button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('pms_eyebrow') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ t('pms_title') }}</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">{{ t('pms_subtitle') }}</p>
    </header>

    <Spinner v-if="loading" :label="t('pms_loading')" />

    <Card v-else-if="blocked" tone="lavender" class="text-center space-y-3">
      <div class="text-4xl">💎</div>
      <p class="font-zen text-stone-700">{{ t('pms_blocked_blurb') }}</p>
      <Button variant="primary" sfx="ui_open" @click="router.push('/me/premium')">{{ t('pms_blocked_cta') }}</Button>
    </Card>

    <EmptyState
      v-else-if="!pattern"
      :show-dodo="true"
      :title="t('pms_empty_title')"
      :subtitle="t('pms_empty_subtitle')"
    />

    <template v-else>
      <Card tone="cream" class="space-y-3">
        <p class="text-xs text-stone-500 font-zen">
          {{ t('pms_meta_basis', { n: pattern.sample_cycles }) }}
          <span :class="pattern.confidence === 'high' ? 'text-sage-500' : 'text-peach-400'" class="font-semibold">
            {{ pattern.confidence === 'high' ? t('pms_confidence_high') : t('pms_confidence_low') }}
          </span>
        </p>
        <h3 class="font-display font-bold text-peach-500 text-base">{{ t('pms_top_title') }}</h3>
        <ul class="space-y-2.5">
          <li
            v-for="sym in pattern.top_symptoms"
            :key="sym"
            class="flex justify-between items-center text-sm font-zen"
          >
            <span class="text-stone-700">{{ tagLabels[sym] || sym }}</span>
            <span class="text-[11px] bg-peach-100 text-peach-500 font-semibold px-2.5 py-1 rounded-full">
              {{ t('pms_count_suffix', { n: pattern.symptom_counts[sym] }) }}
            </span>
          </li>
        </ul>
      </Card>
      <p class="text-xs text-stone-400 text-center font-zen">{{ t('pms_footer') }}</p>
    </template>
  </div>
</template>
