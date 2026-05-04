<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { App as CapacitorApp, type AppState } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'
import { getToken, tokenRef } from './api'
import { useSfx } from './lib/sound'
import { useClickSound } from './composables/useClickSound'
import {
  isLockEnabled,
  isLocked,
  lock,
  markActive,
  shouldLockOnResume,
} from './composables/useAppLock'
import XpToast from './components/XpToast.vue'
import LevelUpModal from './components/LevelUpModal.vue'
import AchievementToast from './components/AchievementToast.vue'
import DodoReplyToast from './components/DodoReplyToast.vue'
import PetOnboardingModal from './components/PetOnboardingModal.vue'
import AmbientSparkles from './components/AmbientSparkles.vue'
import StreakToast from './components/StreakToast.vue'
import { useStreakToast } from './composables/useStreakToast'
import LockView from './views/Lock.vue'

const route = useRoute()
const showTabBar = computed(
  () => !!getToken() && route.path !== '/login' && !locked.value,
)
// kept for backward compat / future direct use; tabClick 改走 useClickSound
useSfx()

// === Lock state ===
const locked = ref(false)

// SPEC-cross-app-streak Phase 1.B — 每日登入 streak toast
// App boot 後若有 token 即 fetch /api/streak/today；首次今日登入會跳 toast。
const { fetchToday: fetchStreakToday } = useStreakToast()

function refreshLockState() {
  locked.value = !!getToken() && isLockEnabled() && isLocked()
}

function handleAppStateChange(state: AppState) {
  if (state.isActive) {
    // 從背景回前景
    if (getToken() && shouldLockOnResume()) {
      lock()
    }
    refreshLockState()
  } else {
    // 進背景時記下時間，下次回來才能算 grace
    markActive()
  }
}

let appStateSub: { remove: () => void } | null = null

onMounted(async () => {
  // 啟動時：若已啟用鎖且有 token，要求驗證一次
  if (getToken() && isLockEnabled()) {
    lock()
  } else {
    markActive()
  }
  refreshLockState()

  // Streak fetch — 不 await，背景跑；fail-soft（composable 自己 catch）
  if (getToken()) {
    void fetchStreakToday()
  }

  // 用戶從 login 流程切回有 token 時也 trigger 一次（App.vue 是 root，不會 unmount）
  watch(tokenRef, (t, prev) => {
    if (t && !prev) {
      void fetchStreakToday()
    }
  })

  if (Capacitor.isNativePlatform()) {
    appStateSub = await CapacitorApp.addListener('appStateChange', handleAppStateChange)
  } else {
    // Web fallback：用 visibilitychange 模擬（dev 友善，雖然 plugin 不可用）
    document.addEventListener('visibilitychange', visibilityHandler)
  }
})

function visibilityHandler() {
  if (document.visibilityState === 'visible') {
    if (getToken() && shouldLockOnResume()) lock()
    refreshLockState()
  } else {
    markActive()
  }
}

onUnmounted(() => {
  appStateSub?.remove()
  document.removeEventListener('visibilitychange', visibilityHandler)
})

function onUnlocked() {
  refreshLockState()
}

// 路由變化時重新評估（涵蓋登入後跳 /calendar 的場景）
watch(
  () => route.path,
  () => refreshLockState(),
)

const _click = useClickSound()
function tabClick() {
  // tab_select：軟 pop + light haptic
  _click.play('choice_select')
}

// 全局 click delegate — 任何 button / a / [role=button] / [data-clickable] click 都自動播 tap
// 已有 v-sound directive 的會 fire 兩次 → 用 dataset flag 跳過，避免重音
function globalClickSound(ev: MouseEvent) {
  const target = ev.target as HTMLElement | null
  if (!target) return
  // 找最近的可點擊容器
  const clickable = target.closest(
    'button, a, [role="button"], [role="tab"], [role="checkbox"], [role="radio"], [data-clickable], summary, label[for], input[type="checkbox"], input[type="radio"], select',
  ) as HTMLElement | null
  if (!clickable) return
  if (clickable.dataset.skipSound === '1') return
  // disabled 不播
  if (clickable.hasAttribute('disabled') || (clickable as HTMLButtonElement).disabled) return
  _click.play('ui_tap')
}

onMounted(() => {
  document.addEventListener('click', globalClickSound, { capture: true })
})
onUnmounted(() => {
  document.removeEventListener('click', globalClickSound, { capture: true } as any)
})
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    :class="{ 'pb-24': showTabBar }"
    style="padding-top: env(safe-area-inset-top)"
  >
    <!-- 全局療癒粒子層（在 router-view 之下，pointer-events: none） -->
    <AmbientSparkles />

    <main class="flex-1 relative">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>

    <!--
      Tab bar — 4 個 tab：月曆 / 記錄 / 日誌 / 我的
      「日誌」原為「朵朵」，重新定位：朵朵已散布全 App（toast / banner / Calendar reflection），
      此 tab 改作為「朵朵 × 妳的對話 / 心情歷程」timeline，命名上更直白。
    -->
    <nav
      v-if="showTabBar"
      class="fixed bottom-0 inset-x-0 bg-white/90 backdrop-blur-md border-t border-cream-200 px-2 pt-1.5 grid grid-cols-4 text-[11px] font-zen z-30 shadow-[0_-4px_16px_-4px_rgba(159,107,62,0.12)]"
      style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom))"
    >
      <RouterLink
        to="/calendar"
        class="flex flex-col items-center justify-center py-1.5 gap-0.5 rounded-2xl transition-colors min-h-[52px]"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-[22px] leading-none" aria-hidden="true">🗓️</span>
        <span class="text-[11px] leading-tight">月曆</span>
      </RouterLink>
      <RouterLink
        to="/log"
        class="flex flex-col items-center justify-center py-1.5 gap-0.5 rounded-2xl transition-colors min-h-[52px]"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-[22px] leading-none" aria-hidden="true">📝</span>
        <span class="text-[11px] leading-tight">記錄</span>
      </RouterLink>
      <RouterLink
        to="/dodo"
        class="flex flex-col items-center justify-center py-1.5 gap-0.5 rounded-2xl transition-colors min-h-[52px]"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-[22px] leading-none" aria-hidden="true">📔</span>
        <span class="text-[11px] leading-tight">日誌</span>
      </RouterLink>
      <RouterLink
        to="/me"
        class="flex flex-col items-center justify-center py-1.5 gap-0.5 rounded-2xl transition-colors min-h-[52px]"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-[22px] leading-none" aria-hidden="true">👤</span>
        <span class="text-[11px] leading-tight">我的</span>
      </RouterLink>
    </nav>

    <!-- Global gamification overlays -->
    <XpToast />
    <LevelUpModal />
    <AchievementToast />
    <DodoReplyToast />
    <StreakToast />
    <PetOnboardingModal v-if="showTabBar" />

    <!-- App lock overlay：擋住 router-view，必須驗證才放行 -->
    <LockView v-if="locked" @unlocked="onUnlocked" />
  </div>
</template>
