<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { demoLogin } from '../api'

const router = useRouter()
const loading = ref(false)
const error = ref<string | null>(null)

const demoUsers = [
  { name: '小敏（28 天週期）', email: 'demo-min@pandora-calendar.test' },
  { name: '雨晴（30 天週期）', email: 'demo-yuching@pandora-calendar.test' },
  { name: '阿伶（26 天週期）', email: 'demo-aling@pandora-calendar.test' },
]

async function pickDemo(email: string) {
  loading.value = true
  error.value = null
  try {
    await demoLogin(email)
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '登入失敗'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-6">
    <div class="text-center mb-10">
      <div class="text-6xl mb-3">🌙</div>
      <h1 class="text-3xl font-bold text-brand-700">潘朵拉月曆</h1>
      <p class="text-brand-600 mt-2">妳的週期 · 朵朵陪妳一起記</p>
    </div>

    <div class="bg-white rounded-3xl shadow-sm p-6 w-full max-w-sm space-y-3">
      <p class="text-sm text-stone-500 text-center mb-2">Phase 0 demo · 選一個示範帳號試試</p>

      <button
        v-for="u in demoUsers"
        :key="u.email"
        :disabled="loading"
        @click="pickDemo(u.email)"
        class="w-full py-3 bg-brand-50 hover:bg-brand-100 disabled:opacity-50 rounded-xl text-sm font-medium text-brand-700 transition"
        :data-test="`demo-login-${u.email}`"
      >
        {{ u.name }}
      </button>

      <p v-if="error" class="text-xs text-red-500 text-center">{{ error }}</p>

      <p class="text-[10px] text-stone-400 text-center pt-2 leading-relaxed">
        Phase 0 不接 Pandora Core Identity，正式版會走集團統一登入。<br />
        資料只存本機 SQLite，不上 cloud。
      </p>
    </div>
  </div>
</template>
