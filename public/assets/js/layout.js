(function () {
    'use strict';

    var shell = document.querySelector('[data-layout-shell]');
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var scrollToMainKey = 'cadastroEmergencial.scrollToMainAfterNavigation';
    var scrollToFilterKey = 'cadastroEmergencial.scrollToFilterAfterSubmit';

    function scrollToMainContent() {
        var main = document.querySelector('.main');

        if (!main) {
            return;
        }

        main.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    if (window.sessionStorage.getItem(scrollToMainKey) === 'true') {
        window.sessionStorage.removeItem(scrollToMainKey);
        window.setTimeout(scrollToMainContent, 120);
    }

    function filterPanels() {
        return document.querySelectorAll('[class*="-filter-panel"], .records-filter-panel');
    }

    function isFilterForm(form) {
        var method = String(form.getAttribute('method') || 'get').toLowerCase();
        var classes = String(form.className || '');

        return method === 'get'
            && (classes.indexOf('-filter-form') !== -1 || form.closest('[class*="-filter-panel"], .records-filter-panel'));
    }

    function scrollToElement(element, behavior) {
        var topbar = document.querySelector('.topbar');
        var topbarHeight = topbar ? topbar.offsetHeight : 0;
        var targetTop = Math.max(0, element.getBoundingClientRect().top + window.pageYOffset - topbarHeight - 16);

        window.scrollTo({
            top: targetTop,
            behavior: behavior || 'smooth'
        });
    }

    function restoreFilterPosition() {
        var raw = window.sessionStorage.getItem(scrollToFilterKey);
        var data;
        var current;
        var panels;
        var target;

        if (!raw) {
            return;
        }

        window.sessionStorage.removeItem(scrollToFilterKey);

        try {
            data = JSON.parse(raw);
            current = new URL(window.location.href);
        } catch (error) {
            return;
        }

        if (!data || data.origin !== current.origin || data.pathname !== current.pathname) {
            return;
        }

        panels = filterPanels();
        target = panels[Math.max(0, parseInt(data.panelIndex || '0', 10))] || panels[0];

        if (target) {
            window.setTimeout(function () {
                scrollToElement(target, 'auto');
            }, 80);
        }
    }

    function storeFilterPosition(targetUrl, panel) {
        var panels = Array.prototype.slice.call(filterPanels());
        var panelIndex = Math.max(0, panels.indexOf(panel));

        window.sessionStorage.setItem(scrollToFilterKey, JSON.stringify({
            origin: targetUrl.origin,
            pathname: targetUrl.pathname,
            panelIndex: panelIndex
        }));
    }

    document.querySelectorAll('form').forEach(function (form) {
        if (!isFilterForm(form)) {
            return;
        }

        form.addEventListener('submit', function () {
            var targetUrl = new URL(form.action || window.location.href, window.location.href);
            var panel = form.closest('[class*="-filter-panel"], .records-filter-panel');

            storeFilterPosition(targetUrl, panel);
        });
    });

    filterPanels().forEach(function (panel) {
        panel.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var targetUrl;

                if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                targetUrl = new URL(link.href, window.location.href);
                if (targetUrl.origin !== window.location.origin) {
                    return;
                }

                storeFilterPosition(targetUrl, panel);
            });
        });
    });

    restoreFilterPosition();

    if (shell && toggle) {
        var storageKey = 'cadastroEmergencial.sidebarCollapsed';
        var storedPreference = window.localStorage.getItem(storageKey);
        var smallScreenQuery = window.matchMedia('(max-width: 760px)');
        var startsOnSmallScreen = smallScreenQuery.matches;
        var collapsed = storedPreference === null ? startsOnSmallScreen : storedPreference === 'true';

        function isSmallScreen() {
            return smallScreenQuery.matches;
        }

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

        document.querySelectorAll('.sidebar-link[href]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var targetUrl;

                if (!isSmallScreen() || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                targetUrl = new URL(link.href, window.location.href);
                if (targetUrl.origin !== window.location.origin) {
                    return;
                }

                window.localStorage.setItem(storageKey, 'true');
                window.sessionStorage.setItem(scrollToMainKey, 'true');
                collapsed = true;
                applyState(true);
            });
        });
    }

    var backToTop = document.querySelector('[data-back-to-top]');
    if (backToTop) {
        var updateBackToTop = function () {
            backToTop.classList.toggle('is-visible', window.scrollY > 360);
        };

        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', updateBackToTop, { passive: true });
        window.addEventListener('resize', updateBackToTop);
        updateBackToTop();
    }

    var timeoutSeconds = parseInt(document.body.getAttribute('data-session-timeout-seconds') || '0', 10);
    var logoutUrl = document.body.getAttribute('data-logout-url') || '';
    var csrfToken = document.body.getAttribute('data-csrf-token') || '';
    var inactivityTimer = null;
    var hasLoggedOut = false;

    function secureLogout() {
        if (hasLoggedOut || !logoutUrl || !csrfToken) {
            return;
        }

        hasLoggedOut = true;
        var form = document.createElement('form');
        var input = document.createElement('input');

        form.method = 'post';
        form.action = logoutUrl;
        form.style.display = 'none';

        input.type = 'hidden';
        input.name = '_csrf_token';
        input.value = csrfToken;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }

    function resetInactivityTimer() {
        if (timeoutSeconds <= 0 || hasLoggedOut) {
            return;
        }

        window.clearTimeout(inactivityTimer);
        inactivityTimer = window.setTimeout(secureLogout, timeoutSeconds * 1000);
    }

    if (timeoutSeconds > 0 && logoutUrl && csrfToken) {
        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart', 'focus'].forEach(function (eventName) {
            window.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });
        resetInactivityTimer();
    }
})();
