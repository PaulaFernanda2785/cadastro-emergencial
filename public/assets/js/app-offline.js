(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', function () {
        var script = document.currentScript || document.querySelector('script[src$="/assets/js/app-offline.js"]');
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
