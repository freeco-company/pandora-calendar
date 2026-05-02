<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getStoredUser, logout } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'

const router = useRouter()
const user = getStoredUser()
const ent = useEntitlementsStore()

onMounted(() => ent.load())

async function doLogout() {
  await logout()
  ent.reset()
  router.push('/login')
}
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <header class="text-center">
      <div class="w-20 h-20 mx-auto rounded-full bg-brand-100 flex items-center justify-center text-3xl">👤</div>
      <h1 class="text-xl font-bold text-brand-700 mt-2">{{ user?.name ?? '朋友' }}</h1>
      <p class="text-xs text-stone-400">{{ user?.email }}</p>
      <span
        v-if="ent.isPremium()"
        data-test="premium-badge"
        class="inline-block mt-2 text-xs bg-brand-600 text-white px-3 py-1 rounded-full"
      >💎 Premium</span>
    </header>

    <section class="bg-white rounded-3xl shadow-sm divide-y divide-brand-50">
      <RouterLink
        to="/me/premium"
        data-test="link-premium"
        class="flex items-center justify-between px-5 py-4 text-sm hover:bg-brand-50"
      >
        <span class="text-brand-700">{{ ent.isPremium() ? '管理 Premium' : '看看 Premium' }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink to="/me/week-report" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-brand-50">
        <span class="text-brand-700">每週朵朵報告</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink to="/me/pms" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-brand-50">
        <span class="text-brand-700">PMS 模式分析</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <!--
        🔒 紅線：婕樂纖會員入口只在這層深層出現，且後端 ProductLinkResolver gate 通過才會
        實際顯示內容。對未綁母艦 / 未付費用戶完全不顯示商品 — 入口仍可點，但內頁會顯示
        「妳還沒開通」。
      -->
      <RouterLink to="/me/jerosse" data-test="link-jerosse" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-brand-50">
        <span class="text-brand-700">婕樂纖會員</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </section>

    <section class="bg-white rounded-3xl shadow-sm p-5 space-y-3 text-sm">
      <h2 class="font-bold text-brand-700">關於潘朵拉月曆</h2>
      <p class="text-stone-600 leading-relaxed">
        妳的週期資料只屬於妳。Phase 0 demo 階段資料僅在本機 SQLite，正式版上架後走集團 Pandora Core 統一帳號，朵朵會跨 App 陪伴妳。
      </p>
      <p class="text-stone-500 text-xs">❌ 不做廣告 · ❌ 不賣資料 · ✅ 妳隨時可以刪除帳號</p>
    </section>

    <button
      @click="doLogout"
      data-test="logout"
      class="w-full py-3 rounded-xl bg-white border border-brand-200 text-brand-700 hover:bg-brand-50"
    >登出</button>

    <p class="text-center text-[10px] text-stone-400 pt-2">
      Pandora Calendar v0.2.0 · P0-P6 scaffold
    </p>
  </div>
</template>
