(function () {
    'use strict';

    var forms = document.querySelectorAll('.js-prevent-double-submit');

    function formatCpf(value) {
        var digits = String(value || '').replace(/\D+/g, '').slice(0, 11);

        if (digits.length > 9) {
            return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
        }

        if (digits.length > 6) {
            return digits.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
        }

        if (digits.length > 3) {
            return digits.replace(/(\d{3})(\d{0,3})/, '$1.$2');
        }

        return digits;
    }

    document.querySelectorAll('[data-cpf-input]').forEach(function (input) {
        input.value = formatCpf(input.value);

        input.addEventListener('input', function () {
            input.value = formatCpf(input.value);
        });
    });

    document.querySelectorAll('[data-military-toggle]').forEach(function (toggle) {
        var form = toggle.closest('form') || document;
        var fields = form.querySelector('[data-military-fields]');
        var inputs = form.querySelectorAll('[data-military-input]');

        function applyState() {
            var enabled = toggle.checked;

            if (fields) {
                fields.hidden = !enabled;
            }

            inputs.forEach(function (input) {
                input.disabled = !enabled;
            });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    });

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
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
