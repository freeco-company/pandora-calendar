<script setup lang="ts">
/**
 * 全螢幕鎖定頁。
 *
 * 進入條件（在 App.vue 控制）：
 * - 啟用過 lock_enabled
 * - sessionStorage app_locked = '1'（首次進 App / 從背景回來超過 30 秒 / 登入後）
 *
 * 解鎖：呼叫生物辨識 → 成功則 unlock() 並 emit('unlocked')
 * 退出：登出回 Login（避免卡死）
 *
 * 注意：這支不直接走 router（因為它在 RouterView 之上 overlay），靠 emit
 * 通知父層（App.vue）關掉 overlay。
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { logout } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import { verify, unlock } from '../composables/useAppLock'

const emit = defineEmits<{
  (e: 'unlocked'): void
}>()

const router = useRouter()
const ent = useEntitlementsStore()
const busy = ref(false)
const errorMsg = ref<string | null>(null)

async function tryUnlock() {
  if (busy.value) return
  busy.value = true
  errorMsg.value = null
  const ok = await verify('解鎖潘朵拉月曆')
  if (ok) {
    unlock()
    emit('unlocked')
  } else {
    errorMsg.value = '驗證未通過，再試一次或使用裝置密碼'
  }
  busy.value = false
}

async function exitToLogin() {
  unlock()
  await logout()
  ent.reset()
  router.push('/login')
  emit('unlocked')
}

onMounted(() => {
  // 進入鎖定頁立刻彈一次驗證（iOS / Android 慣例）
  tryUnlock()
})
</script>

<template>
  <div
    class="fixed inset-0 z-[100] flex flex-col items-center justify-center px-6 py-10 bg-dawn-gradient"
    style="padding-top: env(safe-area-inset-top)"
    data-test="app-lock-screen"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lock-title"
  >
    <div class="max-w-sm w-full text-center space-y-8">
      <div class="space-y-3">
        <div
          class="w-24 h-24 mx-auto rounded-full bg-peach-gradient flex items-center justify-center text-5xl shadow-soft"
          aria-hidden="true"
        >
          🔒
        </div>
        <h1 id="lock-title" class="font-display text-2xl font-bold text-peach-500">
          妳的健康記錄是私密的
        </h1>
        <p class="font-zen text-sm text-stone-600 leading-relaxed">
          請使用 Face ID / 指紋解鎖
        </p>
      </div>

      <div class="space-y-3">
        <button
          :disabled="busy"
          data-test="app-lock-unlock"
          class="w-full py-4 rounded-3xl bg-peach-gradient text-white font-display font-bold text-lg shadow-soft active:scale-[0.98] transition-all disabled:opacity-60"
          @click="tryUnlock"
        >
          {{ busy ? '驗證中…' : '使用 Face ID / 指紋' }}
        </button>

        <p
          v-if="errorMsg"
          class="font-zen text-xs text-sakura-500"
          data-test="app-lock-error"
        >
          {{ errorMsg }}
        </p>

        <button
          class="w-full py-2.5 rounded-2xl text-sm font-zen text-stone-500 hover:text-peach-500 transition-colors"
          data-test="app-lock-exit"
          @click="exitToLogin"
        >
          換朋友 · 登出
        </button>
      </div>

      <p class="font-zen text-[11px] text-stone-400 leading-relaxed pt-4">
        妳的資料只儲存在妳的裝置上。<br />
        我們不賣資料、不放廣告。
      </p>
    </div>
  </div>
</template>
