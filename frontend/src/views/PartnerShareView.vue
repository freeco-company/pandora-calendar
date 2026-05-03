<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PartnerApi, type PartnerShareState } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'

const router = useRouter()
const state = ref<PartnerShareState | null>(null)
const loading = ref(true)
const copied = ref(false)

async function load() {
  loading.value = true
  try {
    state.value = (await PartnerApi.show()).data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function enable() {
  if (!confirm('開啟分享後，伴侶用連結能看到妳目前的 phase 與下次經期倒數（不會看到症狀 / 心情 / 體溫等敏感記錄）。確定？')) return
  await PartnerApi.enable()
  await load()
}

async function disable() {
  if (!confirm('關閉分享？舊連結會立刻失效。')) return
  await PartnerApi.disable()
  await load()
}

async function copyUrl() {
  if (!state.value?.share_url) return
  try {
    await navigator.clipboard.writeText(state.value.share_url)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {/* clipboard 不可用就不處理 */}
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">← 返回</button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Partner</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">伴侶分享</h1>
      <p class="font-zen text-[12px] text-stone-500 mt-1">分享一個只能看「我現在哪個 phase」的連結給伴侶。</p>
    </header>

    <Card tone="cream" class="text-sm font-zen leading-relaxed text-stone-700 space-y-2">
      <p class="font-bold text-peach-500">伴侶會看到</p>
      <ul class="list-disc list-inside text-stone-600 text-[13px]">
        <li>妳現在哪個 phase（經期 / 濾泡 / 排卵 / 黃體）</li>
        <li>距離下次經期幾天</li>
        <li>對應的「貼心提示」（例如黃體期建議多包容）</li>
      </ul>
      <p class="font-bold text-peach-500 mt-2">伴侶看不到</p>
      <ul class="list-disc list-inside text-stone-600 text-[13px]">
        <li>❌ 詳細週期記錄</li>
        <li>❌ 症狀 / 心情 / 體溫</li>
        <li>❌ 朵朵 check-in 內容</li>
        <li>❌ 任何 PII（妳的真名 / email）</li>
      </ul>
    </Card>

    <template v-if="loading">
      <p class="text-center text-stone-400 font-zen text-sm">載入中…</p>
    </template>

    <template v-else-if="state?.enabled && state.share_url">
      <Card tone="plain" class="space-y-3">
        <p class="font-zen text-[11px] text-stone-500">分享連結</p>
        <div class="bg-cream-50 rounded-2xl p-3 break-all font-mono text-[11px] text-stone-700">
          {{ state.share_url }}
        </div>
        <div class="flex gap-2">
          <Button full variant="primary" @click="copyUrl">{{ copied ? '✓ 已複製' : '複製連結' }}</Button>
          <Button variant="secondary" @click="disable">關閉分享</Button>
        </div>
        <p class="text-[10px] text-stone-400 font-zen">
          開啟於 {{ state.enabled_at?.slice(0, 10) }}。重新生成連結會讓舊連結失效。
        </p>
      </Card>
    </template>

    <template v-else>
      <Card tone="plain">
        <Button full variant="primary" @click="enable">產生分享連結</Button>
      </Card>
    </template>
  </div>
</template>
