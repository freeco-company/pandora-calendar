<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Capacitor } from '@capacitor/core'
import { FeedbackApi, type FeedbackCategory } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useSfx } from '../lib/sound'

const router = useRouter()
const sfx = useSfx()

const CATEGORIES: Array<{ value: FeedbackCategory; label: string; emoji: string; hint: string }> = [
  { value: 'bug', label: '哪裡壞掉了', emoji: '🐛', hint: 'App 有 bug、閃退、記錄不見' },
  { value: 'feature', label: '想要新功能', emoji: '💡', hint: '希望朵朵能幫妳做的' },
  { value: 'content', label: '內容回饋', emoji: '📝', hint: '對朵朵的話、衛教文章的想法' },
  { value: 'other', label: '其他', emoji: '🌷', hint: '想跟朵朵說的任何話' },
]

const category = ref<FeedbackCategory>('feature')
const message = ref('')
const submitting = ref(false)
const submittedOk = ref(false)
const errorMsg = ref<string | null>(null)

const trimmed = computed(() => message.value.trim())
const valid = computed(() => trimmed.value.length >= 10 && trimmed.value.length <= 2000)

const APP_VERSION = (import.meta.env.VITE_APP_VERSION as string | undefined) ?? '0.0.0-dev'

function deviceInfo(): string {
  const platform = Capacitor.getPlatform()
  return `${platform} · ${navigator.userAgent}`.slice(0, 500)
}

async function submit() {
  errorMsg.value = null
  if (!valid.value) {
    if (trimmed.value.length < 10) errorMsg.value = '至少寫 10 個字，朵朵才能聽懂妳的意思'
    else errorMsg.value = '訊息太長了，麻煩精簡一下'
    return
  }
  submitting.value = true
  try {
    await FeedbackApi.submit({
      category: category.value,
      message: trimmed.value,
      app_version: APP_VERSION,
      device_info: deviceInfo(),
    })
    sfx.play('correct')
    submittedOk.value = true
    message.value = ''
  } catch (e: any) {
    const status = e?.response?.status
    if (status === 429) {
      errorMsg.value = '妳今天已經告訴我很多了，明天再來吧'
    } else if (status === 422) {
      const errs = e?.response?.data?.errors ?? {}
      errorMsg.value = errs.message?.[0] ?? errs.category?.[0] ?? '送出失敗，再檢查一下'
    } else {
      errorMsg.value = '送出失敗，請稍後再試'
    }
    sfx.play('wrong')
  } finally {
    submitting.value = false
  }
}

function reset() {
  submittedOk.value = false
  errorMsg.value = null
}
</script>

<template>
  <div class="px-5 pt-10 pb-10 max-w-md mx-auto space-y-5">
    <header class="space-y-1">
      <button class="text-xs text-stone-500 font-zen mb-2" @click="router.back()">← 返回</button>
      <h1 class="font-display text-2xl font-bold text-peach-500">給朵朵的話</h1>
      <p class="font-zen text-xs text-stone-500">
        妳的回饋朵朵都會看，這是讓 App 更貼近妳的方式
      </p>
    </header>

    <Card v-if="submittedOk" tone="cream" class="text-center space-y-4">
      <div class="text-5xl">💛</div>
      <h2 class="font-display text-xl font-bold text-peach-500">謝謝妳的回饋，我會記得</h2>
      <p class="font-zen text-sm text-stone-600 leading-relaxed">
        朵朵會把妳說的記下來。妳是讓潘朵拉月曆變得更好的朋友。
      </p>
      <div class="flex flex-col gap-2 pt-2">
        <Button full @click="router.push('/me')">回到我的</Button>
        <Button variant="ghost" full @click="reset">再說一些</Button>
      </div>
    </Card>

    <template v-else>
      <Card tone="plain" class="space-y-3">
        <h2 class="font-display font-bold text-peach-500 text-sm">想聊什麼？</h2>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="opt in CATEGORIES"
            :key="opt.value"
            class="text-left px-3 py-3 rounded-2xl border transition-all"
            :class="
              category === opt.value
                ? 'border-peach-400 bg-peach-50 shadow-soft'
                : 'border-cream-200 bg-white hover:bg-peach-50/50'
            "
            :aria-pressed="category === opt.value"
            @click="category = opt.value; sfx.play('ui_tap')"
          >
            <div class="text-xl mb-1">{{ opt.emoji }}</div>
            <p class="font-zen text-sm text-stone-700 font-medium">{{ opt.label }}</p>
            <p class="font-zen text-[11px] text-stone-400 mt-0.5 leading-tight">{{ opt.hint }}</p>
          </button>
        </div>
      </Card>

      <Card tone="plain" class="space-y-2">
        <label for="fb-message" class="font-display font-bold text-peach-500 text-sm">
          想跟朵朵說的話
        </label>
        <textarea
          id="fb-message"
          v-model="message"
          rows="6"
          maxlength="2000"
          placeholder="慢慢說，朵朵在聽…（至少 10 個字）"
          class="w-full px-4 py-3 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 font-zen text-sm leading-relaxed resize-none"
          :aria-invalid="!!errorMsg"
        />
        <div class="flex justify-between text-[11px] font-zen text-stone-400">
          <span>{{ trimmed.length }} / 2000</span>
          <span v-if="trimmed.length > 0 && trimmed.length < 10">再多 {{ 10 - trimmed.length }} 個字</span>
        </div>
        <p v-if="errorMsg" class="font-zen text-xs text-sakura-500">{{ errorMsg }}</p>
      </Card>

      <p class="font-zen text-[11px] text-stone-400 text-center leading-relaxed px-4">
        送出時會帶上 App 版本與裝置資訊（{{ APP_VERSION }} · {{ Capacitor.getPlatform() }}），方便朵朵 debug
      </p>

      <Button
        full
        size="lg"
        :loading="submitting"
        :disabled="!valid || submitting"
        @click="submit"
      >
        送給朵朵
      </Button>
    </template>
  </div>
</template>
