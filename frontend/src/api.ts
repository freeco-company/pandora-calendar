import axios from 'axios'
import { ref } from 'vue'

const API_BASE = (import.meta.env.VITE_API_BASE as string) ?? 'http://localhost:8000/api'

export const api = axios.create({
  baseURL: API_BASE,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

const TOKEN_KEY = 'pandora_calendar_token'
const REFRESH_KEY = 'pandora_calendar_refresh'
const USER_KEY = 'pandora_calendar_user'

export const tokenRef = ref<string | null>(localStorage.getItem(TOKEN_KEY))

export function getToken(): string | null {
  return tokenRef.value
}

export function setToken(t: string | null): void {
  tokenRef.value = t
  if (t) localStorage.setItem(TOKEN_KEY, t)
  else localStorage.removeItem(TOKEN_KEY)
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_KEY)
}

export function setRefreshToken(t: string | null): void {
  if (t) localStorage.setItem(REFRESH_KEY, t)
  else localStorage.removeItem(REFRESH_KEY)
}

export interface StoredUser {
  id: number | string
  name?: string | null
  email?: string | null
  display_name?: string | null
  identity_uuid?: string | null
}

export function getStoredUser(): StoredUser | null {
  const raw = localStorage.getItem(USER_KEY)
  return raw ? JSON.parse(raw) : null
}

export function setStoredUser(u: StoredUser | null): void {
  if (u) localStorage.setItem(USER_KEY, JSON.stringify(u))
  else localStorage.removeItem(USER_KEY)
}

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/**
 * Phase 0 demo helper（dev/testing only — 後端 abort 在 production）。
 * 留作 e2e + local dev seed 帳號用。
 */
export async function demoLogin(email: string) {
  const { data } = await api.post('/demo/login', { email })
  setToken(data.token)
  setStoredUser(data.user)
  return data.user
}

/**
 * P1 ADR-007: Pandora Core 統一登入（prod 主路徑）。
 * Calendar 端不存 password，全部 forward 給 PC。
 */
export async function platformLogin(email: string, password: string) {
  const { data } = await api.post('/v1/auth/login', { email, password })
  setToken(data.access_token)
  setRefreshToken(data.refresh_token)
  setStoredUser({
    id: data.user?.id,
    email: data.user?.email_canonical ?? email,
    display_name: data.user?.display_name,
  })
  return data.user
}

export async function platformRegister(email: string, password: string, displayName?: string) {
  const { data } = await api.post('/v1/auth/register', {
    email,
    password,
    display_name: displayName,
  })
  return data
}

export async function platformOauthUrl(provider: 'google' | 'line' | 'apple') {
  const { data } = await api.get(`/v1/auth/oauth/${provider}/url`)
  return data.redirect_url as string
}

export async function logout() {
  const refresh = getRefreshToken()
  if (refresh) {
    try {
      await api.post('/v1/auth/logout', { refresh_token: refresh })
    } catch {
      // 失敗不擋登出本地 cleanup
    }
  }
  setToken(null)
  setRefreshToken(null)
  setStoredUser(null)
}

export type Phase = 'menstrual' | 'follicular' | 'ovulation' | 'luteal' | 'unknown'

export interface BodyRhythm {
  date: string
  phase: Phase
  cycle_day: number | null
  next_period_eta: string | null
  days_until_next_period: number | null
}

export interface CyclePrediction {
  today: string
  latest_cycle_start: string | null
  avg_cycle_length: number
  avg_period_length: number
  next_period_eta: string | null
  ovulation_eta: string | null
  sample_size: number
  confidence: 'high' | 'low' | 'none'
}

export interface CycleRecord {
  id: number
  start_date: string
  end_date: string | null
  peak_flow: number | null
  length_days: number | null
  notes: string | null
}

export interface SymptomRecord {
  id: number
  logged_on: string
  tags: string[]
  mood: string | null
  basal_temperature: number | null
  note: string | null
}

export interface DodoCheckin {
  checked_on: string
  mood: string
  phase: Phase | null
  cycle_day: number | null
  dodo_response: string
}

export interface Entitlements {
  premium: boolean
  premium_until: string | null
  product_id: string | null
  platform: string | null
  auto_renew: boolean
}

export interface SubscriptionProduct {
  id: string
  title: string
  price_twd: number
  period: 'month' | 'year'
  discount: string | null
  monthly_equivalent?: number
}

export interface PmsPattern {
  sample_cycles: number
  top_symptoms: string[]
  symptom_counts: Record<string, number>
  confidence: string
}

export interface WeekReport {
  week_start: string
  summary: {
    cycles_started: number
    symptoms_logged: number
    top_symptom_tags: Record<string, number>
    checkins: number
    mood_distribution: Record<string, number>
    health_samples: number
    phase_at_week_end: string
    cycle_day_at_week_end: number | null
    dodo_summary: string
  }
}

export interface ProductLink {
  product_slug: string
  message: string
  mother_url: string
}

export interface ApiError {
  error?: string
  message?: string
  upgrade_to?: string
}

export const CalendarApi = {
  cycles: () => api.get<{ data: CycleRecord[]; prediction: CyclePrediction; body_rhythm: BodyRhythm }>('/v1/cycles'),
  storeCycle: (payload: { start_date: string; end_date?: string; peak_flow?: number; notes?: string }) =>
    api.post<{ data: CycleRecord }>('/v1/cycles', payload),
  deleteCycle: (id: number) => api.delete(`/v1/cycles/${id}`),
  symptoms: (params?: { from?: string; to?: string }) =>
    api.get<{ data: SymptomRecord[] }>('/v1/symptoms', { params }),
  storeSymptom: (payload: { logged_on: string; tags: string[]; mood?: string; note?: string }) =>
    api.post<{ data: SymptomRecord }>('/v1/symptoms', payload),
  bodyRhythm: () => api.get<{ data: BodyRhythm; prediction: CyclePrediction }>('/v1/body-rhythm/me'),
  dodoCheckin: (mood: 'good' | 'okay' | 'bad') =>
    api.post<{ data: DodoCheckin & { id: number } }>('/v1/dodo/checkin', { mood }),
  dodoRecent: () => api.get<{ data: DodoCheckin[] }>('/v1/dodo/recent'),
}

export const SubscriptionApi = {
  me: () => api.get<{ data: Entitlements }>('/v1/subscription/me'),
  products: () => api.get<{ data: SubscriptionProduct[]; features: string[] }>('/v1/subscription/products'),
  verifyApple: (receiptData: string, productId: string) =>
    api.post('/v1/subscription/verify-apple', { receipt_data: receiptData, product_id: productId }),
  verifyGoogle: (purchaseToken: string, productId: string, packageName: string) =>
    api.post('/v1/subscription/verify-google', {
      purchase_token: purchaseToken,
      product_id: productId,
      package_name: packageName,
    }),
  ecpayCheckout: (productId: string, returnUrl: string) =>
    api.post<{ data: { action_url: string; form_params: Record<string, string> } }>(
      '/v1/subscription/ecpay-checkout',
      { product_id: productId, return_url: returnUrl },
    ),
}

export const PremiumApi = {
  pms: () => api.get<{ data: PmsPattern | null }>('/v1/insight/pms'),
  weekReport: () => api.get<{ data: WeekReport }>('/v1/week-report/latest'),
  generateWeekReport: () => api.post<{ data: WeekReport }>('/v1/week-report/generate'),
  startPregnancy: (lmpDate: string) =>
    api.post<{ data: { id: number; estimated_due_date: string; gestational_week: number } }>(
      '/v1/pregnancy',
      { lmp_date: lmpDate },
    ),
  currentPregnancy: () =>
    api.get<{ data: { id: number; lmp_date: string; estimated_due_date: string; gestational_week: number } | null }>(
      '/v1/pregnancy/current',
    ),
  importHealthSamples: (samples: Array<{ metric: string; value: number; recorded_on: string }>) =>
    api.post('/v1/health-samples/import', { source: 'healthkit', samples }),
}

export const CommerceApi = {
  productLinks: () => api.get<{ data: ProductLink[]; gate_passed: boolean }>('/v1/commerce/product-links'),
}

export type GamificationPending =
  | { kind: 'level_up'; level: number; total_xp: number; outfit_state: unknown; pushed_at: string }
  | { kind: 'achievement_unlocked'; code: string; name: string; tier: string; pushed_at: string }
  | { kind: 'outfit_unlocked'; codes: string[]; pushed_at: string }
  | null

export const GamificationApi = {
  pending: () => api.get<{ data: GamificationPending }>('/v1/me/gamification/pending'),
  dodo: () =>
    api.get<{ data: { level: number; total_xp: number; outfit_state: unknown; mood: string } }>('/v1/me/dodo'),
  pet: () =>
    api.get<{ data: { species: string | null; nickname: string | null; level: number } }>('/v1/me/pet'),
}
