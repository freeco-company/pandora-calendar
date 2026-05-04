<script setup lang="ts">
/**
 * ErrorBoundary — 包住 RouterView，任何下層 component render error 都顯示 fallback UI
 * 而不是白屏。也補捉 unhandled promise rejection。
 *
 * 對齊集團「不能因為一個 Premium gate / 401 / 5xx 就讓整頁壞掉」原則。
 */
import { onErrorCaptured, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

const error = ref<Error | null>(null)
const router = useRouter()

onErrorCaptured((err) => {
  error.value = err as Error
  console.error('[ErrorBoundary]', err)
  // 避免 propagate 到上層，整頁掛掉
  return false
})

function handleUnhandled(ev: PromiseRejectionEvent) {
  // Premium gate (PaywallRequiredError) 不算 fatal，靜默 swallow
  const reason: any = ev.reason
  if (reason?.name === 'PaywallRequiredError') {
    ev.preventDefault()
    return
  }
}

onMounted(() => {
  window.addEventListener('unhandledrejection', handleUnhandled)
})
onUnmounted(() => {
  window.removeEventListener('unhandledrejection', handleUnhandled)
})

function retry() {
  error.value = null
  // 重新 mount 當前 route：用 replace 觸發 RouterView 重 render
  const cur = router.currentRoute.value.fullPath
  router.replace('/').then(() => router.replace(cur))
}

function goHome() {
  error.value = null
  router.push('/calendar')
}
</script>

<template>
  <div v-if="error" class="min-h-[60vh] flex items-center justify-center px-6">
    <div class="text-center max-w-sm space-y-4">
      <div class="text-6xl">🌸</div>
      <h2 class="font-display text-xl font-bold text-stone-800">這頁出了點小狀況</h2>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">
        朵朵剛剛遇到一個小問題，妳的資料都還在 💛<br />
        可以重試一次，或回月曆首頁。
      </p>
      <div class="flex gap-2 pt-2">
        <button
          type="button"
          class="flex-1 bg-peach-500 text-white rounded-2xl py-2.5 font-zen text-sm active:scale-95 transition"
          @click="retry"
        >
          再試一次
        </button>
        <button
          type="button"
          class="flex-1 bg-cream-100 text-stone-600 rounded-2xl py-2.5 font-zen text-sm active:scale-95 transition"
          @click="goHome"
        >
          回月曆
        </button>
      </div>
      <details class="text-left mt-3" v-if="error.message">
        <summary class="text-[11px] text-stone-400 cursor-pointer font-zen">技術細節</summary>
        <pre class="text-[10px] text-stone-400 mt-1 whitespace-pre-wrap break-all">{{ error.message }}</pre>
      </details>
    </div>
  </div>
  <slot v-else />
</template>
