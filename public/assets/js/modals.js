(function () {
    'use strict';

    function isOpen(modal) {
        if (!modal) {
            return false;
        }

        if (modal.tagName === 'DIALOG') {
            return Boolean(modal.open);
        }

        return modal.hidden === false || modal.hasAttribute('open');
    }

    function closeModal(modal) {
        if (!modal || !isOpen(modal)) {
            return;
        }

        if (modal.tagName === 'DIALOG') {
            if (typeof modal.close === 'function') {
                modal.close();
            } else {
                modal.removeAttribute('open');
            }
            return;
        }

        modal.hidden = true;
        modal.removeAttribute('open');
        document.body.classList.remove('is-photo-preview-open');
        modal.dispatchEvent(new CustomEvent('modal:closed', { bubbles: true }));
    }

    function closestModal(element) {
        return element instanceof Element
            ? element.closest('dialog, [role="dialog"], .photo-preview-modal')
            : null;
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        if (target.matches('[data-modal-close], .qr-modal-close, .residence-image-modal-close, .family-doc-modal-close, .photo-preview-modal-close')) {
            closeModal(closestModal(target));
            return;
        }

        if (target.matches('dialog, [role="dialog"], .photo-preview-modal')) {
            closeModal(target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var openModals = Array.prototype.slice.call(document.querySelectorAll('dialog, [role="dialog"], .photo-preview-modal'))
            .filter(isOpen);
        var topModal = openModals[openModals.length - 1];

        if (topModal) {
            closeModal(topModal);
        }
    });
})();
