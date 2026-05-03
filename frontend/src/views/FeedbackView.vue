<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Capacitor } from '@capacitor/core'
import { FeedbackApi, type FeedbackCategory } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useSfx } from '../lib/sound'
import { useTone } from '../composables/useTone'

const router = useRouter()
const sfx = useSfx()
const { t } = useTone()

const CATEGORIES = computed<Array<{ value: FeedbackCategory; label: string; emoji: string; hint: string }>>(() => [
  { value: 'bug', label: t('feedback_cat_bug_label'), emoji: '🐛', hint: t('feedback_cat_bug_hint') },
  { value: 'feature', label: t('feedback_cat_feature_label'), emoji: '💡', hint: t('feedback_cat_feature_hint') },
  { value: 'content', label: t('feedback_cat_content_label'), emoji: '📝', hint: t('feedback_cat_content_hint') },
  { value: 'other', label: t('feedback_cat_other_label'), emoji: '🌷', hint: t('feedback_cat_other_hint') },
])

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
    if (trimmed.value.length < 10) errorMsg.value = t('feedback_min_chars')
    else errorMsg.value = t('feedback_too_long')
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
      errorMsg.value = t('feedback_rate_limit')
    } else if (status === 422) {
      const errs = e?.response?.data?.errors ?? {}
      errorMsg.value = errs.message?.[0] ?? errs.category?.[0] ?? t('feedback_send_check')
    } else {
      errorMsg.value = t('feedback_send_failed')
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
      <button class="text-xs text-stone-500 font-zen mb-2" @click="router.back()">{{ t('common_back') }}</button>
      <h1 class="font-display text-2xl font-bold text-peach-500">{{ t('feedback_title') }}</h1>
      <p class="font-zen text-xs text-stone-500">
        {{ t('feedback_subtitle') }}
      </p>
    </header>

    <Card v-if="submittedOk" tone="cream" class="text-center space-y-4">
      <div class="text-5xl">💛</div>
      <h2 class="font-display text-xl font-bold text-peach-500">{{ t('feedback_submitted_title') }}</h2>
      <p class="font-zen text-sm text-stone-600 leading-relaxed">
        {{ t('feedback_submitted_blurb') }}
      </p>
      <div class="flex flex-col gap-2 pt-2">
        <Button full @click="router.push('/me')">{{ t('feedback_back_to_me') }}</Button>
        <Button variant="ghost" full @click="reset">{{ t('feedback_say_more') }}</Button>
      </div>
    </Card>

    <template v-else>
      <Card tone="plain" class="space-y-3">
        <h2 class="font-display font-bold text-peach-500 text-sm">{{ t('feedback_section_topic') }}</h2>
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
          {{ t('feedback_message_label') }}
        </label>
        <textarea
          id="fb-message"
          v-model="message"
          rows="6"
          maxlength="2000"
          :placeholder="t('feedback_message_placeholder')"
          class="w-full px-4 py-3 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 font-zen text-sm leading-relaxed resize-none"
          :aria-invalid="!!errorMsg"
        />
        <div class="flex justify-between text-[11px] font-zen text-stone-400">
          <span>{{ trimmed.length }} / 2000</span>
          <span v-if="trimmed.length > 0 && trimmed.length < 10">{{ t('feedback_chars_remaining', { n: 10 - trimmed.length }) }}</span>
        </div>
        <p v-if="errorMsg" class="font-zen text-xs text-sakura-500">{{ errorMsg }}</p>
      </Card>

      <p class="font-zen text-[11px] text-stone-400 text-center leading-relaxed px-4">
        {{ t('feedback_meta_blurb', { version: APP_VERSION, platform: Capacitor.getPlatform() }) }}
      </p>

      <Button
        full
        size="lg"
        :loading="submitting"
        :disabled="!valid || submitting"
        @click="submit"
      >
        {{ t('feedback_submit_btn') }}
      </Button>
    </template>
  </div>
</template>
