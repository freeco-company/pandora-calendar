/**
 * usePhotoJournal — 進度照（隱私核心 composable）
 *
 * 隱私三層保護（前端側）：
 *   (1) takePhoto / pickFromAlbum 拿到的 binary 預設只放 device（IndexedDB），
 *       backend 永遠只收 metadata（tag / phase / cycle_day / note）。
 *   (2) Capacitor.isNativePlatform 為 true 時 binary 走 Capacitor Camera plugin
 *       的 fileUri；web fallback 走 base64 + IndexedDB。
 *   (3) cloud sync 是 Premium opt-in，由 view 層按鈕觸發 syncToCloud(id, blob)。
 *
 * 紅線：本 composable **不**對 binary 做任何 face / body 偵測或 hash 上報。
 */

import { ref } from 'vue'
import { Capacitor } from '@capacitor/core'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { PhotoJournalApi, type PhotoJournalEntry, type PhotoJournalTag } from '../api'

// IndexedDB 存 device-only binary（不 expose 到 backend）
const DB_NAME = 'pandora_calendar_photo_journal'
const STORE = 'photos'
const DB_VERSION = 1

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = () => {
      const db = req.result
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE) // key = `entry-${id}` 或 `local-${tempId}`
      }
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

async function idbPut(key: string, blob: Blob): Promise<void> {
  const db = await openDb()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).put(blob, key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function idbGet(key: string): Promise<Blob | null> {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly')
    const req = tx.objectStore(STORE).get(key)
    req.onsuccess = () => resolve((req.result as Blob | undefined) ?? null)
    req.onerror = () => reject(req.error)
  })
}

async function idbDelete(key: string): Promise<void> {
  const db = await openDb()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).delete(key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

export interface CapturedPhoto {
  blob: Blob
  /** Capacitor fileUri（native）or 內部 idb key（web） */
  localPath: string
}

async function dataUrlToBlob(dataUrl: string): Promise<Blob> {
  const r = await fetch(dataUrl)
  return r.blob()
}

export function usePhotoJournal() {
  const entries = ref<PhotoJournalEntry[]>([])
  const monthLabel = ref<string>(new Date().toISOString().slice(0, 7))
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function loadMonth(month: string) {
    loading.value = true
    error.value = null
    try {
      const res = await PhotoJournalApi.list(month)
      monthLabel.value = res.data.data.month
      entries.value = res.data.data.entries
    } catch (e: any) {
      error.value = e?.message ?? 'load_failed'
    } finally {
      loading.value = false
    }
  }

  async function takePhoto(): Promise<CapturedPhoto | null> {
    if (Capacitor.isNativePlatform()) {
      const photo = await Camera.getPhoto({
        quality: 80,
        allowEditing: false,
        resultType: CameraResultType.DataUrl,
        source: CameraSource.Camera,
        // 紅線：saveToGallery 預設 false，避免照片自動進系統相簿被別 App 看到
        saveToGallery: false,
      })
      if (!photo.dataUrl) return null
      const blob = await dataUrlToBlob(photo.dataUrl)
      const tempKey = `local-${Date.now()}`
      await idbPut(tempKey, blob)
      return { blob, localPath: tempKey }
    }
    // Web fallback：file input picker
    return webPickFile('camera')
  }

  async function pickFromAlbum(): Promise<CapturedPhoto | null> {
    if (Capacitor.isNativePlatform()) {
      const photo = await Camera.getPhoto({
        quality: 80,
        allowEditing: false,
        resultType: CameraResultType.DataUrl,
        source: CameraSource.Photos,
      })
      if (!photo.dataUrl) return null
      const blob = await dataUrlToBlob(photo.dataUrl)
      const tempKey = `local-${Date.now()}`
      await idbPut(tempKey, blob)
      return { blob, localPath: tempKey }
    }
    return webPickFile('library')
  }

  function webPickFile(_mode: 'camera' | 'library'): Promise<CapturedPhoto | null> {
    return new Promise((resolve) => {
      const input = document.createElement('input')
      input.type = 'file'
      input.accept = 'image/*'
      // mobile web：capture=user 提示開相機
      if (_mode === 'camera') input.setAttribute('capture', 'user')
      input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return resolve(null)
        const tempKey = `local-${Date.now()}`
        await idbPut(tempKey, file)
        resolve({ blob: file, localPath: tempKey })
      }
      input.click()
    })
  }

  /**
   * 寫 metadata（不上傳 binary）— 預設行為。binary 留在 device IndexedDB
   * 並把 idb key rename 成 entry-id 方便之後檢索。
   */
  async function recordEntry(payload: {
    tag: PhotoJournalTag
    captured_on: string
    cycle_day?: number | null
    phase?: string | null
    note?: string | null
    photo?: CapturedPhoto | null
  }): Promise<PhotoJournalEntry> {
    const { photo, ...rest } = payload
    const res = await PhotoJournalApi.create({
      ...rest,
      local_path: photo?.localPath ?? null,
    })
    const entry = res.data.data

    // rename idb key → entry-{id} 方便 detail 取
    if (photo && photo.localPath.startsWith('local-')) {
      const blob = await idbGet(photo.localPath)
      if (blob) {
        await idbPut(`entry-${entry.id}`, blob)
        await idbDelete(photo.localPath)
      }
    }
    entries.value = [...entries.value, entry]
    return entry
  }

  /** 拿 device-side blob 做 thumb / detail 顯示 */
  async function getDeviceBlob(entryId: number): Promise<Blob | null> {
    return idbGet(`entry-${entryId}`)
  }

  async function syncToCloud(id: number): Promise<PhotoJournalEntry | null> {
    const blob = await idbGet(`entry-${id}`)
    if (!blob) {
      error.value = 'no_local_binary'
      return null
    }
    try {
      const res = await PhotoJournalApi.uploadCloud(id, blob)
      const updated = res.data.data
      entries.value = entries.value.map((e) => (e.id === id ? updated : e))
      return updated
    } catch (e: any) {
      const status = e?.response?.status
      if (status === 402) {
        error.value = 'premium_required'
      } else {
        error.value = 'upload_failed'
      }
      return null
    }
  }

  async function unsyncCloud(id: number): Promise<void> {
    await PhotoJournalApi.removeCloudOnly(id)
    entries.value = entries.value.map((e) =>
      e.id === id ? { ...e, cloud_synced: false, cloud_url: null } : e,
    )
  }

  async function removeEntry(id: number): Promise<void> {
    await PhotoJournalApi.remove(id)
    await idbDelete(`entry-${id}`)
    entries.value = entries.value.filter((e) => e.id !== id)
  }

  return {
    entries,
    monthLabel,
    loading,
    error,
    loadMonth,
    takePhoto,
    pickFromAlbum,
    recordEntry,
    getDeviceBlob,
    syncToCloud,
    unsyncCloud,
    removeEntry,
  }
}
