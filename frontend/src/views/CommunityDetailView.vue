<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  CommunityApi,
  type CommunityPostDetail,
  type CommunityReply,
  type ReportReason,
} from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const route = useRoute()
const router = useRouter()
const postId = computed(() => Number(route.params.id))

const loading = ref(true)
const error = ref<string | null>(null)
const post = ref<CommunityPostDetail | null>(null)
const replyBody = ref('')
const submitting = ref(false)
const replyError = ref<string | null>(null)
const replyHint = ref<string | null>(null)
const replyGateHint = ref<string | null>(null)

const REPORT_REASONS = computed<Array<{ value: ReportReason; label: string }>>(() => [
  { value: 'spam', label: t('community_detail_report_reason_spam') },
  { value: 'harassment', label: t('community_detail_report_reason_harassment') },
  { value: 'medical_advice', label: t('community_detail_report_reason_medical_advice') },
  { value: 'commercial', label: t('community_detail_report_reason_commercial') },
  { value: 'self_harm', label: t('community_detail_report_reason_self_harm') },
  { value: 'other', label: t('community_detail_report_reason_other') },
])
const showReportFor = ref<{ type: 'post' | 'reply'; id: number } | null>(null)
const reportReason = ref<ReportReason>('spam')
const reportMessage = ref('')

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await CommunityApi.show(postId.value)
    post.value = data.data
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number } }
    error.value = ax.response?.status === 404 ? t('community_detail_post_gone') : t('community_detail_load_failed')
  } finally {
    loading.value = false
  }
}

async function togglePostLike() {
  if (!post.value) return
  try {
    const { data } = await CommunityApi.likePost(post.value.id)
    post.value.liked = data.data.liked
    post.value.like_count = data.data.like_count
  } catch {
    /* ignore */
  }
}

async function toggleReplyLike(reply: CommunityReply) {
  try {
    const { data } = await CommunityApi.likeReply(reply.id)
    reply.liked = data.data.liked
    reply.like_count = data.data.like_count
  } catch {
    /* ignore */
  }
}

async function submitReply() {
  if (!post.value) return
  if (!replyBody.value.trim()) return
  submitting.value = true
  replyError.value = null
  replyHint.value = null
  replyGateHint.value = null
  try {
    const { data } = await CommunityApi.reply(post.value.id, replyBody.value.trim())
    post.value.replies.push(data.data)
    post.value.reply_count++
    replyBody.value = ''
  } catch (e: unknown) {
    const ax = e as {
      response?: {
        status?: number
        data?: { message?: string; gate?: { hint?: string }; moderation?: { hint?: string } }
      }
    }
    if (ax.response?.status === 422) {
      const d = ax.response.data
      if (d?.gate?.hint) replyGateHint.value = d.gate.hint
      else if (d?.moderation?.hint) replyHint.value = d.moderation.hint
      else replyError.value = d?.message ?? t('community_detail_reply_blocked')
    } else {
      replyError.value = t('community_detail_reply_failed')
    }
  } finally {
    submitting.value = false
  }
}

async function deletePost() {
  if (!post.value) return
  if (!confirm(t('community_detail_delete_confirm'))) return
  try {
    await CommunityApi.remove(post.value.id)
    router.replace('/community')
  } catch {
    alert(t('community_detail_delete_failed'))
  }
}

async function submitReport() {
  if (!showReportFor.value) return
  try {
    await CommunityApi.report({
      target_type: showReportFor.value.type,
      target_id: showReportFor.value.id,
      reason: reportReason.value,
      message: reportMessage.value.trim() || undefined,
    })
    showReportFor.value = null
    reportMessage.value = ''
    alert(t('community_detail_report_thanks'))
  } catch {
    alert(t('community_detail_report_failed'))
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen pb-32 px-5 md:px-8 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto">
    <header class="pt-10 pb-3 flex items-center justify-between">
      <Button variant="ghost" size="sm" @click="router.back()">{{ t('community_detail_back') }}</Button>
      <h1 class="font-display text-base font-bold text-peach-500">{{ t('community_detail_title') }}</h1>
      <span class="w-12"></span>
    </header>

    <Spinner v-if="loading" :label="t('common_loading')" />

    <div v-else-if="error" class="text-center text-stone-500 font-zen py-8">{{ error }}</div>

    <main v-else-if="post" class="space-y-3">
      <!-- Self-harm hotline banner — sticky at top -->
      <div
        v-if="post.has_self_harm_signal"
        class="bg-sakura-50 border-l-4 border-sakura-400 rounded-2xl p-4 text-sm text-stone-700 font-zen"
        role="region"
        aria-label="緊急求助專線"
      >
        <div class="font-display font-bold mb-1 text-sakura-500 flex items-center gap-2">
          <span>🤍</span><span>{{ t('community_detail_self_harm_title') }}</span>
        </div>
        <div class="text-xs text-stone-700 leading-relaxed">
          ・安心專線
          <a href="tel:1925" class="underline text-peach-500">1925</a> ・生命線
          <a href="tel:1995" class="underline text-peach-500">1995</a>
          ／
          <a
            href="https://1925.mohw.gov.tw/"
            target="_blank"
            rel="noopener"
            class="underline text-peach-500"
            >安心專線官方資源</a
          >
        </div>
      </div>

      <Card tone="cream">
        <div class="flex items-center gap-2 text-xs text-stone-500 font-zen mb-2">
          <span class="font-medium font-mono text-[11px]">{{ post.is_dodo ? t('community_dodo_editor') : post.anonymous_handle }}</span>
          <span>·</span>
          <span>{{ post.category }}</span>
          <span v-if="post.is_mine" class="ml-auto text-peach-500">{{ t('community_mine_pill') }}</span>
        </div>
        <h2 class="font-display text-lg font-bold text-stone-700 leading-snug">{{ post.title }}</h2>
        <p class="mt-2 text-stone-700 whitespace-pre-wrap leading-relaxed font-zen text-sm">{{ post.body }}</p>

        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-cream-200 text-sm font-zen">
          <button
            class="flex items-center gap-1"
            :aria-label="post.liked ? `取消喜歡，目前 ${post.like_count} 個喜歡` : `喜歡，目前 ${post.like_count} 個喜歡`"
            :aria-pressed="post.liked"
            @click="togglePostLike"
          >
            <span :class="post.liked ? 'text-sakura-500' : 'text-stone-500'">
              {{ post.liked ? '♥' : '♡' }} {{ post.like_count }}
            </span>
          </button>
          <span class="text-stone-500">💬 {{ post.reply_count }}</span>
          <button
            v-if="!post.is_dodo && !post.is_mine"
            class="ml-auto text-xs text-stone-500"
            @click="showReportFor = { type: 'post', id: post.id }"
          >
            {{ t('community_detail_report_btn') }}
          </button>
          <button
            v-if="post.is_mine"
            class="ml-auto text-xs text-stone-500 hover:text-sakura-500"
            @click="deletePost"
          >
            {{ t('community_detail_delete_btn') }}
          </button>
        </div>
      </Card>

      <!-- Replies -->
      <h3 class="font-display text-sm font-bold text-peach-500 px-1 pt-2">{{ t('community_detail_replies_count', { n: post.reply_count }) }}</h3>

      <Card
        v-for="r in post.replies"
        :key="r.id"
        :tone="r.is_dodo ? 'sakura' : 'plain'"
        class="border"
        :class="r.is_dodo ? 'border-sakura-200' : 'border-transparent'"
      >
        <div class="flex items-center gap-2 text-xs text-stone-500 font-zen mb-1">
          <span class="font-medium font-mono text-[11px]">
            {{ r.is_dodo ? '🌸 ' + t('community_dodo_editor') : r.anonymous_handle }}
          </span>
          <span v-if="r.is_mine" class="ml-auto text-peach-500">{{ t('community_mine_pill') }}</span>
        </div>
        <p class="text-sm text-stone-700 whitespace-pre-wrap leading-relaxed font-zen">{{ r.body }}</p>
        <div class="flex items-center gap-3 mt-2 text-xs font-zen">
          <button
            :aria-label="r.liked ? `取消喜歡，目前 ${r.like_count} 個喜歡` : `喜歡，目前 ${r.like_count} 個喜歡`"
            :aria-pressed="r.liked"
            @click="toggleReplyLike(r)"
          >
            <span :class="r.liked ? 'text-sakura-500' : 'text-stone-500'">
              {{ r.liked ? '♥' : '♡' }} {{ r.like_count }}
            </span>
          </button>
          <button
            v-if="!r.is_dodo && !r.is_mine"
            class="ml-auto text-stone-500"
            @click="showReportFor = { type: 'reply', id: r.id }"
          >
            {{ t('community_detail_report_btn') }}
          </button>
        </div>
      </Card>
    </main>

    <!-- Reply composer (fixed bottom) -->
    <div
      v-if="post && !post.is_mine"
      class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-cream-200 p-3"
      style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.75rem)"
    >
      <div class="max-w-md md:max-w-2xl mx-auto">
        <div v-if="replyGateHint" class="text-xs text-peach-500 font-zen mb-2">{{ replyGateHint }}</div>
        <div v-else-if="replyHint" class="text-xs text-peach-500 font-zen mb-2">{{ replyHint }}</div>
        <div v-else-if="replyError" class="text-xs text-sakura-500 font-zen mb-2">{{ replyError }}</div>
        <div class="flex gap-2">
          <label for="community-reply" class="sr-only">{{ t('community_detail_reply_aria') }}</label>
          <textarea
            id="community-reply"
            v-model="replyBody"
            rows="1"
            maxlength="500"
            :placeholder="t('community_detail_reply_placeholder')"
            :aria-label="t('community_detail_reply_aria')"
            class="flex-1 px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 text-sm resize-none font-zen"
          />
          <Button
            variant="primary"
            size="sm"
            :loading="submitting"
            :disabled="!replyBody.trim()"
            @click="submitReply"
            >{{ t('community_detail_reply_send') }}</Button
          >
        </div>
      </div>
    </div>

    <!-- Report modal -->
    <div
      v-if="showReportFor"
      class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50 animate-fadein"
      role="dialog"
      aria-modal="true"
      aria-labelledby="report-modal-title"
      @click.self="showReportFor = null"
    >
      <div class="bg-white rounded-t-3xl sm:rounded-3xl w-full max-w-md p-5 space-y-3 shadow-soft-lg">
        <h3 id="report-modal-title" class="font-display font-bold text-peach-500 text-base">{{ t('community_detail_report_modal_title') }}</h3>
        <p class="text-xs text-stone-500 font-zen">{{ t('community_detail_report_modal_blurb') }}</p>
        <div class="space-y-2">
          <label v-for="r in REPORT_REASONS" :key="r.value" class="flex items-center gap-2 text-sm font-zen text-stone-700">
            <input v-model="reportReason" type="radio" :value="r.value" class="accent-peach-400" />
            <span>{{ r.label }}</span>
          </label>
        </div>
        <label for="report-message" class="sr-only">{{ t('community_detail_report_message_aria') }}</label>
        <textarea
          id="report-message"
          v-model="reportMessage"
          rows="2"
          maxlength="500"
          :placeholder="t('community_detail_report_message_placeholder')"
          :aria-label="t('community_detail_report_message_aria')"
          class="w-full px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50/40 focus:outline-none focus:border-peach-300 text-sm resize-none font-zen"
        />
        <div class="flex gap-2">
          <Button variant="ghost" size="sm" full @click="showReportFor = null">{{ t('common_cancel') }}</Button>
          <Button variant="primary" size="sm" full @click="submitReport">{{ t('community_detail_reply_send') }}</Button>
        </div>
      </div>
    </div>
  </div>
</template>
