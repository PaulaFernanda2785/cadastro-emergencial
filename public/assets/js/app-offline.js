(function () {
    'use strict';

    var CACHE_NAME = 'cadastro-emergencial-v20260502-86';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    function clearCadastroCaches() {
        if (!window.caches || typeof caches.keys !== 'function') {
            return;
        }

        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key.indexOf('cadastro-emergencial-') === 0) {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        }).catch(function () {});
    }

    function clearOldDynamicCaches() {
        if (!window.caches || typeof caches.keys !== 'function') {
            return;
        }

        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key.indexOf('cadastro-emergencial-') === 0 && key !== CACHE_NAME) {
                    return caches.delete(key);
                }

                return Promise.resolve();
            }));
        }).catch(function () {});
    }

    function shouldUseOfflineWorker() {
        return Boolean(document.querySelector('[data-offline-queue-form]'));
    }

    function disableOfflineWorker() {
        if (!navigator.serviceWorker.getRegistrations) {
            clearCadastroCaches();
            return;
        }

        navigator.serviceWorker.getRegistrations().then(function (registrations) {
            registrations.forEach(function (registration) {
                var scriptUrl = registration.active
                    ? registration.active.scriptURL
                    : registration.installing
                        ? registration.installing.scriptURL
                        : registration.waiting
                            ? registration.waiting.scriptURL
                            : '';

                if (scriptUrl.indexOf('/sw.js') !== -1) {
                    registration.unregister().catch(function () {});
                }
            });
            clearCadastroCaches();
        }).catch(function () {
            clearCadastroCaches();
        });
    }

    window.addEventListener('load', function () {
        if (!shouldUseOfflineWorker()) {
            disableOfflineWorker();
            return;
        }

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
