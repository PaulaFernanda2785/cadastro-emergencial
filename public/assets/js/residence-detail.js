(function () {
    'use strict';

    var modal = document.querySelector('[data-residence-image-modal]');
    var image = document.querySelector('[data-residence-image]');
    var title = document.querySelector('[data-residence-image-title]');
    var triggers = document.querySelectorAll('[data-residence-image-open]');

    if (!modal || !image || triggers.length === 0) {
        return;
    }

    function closeModal() {
        if (typeof modal.close === 'function') {
            modal.close();
        } else {
            modal.removeAttribute('open');
        }
        image.removeAttribute('src');
        document.body.classList.remove('is-photo-preview-open');
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            image.src = trigger.dataset.imageSrc || '';

            if (title) {
                title.textContent = trigger.dataset.imageTitle || 'Imagem';
            }

            document.body.classList.add('is-photo-preview-open');

            if (typeof modal.showModal === 'function') {
                modal.showModal();
                return;
            }

            modal.setAttribute('open', 'open');
        });
    });

    modal.addEventListener('close', function () {
        image.removeAttribute('src');
        document.body.classList.remove('is-photo-preview-open');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.open) {
            closeModal();
        }
    });
})();
