<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getStoredUser, logout } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Character from '../components/Character.vue'
import { useSfx } from '../lib/sound'
import { getPet, savePet } from '../lib/character'
import { getCurrentLevel, getCurrentXp } from '../lib/gamification'

const router = useRouter()
const user = getStoredUser()
const ent = useEntitlementsStore()
const sfx = useSfx()
const pet = ref(getPet())
const level = ref(getCurrentLevel())
const xp = ref(getCurrentXp())
const muted = ref(sfx.isMuted())

onMounted(() => ent.load())

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 6) return '晚安'
  if (h < 12) return '早安'
  if (h < 18) return '午安'
  return '晚安'
})

function toggleMute() {
  muted.value = sfx.toggle()
  if (!muted.value) sfx.play('ui_tap')
}

function editPetName() {
  const name = prompt('給寵物取個暱稱', pet.value.nickname)
  if (name && name.trim()) {
    pet.value = { ...pet.value, nickname: name.trim() }
    savePet(pet.value)
    sfx.play('correct')
  }
}

async function doLogout() {
  sfx.play('ui_close')
  await logout()
  ent.reset()
  router.push('/login')
}
</script>

<template>
  <div class="px-5 pt-10 pb-6 max-w-md mx-auto space-y-5">
    <header class="text-center space-y-2">
      <div class="w-24 h-24 mx-auto rounded-full bg-peach-gradient flex items-center justify-center text-4xl shadow-soft">
        👤
      </div>
      <p class="font-zen text-xs text-stone-500">{{ greeting }}，</p>
      <h1 class="font-display text-2xl font-bold text-peach-500">{{ user?.name ?? '朋友' }}</h1>
      <p class="text-xs text-stone-400 font-zen">{{ user?.email }}</p>
      <span
        v-if="ent.isPremium()"
        data-test="premium-badge"
        class="inline-block mt-1 text-[11px] bg-gradient-to-r from-peach-400 to-sakura-400 text-white px-3 py-1 rounded-full font-zen font-semibold shadow-soft"
      >
        💎 Premium
      </span>
    </header>

    <!-- 我的寵物 -->
    <Card tone="cream" class="text-center space-y-3">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">My Pet</p>
      <div class="flex justify-center">
        <Character
          :species="pet.species"
          :level="level"
          :outfit="pet.outfit"
          mood="happy"
          :size="140"
          :show-rarity="true"
          :show-halo="true"
          :floaty="true"
          :interactive="true"
        />
      </div>
      <button
        @click="editPetName"
        class="font-display text-lg text-peach-500 hover:text-peach-400 transition-colors"
      >
        {{ pet.nickname }} <span class="text-xs text-stone-400">✎</span>
      </button>
      <p class="font-zen text-xs text-stone-500">
        XP {{ xp }} · 連續記錄會讓寵物升級
      </p>
    </Card>

    <Card tone="plain" :padded="false" class="overflow-hidden">
      <RouterLink
        to="/me/premium"
        data-test="link-premium"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ ent.isPremium() ? '管理 Premium' : '看看 Premium' }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/week-report"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">每週朵朵報告</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/pms"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">PMS 模式分析</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <!--
        🔒 紅線：婕樂纖會員入口只在這層深層出現，且後端 ProductLinkResolver gate 通過才會
        實際顯示內容。對未綁母艦 / 未付費用戶完全不顯示商品 — 入口仍可點，但內頁會顯示
        「妳還沒開通」。
      -->
      <RouterLink
        to="/me/jerosse"
        data-test="link-jerosse"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">婕樂纖會員</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </Card>

    <!-- 設定 -->
    <Card tone="plain" class="space-y-3">
      <h3 class="font-display font-bold text-peach-500 text-sm">設定</h3>
      <label class="flex items-center justify-between cursor-pointer">
        <div>
          <p class="font-zen text-sm text-stone-700">音效</p>
          <p class="font-zen text-[11px] text-stone-400 mt-0.5">朵朵的提示音與動畫音效</p>
        </div>
        <button
          @click="toggleMute"
          data-test="sfx-toggle"
          class="relative w-12 h-7 rounded-full transition-colors shrink-0"
          :class="muted ? 'bg-stone-300' : 'bg-peach-400'"
          :aria-pressed="!muted"
        >
          <span
            class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform"
            :class="muted ? '' : 'translate-x-5'"
          />
        </button>
      </label>
    </Card>

    <Card tone="plain" class="space-y-2 text-sm">
      <h2 class="font-display font-bold text-peach-500">關於潘朵拉月曆</h2>
      <p class="text-stone-600 leading-relaxed font-zen text-sm">
        妳的週期資料只屬於妳。Phase 0 demo 階段資料僅在本機 SQLite，正式版上架後走集團 Pandora Core 統一帳號，朵朵會跨 App 陪伴妳。
      </p>
      <p class="text-stone-500 text-[11px] font-zen">❌ 不做廣告 · ❌ 不賣資料 · ✅ 妳隨時可以刪除帳號</p>
    </Card>

    <Button variant="secondary" full data-test="logout" sfx="ui_close" @click="doLogout">登出</Button>

    <p class="text-center text-[10px] text-stone-400 pt-1 font-zen">
      Pandora Calendar v0.3.0 · P0-P6 visual / sound / character
    </p>
  </div>
</template>
