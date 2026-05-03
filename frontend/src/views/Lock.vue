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
import { useTone } from '../composables/useTone'

const { t } = useTone()

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
  const ok = await verify(t('lock_verify_reason'))
  if (ok) {
    unlock()
    emit('unlocked')
  } else {
    errorMsg.value = t('lock_verify_failed')
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
    class="fixed inset-0 z-[100] flex flex-col bg-dawn-gradient"
    style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)"
    data-test="app-lock-screen"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lock-title"
  >
    <!-- 上半：hero（icon + title + subtitle） -->
    <div class="flex-1 flex flex-col items-center justify-center px-6">
      <div class="max-w-sm w-full text-center space-y-6">
        <div
          class="w-32 h-32 mx-auto rounded-full bg-peach-gradient flex items-center justify-center text-7xl shadow-soft"
          aria-hidden="true"
        >
          🔒
        </div>
        <div class="space-y-3">
          <h1
            id="lock-title"
            class="font-display text-3xl font-bold text-peach-500 leading-tight"
          >
            {{ t('lock_title') }}
          </h1>
          <p class="font-zen text-base text-stone-600 leading-relaxed px-2">
            {{ t('lock_subtitle') }}
          </p>
        </div>
      </div>
    </div>

    <!-- 下半：CTA（thumb-zone） -->
    <div class="px-6 pb-6 pt-2">
      <div class="max-w-sm w-full mx-auto space-y-3">
        <p
          v-if="errorMsg"
          class="font-zen text-sm text-sakura-500 text-center"
          data-test="app-lock-error"
          role="alert"
        >
          {{ errorMsg }}
        </p>

        <button
          :disabled="busy"
          data-test="app-lock-unlock"
          class="w-full py-4 rounded-3xl bg-peach-gradient text-white font-display font-bold text-lg shadow-soft active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2"
          @click="tryUnlock"
        >
          <span v-if="busy" class="w-4 h-4 border-2 border-white/60 border-t-white rounded-full animate-spin" />
          <span>{{ busy ? t('lock_btn_busy') : t('lock_btn_idle') }}</span>
        </button>

        <button
          class="w-full py-3 rounded-2xl text-sm font-zen text-stone-500 hover:text-peach-500 transition-colors"
          data-test="app-lock-exit"
          @click="exitToLogin"
        >
          {{ t('lock_logout_btn') }}
        </button>

        <p class="font-zen text-[11px] text-stone-400 leading-relaxed pt-2 text-center whitespace-pre-line">
          {{ t('lock_footer') }}
        </p>
      </div>
    </div>
  </div>
</template>
