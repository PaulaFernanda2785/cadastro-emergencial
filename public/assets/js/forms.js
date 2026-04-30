(function () {
    'use strict';

    var forms = document.querySelectorAll('.js-prevent-double-submit');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }

            if (form.dataset.processing === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.processing = 'true';

            var button = form.querySelector('button[type="submit"]');

            if (!button) {
                return;
            }

            var loadingText = button.dataset.loadingText || 'Processando...';
            var label = button.querySelector('.button-label');

            if (label) {
                label.textContent = loadingText;
            } else {
                button.textContent = loadingText;
            }

            button.disabled = true;
            button.classList.add('is-processing');
            button.setAttribute('aria-busy', 'true');
        });
    });
})();
