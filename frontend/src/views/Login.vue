<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { demoLogin, platformLogin, platformRegister, platformOauthUrl } from '../api'
import { isLockEnabled, lock } from '../composables/useAppLock'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Character from '../components/Character.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()

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
  { labelKey: 'login_demo_user_min', email: 'demo-min@pandora-calendar.test' },
  { labelKey: 'login_demo_user_yuching', email: 'demo-yuching@pandora-calendar.test' },
  { labelKey: 'login_demo_user_aling', email: 'demo-aling@pandora-calendar.test' },
]

function maybeArmLock() {
  // 登入完成後，如果用戶之前啟用過 App 鎖，立刻設旗標讓 App.vue 拉起 Lock overlay
  if (isLockEnabled()) lock()
}

async function pickDemo(addr: string) {
  loading.value = true
  activeEmail.value = addr
  error.value = null
  try {
    await demoLogin(addr)
    maybeArmLock()
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('login_error_login_failed')
  } finally {
    loading.value = false
    activeEmail.value = null
  }
}

async function submitLogin() {
  if (!email.value || !password.value) {
    error.value = t('login_validation_required')
    return
  }
  loading.value = true
  error.value = null
  try {
    await platformLogin(email.value.trim(), password.value)
    maybeArmLock()
    router.push('/calendar')
  } catch (e: any) {
    error.value = e?.response?.data?.detail ?? e?.response?.data?.error ?? t('login_error_login_failed')
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  if (!email.value || !password.value || password.value.length < 8) {
    error.value = t('login_register_validation')
    return
  }
  loading.value = true
  error.value = null
  try {
    await platformRegister(email.value.trim(), password.value, displayName.value.trim() || undefined)
    error.value = null
    mode.value = 'login'
    alert(t('login_register_alert_success'))
  } catch (e: any) {
    error.value = e?.response?.data?.detail ?? e?.response?.data?.error ?? t('login_error_register_failed')
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
    error.value = e?.response?.data?.error ?? `${provider} ${t('login_error_oauth_failed_prefix')}`
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
      <h1 class="font-display text-4xl font-bold text-peach-500 tracking-wide">{{ t('login_app_title') }}</h1>
      <p class="font-zen text-stone-600 mt-2 text-sm">{{ t('login_subtitle') }}</p>
    </header>

    <Card tone="plain" class="w-full max-w-sm space-y-3">
      <!-- Mode tabs -->
      <div class="flex gap-2 mb-1">
        <button
          @click="mode = 'login'"
          :class="mode === 'login' ? 'bg-peach-gradient text-white' : 'bg-cream-50 text-stone-500'"
          class="flex-1 py-2 rounded-2xl text-sm font-zen transition-all"
        >
          {{ t('login_tab_login') }}
        </button>
        <button
          @click="mode = 'register'"
          :class="mode === 'register' ? 'bg-peach-gradient text-white' : 'bg-cream-50 text-stone-500'"
          class="flex-1 py-2 rounded-2xl text-sm font-zen transition-all"
        >
          {{ t('login_tab_register') }}
        </button>
      </div>

      <input
        v-model="email"
        type="email"
        :placeholder="t('login_placeholder_email')"
        autocomplete="email"
        data-test="login-email"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />
      <input
        v-model="password"
        type="password"
        :placeholder="t('login_placeholder_password')"
        autocomplete="current-password"
        data-test="login-password"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />
      <input
        v-if="mode === 'register'"
        v-model="displayName"
        type="text"
        :placeholder="t('login_placeholder_display_name')"
        class="w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm font-zen"
      />

      <Button
        full
        variant="primary"
        :loading="loading"
        @click="mode === 'login' ? submitLogin() : submitRegister()"
        :data-test="`submit-${mode}`"
      >
        {{ mode === 'login' ? t('login_btn_submit_login') : t('login_btn_submit_register') }}
      </Button>

      <!-- OAuth -->
      <div class="flex items-center gap-2 my-2">
        <div class="flex-1 h-px bg-cream-200" />
        <span class="text-[10px] font-zen text-stone-400">{{ t('login_oauth_divider') }}</span>
        <div class="flex-1 h-px bg-cream-200" />
      </div>
      <div class="grid grid-cols-3 gap-2">
        <!-- Google：四色標誌 -->
        <button
          @click="loginWith('google')"
          :disabled="loading"
          :aria-label="t('login_oauth_google_aria')"
          class="py-2.5 rounded-2xl border border-cream-200 bg-white hover:bg-cream-50 disabled:opacity-50 transition-all flex items-center justify-center gap-1.5"
        >
          <svg class="w-4 h-4" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
            <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
            <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
          </svg>
          <span class="text-sm font-zen text-stone-600">Google</span>
        </button>
        <!-- LINE：官方綠 -->
        <button
          @click="loginWith('line')"
          :disabled="loading"
          :aria-label="t('login_oauth_line_aria')"
          class="py-2.5 rounded-2xl border border-cream-200 bg-white hover:bg-cream-50 disabled:opacity-50 transition-all flex items-center justify-center gap-1.5"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#06C755" d="M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.815 4.272 8.85 10.041 9.612.391.084.923.258 1.058.592.122.303.08.778.039 1.085l-.171 1.027c-.053.303-.242 1.186 1.039.647 1.281-.54 6.91-4.069 9.428-6.967C23.156 14.337 24 12.43 24 10.314"/>
            <path fill="#FFF" d="M19.969 13.422h-3.394a.231.231 0 0 1-.23-.231v-.001-5.265-.001a.23.23 0 0 1 .23-.231h3.394c.127 0 .23.104.23.231v.857a.23.23 0 0 1-.23.231h-2.305v.89h2.305c.127 0 .23.104.23.231v.857a.231.231 0 0 1-.23.232h-2.305v.89h2.305c.127 0 .23.104.23.231v.857a.231.231 0 0 1-.23.231M7.245 13.422a.23.23 0 0 0 .23-.231v-.857a.231.231 0 0 0-.23-.231H4.94V7.925a.231.231 0 0 0-.23-.231h-.858a.23.23 0 0 0-.23.231v5.265a.23.23 0 0 0 .23.231h3.394M9.291 7.694H8.434a.23.23 0 0 0-.23.23v5.267a.23.23 0 0 0 .23.23h.857a.23.23 0 0 0 .23-.23V7.924a.23.23 0 0 0-.23-.23M15.118 7.694h-.857a.231.231 0 0 0-.231.23v3.128l-2.41-3.255-.018-.024-.014-.014-.012-.011-.005-.004-.013-.01-.006-.005-.013-.01-.008-.004-.013-.008-.008-.005-.014-.007-.008-.003-.015-.005-.009-.003-.015-.005h-.901a.231.231 0 0 0-.23.23v5.266a.23.23 0 0 0 .23.231h.857a.23.23 0 0 0 .231-.23v-3.127l2.413 3.259c.017.024.038.043.062.058l.001.001.014.009.007.003.011.005.012.005.007.002.017.005h.014a.27.27 0 0 0 .06.008h.853a.231.231 0 0 0 .231-.23V7.924a.231.231 0 0 0-.231-.23"/>
          </svg>
          <span class="text-sm font-zen text-stone-600">LINE</span>
        </button>
        <!-- Apple：黑色 logo -->
        <button
          @click="loginWith('apple')"
          :disabled="loading"
          :aria-label="t('login_oauth_apple_aria')"
          class="py-2.5 rounded-2xl border border-stone-800 bg-stone-900 hover:bg-stone-800 disabled:opacity-50 transition-all flex items-center justify-center gap-1.5"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="white">
            <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
          </svg>
          <span class="text-sm font-zen text-white">Apple</span>
        </button>
      </div>

      <p v-if="error" class="text-xs text-sakura-500 text-center font-zen pt-1">{{ error }}</p>

      <!-- 忘記密碼 fallback：PC self-service password reset 尚未實作，先導 mailto -->
      <p v-if="mode === 'login'" class="text-center pt-1">
        <a
          href="mailto:support@js-store.com.tw?subject=潘朵拉月曆%20-%20忘記密碼&body=請協助重設我的密碼，謝謝。我的 email："
          class="text-[11px] text-stone-400 hover:text-peach-500 transition-colors font-zen underline-offset-2 hover:underline"
        >
          {{ t('login_forgot_password') }}
        </a>
      </p>
    </Card>

    <!-- Dev demo (gated by import.meta.env.DEV) -->
    <Card v-if="showDemo" tone="plain" class="w-full max-w-sm space-y-2 mt-4">
      <p class="font-zen text-[11px] text-stone-500 text-center">{{ t('login_demo_caption') }}</p>
      <button
        v-for="u in demoUsers"
        :key="u.email"
        :disabled="loading"
        @click="pickDemo(u.email)"
        class="w-full px-5 py-2.5 bg-cream-50 hover:bg-peach-50 disabled:opacity-50 rounded-2xl transition-all active:scale-[0.98] flex items-center justify-between text-left text-sm border border-cream-200"
        :data-test="`demo-login-${u.email}`"
      >
        <span class="font-zen text-peach-500">{{ t(u.labelKey) }}</span>
        <span
          v-if="activeEmail === u.email && loading"
          class="w-3 h-3 border-2 border-peach-300 border-t-transparent rounded-full animate-spin"
        />
        <span v-else class="text-peach-400 text-xs">→</span>
      </button>
    </Card>

    <div class="mt-6 max-w-sm w-full text-center space-y-1">
      <p class="font-display font-bold text-peach-500 text-base">{{ t('privacy_yours') }}</p>
      <p class="font-zen text-[11px] text-stone-500 leading-relaxed">
        {{ t('privacy_blurb') }}
      </p>
    </div>

    <p class="font-zen text-[10px] text-stone-400 mt-2 text-center space-x-2">
      <RouterLink to="/privacy" class="hover:text-peach-500 transition-colors">{{ t('login_link_privacy') }}</RouterLink>
      <span>·</span>
      <RouterLink to="/terms" class="hover:text-peach-500 transition-colors">{{ t('login_link_terms') }}</RouterLink>
    </p>
  </div>
</template>
