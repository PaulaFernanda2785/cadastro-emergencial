(function () {
    'use strict';

    var shell = document.querySelector('[data-layout-shell]');
    var toggle = document.querySelector('[data-sidebar-toggle]');

    if (!shell || !toggle) {
        return;
    }

    var storageKey = 'cadastroEmergencial.sidebarCollapsed';
    var storedPreference = window.localStorage.getItem(storageKey);
    var startsOnSmallScreen = window.matchMedia('(max-width: 760px)').matches;
    var collapsed = storedPreference === null ? startsOnSmallScreen : storedPreference === 'true';

    function applyState(isCollapsed) {
        shell.classList.toggle('is-sidebar-collapsed', isCollapsed);
        document.documentElement.classList.remove('sidebar-collapsed-initial');
        toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', isCollapsed ? 'Expandir menu' : 'Recolher menu');
    }

    applyState(collapsed);

    toggle.addEventListener('click', function () {
        collapsed = !shell.classList.contains('is-sidebar-collapsed');
        window.localStorage.setItem(storageKey, String(collapsed));
        applyState(collapsed);
    });
})();
