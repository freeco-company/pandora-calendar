<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { demoLogin } from '../api'
import Card from '../components/ui/Card.vue'
import Character from '../components/Character.vue'

const router = useRouter()
const loading = ref(false)
const error = ref<string | null>(null)
const activeEmail = ref<string | null>(null)

const demoUsers = [
  { name: '小敏', detail: '28 天週期', email: 'demo-min@pandora-calendar.test' },
  { name: '雨晴', detail: '30 天週期', email: 'demo-yuching@pandora-calendar.test' },
  { name: '阿伶', detail: '26 天週期', email: 'demo-aling@pandora-calendar.test' },
]

async function pickDemo(email: string) {
  loading.value = true
  activeEmail.value = email
  error.value = null
  try {
    await demoLogin(email)
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '登入失敗'
  } finally {
    loading.value = false
    activeEmail.value = null
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-6 py-10 bg-dawn-gradient">
    <header class="text-center mb-8 max-w-sm">
      <div class="flex justify-center mb-2">
        <Character species="dodo" :size="148" mood="happy" :show-halo="true" :floaty="true" />
      </div>
      <h1 class="font-display text-4xl font-bold text-peach-500 tracking-wide">潘朵拉月曆</h1>
      <p class="font-zen text-stone-600 mt-2 text-sm">妳的週期 · 朵朵陪妳一起記</p>
    </header>

    <Card tone="plain" class="w-full max-w-sm space-y-3">
      <p class="font-zen text-xs text-stone-500 text-center mb-1">Phase 0 demo · 選一個示範帳號試試</p>

      <button
        v-for="u in demoUsers"
        :key="u.email"
        :disabled="loading"
        @click="pickDemo(u.email)"
        class="w-full px-5 py-3.5 bg-cream-50 hover:bg-peach-50 disabled:opacity-50 rounded-2xl transition-all active:scale-[0.98] flex items-center justify-between text-left shadow-sm border border-cream-200"
        :data-test="`demo-login-${u.email}`"
      >
        <div>
          <p class="font-zen font-semibold text-peach-500 text-sm">{{ u.name }}</p>
          <p class="font-zen text-[11px] text-stone-500 mt-0.5">{{ u.detail }}</p>
        </div>
        <span
          v-if="activeEmail === u.email && loading"
          class="w-4 h-4 border-2 border-peach-300 border-t-transparent rounded-full animate-spin"
        />
        <span v-else class="text-peach-400">→</span>
      </button>

      <p v-if="error" class="text-xs text-sakura-500 text-center font-zen">{{ error }}</p>

      <p class="text-[10px] text-stone-400 text-center pt-2 leading-relaxed font-zen">
        Phase 0 不接 Pandora Core Identity，正式版會走集團統一登入。<br />
        資料只存本機 SQLite，不上 cloud。
      </p>
    </Card>

    <p class="font-zen text-[10px] text-stone-400 mt-6 leading-relaxed text-center">
      ❌ 不做廣告 · ❌ 不賣資料 · 妳的週期資料只屬於妳
    </p>
  </div>
</template>
