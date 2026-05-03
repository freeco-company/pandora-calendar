<script setup lang="ts">
/**
 * StoryUnlockModal — 章節解鎖 fullscreen modal，swipe / tap 看完 25 行對話
 */
import { computed, defineComponent, h, ref, watch } from 'vue'
import type { StoryChapter } from '../api'
import { useTone } from '../composables/useTone'

const props = defineProps<{
  chapter: StoryChapter | null
  open: boolean
}>()
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'finished'): void
}>()

const { t } = useTone()
const idx = ref(0)

const dialog = computed(() => props.chapter?.dialog ?? [])
const total = computed(() => dialog.value.length)
const isLast = computed(() => idx.value >= total.value - 1)

watch(
  () => props.open,
  (v) => {
    if (v) idx.value = 0
  },
)

function next() {
  if (isLast.value) {
    emit('finished')
    emit('close')
    return
  }
  idx.value++
}
function prev() {
  if (idx.value > 0) idx.value--
}
function close() {
  emit('close')
}

function onKeydown(e: KeyboardEvent) {
  if (!props.open) return
  if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') next()
  if (e.key === 'ArrowLeft') prev()
  if (e.key === 'Escape') close()
}

let touchStartX = 0
function onTouchStart(e: TouchEvent) {
  touchStartX = e.touches[0].clientX
}
function onTouchEnd(e: TouchEvent) {
  const dx = e.changedTouches[0].clientX - touchStartX
  if (dx < -40) next()
  else if (dx > 40) prev()
}

const DialogBubble = defineComponent({
  props: {
    speaker: { type: String, default: 'dodo' },
    text: { type: String, required: true },
    small: { type: Boolean, default: false },
  },
  setup(p) {
    return () => {
      const isUser = p.speaker === 'user'
      const isNarration = p.speaker === 'narration'
      if (isNarration) {
        return h(
          'p',
          {
            class: [
              'text-center font-zen italic text-stone-500 px-6 py-2',
              p.small ? 'text-[11px]' : 'text-sm',
            ],
          },
          p.text,
        )
      }
      return h(
        'div',
        { class: ['flex gap-2', isUser ? 'flex-row-reverse' : 'flex-row'] },
        [
          h(
            'div',
            {
              class: [
                'shrink-0 rounded-full bg-white shadow-soft flex items-center justify-center',
                p.small ? 'w-7 h-7 text-base' : 'w-10 h-10 text-xl',
              ],
              'aria-hidden': 'true',
            },
            isUser ? '🌸' : '🐣',
          ),
          h(
            'div',
            {
              class: [
                'rounded-3xl px-3.5 py-2.5 max-w-[80%] font-zen leading-relaxed',
                p.small ? 'text-[11px]' : 'text-sm',
                isUser
                  ? 'bg-peach-500 text-white rounded-tr-md'
                  : 'bg-white text-stone-700 rounded-tl-md shadow-soft',
              ],
            },
            p.text,
          ),
        ],
      )
    }
  },
})
</script>

<template>
  <Teleport to="body">
    <transition name="story-fade">
      <div
        v-if="open && chapter"
        class="fixed inset-0 z-50 bg-gradient-to-br from-peach-100 via-sakura-50 to-cream-100 flex flex-col"
        role="dialog"
        aria-modal="true"
        @keydown="onKeydown"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
        tabindex="0"
        data-test="story-unlock-modal"
      >
        <div class="px-5 pt-12 pb-4 flex items-start justify-between">
          <div>
            <p class="font-zen text-[10px] uppercase tracking-widest text-peach-500">
              {{ t('story_chapter_eyebrow', { n: chapter.chapter }) }}
            </p>
            <p class="font-display font-bold text-peach-500 text-xl mt-0.5">
              {{ chapter.emoji }} {{ chapter.title }}
            </p>
          </div>
          <button
            @click="close"
            class="text-stone-500 hover:text-stone-700 text-2xl leading-none"
            :aria-label="t('common_close')"
            data-test="story-modal-close"
          >×</button>
        </div>

        <div class="flex-1 px-5 overflow-y-auto pb-4">
          <div v-if="dialog[idx]" class="space-y-2">
            <DialogBubble
              v-for="(d, i) in dialog.slice(0, idx)"
              :key="i"
              :speaker="d.speaker"
              :text="d.text"
              :small="true"
              class="opacity-40"
            />
            <transition name="bubble-pop" mode="out-in">
              <DialogBubble :key="idx" :speaker="dialog[idx].speaker" :text="dialog[idx].text" />
            </transition>
          </div>
          <div v-else class="text-center text-stone-400 font-zen text-sm py-12">
            {{ t('story_dialog_loading') }}
          </div>
        </div>

        <div class="px-5 pb-10 pt-3 flex items-center justify-between gap-3 bg-white/40 backdrop-blur">
          <button
            @click="prev"
            :disabled="idx === 0"
            class="font-zen text-xs text-stone-500 disabled:opacity-30 px-2 py-1"
          >
            ← {{ t('story_prev') }}
          </button>
          <div class="flex items-center gap-1.5" data-test="story-progress-dots">
            <span
              v-for="i in total"
              :key="i"
              class="rounded-full transition-all"
              :class="i - 1 === idx ? 'w-4 h-1.5 bg-peach-500' : 'w-1.5 h-1.5 bg-stone-300'"
            />
          </div>
          <button
            @click="next"
            class="bg-peach-500 text-white font-zen text-xs font-bold px-4 py-2 rounded-full active:scale-95"
            data-test="story-next-btn"
          >
            <template v-if="isLast">{{ t('story_finish_btn') }}</template>
            <template v-else>{{ t('story_next') }} →</template>
          </button>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.story-fade-enter-active,
.story-fade-leave-active {
  transition: all 0.4s ease-in-out;
}
.story-fade-enter-from,
.story-fade-leave-to {
  opacity: 0;
}
.bubble-pop-enter-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.bubble-pop-enter-from {
  opacity: 0;
  transform: translateY(8px) scale(0.96);
}
@media (prefers-reduced-motion: reduce) {
  .story-fade-enter-active,
  .story-fade-leave-active,
  .bubble-pop-enter-active {
    transition: opacity 0.2s;
  }
}
</style>
