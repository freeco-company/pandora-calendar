<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  CommunityApi,
  type CommunityCategory,
  type CommunityPost,
  type CommunitySort,
} from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'

const router = useRouter()

const CATEGORIES: Array<{ value: CommunityCategory | 'all'; label: string; emoji: string }> = [
  { value: 'all', label: '全部', emoji: '🌷' },
  { value: 'question', label: '想問', emoji: '❓' },
  { value: 'experience', label: '分享', emoji: '✨' },
  { value: 'tip', label: '小撇步', emoji: '💡' },
  { value: 'support', label: '陪伴', emoji: '🤍' },
]

const SORT_OPTIONS: Array<{ value: CommunitySort; label: string }> = [
  { value: 'latest', label: '最新' },
  { value: 'hot', label: '最熱' },
  { value: 'mine', label: '我的' },
]

const category = ref<CommunityCategory | 'all'>('all')
const sort = ref<CommunitySort>('latest')

const loading = ref(true)
const error = ref<string | null>(null)
const posts = ref<CommunityPost[]>([])

// Probe gate by submitting a "no-op" eligibility check via ListMeta-only response
// — actually the simpler path: we only know gate state when the user tries to
// post. For UX we precompute a soft-eligibility hint via /me data if available;
// here we render the floating "新增" button always and let the create page show
// the friendly gate hint after submit. Keeps this view's implementation light.
const canPostHint = ref<string | null>(null)

const filtered = computed(() => posts.value)

async function load() {
  loading.value = true
  error.value = null
  try {
    const params: { category?: CommunityCategory; sort: CommunitySort } = { sort: sort.value }
    if (category.value !== 'all') params.category = category.value
    const { data } = await CommunityApi.list(params)
    posts.value = data.data
  } catch {
    error.value = '社群暫時開不起來，等一下再試試。'
  } finally {
    loading.value = false
  }
}

function categoryEmoji(cat: CommunityCategory): string {
  const map: Record<CommunityCategory, string> = {
    question: '❓',
    experience: '✨',
    tip: '💡',
    support: '🤍',
  }
  return map[cat] ?? '🌷'
}

function preview(body: string): string {
  const trimmed = body.replace(/\s+/g, ' ').trim()
  return trimmed.length > 80 ? trimmed.slice(0, 80) + '…' : trimmed
}

function relativeTime(iso: string | null): string {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return '剛剛'
  if (min < 60) return `${min} 分鐘前`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr} 小時前`
  const day = Math.floor(hr / 24)
  if (day < 30) return `${day} 天前`
  return new Date(iso).toLocaleDateString('zh-TW')
}

watch([category, sort], load)
onMounted(load)
</script>

<template>
  <div class="min-h-screen pb-24">
    <header class="px-5 pt-5 pb-3">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-cream-900">社群問板</h1>
        <Button variant="ghost" size="sm" @click="router.back()">返回</Button>
      </div>
      <p class="text-xs text-cream-700 mt-1">
        匿名分享、互相陪伴。請對自己和朋友溫柔一點。
      </p>
    </header>

    <!-- Category tabs -->
    <div class="px-3 overflow-x-auto">
      <div class="flex gap-2 pb-2">
        <button
          v-for="c in CATEGORIES"
          :key="c.value"
          class="px-3 py-1.5 rounded-full text-sm whitespace-nowrap transition"
          :class="
            category === c.value
              ? 'bg-peach-300 text-cream-900 font-medium shadow-soft'
              : 'bg-white/70 text-cream-700'
          "
          @click="category = c.value"
        >
          {{ c.emoji }} {{ c.label }}
        </button>
      </div>
    </div>

    <!-- Sort -->
    <div class="px-5 flex gap-2 mb-2">
      <button
        v-for="s in SORT_OPTIONS"
        :key="s.value"
        class="text-xs px-2 py-1 rounded-md"
        :class="sort === s.value ? 'text-peach-700 font-medium' : 'text-cream-600'"
        @click="sort = s.value"
      >
        {{ s.label }}
      </button>
    </div>

    <main class="px-4 space-y-3">
      <Spinner v-if="loading" label="載入中..." />

      <div v-else-if="error" class="text-center text-cream-700 py-8">
        {{ error }}
      </div>

      <EmptyState
        v-else-if="filtered.length === 0"
        :title="sort === 'mine' ? '妳還沒有發過文' : '目前沒有貼文'"
        :subtitle="sort === 'mine' ? '想分享什麼嗎？點右下角加號開始。' : '當第一個分享的人吧。'"
        show-dodo
      />

      <Card
        v-for="p in filtered"
        :key="p.id"
        tone="cream"
        interactive
        class="cursor-pointer"
        @click="router.push(`/community/${p.id}`)"
      >
        <div class="flex items-start gap-3">
          <div
            class="text-2xl flex-shrink-0 w-10 h-10 rounded-full bg-peach-100 flex items-center justify-center"
          >
            {{ p.is_dodo ? '🌸' : categoryEmoji(p.category) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-cream-600">
              <span class="font-medium">{{ p.is_dodo ? '朵朵小編' : p.anonymous_handle }}</span>
              <span>·</span>
              <span>{{ relativeTime(p.published_at) }}</span>
              <span v-if="p.is_mine" class="ml-auto text-peach-600">妳的</span>
            </div>
            <h3 class="font-semibold text-cream-900 mt-1 line-clamp-2">{{ p.title }}</h3>
            <p class="text-sm text-cream-700 mt-1 line-clamp-2">{{ preview(p.body) }}</p>
            <div class="flex items-center gap-4 mt-2 text-xs text-cream-600">
              <span :aria-label="`${p.like_count} 個喜歡`">♡ {{ p.like_count }}</span>
              <span :aria-label="`${p.reply_count} 則回覆`">💬 {{ p.reply_count }}</span>
              <span
                v-if="p.has_self_harm_signal"
                class="ml-auto text-peach-700 bg-peach-50 px-2 py-0.5 rounded"
                >陪伴</span
              >
            </div>
          </div>
        </div>
      </Card>
    </main>

    <!-- FAB -->
    <button
      class="fixed bottom-6 right-5 w-14 h-14 rounded-full bg-peach-400 text-white text-2xl shadow-floaty active:scale-95 transition"
      :title="canPostHint ?? '發新文'"
      :aria-label="canPostHint ?? '發新貼文'"
      @click="router.push('/community/new')"
    >
      ＋
    </button>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
