<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { App as CapacitorApp, type AppState } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'
import { getToken } from './api'
import { useSfx } from './lib/sound'
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
import PetOnboardingModal from './components/PetOnboardingModal.vue'
import LockView from './views/Lock.vue'

const route = useRoute()
const showTabBar = computed(
  () => !!getToken() && route.path !== '/login' && !locked.value,
)
const sfx = useSfx()

// === Lock state ===
const locked = ref(false)

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

function tabClick() {
  sfx.play('ui_tap')
}
</script>

<template>
  <div
    class="min-h-screen flex flex-col"
    :class="{ 'pb-24': showTabBar }"
    style="padding-top: env(safe-area-inset-top)"
  >
    <main class="flex-1 relative">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>

    <nav
      v-if="showTabBar"
      class="fixed bottom-0 inset-x-0 bg-white/85 backdrop-blur-md border-t border-cream-200 px-3 py-2 grid grid-cols-4 text-[11px] font-zen z-30 shadow-[0_-4px_16px_-4px_rgba(159,107,62,0.12)]"
      style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom))"
    >
      <RouterLink
        to="/calendar"
        class="flex flex-col items-center py-1.5 gap-0.5 rounded-2xl transition-colors"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-xl">🗓️</span><span>月曆</span>
      </RouterLink>
      <RouterLink
        to="/log"
        class="flex flex-col items-center py-1.5 gap-0.5 rounded-2xl transition-colors"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-xl">📝</span><span>記錄</span>
      </RouterLink>
      <RouterLink
        to="/dodo"
        class="flex flex-col items-center py-1.5 gap-0.5 rounded-2xl transition-colors"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-xl">🐣</span><span>朵朵</span>
      </RouterLink>
      <RouterLink
        to="/me"
        class="flex flex-col items-center py-1.5 gap-0.5 rounded-2xl transition-colors"
        active-class="text-peach-500 bg-peach-50"
        @click="tabClick"
      >
        <span class="text-xl">👤</span><span>我的</span>
      </RouterLink>
    </nav>

    <!-- Global gamification overlays -->
    <XpToast />
    <LevelUpModal />
    <AchievementToast />
    <PetOnboardingModal v-if="showTabBar" />

    <!-- App lock overlay：擋住 router-view，必須驗證才放行 -->
    <LockView v-if="locked" @unlocked="onUnlocked" />
  </div>
</template>
