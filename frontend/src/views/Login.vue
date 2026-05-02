<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { demoLogin, platformLogin, platformRegister, platformOauthUrl } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Character from '../components/Character.vue'

const router = useRouter()
const loading = ref(false)
const error = ref<string | null>(null)
const activeEmail = ref<string | null>(null)
const mode = ref<'login' | 'register'>('login')
const email = ref('')
const password = ref('')
const displayName = ref('')
const showDemo = ref(import.meta.env.DEV)

const demoUsers = [
  { name: '小敏', detail: '28 天週期', email: 'demo-min@pandora-calendar.test' },
  { name: '雨晴', detail: '30 天週期', email: 'demo-yuching@pandora-calendar.test' },
  { name: '阿伶', detail: '26 天週期', email: 'demo-aling@pandora-calendar.test' },
]

async function pickDemo(addr: string) {
  loading.value = true
  activeEmail.value = addr
  error.value = null
  try {
    await demoLogin(addr)
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '登入失敗'
  } finally {
    loading.value = false
    activeEmail.value = null
  }
}

async function submitLogin() {
  if (!email.value || !password.value) {
    error.value = '請填 email + 密碼'
    return
  }
  loading.value = true
  error.value = null
  try {
    await platformLogin(email.value.trim(), password.value)
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.detail ?? e?.response?.data?.error ?? '登入失敗'
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  if (!email.value || !password.value || password.value.length < 8) {
    error.value = '請填 email + 密碼（至少 8 字元）'
    return
  }
  loading.value = true
  error.value = null
  try {
    await platformRegister(email.value.trim(), password.value, displayName.value.trim() || undefined)
    error.value = null
    mode.value = 'login'
    alert('註冊成功！請收驗證信，之後再登入。')
  } catch (e: any) {
    error.value = e?.response?.data?.detail ?? e?.response?.data?.error ?? '註冊失敗'
  } finally {
    loading.value = false
  }
}

async function loginWith(provider: 'google' | 'line' | 'apple') {
  loading.value = true
  error.value = null
  try {
    const url = await platformOauthUrl(provider)
    // Capacitor app 用 in-app browser；web 直接 redirect
    window.location.href = url
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? `${provider} 登入失敗`
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-6 py-10 bg-dawn-gradient">
    <header class="text-center mb-6 max-w-sm">
      <div class="flex justify-center mb-2">
        <Character species="dodo" :size="148" mood="happy" :show-halo="true" :floaty="true" />
      </div>
      <h1 class="font-display text-4xl font-bold text-peach-500 tracking-wide">潘朵拉月曆</h1>
      <p class="font-zen text-stone-600 mt-2 text-sm">妳的週期 · 朵朵陪妳一起記</p>
    </header>

    <Card tone="plain" class="w-full max-w-sm space-y-3">
      <!-- Mode tabs -->
      <div class="flex gap-2 mb-1">
        <button
          @click="mode = 'login'"
          :class="mode === 'login' ? 'bg-peach-gradient text-white' : 'bg-cream-50 text-stone-500'"
          class="flex-1 py-2 rounded-2xl text-sm font-zen transition-all"
        >
          登入
        </button>
        <button
          @click="mode = 'register'"
          :class="mode === 'register' ? 'bg-peach-gradient text-white' : 'bg-cream-50 text-stone-500'"
          class="flex-1 py-2 rounded-2xl text-sm font-zen transition-all"
        >
          註冊
        </button>
      </div>

      <input
        v-model="email"
        type="email"
        placeholder="email"
        autocomplete="email"
        data-test="login-email"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />
      <input
        v-model="password"
        type="password"
        placeholder="密碼（至少 8 字元）"
        autocomplete="current-password"
        data-test="login-password"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />
      <input
        v-if="mode === 'register'"
        v-model="displayName"
        type="text"
        placeholder="暱稱（選填）"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />

      <Button
        full
        variant="primary"
        :loading="loading"
        @click="mode === 'login' ? submitLogin() : submitRegister()"
        :data-test="`submit-${mode}`"
      >
        {{ mode === 'login' ? '登入' : '建立帳號' }}
      </Button>

      <!-- OAuth -->
      <div class="flex items-center gap-2 my-2">
        <div class="flex-1 h-px bg-cream-200" />
        <span class="text-[10px] font-zen text-stone-400">或</span>
        <div class="flex-1 h-px bg-cream-200" />
      </div>
      <div class="grid grid-cols-3 gap-2">
        <button
          @click="loginWith('google')"
          :disabled="loading"
          class="py-2.5 rounded-2xl border border-cream-200 bg-white hover:bg-cream-50 disabled:opacity-50 text-sm font-zen text-stone-600 transition-all"
        >
          Google
        </button>
        <button
          @click="loginWith('line')"
          :disabled="loading"
          class="py-2.5 rounded-2xl border border-cream-200 bg-white hover:bg-cream-50 disabled:opacity-50 text-sm font-zen text-stone-600 transition-all"
        >
          LINE
        </button>
        <button
          @click="loginWith('apple')"
          :disabled="loading"
          class="py-2.5 rounded-2xl border border-cream-200 bg-white hover:bg-cream-50 disabled:opacity-50 text-sm font-zen text-stone-600 transition-all"
        >
          Apple
        </button>
      </div>

      <p v-if="error" class="text-xs text-sakura-500 text-center font-zen pt-1">{{ error }}</p>
    </Card>

    <!-- Dev demo (gated by import.meta.env.DEV) -->
    <Card v-if="showDemo" tone="plain" class="w-full max-w-sm space-y-2 mt-4">
      <p class="font-zen text-[11px] text-stone-500 text-center">dev demo · 一鍵登入示範帳號</p>
      <button
        v-for="u in demoUsers"
        :key="u.email"
        :disabled="loading"
        @click="pickDemo(u.email)"
        class="w-full px-5 py-2.5 bg-cream-50 hover:bg-peach-50 disabled:opacity-50 rounded-2xl transition-all active:scale-[0.98] flex items-center justify-between text-left text-sm border border-cream-200"
        :data-test="`demo-login-${u.email}`"
      >
        <span class="font-zen text-peach-500">{{ u.name }} · {{ u.detail }}</span>
        <span
          v-if="activeEmail === u.email && loading"
          class="w-3 h-3 border-2 border-peach-300 border-t-transparent rounded-full animate-spin"
        />
        <span v-else class="text-peach-400 text-xs">→</span>
      </button>
    </Card>

    <p class="font-zen text-[10px] text-stone-400 mt-6 leading-relaxed text-center">
      ❌ 不做廣告 · ❌ 不賣資料 · 妳的週期資料只屬於妳
    </p>
  </div>
</template>
