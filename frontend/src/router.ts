import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import { getToken } from './api'

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: '/calendar' },
  { path: '/login', component: () => import('./views/Login.vue'), meta: { public: true } },
  { path: '/calendar', component: () => import('./views/Calendar.vue') },
  { path: '/log', component: () => import('./views/Log.vue') },
  { path: '/dodo', component: () => import('./views/Dodo.vue') },
  { path: '/me', component: () => import('./views/Profile.vue') },
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach((to) => {
  if (!to.meta.public && !getToken()) {
    return { path: '/login', query: { from: to.fullPath } }
  }
})
