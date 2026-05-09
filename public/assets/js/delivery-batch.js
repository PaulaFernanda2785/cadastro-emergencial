(function () {
    'use strict';

    document.querySelectorAll('[data-delivery-batch-form]').forEach(function (form) {
        var selectAll = form.querySelector('[data-batch-select-all]');
        var clearAll = form.querySelector('[data-batch-clear]');
        var counter = form.querySelector('[data-batch-counter]');

        function boxes() {
            return Array.prototype.slice.call(form.querySelectorAll('[data-batch-family]'));
        }

        function updateCounter() {
            var selected = boxes().filter(function (box) {
                return box.checked;
            }).length;

            if (counter) {
                counter.textContent = selected + ' selecionada(s)';
            }
        }

        if (selectAll) {
            selectAll.addEventListener('click', function () {
                boxes().forEach(function (box) {
                    if (!box.disabled) {
                        box.checked = true;
                    }
                });
                updateCounter();
            });
        }

        if (clearAll) {
            clearAll.addEventListener('click', function () {
                boxes().forEach(function (box) {
                    box.checked = false;
                });
                updateCounter();
            });
        }

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[data-batch-family]')) {
                updateCounter();
            }
        });

        updateCounter();
    });

    document.querySelectorAll('[data-delivery-items-form]').forEach(function (form) {
        function applyTypeState(option) {
            var toggle = option.querySelector('[data-delivery-type-toggle]');
            var fields = option.querySelector('[data-delivery-type-fields]');
            var inputs = option.querySelectorAll('[data-delivery-type-input]');
            var enabled = !!(toggle && toggle.checked);

            if (fields) {
                fields.hidden = !enabled;
            }

            inputs.forEach(function (input) {
                input.disabled = !enabled;
            });
        }

        form.querySelectorAll('[data-delivery-type-option]').forEach(function (option) {
            var toggle = option.querySelector('[data-delivery-type-toggle]');

            if (toggle) {
                toggle.addEventListener('change', function () {
                    applyTypeState(option);
                });
            }

            applyTypeState(option);
        });
    });

    document.querySelectorAll('[data-smart-search]').forEach(function (input) {
        var hidden = document.querySelector('[data-smart-hidden="' + input.dataset.smartTarget + '"]');
        var list = input.getAttribute('list') ? document.getElementById(input.getAttribute('list')) : null;

        function normalize(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        function syncHidden() {
            var value = input.value.trim();
            var normalizedValue = normalize(value);
            var match = null;
            var partialMatch = null;

            if (!hidden || !list) {
                return;
            }

            var idMatch = value.match(/a[cç][aã]o\s*#\s*(\d+)/i);

            Array.prototype.slice.call(list.options || []).some(function (option) {
                var optionId = option.dataset.id || '';
                var normalizedOption = normalize(option.value);

                if (idMatch && optionId === idMatch[1]) {
                    match = option;
                    return true;
                }

                if (option.value === value) {
                    match = option;
                    return true;
                }

                if (!partialMatch && normalizedValue !== '' && (
                    normalizedOption.indexOf(normalizedValue) !== -1 ||
                    normalizedValue.indexOf(normalizedOption) !== -1
                )) {
                    partialMatch = option;
                }

                return false;
            });

            match = match || partialMatch;
            hidden.value = match ? match.dataset.id || '' : '';
        }

        input.addEventListener('input', syncHidden);
        input.addEventListener('change', syncHidden);
        syncHidden();
    });
})();
