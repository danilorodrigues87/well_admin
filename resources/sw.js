/* Well Admin PWA — service worker (placeholders substituídos em /sw.js) */
var CACHE_NAME = '__WELL_PWA_CACHE__';
var BASE = '__WELL_PWA_BASE__';

var PRECACHE = [
  BASE + '/resources/css/styles.css',
  BASE + '/resources/css/panel-theme.css',
  BASE + '/resources/css/panel-mobile.css',
  BASE + '/resources/assets/imgs/logo.png',
  BASE + '/resources/assets/imgs/favicon.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE.filter(Boolean)).catch(function () {
        /* offline parcial ok */
      });
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) {
          return caches.delete(k);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  if (req.method !== 'GET') {
    return;
  }
  var url = new URL(req.url);
  if (url.pathname.indexOf('/painel') === 0 || url.pathname.indexOf('/api/') === 0) {
    return;
  }
  if (url.pathname.indexOf('/resources/') === -1) {
    return;
  }
  event.respondWith(
    caches.match(req).then(function (cached) {
      if (cached) {
        return cached;
      }
      return fetch(req).then(function (res) {
        if (!res || res.status !== 200 || res.type !== 'basic') {
          return res;
        }
        var clone = res.clone();
        caches.open(CACHE_NAME).then(function (cache) {
          cache.put(req, clone);
        });
        return res;
      });
    })
  );
});
