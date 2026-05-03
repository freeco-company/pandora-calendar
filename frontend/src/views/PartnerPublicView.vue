<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { PartnerApi } from '../api'
import Card from '../components/ui/Card.vue'

const route = useRoute()
const data = ref<any>(null)
const error = ref<string | null>(null)
const loading = ref(true)

const TONE: Record<string, string> = {
  menstrual: 'bg-gradient-to-br from-sakura-100 to-cream-50',
  follicular: 'bg-gradient-to-br from-cream-100 to-peach-50',
  ovulation: 'bg-gradient-to-br from-peach-100 to-cream-50',
  luteal: 'bg-gradient-to-br from-lavender-100 to-cream-50',
  unknown: 'bg-cream-50',
}

const PHASE_LABEL: Record<string, string> = {
  menstrual: '經期', follicular: '濾泡期', ovulation: '排卵期', luteal: '黃體期', unknown: '尚未推算',
}

onMounted(async () => {
  try {
    const r = await PartnerApi.publicView(route.params.token as string)
    data.value = r.data.data
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? '連結失效'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div :class="['min-h-screen flex items-center justify-center p-6', data ? TONE[data.phase] || 'bg-cream-50' : 'bg-cream-50']">
    <div class="w-full max-w-sm space-y-4">
      <Card tone="plain" class="text-center space-y-3">
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Partner View</p>

        <template v-if="loading">
          <p class="text-stone-400 text-sm font-zen">載入中…</p>
        </template>

        <template v-else-if="error">
          <p class="text-3xl">🔒</p>
          <h1 class="font-display text-xl font-bold text-stone-700">{{ error }}</h1>
          <p class="text-[12px] text-stone-500 font-zen">
            這個連結可能已被關閉或重新生成。請跟對方再要一次。
          </p>
        </template>

        <template v-else-if="data">
          <p class="font-display text-lg text-stone-700">{{ data.display_name }} 目前</p>
          <h1 class="font-display text-3xl font-bold text-peach-500">
            {{ PHASE_LABEL[data.phase] }}
          </h1>
          <p
            v-if="data.days_until_next_period !== null"
            class="font-zen text-[13px] text-stone-600"
          >
            距離下次經期約
            <span class="font-bold text-peach-500">
              {{ data.days_until_next_period < 0 ? '遲到 ' + Math.abs(data.days_until_next_period) : data.days_until_next_period }}
            </span>
            天
          </p>

          <div class="bg-cream-50 rounded-2xl px-4 py-3 mt-2 text-sm text-stone-700 font-zen leading-relaxed text-left">
            💡 {{ data.partner_hint }}
          </div>
        </template>

        <p class="text-[10px] text-stone-400 font-zen pt-3 border-t border-cream-200">
          由 潘朵拉月曆 anonymously 提供 · 沒有個人記錄會被分享
        </p>
      </Card>
    </div>
  </div>
</template>
