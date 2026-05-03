import * as Sentry from '@sentry/capacitor'
import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import { getToken } from './api'

const ONBOARDING_DONE_KEY = 'pandora_calendar_onboarding_done'

export function isOnboardingDone(): boolean {
  try {
    return localStorage.getItem(ONBOARDING_DONE_KEY) === '1'
  } catch {
    return true // 私密模式，避免導向 loop
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/calendar' },
  { path: '/login', component: () => import('./views/Login.vue'), meta: { public: true } },
  { path: '/onboarding', component: () => import('./views/Onboarding.vue') },
  { path: '/calendar', component: () => import('./views/Calendar.vue') },
  { path: '/log', component: () => import('./views/Log.vue') },
  { path: '/dodo', component: () => import('./views/Dodo.vue') },
  { path: '/me', component: () => import('./views/Profile.vue') },
  { path: '/me/premium', component: () => import('./views/Paywall.vue') },
  { path: '/me/week-report', component: () => import('./views/WeekReportView.vue') },
  { path: '/me/pms', component: () => import('./views/PmsView.vue') },
  { path: '/me/pregnancy', component: () => import('./views/PregnancyModeView.vue') },
  { path: '/me/jerosse', component: () => import('./views/JerosseDeep.vue') },
  { path: '/me/qna', name: 'qna', component: () => import('./views/QnaView.vue') },
  // P5 — 新版婕樂纖會員深層頁（走 /v1/ecommerce/* endpoints + gate composable）
  { path: '/me/jerosse-member', component: () => import('./views/EcommerceMemberView.vue') },
  { path: '/me/journey', component: () => import('./views/JourneyView.vue') },
  { path: '/me/action-today', name: 'action-today', component: () => import('./views/DailyActionView.vue') },
  { path: '/me/pattern-report', name: 'pattern-report', component: () => import('./views/PatternReportView.vue') },
  { path: '/me/bbt', component: () => import('./views/BbtView.vue') },
  { path: '/me/health-integration', component: () => import('./views/HealthIntegrationView.vue') },
  { path: '/me/photo-journal', component: () => import('./views/PhotoJournalView.vue') },
  { path: '/me/partner', component: () => import('./views/PartnerShareView.vue') },
  { path: '/partner/:token', component: () => import('./views/PartnerPublicView.vue'), meta: { public: true } },
  { path: '/privacy', component: () => import('./views/Privacy.vue'), meta: { public: true } },
  { path: '/terms', component: () => import('./views/Terms.vue'), meta: { public: true } },
  // Wave 1 — 衛教 / 安全資訊不擋未登入用戶；FAQ 同理（防爬蟲也方便客服分享連結）
  { path: '/faq', component: () => import('./views/FaqView.vue'), meta: { public: true } },
  { path: '/health-check', component: () => import('./views/MedicalSafetyView.vue'), meta: { public: true } },
  { path: '/feedback', component: () => import('./views/FeedbackView.vue') },
  { path: '/community', name: 'community', component: () => import('./views/CommunityListView.vue') },
  { path: '/community/new', name: 'community-new', component: () => import('./views/CommunityCreateView.vue') },
  { path: '/community/:id', name: 'community-detail', component: () => import('./views/CommunityDetailView.vue') },
  { path: '/year-review/:year?', component: () => import('./views/YearReviewView.vue'), meta: { hideTabBar: true } },
  { path: '/subscription/cancel', component: () => import('./views/CancelInterceptView.vue') },
  // Lock deep-link fallback（biometric 失敗時跳這裡）
  { path: '/lock', component: () => import('./views/Lock.vue'), meta: { hideTabBar: true, public: true } },
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach((to) => {
  if (!to.meta.public && !getToken()) {
    return { path: '/login', query: { from: to.fullPath } }
  }
  // 已登入但還沒走完 onboarding → 強制導向（除非正在 onboarding 頁本身）
  if (
    getToken() &&
    !isOnboardingDone() &&
    to.path !== '/onboarding' &&
    to.path !== '/login' &&
    !to.meta.public
  ) {
    return { path: '/onboarding' }
  }
})

// navigation breadcrumb（health route → redact）
const ROUTER_HEALTH_SEGMENTS = [
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
  '/log', // Log.vue 是症狀 / 經期記錄頁
  '/dodo', // DodoCheckin 進入頁
  '/photo-journal', // P5 進度照（極私密 — Sentry breadcrumb redact）
  '/qna', // P4 含金量 Q&A — 用戶開放問題（敏感）
]

function redactRoutePath(p: string): string {
  const lower = p.toLowerCase()
  for (const seg of ROUTER_HEALTH_SEGMENTS) {
    if (lower.includes(seg)) return '[health-route]'
  }
  return p
}

router.afterEach((to) => {
  Sentry.addBreadcrumb({
    category: 'navigation',
    level: 'info',
    message: redactRoutePath(to.path),
    data: {},
  })
})
