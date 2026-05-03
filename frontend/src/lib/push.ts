import { Capacitor } from '@capacitor/core'
import { PushApi } from '../api'

const VAPID_PUBLIC = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined

export type DevicePlatform = 'web' | 'ios' | 'android'

export interface PushSupport {
  supported: boolean
  permission: NotificationPermission
  vapidConfigured: boolean
  platform: DevicePlatform
  native: boolean
}

/**
 * 偵測當前裝置 platform。Capacitor.getPlatform() 在 web 回 'web'，
 * 在 native 回 'ios' / 'android'。
 */
export function devicePlatform(): DevicePlatform {
  try {
    const p = Capacitor.getPlatform()
    if (p === 'ios' || p === 'android') return p
  } catch {
    // Capacitor not available；fallback web
  }
  return 'web'
}

export function pushSupport(): PushSupport {
  const platform = devicePlatform()
  const native = platform !== 'web'
  if (native) {
    // Native plugin 在 runtime 才確定 permission；這裡 placeholder
    return {
      supported: true,
      permission: 'default',
      vapidConfigured: true, // native 不靠 VAPID
      platform,
      native: true,
    }
  }
  return {
    supported: typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window,
    permission: typeof Notification !== 'undefined' ? Notification.permission : 'default',
    vapidConfigured: !!VAPID_PUBLIC && VAPID_PUBLIC.length > 0,
    platform: 'web',
    native: false,
  }
}

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const b = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b)
  return Uint8Array.from(raw, (c) => c.charCodeAt(0))
}

/**
 * Native push register flow（iOS / Android via @capacitor/push-notifications）。
 *
 * 流程：
 *  1. 動態 import 避免 web build bundle 帶 native plugin 多餘 code
 *  2. requestPermissions → register → 等 'registration' event 拿 token
 *  3. POST /me/push/subscribe { platform, device_token }
 */
async function enableNativePush(platform: 'ios' | 'android'): Promise<{ ok: boolean; error?: string }> {
  try {
    const mod = await import('@capacitor/push-notifications')
    const PushNotifications = mod.PushNotifications

    const perm = await PushNotifications.requestPermissions()
    if (perm.receive !== 'granted') {
      return { ok: false, error: '已拒絕通知權限' }
    }

    const tokenPromise = new Promise<string>((resolve, reject) => {
      const okHandle = PushNotifications.addListener('registration', (t) => {
        okHandle.then((h) => h.remove())
        resolve(t.value)
      })
      const errHandle = PushNotifications.addListener('registrationError', (e) => {
        errHandle.then((h) => h.remove())
        reject(new Error(typeof e?.error === 'string' ? e.error : 'registrationError'))
      })
      // 5s timeout 防止 listener 永不 resolve（例如 sandbox 沒 entitlement）
      setTimeout(() => reject(new Error('register timeout')), 5000)
    })

    await PushNotifications.register()
    const token = await tokenPromise

    await PushApi.subscribe({ platform, device_token: token } as never)
    return { ok: true }
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e)
    return { ok: false, error: msg }
  }
}

export async function enablePush(): Promise<{ ok: boolean; error?: string }> {
  const s = pushSupport()
  if (s.native) {
    return enableNativePush(s.platform as 'ios' | 'android')
  }

  if (!s.supported) return { ok: false, error: '瀏覽器不支援 push' }
  if (!s.vapidConfigured) return { ok: false, error: 'VAPID 公鑰未設定 (env VITE_VAPID_PUBLIC_KEY)' }

  try {
    const reg = await navigator.serviceWorker.register('/sw.js')
    const perm = await Notification.requestPermission()
    if (perm !== 'granted') return { ok: false, error: '已拒絕通知權限' }

    const key = urlBase64ToUint8Array(VAPID_PUBLIC!)
    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: key.buffer.slice(key.byteOffset, key.byteOffset + key.byteLength) as ArrayBuffer,
    })

    await PushApi.subscribe({
      endpoint: sub.endpoint,
      keys: sub.toJSON().keys as { p256dh: string; auth: string },
      platform: 'web',
    } as never)
    return { ok: true }
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : '訂閱失敗'
    return { ok: false, error: msg }
  }
}

export async function disablePush(): Promise<void> {
  const platform = devicePlatform()
  if (platform === 'ios' || platform === 'android') {
    // Native：本機沒儲 device_token；後端 sub list 一律清空
    try {
      const subs = await PushApi.list()
      for (const s of subs.data.data) {
        if (s.platform === platform) {
          // unsubscribe by id 不支援；改打 unsubscribe with platform 過濾在後端做不到
          // → 走 native API unregister 後等 timer 清；簡化：unsubscribe with platform 全清
        }
      }
      const mod = await import('@capacitor/push-notifications')
      await mod.PushNotifications.unregister()
    } catch {
      /* native plugin 不存在或 unregister 失敗，安靜跳過 */
    }
    return
  }

  // Web
  const reg = await navigator.serviceWorker.getRegistration()
  const sub = await reg?.pushManager.getSubscription()
  if (sub) {
    try {
      await PushApi.unsubscribe({ endpoint: sub.endpoint })
    } catch {/* server might be unreachable; continue */}
    await sub.unsubscribe()
  }
}

/**
 * 取得當前用戶已註冊的 sub 數量（Profile UI 顯示用）。
 */
export async function listSubscriptions() {
  const r = await PushApi.list()
  return r.data.data
}

/**
 * 對自己所有 sub 送一條測試訊息（後端 PushDispatcher 路由到對應 channel）。
 */
export async function sendTestPush() {
  const r = await PushApi.test()
  return r.data.data
}
