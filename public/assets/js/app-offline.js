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

        navigator.serviceWorker.register(swUrl).catch(function () {});
    });
})();
