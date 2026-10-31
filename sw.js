const CACHE_NAME = 'bengalseva-cache-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/manifest.json',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/images/logo.png'
];

// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  return self.clients.claim();
});

// Network-First Fetch Strategy
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Cache successful responses for assets
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // Fallback response if offline
          return new Response(
            '<div style="text-align:center;padding:50px;font-family:sans-serif;"><h2>You are currently offline</h2><p>Please check your internet connection to access BengalSeva.</p><button onclick="location.reload()" style="padding:10px 20px;font-weight:bold;background:#0d6efd;color:#fff;border:none;border-radius:6px;">Retry</button></div>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        });
      })
  );
});