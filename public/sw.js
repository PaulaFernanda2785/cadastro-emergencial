const CACHE_NAME = 'cadastro-emergencial-v1';
const CORE_ASSETS = [
    './assets/css/app.css',
    './assets/js/forms.js',
    './assets/js/layout.js',
    './assets/js/geolocation.js',
    './assets/js/action-form.js',
    './assets/js/offline-queue.js',
    './assets/js/qrcode.bundle.js',
    './assets/js/actions-index.js',
    './assets/images/logo-cedec.png',
    './assets/images/logo.cbmpa.ico'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(CORE_ASSETS);
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
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
            return caches.match(request).then(function (cached) {
                return cached || caches.match('./');
            });
        })
    );
});
