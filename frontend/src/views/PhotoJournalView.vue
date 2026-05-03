<script setup lang="ts">
/**
 * P5 進度照 photo journal — 隱私優先 view。
 *
 * 紅線（hard）:
 *   1. 大字隱私說明在頁首必出現（妳的照片只在妳的裝置上）
 *   2. 雲端同步是 Premium opt-in、預設 OFF
 *   3. 不顯示任何「分享 / 公開連結」按鈕
 *   4. compare mode 只是 side-by-side device 顯示，不傳送
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { usePhotoJournal } from '../composables/usePhotoJournal'
import { useTone } from '../composables/useTone'
import { useEntitlementsStore } from '../stores/entitlements'
import { CalendarApi, type PhotoJournalTag, type PhotoJournalEntry } from '../api'

const { t } = useTone()
const router = useRouter()
const ent = useEntitlementsStore()
const journal = usePhotoJournal()

const month = ref<string>(new Date().toISOString().slice(0, 7))
const tag = ref<PhotoJournalTag>('face')
const noteDraft = ref('')
const selected = ref<PhotoJournalEntry | null>(null)
const compareIds = ref<number[]>([])
const compareMode = ref(false)
const message = ref<string | null>(null)
const blobCache = ref<Record<number, string>>({}) // entry id → object URL

// 取當下 BodyRhythm 寫進 entry（避免後端漂移）
const currentPhase = ref<string | null>(null)
const currentCycleDay = ref<number | null>(null)

async function refreshPhase() {
  try {
    const r = await CalendarApi.bodyRhythm()
    currentPhase.value = r.data.data?.phase ?? null
    currentCycleDay.value = r.data.data?.cycle_day ?? null
  } catch {
    /* offline ok */
  }
}

const filteredEntries = computed(() =>
  journal.entries.value.filter((e) => e.tag === tag.value),
)

// 月曆 grid mini view：依 captured_on 對齊到該月每一天
const calendarGrid = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  const lastDay = new Date(y, m, 0).getDate()
  const firstWeekday = new Date(y, m - 1, 1).getDay() // 0=Sun
  const cells: Array<{ day: number | null; entry: PhotoJournalEntry | null }> = []
  for (let i = 0; i < firstWeekday; i++) cells.push({ day: null, entry: null })
  for (let d = 1; d <= lastDay; d++) {
    const ds = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const e = filteredEntries.value.find((x) => x.captured_on === ds) ?? null
    cells.push({ day: d, entry: e })
  }
  return cells
})

async function loadMonthBlobs() {
  for (const e of filteredEntries.value) {
    if (blobCache.value[e.id]) continue
    const blob = await journal.getDeviceBlob(e.id)
    if (blob) blobCache.value[e.id] = URL.createObjectURL(blob)
  }
}

watch(
  () => journal.entries.value,
  () => loadMonthBlobs(),
  { deep: true },
)

async function loadMonth(m: string) {
  month.value = m
  await journal.loadMonth(m)
  // 釋放上月 blob URL
  Object.values(blobCache.value).forEach((u) => URL.revokeObjectURL(u))
  blobCache.value = {}
  await loadMonthBlobs()
}

function shiftMonth(delta: number) {
  const [y, m] = month.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  loadMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
}

async function captureToday() {
  message.value = null
  try {
    const photo = tag.value === 'note' ? null : await journal.takePhoto()
    if (tag.value !== 'note' && !photo) return
    const todayStr = new Date().toISOString().slice(0, 10)
    const entry = await journal.recordEntry({
      tag: tag.value,
      captured_on: todayStr,
      phase: currentPhase.value,
      cycle_day: currentCycleDay.value,
      note: noteDraft.value || null,
      photo,
    })
    noteDraft.value = ''
    if (photo) {
      blobCache.value[entry.id] = URL.createObjectURL(photo.blob)
    }
    message.value = t('photo_journal_saved')
  } catch (e: any) {
    message.value = t('photo_journal_save_failed')
  }
}

async function pickFromAlbum() {
  message.value = null
  const photo = await journal.pickFromAlbum()
  if (!photo) return
  const todayStr = new Date().toISOString().slice(0, 10)
  const entry = await journal.recordEntry({
    tag: tag.value,
    captured_on: todayStr,
    phase: currentPhase.value,
    cycle_day: currentCycleDay.value,
    note: noteDraft.value || null,
    photo,
  })
  noteDraft.value = ''
  blobCache.value[entry.id] = URL.createObjectURL(photo.blob)
  message.value = t('photo_journal_saved')
}

function openDetail(entry: PhotoJournalEntry) {
  if (compareMode.value) {
    toggleCompare(entry.id)
    return
  }
  selected.value = entry
}

function toggleCompare(id: number) {
  if (compareIds.value.includes(id)) {
    compareIds.value = compareIds.value.filter((x) => x !== id)
  } else if (compareIds.value.length < 2) {
    compareIds.value = [...compareIds.value, id]
  }
}

async function syncSelectedToCloud() {
  if (!selected.value) return
  if (!ent.isPremium()) {
    router.push('/me/premium')
    return
  }
  const r = await journal.syncToCloud(selected.value.id)
  if (r) {
    selected.value = r
    message.value = t('photo_journal_cloud_synced')
  } else if (journal.error.value === 'premium_required') {
    router.push('/me/premium')
  }
}

async function unsyncSelected() {
  if (!selected.value) return
  await journal.unsyncCloud(selected.value.id)
  selected.value = { ...selected.value, cloud_synced: false, cloud_url: null }
  message.value = t('photo_journal_cloud_unsynced')
}

async function deleteSelected() {
  if (!selected.value) return
  if (!confirm(t('photo_journal_delete_confirm'))) return
  await journal.removeEntry(selected.value.id)
  if (blobCache.value[selected.value.id]) {
    URL.revokeObjectURL(blobCache.value[selected.value.id])
    delete blobCache.value[selected.value.id]
  }
  selected.value = null
}

const compareEntries = computed(() =>
  compareIds.value
    .map((id) => journal.entries.value.find((e) => e.id === id))
    .filter((e): e is PhotoJournalEntry => !!e),
)

onMounted(async () => {
  ent.load()
  await refreshPhase()
  await loadMonth(month.value)
})
</script>

<template>
  <div class="px-5 pt-8 pb-12 max-w-md md:max-w-2xl mx-auto space-y-5">
    <!-- ============ 大字隱私說明（紅線：必出現在頁首）============ -->
    <Card tone="cream" class="text-center space-y-2" data-test="privacy-banner">
      <p class="font-display font-bold text-peach-500 text-lg">
        {{ t('photo_journal_privacy_title') }}
      </p>
      <p class="font-zen text-[13px] text-stone-600 leading-relaxed">
        {{ t('photo_journal_privacy_blurb') }}
      </p>
      <p class="font-zen text-[11px] text-stone-500">
        {{ t('photo_journal_privacy_premium_hint') }}
      </p>
    </Card>

    <!-- 月份切換 + tag -->
    <div class="flex items-center justify-between">
      <button @click="shiftMonth(-1)" class="px-3 py-1 text-peach-500 font-zen">←</button>
      <p class="font-display text-lg font-bold text-peach-500">{{ month }}</p>
      <button @click="shiftMonth(1)" class="px-3 py-1 text-peach-500 font-zen">→</button>
    </div>

    <div class="flex gap-2 justify-center" data-test="tag-switcher">
      <button
        v-for="opt in (['face', 'body', 'note'] as PhotoJournalTag[])"
        :key="opt"
        @click="tag = opt"
        :data-test="`tag-${opt}`"
        class="px-4 py-1.5 rounded-full text-xs font-zen transition-colors"
        :class="tag === opt ? 'bg-peach-400 text-white' : 'bg-white text-stone-500 border border-cream-200'"
      >
        {{ t(`photo_journal_tag_${opt}`) }}
      </button>
    </div>

    <!-- 月曆 grid mini -->
    <Card tone="plain" class="space-y-3">
      <div class="grid grid-cols-7 gap-1.5" data-test="month-grid">
        <div
          v-for="(cell, i) in calendarGrid"
          :key="i"
          class="aspect-square rounded-lg overflow-hidden relative"
          :class="cell.entry ? 'cursor-pointer ring-1 ring-peach-200' : 'bg-cream-50'"
          @click="cell.entry && openDetail(cell.entry)"
        >
          <template v-if="cell.entry && blobCache[cell.entry.id]">
            <img
              :src="blobCache[cell.entry.id]"
              :alt="`day-${cell.day}`"
              class="absolute inset-0 w-full h-full object-cover"
              :class="compareIds.includes(cell.entry.id) ? 'ring-2 ring-peach-400' : ''"
            />
          </template>
          <template v-else-if="cell.entry">
            <div class="absolute inset-0 flex items-center justify-center text-[18px]">
              📝
            </div>
          </template>
          <span
            v-if="cell.day"
            class="absolute top-0.5 left-1 text-[9px] font-zen text-stone-400"
            :class="cell.entry ? 'text-white drop-shadow' : ''"
          >
            {{ cell.day }}
          </span>
        </div>
      </div>

      <div class="flex justify-between items-center pt-2 border-t border-cream-100">
        <button
          @click="compareMode = !compareMode; compareIds = []"
          data-test="compare-toggle"
          class="text-xs font-zen text-peach-500"
        >
          {{ compareMode ? t('photo_journal_compare_off') : t('photo_journal_compare_on') }}
        </button>
        <p class="text-[11px] font-zen text-stone-400">
          {{ t('photo_journal_count', { n: filteredEntries.length }) }}
        </p>
      </div>
    </Card>

    <!-- compare mode 顯示 -->
    <Card v-if="compareMode && compareEntries.length === 2" tone="plain" data-test="compare-view">
      <p class="font-display text-sm text-peach-500 mb-2">{{ t('photo_journal_compare_title') }}</p>
      <div class="grid grid-cols-2 gap-2">
        <div v-for="e in compareEntries" :key="e.id" class="space-y-1">
          <img
            v-if="blobCache[e.id]"
            :src="blobCache[e.id]"
            class="w-full aspect-square object-cover rounded-xl"
            :alt="e.captured_on"
          />
          <p class="text-[11px] font-zen text-stone-500 text-center">
            {{ e.captured_on }} · {{ e.phase ?? '—' }}
          </p>
        </div>
      </div>
    </Card>

    <!-- 拍今天 + note 草稿 -->
    <Card tone="plain" class="space-y-3">
      <p class="font-display text-sm text-peach-500">
        {{ t('photo_journal_record_today') }}
      </p>
      <textarea
        v-model="noteDraft"
        :placeholder="t('photo_journal_note_placeholder')"
        maxlength="500"
        rows="2"
        data-test="note-input"
        class="w-full px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50 text-sm font-zen focus:outline-none focus:border-peach-300"
      />
      <p class="text-[10px] text-stone-400 font-zen text-right">{{ noteDraft.length }}/500</p>
      <div class="flex gap-2">
        <Button
          v-if="tag !== 'note'"
          full
          size="sm"
          data-test="capture-fab"
          @click="captureToday"
        >
          📷 {{ t('photo_journal_take_photo') }}
        </Button>
        <Button
          v-if="tag !== 'note'"
          variant="secondary"
          size="sm"
          data-test="pick-album"
          @click="pickFromAlbum"
        >
          🖼
        </Button>
        <Button
          v-if="tag === 'note'"
          full
          size="sm"
          :disabled="!noteDraft.trim()"
          data-test="save-note"
          @click="captureToday"
        >
          💛 {{ t('photo_journal_save_note') }}
        </Button>
      </div>
      <p v-if="message" class="text-xs font-zen text-stone-500" data-test="status-message">{{ message }}</p>
    </Card>

    <!-- detail modal -->
    <div
      v-if="selected"
      class="fixed inset-0 bg-black/60 z-50 flex items-end justify-center"
      data-test="detail-modal"
      @click.self="selected = null"
    >
      <div class="bg-white rounded-t-3xl max-w-md w-full p-5 space-y-3 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center">
          <p class="font-display text-base text-peach-500">
            {{ selected.captured_on }}
          </p>
          <button @click="selected = null" class="text-stone-400 text-xl">×</button>
        </div>
        <img
          v-if="blobCache[selected.id]"
          :src="blobCache[selected.id]"
          class="w-full rounded-2xl"
          :alt="selected.captured_on"
        />
        <div
          v-else-if="selected.cloud_synced && selected.cloud_url"
          class="w-full aspect-square rounded-2xl bg-cream-50 flex items-center justify-center text-stone-400 font-zen text-xs"
        >
          {{ t('photo_journal_cloud_only_hint') }}
        </div>
        <p class="text-[11px] font-zen text-stone-500">
          {{ selected.phase ?? '—' }} · cycle day {{ selected.cycle_day ?? '—' }}
        </p>
        <p v-if="selected.note" class="text-sm font-zen text-stone-700 whitespace-pre-wrap">
          {{ selected.note }}
        </p>

        <!-- cloud sync 控制（Premium gate）-->
        <div class="border-t border-cream-100 pt-3 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-xs font-zen text-stone-600">
              {{ t('photo_journal_cloud_state') }}：
              <span :class="selected.cloud_synced ? 'text-peach-500' : 'text-stone-400'">
                {{ selected.cloud_synced ? t('photo_journal_cloud_on') : t('photo_journal_cloud_off') }}
              </span>
            </p>
          </div>
          <Button
            v-if="!selected.cloud_synced"
            size="sm"
            variant="secondary"
            full
            data-test="cloud-sync-btn"
            @click="syncSelectedToCloud"
          >
            {{ ent.isPremium() ? t('photo_journal_cloud_enable') : t('photo_journal_cloud_premium_only') }}
          </Button>
          <Button
            v-else
            size="sm"
            variant="ghost"
            full
            data-test="cloud-unsync-btn"
            @click="unsyncSelected"
          >
            {{ t('photo_journal_cloud_disable') }}
          </Button>
          <button
            data-test="entry-delete"
            class="w-full text-[12px] text-sakura-500 font-zen pt-1"
            @click="deleteSelected"
          >
            {{ t('photo_journal_delete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
