const CACHE_NAME = 'cadastro-emergencial-v20260509-025';
const CORE_ASSETS = [
    './manifest.webmanifest',
    './assets/css/app.css',
    './assets/js/app-offline.js',
    './assets/js/modals.js',
    './assets/js/forms.js',
    './assets/js/layout.js',
    './assets/js/dashboard-map.js',
    './assets/js/geolocation.js',
    './assets/js/action-form.js',
    './assets/js/residence-form.js',
    './assets/js/residence-detail.js',
    './assets/js/family-form.js',
    './assets/js/offline-queue.js',
    './assets/js/qrcode.bundle.js',
    './assets/js/actions-index.js',
    './assets/js/delivery-qr-scanner.js',
    './assets/js/delivery-batch.js',
    './assets/js/family-receipt.js',
    './assets/images/app-icon-192.png',
    './assets/images/app-icon-512.png',
    './assets/images/app-icon-180.png',
    './assets/images/app-icon-maskable-512.png',
    './assets/images/logo-cedec.png',
    './assets/images/logo-cadastro-emergencial-app.png',
    './assets/images/arrow-up.svg',
    './favicon.ico',
    './assets/images/logo.cbmpa.ico'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(CORE_ASSETS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key.indexOf('cadastro-emergencial-') === 0 && key !== CACHE_NAME) {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    var url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(fetch(request));
        return;
    }

    if (
        url.pathname.indexOf('/assets/') === -1
        && !url.pathname.endsWith('/manifest.webmanifest')
    ) {
        return;
    }

    event.respondWith(
        fetch(request).then(function (response) {
            var copy = response.clone();

            if (response.ok) {
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(request, copy);
                });
            }

            return response;
        }).catch(function () {
            return caches.match(request);
        })
    );
});
