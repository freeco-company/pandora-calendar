<script setup lang="ts">
/**
 * DailyActionView — 妳今天的行動 + 過去 30 天 + 妳的 protocol
 *
 * 三個區塊：
 *   1. 今天的 TodayActionCard（共用元件）
 *   2. pattern report CTA（妳的這個月）
 *   3. 過去 30 天 history（grouped by week，caption 用「上週」「2 週前」+ 該週 helpful/unhelpful 統計）
 *   4. 妳的健康 protocol（free 鎖頭 / Premium 解鎖）
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ActionApi,
  PaywallRequiredError,
  type ActionHistoryRow,
  type ProtocolByPhase,
} from '../api'
import TodayActionCard from '../components/TodayActionCard.vue'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Icon, { type IconName } from '../components/icons/Icon.vue'
import { useTone } from '../composables/useTone'
import { useEntitlementsStore } from '../stores/entitlements'

const { t } = useTone()
const router = useRouter()
const ent = useEntitlementsStore()

const history = ref<ActionHistoryRow[]>([])
const historyLoading = ref(false)
const historyError = ref<string | null>(null)

const protocol = ref<ProtocolByPhase | null>(null)
const protocolLoading = ref(false)
const protocolError = ref<string | null>(null)
const protocolPaywall = ref(false)

async function loadHistory() {
  historyLoading.value = true
  historyError.value = null
  try {
    const r = await ActionApi.history(30)
    history.value = r.data.data
  } catch (e: any) {
    historyError.value = e?.response?.data?.message ?? t('action_error_load')
  } finally {
    historyLoading.value = false
  }
}

async function loadProtocol() {
  protocolLoading.value = true
  protocolError.value = null
  protocolPaywall.value = false
  try {
    const r = await ActionApi.protocol()
    protocol.value = r.data.data
  } catch (e: any) {
    if (e instanceof PaywallRequiredError) {
      protocolPaywall.value = true
    } else {
      protocolError.value = e?.response?.data?.message ?? t('action_error_load')
    }
  } finally {
    protocolLoading.value = false
  }
}

onMounted(() => {
  loadHistory()
  loadProtocol()
  ent.load()
})

// group history into 7-day buckets, newest first
interface Bucket {
  label: string       // 「這週」「上週」「2 週前」「3 週前」
  total: number
  helpful: number
  unhelpful: number
  rows: ActionHistoryRow[]
}

const WEEK_LABELS = ['這週', '上週', '2 週前', '3 週前']

const buckets = computed<Bucket[]>(() => {
  const sorted = [...history.value].sort((a, b) => (a.for_date < b.for_date ? 1 : -1))
  const out: Bucket[] = []
  for (let w = 0; w < 4; w++) {
    const start = w * 7
    const end = Math.min(start + 7, 30)
    const todayMs = new Date().setHours(0, 0, 0, 0)
    const startMs = todayMs - start * 86400000
    const endMs = todayMs - (end - 1) * 86400000
    const startStr = new Date(endMs).toISOString().slice(0, 10)
    const endStr = new Date(startMs).toISOString().slice(0, 10)
    const rows = sorted.filter((r) => r.for_date >= startStr && r.for_date <= endStr)
    if (rows.length === 0) continue
    const helpful = rows.filter((r) => (r as any).feedback === 'helpful').length
    const unhelpful = rows.filter((r) => (r as any).feedback === 'unhelpful').length
    out.push({
      label: WEEK_LABELS[w] ?? `${w} 週前`,
      total: rows.length,
      helpful,
      unhelpful,
      rows,
    })
  }
  return out
})

type ProtocolPhase = 'menstrual' | 'follicular' | 'ovulation' | 'luteal'
const phaseOrder: ProtocolPhase[] = ['menstrual', 'follicular', 'ovulation', 'luteal']
const phaseIcon: Record<ProtocolPhase, IconName> = {
  menstrual: 'phase-menstrual',
  follicular: 'phase-follicular',
  ovulation: 'phase-ovulation',
  luteal: 'phase-luteal',
}

function phaseLabel(p: ProtocolPhase) {
  return t(`action_phase_${p}`)
}

function pctLabel(eff: number) {
  return `${Math.round(eff * 100)}%`
}

function goPaywall() {
  router.push('/me/premium')
}

function goPatternReport() {
  router.push('/me/pattern-report')
}

const isPremium = computed(() => ent.isPremium())
</script>

<template>
  <div class="px-5 md:px-8 pt-8 pb-24 max-w-md md:max-w-3xl mx-auto space-y-6" data-test="daily-action-view">
    <!-- Section 1：今天的 action（主視覺） -->
    <section>
      <TodayActionCard />
    </section>

    <!-- Section 2：pattern report CTA「最新一份妳的這個月」-->
    <button
      type="button"
      @click="goPatternReport"
      class="w-full rounded-3xl bg-gradient-to-br from-lavender-100 via-lavender-50 to-cream-50 px-5 py-4 shadow-soft active:scale-[0.99] transition-transform text-left flex items-center justify-between gap-3"
      data-test="action-view-pattern-cta"
    >
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <span class="shrink-0 text-2xl">📖</span>
        <div class="min-w-0">
          <p class="font-zen text-[11px] uppercase tracking-widest text-lavender-500/80">
            {{ t('pattern_report_heading') }}
          </p>
          <p class="font-display font-bold text-stone-700 text-base mt-0.5 truncate">
            {{ t('action_view_pattern_report') }}
          </p>
        </div>
      </div>
      <span class="text-xl text-lavender-500 shrink-0" aria-hidden="true">→</span>
    </button>

    <!-- Section 3：過去 30 天 history（by week + helpful 統計） -->
    <section>
      <h2 class="font-display font-bold text-peach-500 text-lg mb-3">{{ t('action_history_title') }}</h2>
      <Spinner v-if="historyLoading" />
      <Card v-else-if="historyError" tone="cream">
        <p class="text-sm font-zen text-stone-500">{{ historyError }}</p>
        <button
          type="button"
          @click="loadHistory"
          class="text-peach-500 text-sm font-zen underline mt-2"
        >
          {{ t('btn_retry') }}
        </button>
      </Card>
      <Card v-else-if="!buckets.length" tone="cream" class="text-center py-6">
        <Icon name="sprout" :size="36" animated decorative class="mb-2 mx-auto" />
        <p class="text-sm font-zen text-stone-500">
          {{ t('action_history_empty') }}
        </p>
      </Card>
      <div v-else class="space-y-3">
        <Card v-for="b in buckets" :key="b.label" tone="cream" class="space-y-2">
          <div class="flex items-baseline justify-between gap-2 mb-1">
            <p class="font-display font-bold text-stone-700 text-sm">{{ b.label }}</p>
            <p class="font-zen text-[11px] text-stone-500 flex items-center gap-2">
              <span>{{ b.total }} 件</span>
              <span v-if="b.helpful > 0" class="text-sage-500 inline-flex items-center gap-0.5"><Icon name="heart" :size="12" decorative /> {{ b.helpful }}</span>
              <span v-if="b.unhelpful > 0" class="text-stone-400 inline-flex items-center gap-0.5"><Icon name="cloud-rain" :size="12" decorative /> {{ b.unhelpful }}</span>
            </p>
          </div>
          <ul class="space-y-1.5">
            <li
              v-for="row in b.rows"
              :key="row.id"
              class="flex items-start gap-2 text-sm font-zen"
              data-test="action-history-row"
            >
              <span
                class="mt-1.5 w-2 h-2 rounded-full shrink-0"
                :class="row.is_completed ? 'bg-sage-500' : 'bg-stone-300'"
                aria-hidden="true"
              />
              <span class="flex-1 min-w-0">
                <span :class="row.is_completed ? 'text-stone-700' : 'text-stone-400 line-through'">
                  {{ row.title }}
                </span>
              </span>
              <span
                class="text-[10px] font-zen px-2 py-0.5 rounded-full shrink-0"
                :class="row.is_completed ? 'bg-sage-50 text-sage-500' : 'bg-stone-50 text-stone-400'"
              >
                {{ row.is_completed ? t('action_history_completed') : t('action_history_not_done') }}
              </span>
            </li>
          </ul>
        </Card>
      </div>
    </section>

    <!-- Section 4：妳的 protocol — peach gradient CTA card -->
    <section>
      <div class="flex items-baseline justify-between mb-1">
        <h2 class="font-display font-bold text-peach-500 text-lg">{{ t('action_protocol_title') }}</h2>
        <span v-if="isPremium" class="font-zen text-[10px] text-sage-500 bg-sage-50 px-2 py-0.5 rounded-full">
          <Icon name="unlock" :size="12" decorative class="inline-block align-middle mr-0.5" />已解鎖
        </span>
      </div>
      <p class="font-zen text-[12px] text-stone-500 mb-3">{{ t('action_protocol_subtitle') }}</p>

      <Spinner v-if="protocolLoading" />

      <!-- Free user：peach gradient + 鎖頭 CTA card -->
      <button
        v-else-if="protocolPaywall"
        type="button"
        @click="goPaywall"
        class="w-full text-left rounded-3xl bg-gradient-to-br from-peach-300 via-peach-400 to-sakura-400 p-5 shadow-soft active:scale-[0.99] transition-transform overflow-hidden relative"
        data-test="action-protocol-paywall"
      >
        <div class="absolute -bottom-4 -right-4 opacity-10 leading-none select-none" aria-hidden="true"><Icon name="lock" :size="140" decorative /></div>
        <div class="relative space-y-2 text-white">
          <div class="flex items-center gap-2">
            <Icon name="lock" :size="24" decorative />
            <span class="font-zen text-[11px] tracking-widest uppercase opacity-90">Premium</span>
          </div>
          <p class="font-display font-black text-lg leading-snug">
            {{ t('action_protocol_premium_gate') }}
          </p>
          <span class="inline-block mt-2 px-4 py-2 rounded-full bg-white text-peach-500 font-display font-bold text-sm">
            {{ t('action_protocol_unlock') }} →
          </span>
        </div>
      </button>

      <Card v-else-if="protocolError" tone="cream">
        <p class="text-sm font-zen text-stone-500">{{ protocolError }}</p>
        <button
          type="button"
          @click="loadProtocol"
          class="text-peach-500 text-sm font-zen underline mt-2"
        >
          {{ t('btn_retry') }}
        </button>
      </Card>

      <!-- Premium：完整 protocol 4 phase -->
      <div v-else-if="protocol" class="space-y-3" data-test="action-protocol-list">
        <Card v-for="phase in phaseOrder" :key="phase" tone="cream">
          <div class="flex items-center gap-2 mb-2">
            <Icon :name="phaseIcon[phase]" :size="22" animated decorative />
            <p class="font-display font-bold text-peach-500 text-sm">{{ phaseLabel(phase) }}</p>
          </div>
          <ul v-if="protocol[phase]?.length" class="space-y-1.5">
            <li
              v-for="entry in protocol[phase]"
              :key="entry.action_key"
              class="flex items-start justify-between gap-2 text-sm font-zen"
            >
              <span class="text-stone-700 flex-1 min-w-0">{{ entry.title }}</span>
              <span class="text-[11px] text-sage-500 font-bold shrink-0">
                {{ pctLabel(entry.effectiveness) }}
                <span class="text-stone-400 font-normal ml-1">· {{ entry.sample_size }} {{ t('action_protocol_sample') }}</span>
              </span>
            </li>
          </ul>
          <p v-else class="text-[12px] font-zen text-stone-400">—</p>
        </Card>
      </div>
    </section>
  </div>
</template>
