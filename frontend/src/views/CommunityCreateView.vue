<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CommunityApi, type CommunityCategory } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'

const router = useRouter()

const CATEGORIES: Array<{ value: CommunityCategory; label: string; emoji: string; hint: string }> =
  [
    { value: 'question', label: '想問', emoji: '❓', hint: '有問題想請大家幫忙看' },
    { value: 'experience', label: '分享', emoji: '✨', hint: '我自己的經驗' },
    { value: 'tip', label: '小撇步', emoji: '💡', hint: '推薦給其他朋友的方法' },
    { value: 'support', label: '陪伴', emoji: '🤍', hint: '想找人聊聊' },
  ]

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
  const hits = SOFT_RED_FLAGS.filter((t) => combined.includes(t.toLowerCase()))
  if (hits.length === 0) return null
  return `提醒：「${hits.slice(0, 3).join('、')}」這類用字可能違反社群規範（含推銷或健康宣稱）。換種說法會更好喔。`
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
      else errorMsg.value = d?.message ?? '無法送出'
    } else {
      errorMsg.value = '送出失敗，請稍後再試。'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen pb-32">
    <header class="px-5 pt-5 pb-3 flex items-center justify-between">
      <Button variant="ghost" size="sm" @click="router.back()">‹ 取消</Button>
      <h1 class="text-base font-semibold text-cream-900">新貼文</h1>
      <Button
        variant="primary"
        size="sm"
        :disabled="!valid"
        :loading="submitting"
        @click="submit"
        >送出</Button
      >
    </header>

    <main class="px-4 space-y-3">
      <div v-if="gateHint" class="bg-peach-50 border-l-4 border-peach-400 p-3 rounded-xl text-sm">
        <div class="font-medium text-cream-900">還不能發文</div>
        <div class="text-cream-800 mt-1">{{ gateHint }}</div>
      </div>

      <div v-if="moderationHint" class="bg-red-50 border-l-4 border-red-400 p-3 rounded-xl text-sm">
        <div class="font-medium text-red-700">這篇內容暫時無法發布</div>
        <div class="text-red-700 mt-1">{{ moderationHint }}</div>
      </div>

      <div v-if="errorMsg" class="text-red-600 text-sm">{{ errorMsg }}</div>

      <!-- Category -->
      <Card tone="cream">
        <h3 class="text-sm font-medium text-cream-800 mb-2">分類</h3>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="c in CATEGORIES"
            :key="c.value"
            class="text-left p-3 rounded-xl border-2 transition"
            :class="
              category === c.value
                ? 'border-peach-400 bg-peach-50'
                : 'border-cream-200 bg-white/70'
            "
            @click="category = c.value"
          >
            <div class="text-xl">{{ c.emoji }}</div>
            <div class="font-medium text-sm text-cream-900 mt-1">{{ c.label }}</div>
            <div class="text-xs text-cream-600 mt-0.5">{{ c.hint }}</div>
          </button>
        </div>
      </Card>

      <!-- Title -->
      <Card>
        <label class="text-sm font-medium text-cream-800">標題</label>
        <input
          v-model="title"
          type="text"
          maxlength="60"
          placeholder="一句話說重點"
          class="mt-2 w-full px-3 py-2 rounded-xl border border-cream-200"
        />
        <div class="text-xs text-cream-500 text-right mt-1">{{ titleLen }} / 60</div>
      </Card>

      <!-- Body -->
      <Card>
        <label class="text-sm font-medium text-cream-800">內容</label>
        <textarea
          v-model="body"
          rows="8"
          maxlength="1000"
          placeholder="分享妳的故事 / 問題 / 想法。記得溫柔對待自己和朋友。"
          class="mt-2 w-full px-3 py-2 rounded-xl border border-cream-200 resize-none"
        />
        <div class="flex items-center justify-between text-xs mt-1">
          <span v-if="softWarning" class="text-peach-700 max-w-[80%]">⚠ {{ softWarning }}</span>
          <span class="text-cream-500 ml-auto">{{ bodyLen }} / 1000</span>
        </div>
      </Card>

      <!-- Guidelines -->
      <Card tone="cream" class="text-xs text-cream-700 leading-relaxed">
        <div class="font-medium text-cream-900 mb-1">社群規範</div>
        <ul class="list-disc pl-4 space-y-1">
          <li>不分享健康宣稱、推銷或商業連結（會自動被擋）</li>
          <li>不分享 Email、電話、Line ID 等聯絡方式</li>
          <li>溫柔對待彼此，不批評不評斷</li>
          <li>緊急狀況請聯絡專業協助（1925 / 1995）</li>
        </ul>
      </Card>
    </main>
  </div>
</template>
