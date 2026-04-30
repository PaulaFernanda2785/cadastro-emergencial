(function () {
    'use strict';

    var modal = document.querySelector('[data-action-qr-modal]');
    var title = document.querySelector('[data-action-qr-title]');
    var canvas = document.querySelector('[data-action-qr-canvas]');
    var registerLink = document.querySelector('[data-action-qr-register]');

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.dataset.confirm || 'Confirmar esta ação?';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    });

    if (!modal || !title || !canvas || !registerLink) {
        return;
    }

    document.querySelectorAll('[data-action-qr-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var actionTitle = button.dataset.title || 'Ação emergencial';
            var registerUrl = button.dataset.registerUrl || '#';

            title.textContent = actionTitle;
            registerLink.href = registerUrl;

            if (window.QRCode && typeof window.QRCode.toCanvas === 'function') {
                window.QRCode.toCanvas(canvas, registerUrl, {
                    errorCorrectionLevel: 'M',
                    margin: 2,
                    width: 260
                }, function () {});
            }

            if (typeof modal.showModal === 'function') {
                modal.showModal();
                return;
            }

            modal.setAttribute('open', 'open');
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            if (typeof modal.close === 'function') {
                modal.close();
                return;
            }

            modal.removeAttribute('open');
        }
    });
})();
