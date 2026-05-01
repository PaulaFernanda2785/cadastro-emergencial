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
                    box.checked = true;
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

    document.querySelectorAll('[data-smart-search]').forEach(function (input) {
        var hidden = document.querySelector('[data-smart-hidden="' + input.dataset.smartTarget + '"]');
        var list = input.getAttribute('list') ? document.getElementById(input.getAttribute('list')) : null;

        function syncHidden() {
            var value = input.value.trim();
            var match = null;

            if (!hidden || !list) {
                return;
            }

            Array.prototype.slice.call(list.options || []).some(function (option) {
                if (option.value === value) {
                    match = option;
                    return true;
                }

                return false;
            });

            hidden.value = match ? match.dataset.id || '' : '';
        }

        input.addEventListener('input', syncHidden);
        input.addEventListener('change', syncHidden);
        syncHidden();
    });
})();
