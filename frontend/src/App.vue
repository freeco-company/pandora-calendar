<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { getToken } from './api'
import { useSfx } from './lib/sound'
import XpToast from './components/XpToast.vue'
import LevelUpModal from './components/LevelUpModal.vue'
import AchievementToast from './components/AchievementToast.vue'

const route = useRoute()
const showTabBar = computed(() => !!getToken() && route.path !== '/login')
const sfx = useSfx()

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
  </div>
</template>
