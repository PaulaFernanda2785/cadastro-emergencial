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

    function localPhoneDigits(value) {
        var digits = String(value || '').replace(/\D+/g, '');

        if (digits.indexOf('55') === 0 && (digits.length === 12 || digits.length === 13)) {
            digits = digits.slice(2);
        } else if (digits.indexOf('0') === 0 && (digits.length === 13 || digits.length === 14)) {
            digits = digits.slice(3);
        } else if (digits.indexOf('0') === 0 && (digits.length === 11 || digits.length === 12)) {
            digits = digits.slice(1);
        }

        return digits.slice(0, 11);
    }

    function formatPhone(value) {
        var digits = localPhoneDigits(value);

        if (digits.length > 10) {
            return digits.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }

        if (digits.length > 6) {
            return digits.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        }

        if (digits.length > 2) {
            return digits.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        }

        if (digits.length > 0) {
            return '(' + digits;
        }

        return '';
    }

    document.querySelectorAll('[data-cpf-input]').forEach(function (input) {
        input.value = formatCpf(input.value);

        input.addEventListener('input', function () {
            input.value = formatCpf(input.value);
        });
    });

    document.querySelectorAll('[data-phone-input]').forEach(function (input) {
        input.value = formatPhone(input.value);

        input.addEventListener('input', function () {
            input.value = formatPhone(input.value);
        });

        input.addEventListener('blur', function () {
            var digits = localPhoneDigits(input.value);

            if (digits.length !== 0 && digits.length !== 10 && digits.length !== 11) {
                input.setCustomValidity('Informe DDD e numero valido. Exemplo: (85) 99999-9999.');
                input.reportValidity();
                return;
            }

            input.setCustomValidity('');
            input.value = formatPhone(input.value);
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
