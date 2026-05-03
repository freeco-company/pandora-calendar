import axios from 'axios'
import * as Sentry from '@sentry/capacitor'
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

// 401 auto-refresh：access token 過期時自動拿 refresh token 換新 access token + retry 一次
let refreshPromise: Promise<string | null> | null = null

async function attemptRefresh(): Promise<string | null> {
  const refresh = getRefreshToken()
  if (!refresh) return null

  if (!refreshPromise) {
    refreshPromise = (async () => {
      try {
        const { data } = await axios.post(
          `${API_BASE}/v1/auth/refresh`,
          { refresh_token: refresh },
          { headers: { Accept: 'application/json', 'Content-Type': 'application/json' } },
        )
        if (data?.access_token) {
          setToken(data.access_token)
          if (data.refresh_token) setRefreshToken(data.refresh_token)
          return data.access_token as string
        }
      } catch {
        setToken(null)
        setRefreshToken(null)
        setStoredUser(null)
      }
      return null
    })().finally(() => {
      refreshPromise = null
    })
  }
  return refreshPromise
}

api.interceptors.response.use(
  (resp) => resp,
  async (error) => {
    const original = error.config
    const status: number | undefined = error.response?.status
    const url: string = String(original?.url ?? '')

    // Sentry 觀測：5xx 報錯（不附 response body — 可能含 health data），4xx 留 breadcrumb，401 不報
    if (status !== undefined) {
      if (status >= 500) {
        // 不附 response.data — 後端錯誤訊息可能 echo back 用戶輸入或 cycle / symptom 資料
        Sentry.captureMessage(`API ${status} ${original?.method?.toUpperCase() ?? 'GET'} ${redactUrl(url)}`, 'error')
      } else if (status >= 400 && status !== 401) {
        // 4xx (除 401) → breadcrumb only；401 是 token 過期 / refresh path 預期事件
        Sentry.addBreadcrumb({
          category: 'api.4xx',
          level: 'warning',
          message: `${status} ${redactUrl(url)}`,
          data: {},
        })
      }
    } else if (error.message) {
      // network error / timeout → message
      Sentry.captureMessage(`API network error ${redactUrl(url)}: ${error.message}`, 'warning')
    }

    // 跳過 auth/* 自己 — refresh 失敗不要遞迴 refresh
    if (
      status === 401 &&
      original &&
      !original._retry &&
      !url.includes('/v1/auth/')
    ) {
      original._retry = true
      const newToken = await attemptRefresh()
      if (newToken) {
        original.headers.Authorization = `Bearer ${newToken}`
        return api(original)
      }
    }
    return Promise.reject(error)
  },
)

// health-route 在 URL 出現 → redact 整段（不要把 /cycles/123 / /symptoms/x 送 Sentry）
const HEALTH_URL_SEGMENTS = [
  '/cycles',
  '/symptoms',
  '/symptom-tags',
  '/bbt',
  '/pms',
  '/pregnancy',
  '/body-rhythm',
  '/bodyrhythm',
  '/dodo/checkin',
  '/insights',
  '/onboarding',
]

function redactUrl(u: string): string {
  if (!u) return '[empty]'
  const lower = u.toLowerCase()
  for (const seg of HEALTH_URL_SEGMENTS) {
    if (lower.includes(seg)) return '[health-route]'
  }
  return u
}

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
  // 不論 PC logout 成功與否，本地 token / cache 一定要清乾淨（避免登出半套）
  try {
    if (refresh) await api.post('/v1/auth/logout', { refresh_token: refresh })
  } catch {
    /* swallow — local cleanup must always run */
  }
  setToken(null)
  setRefreshToken(null)
  setStoredUser(null)
  // 順便清 LS XP cache 與 pet 設定（避免下個登入用戶看到上一個人的 cache）
  try {
    localStorage.removeItem('pandora_calendar_xp_total')
    localStorage.removeItem('pandora_calendar_level')
    localStorage.removeItem('pandora_calendar_pet')
  } catch {
    /* localStorage may be disabled in private mode */
  }
}

/**
 * App Store / GDPR：刪除我在 calendar 端的所有資料。
 * 跨集團砍帳號需另外 contact support（PC self-service delete 尚未實作）。
 */
export async function deleteCalendarData() {
  const { data } = await api.delete('/v1/me')
  // 連同登出本地（PC user mirror 還在，但 calendar 本機 token 不留）
  setToken(null)
  setRefreshToken(null)
  setStoredUser(null)
  return data
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
  product_name?: string
}

export interface EcommerceEligibility {
  eligible: boolean
  reasons: Array<'not_linked' | 'no_purchase' | 'no_subscription' | 'too_new'>
  days_used: number
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
  updateCycle: (id: number, payload: { start_date?: string; end_date?: string | null; peak_flow?: number; notes?: string }) =>
    api.patch<{ data: CycleRecord }>(`/v1/cycles/${id}`, payload),
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

// === P4 孕期模式 ===
export type PregnancyEndReason = 'birth' | 'miscarriage' | 'cancelled' | 'false_alarm'

export interface PregnancyAction {
  key: string
  label: string
}

export interface PregnancyState {
  id: number
  lmp_date: string
  estimated_due_date: string
  mode_started_at: string | null
  status: 'active' | 'paused' | 'ended'
  gestational_week: number
  trimester: 1 | 2 | 3
  days_until_due: number
  fetal_size: { label: string; emoji: string }
  this_week: {
    week: number
    trimester: 1 | 2 | 3
    dodo_message: string
    suggested_actions: PregnancyAction[]
  }
}

export interface PregnancyWeekContent {
  week: number
  trimester: 1 | 2 | 3
  fetal_size: { label: string; emoji: string }
  dodo_message: string
  suggested_actions: PregnancyAction[]
}

export const PregnancyApi = {
  current: () => api.get<{ data: PregnancyState | null }>('/v1/pregnancy/current'),
  start: (lmpDate: string) =>
    api.post<{ data: PregnancyState }>('/v1/pregnancy/start', { lmp_date: lmpDate }),
  end: (reason: PregnancyEndReason) =>
    api.patch<{ data: { id: number; status: string; ended_reason: string; ended_on: string } }>(
      '/v1/pregnancy/end',
      { reason },
    ),
  week: (week: number) =>
    api.get<{ data: PregnancyWeekContent }>(`/v1/pregnancy/week/${week}`),
}

export const CommerceApi = {
  productLinks: () => api.get<{ data: ProductLink[]; gate_passed: boolean }>('/v1/commerce/product-links'),
}

// P5 婕樂纖深層商品連結（嚴守紅線：只在「我的 → 婕樂纖會員」呼叫）
export const EcommerceApi = {
  eligibility: () => api.get<{ data: EcommerceEligibility }>('/v1/ecommerce/eligibility'),
  recommendations: () => api.get<{ data: ProductLink[] }>('/v1/ecommerce/recommendations'),
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
}

export interface PetState {
  species: string | null
  nickname: string | null
  level: number
  onboarded: boolean
  available_species: string[]
}

export const PetApi = {
  show: () => api.get<{ data: PetState }>('/v1/me/pet'),
  update: (species: string, nickname: string) =>
    api.patch<{ data: { species: string; nickname: string; onboarded: boolean } }>('/v1/me/pet', { species, nickname }),
}

export interface JourneyData {
  level: number
  total_xp: number
  progress_in_level: number
  need_for_next_level: number
  next_level_at_xp: number
  streak_days: number
  last_30_days: { cycles_logged: number; symptoms_logged: number; dodo_checkins: number }
  outfit_owned: string[]
  outfit_equipped: string | null
  milestones: Array<{ code: string; name: string; icon: string; unlocked: boolean; progress?: number; target?: number }>
}

export const JourneyApi = {
  show: () => api.get<{ data: JourneyData }>('/v1/me/journey'),
}

export interface AchievementRow {
  key: string
  name: string
  hint: string
  kind: 'first' | 'streak' | 'milestone'
  tier: 'bronze' | 'silver' | 'gold'
  badge: string
  badge_url: string
  xp: number
  target: number | null
  unlocked: boolean
  unlocked_at: string | null
}

export const AchievementsApi = {
  list: () =>
    api.get<{ data: { unlocked_count: number; total: number; achievements: AchievementRow[] } }>(
      '/v1/me/achievements',
    ),
}

export interface OutfitRow {
  code: string
  name: string
  hint: string
  rarity: 'common' | 'rare' | 'epic' | 'legendary'
  icon: string
  unlock: { type: string; value: any }
  svg_url: string
  unlocked: boolean
  equipped: boolean
}

export const OutfitsApi = {
  list: () =>
    api.get<{ data: { unlocked_count: number; total: number; equipped: string; outfits: OutfitRow[] } }>(
      '/v1/me/outfits',
    ),
  equip: (code: string) =>
    api.post<{ data: { equipped: string } }>('/v1/me/outfits/equip', { code }),
}

export interface DailyReminder {
  phase: Phase
  cycle_day: number | null
  days_until_next_period: number | null
  icon: string
  title: string
  body: string
  tone: 'sakura' | 'cream' | 'peach' | 'lavender' | 'sage'
}

export const ReminderApi = {
  today: () => api.get<{ data: DailyReminder }>('/v1/me/daily-reminder'),
}

export const DodoChatApi = {
  history: (limit = 20) =>
    api.get<{ data: Array<{ id: number; checked_on: string; mood: string; phase: string; cycle_day: number | null; dodo_response: string }> }>(
      '/v1/me/dodo/history',
      { params: { limit } },
    ),
}

export interface BbtRow {
  id: number
  measured_on: string
  temperature_c: string
  note: string | null
}

export const BbtApi = {
  list: (params?: { from?: string; to?: string }) =>
    api.get<{ data: BbtRow[]; avg_low: number; avg_high: number }>('/v1/me/bbt', { params }),
  store: (measured_on: string, temperature_c: number, note?: string) =>
    api.post<{ data: BbtRow }>('/v1/me/bbt', { measured_on, temperature_c, note }),
  destroy: (id: number) => api.delete(`/v1/me/bbt/${id}`),
}

export interface PartnerShareState {
  enabled: boolean
  token: string | null
  enabled_at: string | null
  share_url: string | null
}

export const PartnerApi = {
  show: () => api.get<{ data: PartnerShareState }>('/v1/me/partner-share'),
  enable: () => api.post<{ data: { token: string; share_url: string } }>('/v1/me/partner-share'),
  disable: () => api.delete('/v1/me/partner-share'),
  publicView: (token: string) =>
    api.get<{
      data: { display_name: string; phase: Phase; days_until_next_period: number | null; partner_hint: string }
    }>(`/v1/partner/${token}`),
}

export const PushApi = {
  subscribe: (sub: (PushSubscriptionJSON & { platform?: string }) | { platform: 'ios' | 'android'; device_token: string }) =>
    api.post('/v1/me/push/subscribe', sub),
  unsubscribe: (target: { endpoint?: string; device_token?: string }) =>
    api.post('/v1/me/push/unsubscribe', target),
  list: () =>
    api.get<{ data: Array<{ id: number; platform: string; last_used_at: string | null; created_at: string | null }> }>(
      '/v1/me/push/subscriptions',
    ),
  test: () =>
    api.post<{ data: { count: number; results: Array<{ platform: string; ok: boolean; reason: string | null }> } }>(
      '/v1/me/push/test',
    ),
}

// =====================================================================
// Wave 1: export / year-review / faq / feedback / medical-safety / churn
// =====================================================================

/**
 * Premium endpoint 失敗時，後端會回 402/403 + body { paywall_redirect: '/paywall' }。
 * 把它包成可被 view 攔截的 error，讓上層自動 router.push('/paywall')。
 */
export class PaywallRequiredError extends Error {
  paywallRedirect: string
  constructor(redirect: string) {
    super('Premium required')
    this.name = 'PaywallRequiredError'
    this.paywallRedirect = redirect
  }
}

function unwrapPremium<T>(p: Promise<T>): Promise<T> {
  return p.catch((err) => {
    const status = err?.response?.status
    const redirect = err?.response?.data?.paywall_redirect
    if ((status === 402 || status === 403) && typeof redirect === 'string') {
      throw new PaywallRequiredError(redirect)
    }
    throw err
  })
}

export interface ExportLink {
  download_url: string
  expires_at: string
}

export const ExportApi = {
  pdf: (params?: { from?: string; to?: string }) =>
    unwrapPremium(api.post<{ data: ExportLink }>('/v1/export/pdf', params ?? {})),
  csv: (params?: { from?: string; to?: string }) =>
    unwrapPremium(api.post<{ data: ExportLink }>('/v1/export/csv', params ?? {})),
}

export interface YearReviewCard {
  id: string
  title: string
  subtitle?: string | null
  body: string
  emoji: string
  sort: number
}

export const YearReviewApi = {
  show: (year: number) =>
    unwrapPremium(
      api.get<{ data: { cards: YearReviewCard[]; year: number; insufficient?: boolean } }>(
        `/v1/year-review/${year}`,
      ),
    ),
}

export type FeedbackCategory = 'bug' | 'feature' | 'content' | 'other'

export interface FeedbackPayload {
  category: FeedbackCategory
  message: string
  app_version?: string
  device_info?: string
}

export const FeedbackApi = {
  submit: (payload: FeedbackPayload) => api.post('/v1/feedback', payload),
}

export interface FaqItem {
  q: string
  a: string
  related_links?: Array<{ label: string; href: string; external?: boolean }>
}

export interface FaqGroup {
  category: 'usage' | 'privacy' | 'subscription' | 'health' | string
  category_label: string
  items: FaqItem[]
}

export const FaqApi = {
  list: () => api.get<{ data: FaqGroup[] }>('/v1/faq'),
}

export type MedicalUrgency = 'low' | 'medium' | 'high'
export type MedicalContext =
  | 'period_late'
  | 'heavy_flow'
  | 'severe_cramps'
  | 'irregular'
  | 'spotting'

export interface MedicalEvaluation {
  urgency: MedicalUrgency
  action: string
  message: string
  suggest_test: boolean
  find_doctor_url: string | null
}

export const MedicalSafetyApi = {
  evaluate: (params: { context: MedicalContext; days_late?: number }) =>
    api.get<{ data: MedicalEvaluation }>('/v1/medical-safety/evaluate', { params }),
}

export interface ChurnInterceptReason {
  code: string
  label: string
  offer_kind: 'pause' | 'discount' | 'feedback' | 'privacy' | 'feature_promise' | 'none'
}

export interface ChurnInterceptPauseOption {
  months: number
  label: string
}

export interface ChurnInterceptData {
  reasons: ChurnInterceptReason[]
  pause_options: ChurnInterceptPauseOption[]
  win_back: { headline: string; body: string; pause_default_months: number }
  discount?: { percent: number; valid_days: number; copy: string } | null
}

export const SubscriptionFlowApi = {
  churnIntercept: () => api.get<{ data: ChurnInterceptData }>('/v1/subscription/churn-intercept'),
  pause: (months: number, reason: string) =>
    api.post<{ data: { resume_at: string } }>('/v1/subscription/pause', { months, reason }),
  cancelFeedback: (reason: string, message?: string) =>
    api.post('/v1/subscription/cancel-feedback', { reason, message }),
}

// =====================================================================
// Daily Action Engine（個人化每日小任務 + pattern report）
// =====================================================================

export type ActionType = 'symptom_relief' | 'phase_support' | 'mood' | 'habit' | 'general'
export type ActionDifficulty = 'easy' | 'medium' | 'hard'
export type ActionFeedback = 'helpful' | 'neutral' | 'unhelpful'

export interface DailyAction {
  id: number
  action_key: string
  title: string
  body: string
  type: ActionType
  phase: Phase | null
  expected_benefit: string | null
  time_minutes: number | null
  difficulty: ActionDifficulty
  is_completed: boolean
  feedback?: ActionFeedback | null
}

export interface ActionCompleteResponse {
  data: DailyAction
  dodo_reply?: string | null
}

export interface ActionFeedbackResponse {
  data: DailyAction
  dodo_reply?: string | null
}

export interface ActionHistoryRow extends DailyAction {
  for_date: string
  completed_at: string | null
}

export interface ProtocolEntry {
  action_key: string
  title: string
  effectiveness: number // 0..1
  sample_size: number
}

export interface ProtocolByPhase {
  menstrual: ProtocolEntry[]
  follicular: ProtocolEntry[]
  ovulation: ProtocolEntry[]
  luteal: ProtocolEntry[]
}

export interface ProtocolInsight {
  insight_key: string
  message: string
  action_cta: string | null
  source:
    | 'specific_action_works'
    | 'type_responds'
    | 'recurring_phase_symptom'
    | string
}

export interface DailyActionTodayResponse {
  data: DailyAction | null
  message?: string
  protocol_insight: ProtocolInsight | null
}

export const ActionApi = {
  today: () => api.get<DailyActionTodayResponse>('/v1/actions/today'),
  complete: (id: number) => api.post<ActionCompleteResponse>(`/v1/actions/${id}/complete`),
  feedback: (id: number, feedback: ActionFeedback, body_note?: string) =>
    api.post<ActionFeedbackResponse>(`/v1/actions/${id}/feedback`, { feedback, body_note }),
  history: (days = 30) =>
    api.get<{ data: ActionHistoryRow[] }>('/v1/actions/history', { params: { days } }),
  protocol: () => unwrapPremium(api.get<{ data: ProtocolByPhase }>('/v1/actions/protocol')),
}

// ===== Health reflection (Premium) =====
export interface HealthReflection {
  message: string
  suggested_action_type: 'sleep' | 'move' | 'eat' | 'relax' | 'track' | 'learn' | 'connect' | string
  severity: 'info' | 'notice' | 'heads_up' | string
  source: string
}

export const ReflectionApi = {
  today: () =>
    unwrapPremium(api.get<{ data: HealthReflection | null }>('/v1/health-samples/reflection/today')),
}

// ===== Protocol insight surfacing =====
export const ProtocolInsightApi = {
  active: () => api.get<{ data: ProtocolInsight | null }>('/v1/protocol-insights/active'),
  dismiss: (key: string) =>
    api.post<{ data: { dismissed: true; insight_key: string; dismissed_at: string | null } }>(
      `/v1/protocol-insights/${encodeURIComponent(key)}/dismiss`,
    ),
}

export interface PatternReportSummary {
  phase_summary: Record<string, number> // phase → days count
  top_actions: Array<{ action_key: string; title: string; effectiveness: number; sample_size: number }>
  top_unhelpful: Array<{ action_key: string; title: string; effectiveness: number; sample_size: number }>
  vs_previous: Array<{ symptom: string; delta: number; direction: 'up' | 'down' | 'flat' }>
  dodo_message: string
  generated_at: string
  month_label?: string
}

export interface PatternReportListRow {
  id: number
  generated_at: string
  month_label: string
}

export const PatternReportApi = {
  latest: () => api.get<{ data: PatternReportSummary | null }>('/v1/pattern-report/latest'),
  list: () => api.get<{ data: PatternReportListRow[] }>('/v1/pattern-report/list'),
}

// Community Q&A
export type CommunityCategory = 'question' | 'experience' | 'tip' | 'support'
export type CommunitySort = 'latest' | 'hot' | 'mine'
export type ReportReason =
  | 'spam'
  | 'harassment'
  | 'medical_advice'
  | 'commercial'
  | 'self_harm'
  | 'other'

export interface CommunityPost {
  id: number
  category: CommunityCategory
  title: string
  body: string
  anonymous_handle: string
  is_mine: boolean
  is_dodo: boolean
  liked: boolean
  like_count: number
  reply_count: number
  has_self_harm_signal: boolean
  published_at: string | null
  created_at: string | null
}

// ── P4 含金量 Q&A — 朵朵 LLM + RAG ─────────────────────────
export interface QnaItem {
  id: number
  question: string
  answer: string
  sources: number[]
  safety_flag: 'redline_self_harm' | 'redline_compliance' | null
  remaining_today?: number | null
  is_premium?: boolean
  created_at?: string | null
}

export interface QnaAskResponse {
  data: {
    id: number
    answer: string
    sources: number[]
    safety_flag: 'redline_self_harm' | 'redline_compliance' | null
    remaining_today: number | null
    is_premium: boolean
  }
}

export interface QnaHistoryResponse {
  data: QnaItem[]
  meta: { remaining_today: number | null; is_premium: boolean }
}

export const QnaApi = {
  ask: (question: string) =>
    api.post<QnaAskResponse>('/v1/qna/ask', { question }),
  history: (days = 30) =>
    api.get<QnaHistoryResponse>('/v1/qna/history', { params: { days } }),
  remove: (id: number) => api.delete<{ data: { deleted: true } }>(`/v1/qna/${id}`),
}

export interface CommunityReply {
  id: number
  post_id: number
  body: string
  anonymous_handle: string
  is_mine: boolean
  is_dodo: boolean
  liked: boolean
  like_count: number
  created_at: string | null
}

export interface CommunityPostDetail extends CommunityPost {
  replies: CommunityReply[]
}

export interface CommunityListMeta {
  current_page: number
  last_page: number
  total: number
}

export interface CommunityGateInfo {
  ok: boolean
  reason?: string
  hint?: string
  days_remaining?: number
  records_remaining?: number
}

export const CommunityApi = {
  list: (params?: { category?: CommunityCategory; sort?: CommunitySort; page?: number }) =>
    api.get<{ data: CommunityPost[]; meta: CommunityListMeta }>('/v1/community/posts', { params }),
  show: (id: number) =>
    api.get<{ data: CommunityPostDetail }>(`/v1/community/posts/${id}`),
  create: (payload: { category: CommunityCategory; title: string; body: string }) =>
    api.post<{ data: CommunityPost }>('/v1/community/posts', payload),
  remove: (id: number) =>
    api.delete<{ data: { deleted: true } }>(`/v1/community/posts/${id}`),
  reply: (postId: number, body: string) =>
    api.post<{ data: CommunityReply }>(`/v1/community/posts/${postId}/replies`, { body }),
  likePost: (id: number) =>
    api.post<{ data: { liked: boolean; like_count: number } }>(`/v1/community/posts/${id}/like`),
  likeReply: (id: number) =>
    api.post<{ data: { liked: boolean; like_count: number } }>(`/v1/community/replies/${id}/like`),
  report: (payload: {
    target_type: 'post' | 'reply'
    target_id: number
    reason: ReportReason
    message?: string
  }) =>
    api.post<{ data: { reported?: boolean; already_reported?: boolean } }>(
      '/v1/community/reports',
      payload,
    ),
}

// =====================================================================
// P5 Photo Journal — 進度照
// 隱私核心：metadata 寫 backend，binary 預設只在 device；Premium 才能 cloud sync。
// =====================================================================
export type PhotoJournalTag = 'face' | 'body' | 'note'

export interface PhotoJournalEntry {
  id: number
  tag: PhotoJournalTag
  captured_on: string // YYYY-MM-DD
  cycle_day: number | null
  phase: string | null
  note: string | null
  local_path: string | null
  thumb_blurhash: string | null
  cloud_synced: boolean
  cloud_url: string | null
  created_at: string | null
}

export interface PhotoJournalMonth {
  month: string
  count: number
  entries: PhotoJournalEntry[]
}

export const PhotoJournalApi = {
  create: (payload: {
    tag: PhotoJournalTag
    captured_on: string
    cycle_day?: number | null
    phase?: string | null
    note?: string | null
    local_path?: string | null
    thumb_blurhash?: string | null
  }) => api.post<{ data: PhotoJournalEntry }>('/v1/photo-journal', payload),

  list: (month: string) =>
    api.get<{ data: PhotoJournalMonth }>('/v1/photo-journal/list', { params: { month } }),

  show: (id: number) => api.get<{ data: PhotoJournalEntry }>(`/v1/photo-journal/${id}`),

  uploadCloud: (id: number, file: Blob) => {
    const fd = new FormData()
    fd.append('photo', file)
    return api.post<{ data: PhotoJournalEntry }>(
      `/v1/photo-journal/${id}/upload-cloud`,
      fd,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
  },

  remove: (id: number) =>
    api.delete<{ data: { deleted: true } }>(`/v1/photo-journal/${id}`),

  removeCloudOnly: (id: number) =>
    api.delete<{ data: PhotoJournalEntry }>(`/v1/photo-journal/${id}/cloud-only`),
}

// =====================================================================
// Game Depth — 朵朵幣 / 寵物羈絆 / 段位 / 路徑 / 圖鑑 / 故事 / 隨機事件 / 節氣
// 後端 endpoints 規劃中（agent A 同步寫），UI 寫 graceful empty state
// =====================================================================

export interface EconomyTransaction {
  id: number
  delta: number
  balance_after: number
  source: string
  reason: string | null
  created_at: string
}

export interface EconomyBalance {
  balance: number
}

export const EconomyApi = {
  balance: () => api.get<{ data: EconomyBalance }>('/v1/economy/balance'),
  history: (limit = 50) =>
    api.get<{ data: { transactions: EconomyTransaction[] } }>('/v1/economy/history', { params: { limit } }),
  spend: (item_code: string, cost: number) =>
    api.post<{ data: { balance: number; item_code: string } }>('/v1/economy/spend', { item_code, cost }),
}

export type IntimacyTier = 'stranger' | 'familiar' | 'friendly' | 'close' | 'soulmate' | 'legendary'

export interface PetBondState {
  species: string | null
  bond_xp: number
  bond_level: number
  intimacy_tier: IntimacyTier
  next_tier_at: number
  progress_percent: number
}

export const PetBondApi = {
  show: () => api.get<{ data: PetBondState }>('/v1/me/pet/bond'),
  feed: (item_code: string) =>
    api.post<{ data: PetBondState & { delta: number } }>('/v1/me/pet/feed', { item_code }),
  petHead: () => api.post<{ data: PetBondState & { delta: number } }>('/v1/me/pet/pet-head'),
}

export type RankTier = 'stone' | 'cream' | 'gold' | 'rose' | 'purple' | 'indigo'

export interface RankState {
  tier: RankTier
  tier_index: number
  xp: number
  next_threshold: number
  progress_percent: number
  philosophy?: string | null
}

export const RankApi = {
  show: () => api.get<{ data: RankState }>('/v1/me/rank'),
}

export type SkillPathKey = 'fertility' | 'wellness' | 'beauty' | null

export interface SkillQuest {
  key: string
  title: string
  description: string
  reward_coin: number
  reward_xp: number
  dodo_intro: string
  dodo_complete: string
  is_completed: boolean
  progress: number
  target: number
}

export interface SkillPathState {
  path: SkillPathKey
  progress: number
  total_quests: number
  changed_at: string | null
  can_change: boolean
}

export const SkillPathApi = {
  show: () => api.get<{ data: SkillPathState }>('/v1/me/skill-path'),
  choose: (path: NonNullable<SkillPathKey>) =>
    api.post<{ data: SkillPathState }>('/v1/me/skill-path', { path }),
  quests: () => api.get<{ data: { quests: SkillQuest[] } }>('/v1/me/skill-path/quests'),
}

export type DexRarity = 'common' | 'rare' | 'epic' | 'legendary'

export interface BodyDexEntry {
  code: string
  name: string
  emoji: string
  rarity: DexRarity
  description: string
  why_text: string
  comfort_action_keys: string[]
  dodo_companion: string
  collected: boolean
  count: number
  first_collected_at: string | null
}

export const BodyDexApi = {
  show: () => api.get<{ data: { collected: BodyDexEntry[]; locked: BodyDexEntry[]; total: number; collected_count: number } }>(
    '/v1/me/body-dex',
  ),
}

export interface StoryDialog {
  speaker: 'dodo' | 'user' | 'narration'
  text: string
}

export interface StoryChapter {
  chapter: number
  title: string
  emoji: string
  unlocked: boolean
  unlock_hint: string | null
  unlock_cost_coin: number | null
  read_at: string | null
  dialog?: StoryDialog[]
}

export const StoryApi = {
  chapters: () =>
    api.get<{ data: { unlocked: number[]; current: number; chapters: StoryChapter[] } }>('/v1/me/stories/chapters'),
  unlock: (chapter: number) =>
    api.post<{ data: { chapter: number; balance: number; chapter_data: StoryChapter } }>(
      `/v1/me/stories/${chapter}/unlock`,
    ),
  read: (chapter: number) =>
    api.post<{ data: { chapter: number } }>(`/v1/me/stories/${chapter}/read`),
}

export interface RandomEvent {
  id: number
  event_kind: string
  title: string
  emoji: string
  dodo_dialog: string
  reward_coin: number
  reward_xp: number
  expires_at: string | null
  claimed: boolean
}

export const RandomEventApi = {
  today: () => api.get<{ data: RandomEvent | null }>('/v1/me/random-event/today'),
  claim: (id: number) =>
    api.post<{ data: { id: number; balance: number; xp: number; reward_coin: number } }>(
      `/v1/me/random-event/${id}/claim`,
    ),
}

export interface SolarTermBanner {
  term_key: string
  term_name: string
  start_date: string
  end_date: string
  dodo_message: string
  reward_coin: number
  participated: boolean
}

export const SolarTermApi = {
  current: () => api.get<{ data: SolarTermBanner | null }>('/v1/solar-term/current'),
  participate: (term: string) =>
    api.post<{ data: { term: string; balance: number } }>(`/v1/solar-term/${term}/participate`),
}
