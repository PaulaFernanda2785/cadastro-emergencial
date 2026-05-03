(function () {
    'use strict';

    var modal = document.querySelector('[data-action-qr-modal]');
    var title = document.querySelector('[data-action-qr-title]');
    var canvas = document.querySelector('[data-action-qr-canvas]');
    var registerLink = document.querySelector('[data-action-qr-register]');
    var sharedLink = document.querySelector('[data-action-qr-link]');
    var copyButton = document.querySelector('[data-action-qr-copy]');
    var shareButton = document.querySelector('[data-action-qr-share]');
    var copyStatus = document.querySelector('[data-action-qr-copy-status]');

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

    function normalizeRegisterUrl(value) {
        if (!value || value === '#') {
            return '#';
        }

        try {
            return new URL(value, window.location.origin).href;
        } catch (error) {
            return value;
        }
    }

    function shareText(link) {
        return 'Acesse o cadastro web desta ação emergencial:\n' + link;
    }

    document.querySelectorAll('[data-action-qr-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var actionTitle = button.dataset.title || 'Ação emergencial';
            var registerUrl = normalizeRegisterUrl(button.dataset.registerUrl || '#');

            title.textContent = actionTitle;
            registerLink.href = registerUrl;

            if (sharedLink) {
                sharedLink.value = registerUrl;
            }

            if (copyStatus) {
                copyStatus.textContent = '';
            }

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

    function setCopyStatus(message) {
        if (copyStatus) {
            copyStatus.textContent = message;
        }
    }

    function currentRegisterUrl() {
        return normalizeRegisterUrl(registerLink.href || (sharedLink ? sharedLink.value : ''));
    }

    function fallbackCopy(text) {
        if (!sharedLink) {
            return false;
        }

        sharedLink.focus();
        sharedLink.select();
        sharedLink.setSelectionRange(0, sharedLink.value.length);

        try {
            return document.execCommand('copy');
        } catch (error) {
            return false;
        }
    }

    if (copyButton) {
        copyButton.addEventListener('click', function () {
            var link = currentRegisterUrl();

            if (!link || link === '#') {
                setCopyStatus('Link indisponível.');
                return;
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(link).then(function () {
                    setCopyStatus('Link copiado.');
                }).catch(function () {
                    setCopyStatus(fallbackCopy(link) ? 'Link copiado.' : 'Não foi possível copiar automaticamente.');
                });
                return;
            }

            setCopyStatus(fallbackCopy(link) ? 'Link copiado.' : 'Não foi possível copiar automaticamente.');
        });
    }

    if (shareButton) {
        shareButton.addEventListener('click', function () {
            var link = currentRegisterUrl();

            if (!link || link === '#') {
                setCopyStatus('Link indisponível.');
                return;
            }

            if (navigator.share) {
                navigator.share({
                    title: title.textContent || 'Ação emergencial',
                    text: 'Acesse o cadastro web desta ação emergencial.',
                    url: link
                }).catch(function () {});
                return;
            }

            window.open('https://wa.me/?text=' + encodeURIComponent(shareText(link)), '_blank', 'noopener');
            setCopyStatus('Compartilhamento aberto pelo WhatsApp.');
        });
    }
})();
