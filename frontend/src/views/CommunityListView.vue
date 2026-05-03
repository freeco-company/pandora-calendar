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
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()

const CATEGORIES = computed<Array<{ value: CommunityCategory | 'all'; label: string; emoji: string }>>(() => [
  { value: 'all', label: t('community_cat_all'), emoji: '🌷' },
  { value: 'question', label: t('community_cat_question'), emoji: '❓' },
  { value: 'experience', label: t('community_cat_experience'), emoji: '✨' },
  { value: 'tip', label: t('community_cat_tip'), emoji: '💡' },
  { value: 'support', label: t('community_cat_support'), emoji: '🤍' },
])

const SORT_OPTIONS = computed<Array<{ value: CommunitySort; label: string }>>(() => [
  { value: 'latest', label: t('community_sort_latest') },
  { value: 'hot', label: t('community_sort_hot') },
  { value: 'mine', label: t('community_sort_mine') },
])

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
    error.value = t('community_load_failed')
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
  if (min < 1) return t('community_time_just_now')
  if (min < 60) return t('community_time_min_ago', { n: min })
  const hr = Math.floor(min / 60)
  if (hr < 24) return t('community_time_hr_ago', { n: hr })
  const day = Math.floor(hr / 24)
  if (day < 30) return t('community_time_day_ago', { n: day })
  return new Date(iso).toLocaleDateString('zh-TW')
}

watch([category, sort], load)
onMounted(load)
</script>

<template>
  <div class="min-h-screen pb-24 px-5 md:px-8 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto">
    <header class="pt-10 pb-4">
      <button class="text-stone-500 font-zen text-sm mb-2" @click="router.back()">{{ t('btn_back') }}</button>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('community_list_subtitle_eyebrow') ?? t('community_list_subtitle') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ t('community_list_title') }}</h1>
      <p class="font-zen text-xs text-stone-500 mt-1">
        {{ t('community_list_subtitle') }}
      </p>
    </header>

    <!-- Category tabs -->
    <div class="overflow-x-auto -mx-1 mb-2">
      <div class="flex gap-2 px-1 pb-2">
        <button
          v-for="c in CATEGORIES"
          :key="c.value"
          class="px-3 py-1.5 rounded-full text-sm whitespace-nowrap font-zen transition"
          :class="
            category === c.value
              ? 'bg-peach-300 text-white font-medium shadow-soft'
              : 'bg-white/80 text-stone-600 border border-cream-200'
          "
          @click="category = c.value"
        >
          {{ c.emoji }} {{ c.label }}
        </button>
      </div>
    </div>

    <!-- Sort -->
    <div class="flex gap-2 mb-3">
      <button
        v-for="s in SORT_OPTIONS"
        :key="s.value"
        class="text-xs px-2 py-1 rounded-md font-zen"
        :class="sort === s.value ? 'text-peach-500 font-medium' : 'text-stone-500'"
        @click="sort = s.value"
      >
        {{ s.label }}
      </button>
    </div>

    <main class="space-y-3">
      <Spinner v-if="loading" :label="t('common_loading')" />

      <div v-else-if="error" class="text-center text-stone-500 font-zen py-8">
        {{ error }}
      </div>

      <EmptyState
        v-else-if="filtered.length === 0"
        :title="sort === 'mine' ? t('community_empty_mine_title') : t('community_empty_title')"
        :subtitle="sort === 'mine' ? t('community_empty_mine_subtitle') : t('community_empty_subtitle')"
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
            <div class="flex items-center gap-2 text-xs text-stone-500 font-zen">
              <span class="font-medium font-mono text-[11px]">{{ p.is_dodo ? t('community_dodo_editor') : p.anonymous_handle }}</span>
              <span>·</span>
              <span>{{ relativeTime(p.published_at) }}</span>
              <span v-if="p.is_mine" class="ml-auto text-peach-500 font-zen">{{ t('community_mine_pill') }}</span>
            </div>
            <h3 class="font-display font-bold text-stone-700 text-base mt-1 line-clamp-2">{{ p.title }}</h3>
            <p class="text-sm text-stone-600 font-zen leading-relaxed mt-1 line-clamp-2">{{ preview(p.body) }}</p>
            <div class="flex items-center gap-4 mt-2 text-xs text-stone-500 font-zen">
              <span :aria-label="`${p.like_count} 個喜歡`">♡ {{ p.like_count }}</span>
              <span :aria-label="`${p.reply_count} 則回覆`">💬 {{ p.reply_count }}</span>
              <span
                v-if="p.has_self_harm_signal"
                class="ml-auto text-peach-500 bg-peach-50 px-2 py-0.5 rounded-full"
                >{{ t('community_cat_support') }}</span
              >
            </div>
          </div>
        </div>
      </Card>
    </main>

    <!-- FAB -->
    <button
      class="fixed bottom-6 right-5 w-14 h-14 rounded-full bg-peach-gradient text-white text-2xl shadow-soft-lg active:scale-95 transition"
      :title="canPostHint ?? t('community_fab_default')"
      :aria-label="canPostHint ?? t('community_fab_label')"
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
