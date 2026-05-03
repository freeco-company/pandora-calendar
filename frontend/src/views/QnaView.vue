<script setup lang="ts">
/**
 * QnaView — 朵朵開放問答 (P4 Premium gate 3/day · Premium 無限)
 *
 * 三層守門 UI 對應：
 *   - safety_flag === 'redline_self_harm' → 紅色 1925 banner，朵朵 reply 帶 hotline link
 *   - safety_flag === 'redline_compliance' → 顯示一般 safe response（不特別 hint）
 *   - quotaExceeded → 跳 paywall hint
 *
 * NOTE: 所有 emoji 走 <Icon /> wave 10 元件（dodo / heart / journal / clock）
 */
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import Icon from '../components/icons/Icon.vue'
import { useTone } from '../composables/useTone'
import { useSfx } from '../lib/sound'
import { useQna } from '../composables/useQna'

const { t } = useTone()
const router = useRouter()
const sfx = useSfx()

const qna = useQna()
const input = ref('')
const showQuotaModal = ref(false)
const scrollAnchor = ref<HTMLElement | null>(null)

const examples = computed(() => [
  t('qna_example_1'),
  t('qna_example_2'),
  t('qna_example_3'),
  t('qna_example_4'),
  t('qna_example_5'),
])

const remainingLabel = computed(() => {
  if (qna.isPremium.value) return t('qna_remaining_unlimited')
  if (qna.remaining.value === null) return ''
  return t('qna_remaining_today').replace('{n}', String(qna.remaining.value))
})

async function send() {
  const q = input.value.trim()
  if (!q || qna.sending.value) return
  sfx.play('ui_tap')
  input.value = ''
  const res = await qna.ask(q)
  if (res.quotaExceeded) {
    showQuotaModal.value = true
    input.value = q
  }
  await nextTick()
  scrollToBottom()
}

function pickExample(text: string) {
  input.value = text
}

function scrollToBottom() {
  scrollAnchor.value?.scrollIntoView({ behavior: 'smooth', block: 'end' })
}

watch(() => qna.turns.value.length, () => nextTick(scrollToBottom))

onMounted(async () => {
  await qna.load()
  await nextTick()
  scrollToBottom()
})

function goPaywall() {
  router.push('/me/premium')
}

function goInsight(id: number) {
  // daily_insight detail 頁尚未獨立路由 — 回 /calendar 並讓 user 看當日衛教
  // wave 11 補：/insights/:id deep link
  router.push({ path: '/calendar', query: { insight: String(id) } })
}
</script>

<template>
  <div class="min-h-screen pb-32 bg-cream-50" data-test="qna-view">
    <!-- Header -->
    <header class="sticky top-0 z-20 bg-cream-50/95 backdrop-blur px-5 py-3 border-b border-cream-200">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="text-stone-500 active:scale-95"
          @click="router.back()"
          :aria-label="t('common_back')"
        >←</button>
        <Icon name="dodo" :size="28" animated decorative />
        <div class="flex-1">
          <h1 class="font-display font-bold text-peach-500 text-base">{{ t('qna_title') }}</h1>
          <p class="font-zen text-[11px] text-stone-500">{{ remainingLabel }}</p>
        </div>
      </div>
    </header>

    <!-- Conversation timeline -->
    <main class="px-4 pt-4 max-w-2xl mx-auto" data-test="qna-timeline">
      <Spinner v-if="qna.loading.value" />

      <!-- Empty state with examples -->
      <div v-else-if="qna.turns.value.length === 0" class="text-center pt-6">
        <div class="flex justify-center mb-3">
          <Icon name="dodo" :size="56" animated decorative />
        </div>
        <p class="font-display text-peach-500 text-lg mb-1">{{ t('qna_empty_greeting') }}</p>
        <p class="font-zen text-stone-500 text-sm mb-6">{{ t('qna_input_placeholder') }}</p>

        <p class="font-zen text-[11px] text-stone-400 mb-2">{{ t('qna_examples_label') }}</p>
        <div class="flex flex-col gap-2">
          <button
            v-for="ex in examples"
            :key="ex"
            type="button"
            class="text-left bg-white rounded-2xl px-4 py-3 shadow-soft text-sm font-zen text-stone-700 hover:bg-peach-50 active:scale-[0.99] transition"
            data-test="qna-example"
            @click="pickExample(ex)"
          >
            <Icon name="sparkle" :size="14" :decorative="true" class="mr-1 text-peach-400" />
            {{ ex }}
          </button>
        </div>
      </div>

      <!-- Chat turns -->
      <div v-else class="flex flex-col gap-4">
        <template v-for="turn in qna.turns.value" :key="turn.id">
          <!-- user bubble -->
          <div class="flex justify-end">
            <div class="max-w-[80%] bg-peach-500 text-white rounded-3xl rounded-br-md px-4 py-2.5 font-zen text-sm">
              {{ turn.question }}
            </div>
          </div>

          <!-- self-harm safety banner -->
          <div
            v-if="turn.safety_flag === 'redline_self_harm'"
            class="bg-red-50 border-2 border-red-300 rounded-3xl px-4 py-3"
            data-test="qna-self-harm-banner"
          >
            <p class="font-display font-bold text-red-700 text-base mb-1">{{ t('qna_blocked_safety_title') }}</p>
            <p class="font-zen text-red-700 text-sm leading-relaxed mb-2">{{ t('qna_blocked_safety_body') }}</p>
            <a href="tel:1925" class="inline-block bg-red-600 text-white rounded-full px-4 py-2 font-display text-sm">
              {{ t('qna_hotline_btn') }}
            </a>
          </div>

          <!-- dodo bubble -->
          <div class="flex items-start gap-2" data-test="qna-dodo-bubble">
            <Icon name="dodo" :size="32" animated decorative class="shrink-0 mt-1" />
            <div class="max-w-[80%] flex-1">
              <div class="bg-white rounded-3xl rounded-bl-md px-4 py-3 shadow-soft">
                <Spinner v-if="turn.pending" size="sm" />
                <p v-else class="font-zen text-stone-700 text-sm leading-relaxed whitespace-pre-line">{{ turn.answer }}</p>
              </div>

              <!-- sources -->
              <div v-if="turn.sources.length > 0 && !turn.pending" class="flex flex-wrap gap-1.5 mt-2">
                <button
                  v-for="sid in turn.sources"
                  :key="sid"
                  type="button"
                  class="inline-flex items-center gap-1 bg-cream-100 hover:bg-peach-50 rounded-full px-3 py-1 text-[11px] font-zen text-peach-500"
                  data-test="qna-source-chip"
                  @click="goInsight(sid)"
                >
                  <Icon name="journal" :size="12" decorative />
                  {{ t('qna_view_full_article') }}
                </button>
              </div>

              <!-- delete own -->
              <div v-if="!turn.pending && turn.id > 0" class="mt-1.5">
                <button
                  type="button"
                  class="text-[10px] text-stone-400 hover:text-stone-600"
                  @click="qna.remove(turn.id)"
                  data-test="qna-delete-turn"
                >{{ t('qna_delete_turn') }}</button>
              </div>
            </div>
          </div>
        </template>
        <div ref="scrollAnchor"></div>
      </div>
    </main>

    <!-- Disclaimer banner（永遠在底，sticky 在 input 上方） -->
    <div class="fixed left-0 right-0 bottom-[88px] px-4 pointer-events-none z-10">
      <div class="max-w-2xl mx-auto bg-cream-100/95 backdrop-blur rounded-2xl px-3 py-2 shadow-soft pointer-events-auto">
        <p class="font-zen text-[11px] text-stone-600 leading-snug flex items-start gap-1.5">
          <Icon name="heart" :size="12" decorative class="text-peach-400 shrink-0 mt-0.5" />
          <span><strong class="text-peach-600">{{ t('qna_disclaimer_strong') }}</strong> {{ t('qna_disclaimer_body') }}</span>
        </p>
      </div>
    </div>

    <!-- Input -->
    <footer class="fixed bottom-0 left-0 right-0 bg-cream-50 border-t border-cream-200 px-3 py-3 z-20">
      <div class="max-w-2xl mx-auto flex gap-2 items-end">
        <textarea
          v-model="input"
          :placeholder="t('qna_input_placeholder')"
          rows="1"
          maxlength="500"
          class="flex-1 resize-none bg-white border border-cream-200 rounded-3xl px-4 py-2.5 font-zen text-sm text-stone-700 focus:outline-none focus:border-peach-300"
          data-test="qna-input"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          tone="primary"
          :disabled="qna.sending.value || !input.trim()"
          data-test="qna-send"
          @click="send"
        >
          {{ qna.sending.value ? t('qna_sending') : t('qna_send') }}
        </Button>
      </div>
    </footer>

    <!-- Quota modal -->
    <Transition name="ach">
      <div
        v-if="showQuotaModal"
        class="fixed inset-0 bg-stone-900/40 backdrop-blur-sm flex items-center justify-center z-30 px-6"
        data-test="qna-quota-modal"
        @click.self="showQuotaModal = false"
      >
        <Card class="max-w-sm w-full text-center">
          <div class="flex justify-center mb-3">
            <Icon name="gem" :size="56" animated decorative />
          </div>
          <h2 class="font-display font-bold text-peach-500 text-lg mb-1">{{ t('qna_quota_title') }}</h2>
          <p class="font-zen text-stone-600 text-sm mb-4">{{ t('qna_premium_gate') }}</p>
          <div class="flex flex-col gap-2">
            <Button tone="primary" @click="goPaywall">{{ t('qna_quota_upgrade_btn') }}</Button>
            <button class="font-zen text-sm text-stone-400" @click="showQuotaModal = false">{{ t('qna_quota_cancel_btn') }}</button>
          </div>
        </Card>
      </div>
    </Transition>
  </div>
</template>
