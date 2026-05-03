<script setup lang="ts">
/**
 * DailyActionView — 妳今天的行動 + 過去 30 天 + 妳的 protocol
 *
 * 三個區塊：
 *   1. 今天的 TodayActionCard（共用元件）
 *   2. 過去 30 天 history（grouped by 7-day buckets）
 *   3. 妳的健康 protocol（free top1 / Premium 完整）
 *   4. CTA 跳 pattern report
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
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()

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
})

// group history into 7-day buckets, newest bucket first
interface Bucket {
  label: string
  rows: ActionHistoryRow[]
}

const buckets = computed<Bucket[]>(() => {
  const sorted = [...history.value].sort((a, b) => (a.for_date < b.for_date ? 1 : -1))
  const out: Bucket[] = []
  for (let i = 0; i < 30; i += 7) {
    const start = i
    const end = Math.min(i + 7, 30)
    const todayMs = new Date().setHours(0, 0, 0, 0)
    const startMs = todayMs - start * 86400000
    const endMs = todayMs - (end - 1) * 86400000
    const startStr = new Date(endMs).toISOString().slice(0, 10)
    const endStr = new Date(startMs).toISOString().slice(0, 10)
    const rows = sorted.filter((r) => r.for_date >= startStr && r.for_date <= endStr)
    if (rows.length > 0) {
      out.push({ label: `${startStr} – ${endStr}`, rows })
    }
  }
  return out
})

type ProtocolPhase = 'menstrual' | 'follicular' | 'ovulation' | 'luteal'
const phaseOrder: ProtocolPhase[] = ['menstrual', 'follicular', 'ovulation', 'luteal']

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
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-24 max-w-md md:max-w-3xl mx-auto space-y-6" data-test="daily-action-view">
    <!-- Section 1: 今天的 action -->
    <section>
      <TodayActionCard />
    </section>

    <!-- Section 2: pattern report CTA -->
    <button
      type="button"
      @click="goPatternReport"
      class="w-full rounded-3xl bg-gradient-to-br from-lavender-50 to-cream-50 px-5 py-4 shadow-soft active:scale-[0.99] transition-transform text-left flex items-center justify-between"
      data-test="action-view-pattern-cta"
    >
      <div>
        <p class="font-zen text-[11px] uppercase tracking-widest text-lavender-500/80">
          {{ t('pattern_report_heading') }}
        </p>
        <p class="font-display font-bold text-stone-700 text-base mt-0.5">
          {{ t('action_view_pattern_report') }}
        </p>
      </div>
      <span class="text-2xl text-lavender-500" aria-hidden="true">→</span>
    </button>

    <!-- Section 3: 過去 30 天 -->
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
      <Card v-else-if="!buckets.length" tone="cream">
        <p class="text-sm font-zen text-stone-500 text-center py-2">
          {{ t('action_history_empty') }}
        </p>
      </Card>
      <div v-else class="space-y-3">
        <Card v-for="b in buckets" :key="b.label" tone="cream" class="space-y-2">
          <p class="font-zen text-[11px] tracking-wider text-stone-500">{{ b.label }}</p>
          <ul class="space-y-1.5">
            <li
              v-for="row in b.rows"
              :key="row.id"
              class="flex items-start gap-2 text-sm font-zen"
              data-test="action-history-row"
            >
              <span
                class="mt-0.5 w-2 h-2 rounded-full shrink-0"
                :class="row.is_completed ? 'bg-sage-500' : 'bg-stone-300'"
                aria-hidden="true"
              />
              <span class="flex-1 min-w-0">
                <span class="text-stone-500 text-[11px] mr-2">{{ row.for_date }}</span>
                <span :class="row.is_completed ? 'text-stone-700' : 'text-stone-400'">
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

    <!-- Section 4: 妳的 protocol -->
    <section>
      <h2 class="font-display font-bold text-peach-500 text-lg">{{ t('action_protocol_title') }}</h2>
      <p class="font-zen text-[12px] text-stone-500 mb-3 mt-0.5">{{ t('action_protocol_subtitle') }}</p>

      <Spinner v-if="protocolLoading" />

      <Card v-else-if="protocolPaywall" tone="cream" class="space-y-3" data-test="action-protocol-paywall">
        <p class="font-zen text-sm text-stone-700 leading-relaxed">
          {{ t('action_protocol_premium_gate') }}
        </p>
        <button
          type="button"
          @click="goPaywall"
          class="w-full sm:w-auto px-5 py-2.5 rounded-full bg-peach-gradient text-white font-display font-bold text-sm shadow-soft active:scale-95"
        >
          {{ t('action_protocol_unlock') }}
        </button>
      </Card>

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

      <div v-else-if="protocol" class="space-y-3" data-test="action-protocol-list">
        <Card v-for="phase in phaseOrder" :key="phase" tone="cream">
          <p class="font-display font-bold text-peach-500 text-sm mb-2">{{ phaseLabel(phase) }}</p>
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
