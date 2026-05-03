import { PushApi } from '../api'

const VAPID_PUBLIC = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined

export interface PushSupport {
  supported: boolean
  permission: NotificationPermission
  vapidConfigured: boolean
}

export function pushSupport(): PushSupport {
  return {
    supported: typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window,
    permission: typeof Notification !== 'undefined' ? Notification.permission : 'default',
    vapidConfigured: !!VAPID_PUBLIC && VAPID_PUBLIC.length > 0,
  }
}

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const b = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b)
  return Uint8Array.from(raw, (c) => c.charCodeAt(0))
}

export async function enablePush(): Promise<{ ok: boolean; error?: string }> {
  const s = pushSupport()
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
    } as any)
    return { ok: true }
  } catch (e: any) {
    return { ok: false, error: e?.message ?? '訂閱失敗' }
  }
}

export async function disablePush(): Promise<void> {
  const reg = await navigator.serviceWorker.getRegistration()
  const sub = await reg?.pushManager.getSubscription()
  if (sub) {
    try {
      await PushApi.unsubscribe(sub.endpoint)
    } catch {/* server might be unreachable; continue */}
    await sub.unsubscribe()
  }
}
