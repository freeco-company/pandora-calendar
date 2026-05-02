<script setup lang="ts">
import { useRouter } from 'vue-router'
import { getStoredUser, logout } from '../api'

const router = useRouter()
const user = getStoredUser()

async function doLogout() {
  await logout()
  router.push('/login')
}
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <header class="text-center">
      <div class="w-20 h-20 mx-auto rounded-full bg-brand-100 flex items-center justify-center text-3xl">👤</div>
      <h1 class="text-xl font-bold text-brand-700 mt-2">{{ user?.name ?? '朋友' }}</h1>
      <p class="text-xs text-stone-400">{{ user?.email }}</p>
    </header>

    <section class="bg-white rounded-3xl shadow-sm p-5 space-y-3 text-sm">
      <h2 class="font-bold text-brand-700">關於潘朵拉月曆</h2>
      <p class="text-stone-600 leading-relaxed">
        妳的週期資料只屬於妳。Phase 0 demo 階段資料僅在本機 SQLite，正式版上架後走集團 Pandora Core 統一帳號，朵朵會跨 App 陪伴妳。
      </p>
      <p class="text-stone-500 text-xs">
        ❌ 不做廣告 · ❌ 不賣資料 · ✅ 妳隨時可以刪除帳號
      </p>
    </section>

    <!--
      🔒 集團硬規則：未綁母艦 / 未在婕樂纖消費過用戶 → 此頁面 zero 加盟 CTA。
      所有商品 / 加盟連結點留待 P5+，且只在「我的 → 婕樂纖會員」深層出現，gate 為:
      母艦消費 ≥ 1 + 訂閱中 + 月曆連用 ≥ 90 天。Phase 0 demo 完全不顯示。
    -->

    <button
      @click="doLogout"
      data-test="logout"
      class="w-full py-3 rounded-xl bg-white border border-brand-200 text-brand-700 hover:bg-brand-50"
    >登出</button>

    <p class="text-center text-[10px] text-stone-400 pt-2">
      Pandora Calendar v0.1.0 · Phase 0 demo build
    </p>
  </div>
</template>
