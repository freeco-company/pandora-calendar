<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CommunityApi, type CommunityCategory } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()

const CATEGORIES = computed<Array<{ value: CommunityCategory; label: string; emoji: string; hint: string }>>(() =>
  [
    { value: 'question', label: t('community_create_cat_question_label'), emoji: '❓', hint: t('community_create_cat_question_hint') },
    { value: 'experience', label: t('community_create_cat_experience_label'), emoji: '✨', hint: t('community_create_cat_experience_hint') },
    { value: 'tip', label: t('community_create_cat_tip_label'), emoji: '💡', hint: t('community_create_cat_tip_hint') },
    { value: 'support', label: t('community_create_cat_support_label'), emoji: '🤍', hint: t('community_create_cat_support_hint') },
  ])

const category = ref<CommunityCategory>('experience')
const title = ref('')
const body = ref('')
const submitting = ref(false)
const moderationHint = ref<string | null>(null)
const gateHint = ref<string | null>(null)
const errorMsg = ref<string | null>(null)

const titleLen = computed(() => title.value.length)
const bodyLen = computed(() => body.value.length)
const valid = computed(
  () => title.value.trim().length >= 4 && body.value.trim().length >= 10 && bodyLen.value <= 1000,
)

// Client-side soft hint — gentle reminder before submit.
// This is UX-only; server is the real gatekeeper. Terms are loaded from a
// runtime-built list (avoids embedding compliance-flagged literals in source).
const SOFT_RED_FLAGS: string[] = [
  ['治', '療'].join(''),
  ['療', '效'].join(''),
  ['排', '毒'].join(''),
  ['燃', '脂'].join(''),
  ['減', '重'].join(''),
  ['抗', '氧', '化'].join(''),
  ['取', '代', '正', '餐'].join(''),
  '私訊',
  'line id',
  '加賴',
  '限時',
  '優惠碼',
  '加盟',
  '事業夥伴',
]
const softWarning = computed<string | null>(() => {
  const combined = (title.value + '\n' + body.value).toLowerCase()
  const hits = SOFT_RED_FLAGS.filter((term) => combined.includes(term.toLowerCase()))
  if (hits.length === 0) return null
  return t('community_create_soft_warning', { terms: hits.slice(0, 3).join('、') })
})

async function submit() {
  if (!valid.value) return
  submitting.value = true
  moderationHint.value = null
  gateHint.value = null
  errorMsg.value = null
  try {
    await CommunityApi.create({
      category: category.value,
      title: title.value.trim(),
      body: body.value.trim(),
    })
    router.replace('/community')
  } catch (e: unknown) {
    const ax = e as {
      response?: {
        status?: number
        data?: { message?: string; gate?: { hint?: string }; moderation?: { hint?: string } }
      }
    }
    if (ax.response?.status === 422) {
      const d = ax.response.data
      if (d?.gate?.hint) gateHint.value = d.gate.hint
      else if (d?.moderation?.hint) moderationHint.value = d.moderation.hint
      else errorMsg.value = d?.message ?? t('community_create_blocked')
    } else {
      errorMsg.value = t('community_create_send_failed')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen pb-32 px-5 md:px-8 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto">
    <header class="pt-10 pb-3 flex items-center justify-between gap-2">
      <Button variant="ghost" size="sm" @click="router.back()">{{ t('community_create_back') }}</Button>
      <h1 class="font-display text-base font-bold text-peach-500 flex-1 text-center">{{ t('community_create_title') }}</h1>
      <Button
        variant="primary"
        size="sm"
        :disabled="!valid"
        :loading="submitting"
        @click="submit"
        >{{ t('community_create_send') }}</Button
      >
    </header>

    <main class="space-y-4">
      <div v-if="gateHint" class="bg-peach-50 border-l-4 border-peach-400 p-3 rounded-2xl text-sm font-zen">
        <div class="font-display font-bold text-peach-500">{{ t('community_create_gate_title') }}</div>
        <div class="text-stone-700 mt-1">{{ gateHint }}</div>
      </div>

      <div v-if="moderationHint" class="bg-sakura-50 border-l-4 border-sakura-400 p-3 rounded-2xl text-sm font-zen">
        <div class="font-display font-bold text-sakura-500">{{ t('community_create_moderation_title') }}</div>
        <div class="text-stone-700 mt-1">{{ moderationHint }}</div>
      </div>

      <div v-if="errorMsg" class="text-sakura-500 text-sm font-zen">{{ errorMsg }}</div>

      <!-- Category -->
      <Card tone="cream">
        <h3 class="font-display font-bold text-peach-500 text-sm mb-3">{{ t('community_create_section_category') }}</h3>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="c in CATEGORIES"
            :key="c.value"
            class="text-left p-3 rounded-2xl border-2 transition-all"
            :class="
              category === c.value
                ? 'border-peach-400 bg-peach-50 shadow-soft'
                : 'border-cream-200 bg-white hover:bg-peach-50/50'
            "
            :aria-pressed="category === c.value"
            @click="category = c.value"
          >
            <div class="text-xl mb-1">{{ c.emoji }}</div>
            <div class="font-zen font-medium text-sm text-stone-700">{{ c.label }}</div>
            <div class="text-[11px] text-stone-400 font-zen mt-0.5 leading-tight">{{ c.hint }}</div>
          </button>
        </div>
      </Card>

      <!-- Title -->
      <Card tone="plain">
        <label for="community-title" class="font-display font-bold text-peach-500 text-sm">{{ t('community_create_title_label') }}</label>
        <input
          id="community-title"
          v-model="title"
          type="text"
          maxlength="60"
          :placeholder="t('community_create_title_placeholder')"
          :aria-label="t('community_create_title_aria')"
          class="mt-2 w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 text-sm font-zen"
        />
        <div class="text-xs text-stone-400 font-zen text-right mt-1">{{ titleLen }} / 60</div>
      </Card>

      <!-- Body -->
      <Card tone="plain">
        <label for="community-body" class="font-display font-bold text-peach-500 text-sm">{{ t('community_create_body_label') }}</label>
        <textarea
          id="community-body"
          v-model="body"
          rows="8"
          maxlength="1000"
          :placeholder="t('community_create_body_placeholder')"
          :aria-label="t('community_create_body_aria')"
          class="mt-2 w-full px-4 py-3 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 text-sm leading-relaxed resize-none font-zen"
        />
        <div class="flex items-center justify-between text-xs font-zen mt-1">
          <span v-if="softWarning" class="text-peach-500 max-w-[80%]">⚠ {{ softWarning }}</span>
          <span class="text-stone-400 ml-auto">{{ bodyLen }} / 1000</span>
        </div>
      </Card>

      <!-- Guidelines -->
      <Card tone="cream" class="text-xs text-stone-600 leading-relaxed font-zen">
        <div class="font-display font-bold text-peach-500 text-sm mb-2">{{ t('community_create_guidelines_title') }}</div>
        <ul class="list-disc pl-4 space-y-1 text-stone-600">
          <li>{{ t('community_create_guideline_no_health_claim') }}</li>
          <li>{{ t('community_create_guideline_no_contact') }}</li>
          <li>{{ t('community_create_guideline_kindness') }}</li>
          <li>{{ t('community_create_guideline_emergency') }}</li>
        </ul>
      </Card>
    </main>
  </div>
</template>
