// PostFlow service worker: keeps the app shell available offline and shows reminders.
const CACHE = 'postflow-v1'
const OFFLINE_URL = '/offline.html'

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])))
    self.skipWaiting()
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
    )
    self.clients.claim()
})

self.addEventListener('fetch', (event) => {
    let request = event.request
    if (request.method !== 'GET') return

    let url = new URL(request.url)

    // Built CSS/JS/fonts never change name once built: cache first.
    if (url.origin === location.origin && url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                let copy = response.clone()
                caches.open(CACHE).then((cache) => cache.put(request, copy))
                return response
            }))
        )
        return
    }

    // Pages: always fresh from the network, offline page when there is no connection.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)))
    }
})

self.addEventListener('push', (event) => {
    let data = {}
    try { data = event.data ? event.data.json() : {} } catch (e) { data = { body: event.data?.text() } }

    event.waitUntil(self.registration.showNotification(data.title || 'PostFlow', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag,
        data: { url: data.url || '/today' },
    }))
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()
    let target = event.notification.data?.url || '/today'

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (let client of clients) {
                if ('focus' in client) {
                    client.navigate(target)
                    return client.focus()
                }
            }
            return self.clients.openWindow(target)
        })
    )
})
