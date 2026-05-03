// 潘朵拉月曆 — Web Push Service Worker
// 收到 push event → 顯示 notification（標題 + 內容由 backend payload 決定）

self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('push', (event) => {
  let data = {}
  try {
    data = event.data ? event.data.json() : {}
  } catch (e) {
    data = { title: '潘朵拉月曆', body: event.data?.text() || '朵朵想跟妳說話' }
  }

  const title = data.title || '潘朵拉月曆'
  const options = {
    body: data.body || '',
    icon: data.icon || '/character/anchors/dodo.png',
    badge: '/favicon.svg',
    tag: data.tag || 'pandora-calendar',
    data: { url: data.url || '/#/dodo' },
    requireInteraction: false,
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const targetUrl = event.notification.data?.url || '/'
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(targetUrl)
          return client.focus()
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl)
      }
    }),
  )
})
