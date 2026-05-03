/**
 * App 生物辨識鎖（Face ID / Touch ID / Fingerprint）
 *
 * 設計：
 * - 偏好（lock_enabled）存 localStorage，跨 session 保留
 * - 鎖定狀態（app_locked）存 sessionStorage，session 結束自動清
 * - 從背景回前景超過 30 秒（LOCK_GRACE_MS） 觸發鎖
 * - Web 環境直接回報 unsupported，所有 lock 操作 noop
 *
 * 整合點：
 * - App.vue 啟動 + Capacitor App lifecycle 監聽
 * - Profile.vue 安全區的 toggle
 * - Lock.vue 解鎖頁
 * - Login.vue 登入後若 lock_enabled 直接進 Lock
 */
import { Capacitor } from '@capacitor/core'
import { BiometricAuth, BiometryErrorType } from '@aparajita/capacitor-biometric-auth'

const LOCK_ENABLED_KEY = 'pandora_calendar_lock_enabled'
const LAST_ACTIVE_KEY = 'pandora_calendar_last_active'
const LOCKED_FLAG_KEY = 'app_locked'
const LOCK_GRACE_MS = 30_000 // 進背景 30 秒以上才需要重新解鎖

export interface BiometricAvailability {
  available: boolean
  reason?: string // 給 UI 顯示用的原因（mock / no hardware / not enrolled / web）
}

export function isLockEnabled(): boolean {
  try {
    return localStorage.getItem(LOCK_ENABLED_KEY) === '1'
  } catch {
    return false
  }
}

export function setLockEnabled(enabled: boolean): void {
  try {
    if (enabled) localStorage.setItem(LOCK_ENABLED_KEY, '1')
    else localStorage.removeItem(LOCK_ENABLED_KEY)
  } catch {
    // 私密模式 — 忽略
  }
}

export function isLocked(): boolean {
  try {
    return sessionStorage.getItem(LOCKED_FLAG_KEY) === '1'
  } catch {
    return false
  }
}

export function lock(): void {
  try {
    sessionStorage.setItem(LOCKED_FLAG_KEY, '1')
  } catch {
    /* noop */
  }
}

export function unlock(): void {
  try {
    sessionStorage.removeItem(LOCKED_FLAG_KEY)
  } catch {
    /* noop */
  }
  markActive()
}

export function markActive(): void {
  try {
    sessionStorage.setItem(LAST_ACTIVE_KEY, String(Date.now()))
  } catch {
    /* noop */
  }
}

export function shouldLockOnResume(): boolean {
  if (!isLockEnabled()) return false
  let last = 0
  try {
    last = Number(sessionStorage.getItem(LAST_ACTIVE_KEY) ?? '0')
  } catch {
    return false
  }
  if (!last) return true
  return Date.now() - last > LOCK_GRACE_MS
}

/**
 * 跨平台檢查生物辨識是否可用。
 * Web 永遠回 unsupported。
 */
export async function isBiometricAvailable(): Promise<BiometricAvailability> {
  if (!Capacitor.isNativePlatform()) {
    return { available: false, reason: 'web' }
  }
  try {
    const info = await BiometricAuth.checkBiometry()
    if (info.isAvailable) {
      return { available: true }
    }
    // 對應到 plugin 的錯誤類型，給 UI 友善文案
    const code = info.code
    if (code === BiometryErrorType.biometryNotEnrolled) {
      return { available: false, reason: 'not_enrolled' }
    }
    if (code === BiometryErrorType.biometryNotAvailable || code === BiometryErrorType.noDeviceCredential) {
      return { available: false, reason: 'no_hardware' }
    }
    return { available: false, reason: info.reason || 'unknown' }
  } catch (e) {
    return { available: false, reason: 'error' }
  }
}

/**
 * 觸發生物辨識驗證，成功回 true。
 * Web 環境永遠回 false。
 */
export async function verify(reason = '解鎖潘朵拉月曆'): Promise<boolean> {
  if (!Capacitor.isNativePlatform()) return false
  try {
    await BiometricAuth.authenticate({
      reason,
      cancelTitle: '取消',
      allowDeviceCredential: true,
      iosFallbackTitle: '使用密碼',
      androidTitle: '潘朵拉月曆',
      androidSubtitle: reason,
      androidConfirmationRequired: false,
    })
    return true
  } catch {
    return false
  }
}

export const _internal = {
  LOCK_ENABLED_KEY,
  LAST_ACTIVE_KEY,
  LOCKED_FLAG_KEY,
  LOCK_GRACE_MS,
}
