import { ref } from 'vue'
import { Capacitor } from '@capacitor/core'
import { api } from '../api'

/**
 * useHealthKit — HealthKit (iOS) / Health Connect (Android) 整合 composable
 *
 * 平台行為：
 *  - iOS（native）：HealthKit
 *  - Android（native）：Health Connect
 *  - web：graceful degrade（isAvailable() === false，所有方法 no-op 回 friendly error）
 *
 * 使用 capacitor-health@0.0.14（mley/perfood 衍生，Capacitor 6 相容）。
 * Plugin 模組為 dynamic import — 避免 web bundle 拉進 native-only code。
 */

export type HealthDataType = 'bbt' | 'steps' | 'sleep' | 'menstrual_flow'

export interface HealthSampleRow {
  date: string // ISO YYYY-MM-DD
  datetime?: string // ISO 8601（含時間，原 sample 採集時刻）
  value: number
  unit?: string
  meta?: Record<string, unknown>
}

export interface SyncResult {
  imported: number
  duplicates: number
  errors: string[]
}

export interface PlatformInfo {
  available: boolean
  platform: 'ios' | 'android' | 'web' | 'unknown'
  reason?: string
}

interface CapHealthPlugin {
  isHealthAvailable: () => Promise<{ available: boolean }>
  requestHealthPermissions: (opts: { permissions: string[] }) => Promise<{ permissions: string[] }>
  queryAggregated: (opts: {
    startDate: string
    endDate: string
    dataType: string
    bucket?: string
  }) => Promise<{ aggregatedData: Array<{ startDate: string; endDate: string; value: number; unit?: string }> }>
  // 部分 plugin 版本提供 query (raw samples) — 透過 try/catch wrapper 容錯
  query?: (opts: {
    startDate: string
    endDate: string
    dataType: string
    limit?: number
  }) => Promise<{ resultData: Array<{ startDate: string; endDate: string; value: number; unit?: string }> }>
  storeHealthData?: (opts: {
    dataType: string
    value: number
    startDate: string
    endDate: string
  }) => Promise<{ success: boolean }>
}

const HEALTH_TYPE_MAP: Record<HealthDataType, string> = {
  bbt: 'BASAL_BODY_TEMPERATURE',
  steps: 'STEPS',
  sleep: 'SLEEP_ASLEEP',
  menstrual_flow: 'MENSTRUATION',
}

let _plugin: CapHealthPlugin | null = null
let _pluginLoaded = false

async function loadPlugin(): Promise<CapHealthPlugin | null> {
  if (_pluginLoaded) return _plugin
  _pluginLoaded = true
  if (!Capacitor.isNativePlatform()) return null
  try {
    const mod = await import(/* @vite-ignore */ 'capacitor-health')
    _plugin = (mod as unknown as { Health?: CapHealthPlugin }).Health ?? null
  } catch {
    _plugin = null
  }
  return _plugin
}

export function useHealthKit() {
  const lastSyncedAt = ref<string | null>(localStorage.getItem('pandora_calendar_last_health_sync'))
  const syncing = ref(false)
  const lastError = ref<string | null>(null)

  function platformInfo(): PlatformInfo {
    const native = Capacitor.isNativePlatform()
    const platform = Capacitor.getPlatform() as 'ios' | 'android' | 'web'
    if (!native) {
      return { available: false, platform: platform === 'web' ? 'web' : 'unknown', reason: 'web 環境不支援，請在 iOS / Android App 中使用' }
    }
    return { available: true, platform: platform === 'ios' || platform === 'android' ? platform : 'unknown' }
  }

  async function isAvailable(): Promise<boolean> {
    const info = platformInfo()
    if (!info.available) return false
    const plugin = await loadPlugin()
    if (!plugin) return false
    try {
      const r = await plugin.isHealthAvailable()
      return !!r.available
    } catch {
      return false
    }
  }

  async function requestAuth(types: HealthDataType[]): Promise<boolean> {
    const plugin = await loadPlugin()
    if (!plugin) {
      lastError.value = '此裝置不支援 HealthKit / Health Connect'
      return false
    }
    try {
      const permissions = types.map((t) => HEALTH_TYPE_MAP[t])
      await plugin.requestHealthPermissions({ permissions })
      return true
    } catch (e) {
      lastError.value = (e as Error)?.message ?? '授權被拒絕'
      return false
    }
  }

  async function readRange(kind: HealthDataType, from: Date, to: Date): Promise<HealthSampleRow[]> {
    const plugin = await loadPlugin()
    if (!plugin) return []
    const dataType = HEALTH_TYPE_MAP[kind]
    const startDate = from.toISOString()
    const endDate = to.toISOString()
    try {
      // 優先 raw samples（BBT 需個別 sample；steps / sleep 也接受 daily aggregation）
      if (plugin.query) {
        const r = await plugin.query({ startDate, endDate, dataType })
        return r.resultData.map((s) => ({
          date: s.startDate.slice(0, 10),
          datetime: s.startDate,
          value: s.value,
          unit: s.unit,
        }))
      }
      const agg = await plugin.queryAggregated({ startDate, endDate, dataType, bucket: 'day' })
      return agg.aggregatedData.map((s) => ({
        date: s.startDate.slice(0, 10),
        datetime: s.startDate,
        value: s.value,
        unit: s.unit,
      }))
    } catch (e) {
      lastError.value = `讀取 ${kind} 失敗：${(e as Error)?.message ?? '未知錯誤'}`
      return []
    }
  }

  function readBBT(from: Date, to: Date): Promise<HealthSampleRow[]> {
    return readRange('bbt', from, to)
  }

  function readSteps(from: Date, to: Date): Promise<HealthSampleRow[]> {
    return readRange('steps', from, to)
  }

  function readSleep(from: Date, to: Date): Promise<HealthSampleRow[]> {
    return readRange('sleep', from, to)
  }

  /**
   * 寫一筆經期記錄回 HealthKit / Health Connect，讓 Apple Health App / Health Connect 也看得到。
   * level: 0(none) / 1(light) / 2(medium) / 3(heavy)
   */
  async function writeMenstrualFlow(date: string, level: 0 | 1 | 2 | 3): Promise<boolean> {
    const plugin = await loadPlugin()
    if (!plugin?.storeHealthData) {
      lastError.value = '此裝置不支援寫入經期資料'
      return false
    }
    try {
      const startDate = `${date}T00:00:00.000Z`
      const endDate = `${date}T23:59:59.000Z`
      await plugin.storeHealthData({ dataType: HEALTH_TYPE_MAP.menstrual_flow, value: level, startDate, endDate })
      return true
    } catch (e) {
      lastError.value = `寫入失敗：${(e as Error)?.message ?? '未知錯誤'}`
      return false
    }
  }

  async function pushToServer(kind: HealthDataType, samples: HealthSampleRow[]): Promise<SyncResult> {
    if (samples.length === 0) {
      return { imported: 0, duplicates: 0, errors: [] }
    }
    const platform = Capacitor.getPlatform()
    const source = platform === 'ios' ? 'healthkit' : platform === 'android' ? 'health_connect' : 'manual'
    const res = await api.post('/v1/health-samples/sync', { kind, source, samples })
    return res.data?.data as SyncResult
  }

  /**
   * 一次拉最近 N 天並推到 backend（預設 7 天）。
   * 回傳每個 kind 的 SyncResult。
   */
  async function syncRecent(days = 7, types: HealthDataType[] = ['bbt', 'steps', 'sleep']): Promise<Record<HealthDataType, SyncResult>> {
    syncing.value = true
    lastError.value = null
    const results = {} as Record<HealthDataType, SyncResult>
    try {
      const to = new Date()
      const from = new Date(Date.now() - days * 24 * 60 * 60 * 1000)
      for (const kind of types) {
        const samples = await readRange(kind, from, to)
        try {
          results[kind] = await pushToServer(kind, samples)
        } catch (e) {
          const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
          results[kind] = { imported: 0, duplicates: 0, errors: [msg ?? (e as Error).message] }
        }
      }
      const now = new Date().toISOString()
      lastSyncedAt.value = now
      try {
        localStorage.setItem('pandora_calendar_last_health_sync', now)
      } catch {
        /* private mode */
      }
      return results
    } finally {
      syncing.value = false
    }
  }

  return {
    lastSyncedAt,
    syncing,
    lastError,
    platformInfo,
    isAvailable,
    requestAuth,
    readBBT,
    readSteps,
    readSleep,
    writeMenstrualFlow,
    syncRecent,
  }
}
