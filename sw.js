const CACHE_NAME = 'anikatsu-shell-v3';
const SHELL_ASSETS = [
  '/anikatsu/files/css/anikami.css'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(SHELL_ASSETS).catch(function() { return; });
    }).then(function() {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames
          .filter(function(name) { return name !== CACHE_NAME; })
          .map(function(name) { return caches.delete(name); })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function(event) {
  var request = event.request;

  // Never intercept navigation requests — let the browser handle page loads directly.
  if (request.mode === 'navigate') {
    return;
  }

  var requestUrl;
  try {
    requestUrl = new URL(request.url);
  } catch (e) {
    return;
  }

  // Only handle same-origin requests.
  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  // Never cache API, PHP, or POST requests.
  if (request.method !== 'GET') return;
  if (requestUrl.pathname.indexOf('/api/') !== -1) return;
  if (requestUrl.pathname.endsWith('.php')) return;

  var isCssRequest = request.destination === 'style' || requestUrl.pathname.endsWith('.css');

  if (isCssRequest) {
    // Network-first for CSS so updates get through.
    event.respondWith(
      fetch(request).then(function(networkResponse) {
        if (networkResponse && networkResponse.status === 200) {
          var clone = networkResponse.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(request, clone);
          });
        }
        return networkResponse;
      }).catch(function() {
        return caches.match(request).then(function(r) { return r || fetch(request); });
      })
    );
    return;
  }

  // Cache-first for other static assets (fonts, images, JS).
  var isStaticAsset = request.destination === 'script'
    || request.destination === 'image'
    || request.destination === 'font'
    || requestUrl.pathname.indexOf('/files/') !== -1;

  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then(function(response) {
        return response || fetch(request).then(function(networkResponse) {
          if (networkResponse && networkResponse.status === 200) {
            var clone = networkResponse.clone();
            caches.open(CACHE_NAME).then(function(cache) { cache.put(request, clone); });
          }
          return networkResponse;
        }).catch(function() { return new Response('', {status: 503}); });
      })
    );
    return;
  }
});
