(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    function clearOldDynamicCaches() {
        if (!window.caches || typeof caches.keys !== 'function') {
            return;
        }

        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key.indexOf('cadastro-emergencial-') === 0 && key !== 'cadastro-emergencial-v20260501-35') {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        }).catch(function () {});
    }

    window.addEventListener('load', function () {
        clearOldDynamicCaches();

        var script = document.currentScript || document.querySelector('script[src*="/assets/js/app-offline.js"]');
        var swUrl = script && script.src
            ? script.src.replace('/assets/js/app-offline.js', '/sw.js')
            : '/sw.js';

        navigator.serviceWorker.register(swUrl).then(function (registration) {
            registration.update().catch(function () {});

            if (registration.waiting) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
            }

            registration.addEventListener('updatefound', function () {
                var worker = registration.installing;

                if (!worker) {
                    return;
                }

                worker.addEventListener('statechange', function () {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        worker.postMessage({ type: 'SKIP_WAITING' });
                    }
                });
            });
        }).catch(function () {});
    });
})();
