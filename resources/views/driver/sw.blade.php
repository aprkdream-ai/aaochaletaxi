/* AaoChaleTaxi driver service worker — version {{ $version }} */
const CACHE = 'driver-{{ $version }}';
const PRECACHE = @json($assets);
const OFFLINE = @json(parse_url(route('driver.offline'), PHP_URL_PATH));

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('driver-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Hashed build assets & icons: cache-first.
    if (url.pathname.startsWith('/build/') || /\.(png|svg|woff2?)$/.test(url.pathname)) {
        e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
            return res;
        })));
        return;
    }

    // Pages: always network (trip data must be fresh); offline page when there is no connection.
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    }
});

// FCM / Web Push: payload {notification:{title,body}, data:{link}} (FCM v1 webpush format).
self.addEventListener('push', (e) => {
    let p = {};
    try { p = e.data ? e.data.json() : {}; } catch (err) { p = { notification: { title: 'AaoChaleTaxi', body: e.data ? e.data.text() : '' } }; }
    const n = p.notification || {};
    const data = p.data || {};
    e.waitUntil(self.registration.showNotification(n.title || 'New trip', {
        body: n.body || '', icon: '/icon-192.png', badge: '/icon-192.png', tag: data.booking || 'trip',
        renotify: true, vibrate: [200, 100, 200], data: { link: data.link || (p.fcmOptions && p.fcmOptions.link) || '/driver' },
    }));
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const link = (e.notification.data && e.notification.data.link) || '/driver';
    e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const c of list) { if (c.url.includes('/driver') && 'focus' in c) { c.navigate(link); return c.focus(); } }
        return self.clients.openWindow(link);
    }));
});
