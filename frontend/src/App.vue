<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { getToken } from './api'

const route = useRoute()
const showTabBar = computed(() => !!getToken() && route.path !== '/login')
</script>

<template>
  <div class="min-h-screen flex flex-col" :class="{ 'pb-20': showTabBar }">
    <main class="flex-1">
      <RouterView />
    </main>

    <nav
      v-if="showTabBar"
      class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-brand-100 px-4 py-2 grid grid-cols-4 text-xs z-30"
      style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom))"
    >
      <RouterLink to="/calendar" class="flex flex-col items-center py-1.5 gap-0.5" active-class="text-brand-600">
        <span class="text-xl">🗓️</span><span>月曆</span>
      </RouterLink>
      <RouterLink to="/log" class="flex flex-col items-center py-1.5 gap-0.5" active-class="text-brand-600">
        <span class="text-xl">📝</span><span>記錄</span>
      </RouterLink>
      <RouterLink to="/dodo" class="flex flex-col items-center py-1.5 gap-0.5" active-class="text-brand-600">
        <span class="text-xl">🐣</span><span>朵朵</span>
      </RouterLink>
      <RouterLink to="/me" class="flex flex-col items-center py-1.5 gap-0.5" active-class="text-brand-600">
        <span class="text-xl">👤</span><span>我的</span>
      </RouterLink>
    </nav>
  </div>
</template>
