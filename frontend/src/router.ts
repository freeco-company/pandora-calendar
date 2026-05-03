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
  { path: '/me/jerosse', component: () => import('./views/JerosseDeep.vue') },
  { path: '/me/journey', component: () => import('./views/JourneyView.vue') },
  { path: '/me/bbt', component: () => import('./views/BbtView.vue') },
  { path: '/me/partner', component: () => import('./views/PartnerShareView.vue') },
  { path: '/partner/:token', component: () => import('./views/PartnerPublicView.vue'), meta: { public: true } },
  { path: '/privacy', component: () => import('./views/Privacy.vue'), meta: { public: true } },
  { path: '/terms', component: () => import('./views/Terms.vue'), meta: { public: true } },
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
