(function () {
    'use strict';

    var shell = document.querySelector('[data-layout-shell]');
    var toggle = document.querySelector('[data-sidebar-toggle]');

    if (!shell || !toggle) {
        return;
    }

    var storageKey = 'cadastroEmergencial.sidebarCollapsed';
    var collapsed = window.localStorage.getItem(storageKey) === 'true';

    function applyState(isCollapsed) {
        shell.classList.toggle('is-sidebar-collapsed', isCollapsed);
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
