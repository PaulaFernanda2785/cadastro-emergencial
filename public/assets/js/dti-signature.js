(function () {
    'use strict';

    document.querySelectorAll('[data-dti-cosigner-picker]').forEach(function (picker) {
        var search = picker.querySelector('[data-dti-cosigner-search]');
        var selectedBox = picker.querySelector('[data-dti-cosigner-selected]');
        var status = picker.querySelector('[data-dti-cosigner-status]');
        var options = Array.prototype.slice.call(picker.querySelectorAll('[data-dti-cosigner-option]'));
        var inputName = picker.getAttribute('data-dti-cosigner-name') || 'assinantes_usuarios[]';
        var emptyText = picker.getAttribute('data-dti-cosigner-empty') || 'Nenhum coassinante selecionado.';
        var searchText = picker.getAttribute('data-dti-cosigner-search-text') || 'Digite para buscar usuarios do sistema.';
        var selected = new Map();

        function notifySelectedChange() {
            picker.dispatchEvent(new CustomEvent('dti-cosigner-change', {
                bubbles: true,
                detail: {
                    count: selected.size,
                    inputName: inputName
                }
            }));
        }

        function normalize(value) {
            return (value || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function renderSelected() {
            if (!selectedBox) {
                return;
            }

            selectedBox.innerHTML = '';

            if (selected.size === 0) {
                var empty = document.createElement('span');
                empty.textContent = emptyText;
                selectedBox.appendChild(empty);
                notifySelectedChange();
                return;
            }

            selected.forEach(function (item, id) {
                var chip = document.createElement('span');
                var hidden = document.createElement('input');
                var text = document.createElement('strong');
                var remove = document.createElement('button');

                chip.className = 'dti-cosigner-chip';
                hidden.type = 'hidden';
                hidden.name = inputName;
                hidden.value = id;
                text.textContent = item.label;
                remove.type = 'button';
                remove.textContent = 'Remover';
                remove.addEventListener('click', function () {
                    selected.delete(id);
                    renderSelected();
                    renderOptions();
                });

                chip.appendChild(hidden);
                chip.appendChild(text);
                chip.appendChild(remove);
                selectedBox.appendChild(chip);
            });

            notifySelectedChange();
        }

        function renderOptions() {
            var term = normalize(search ? search.value : '');
            var visibleCount = 0;

            if (status && term === '') {
                status.textContent = searchText;
            }

            options.forEach(function (button) {
                var id = button.dataset.id || '';
                var text = normalize(button.dataset.search || button.textContent || '');
                var visible = term !== '' && !selected.has(id) && text.indexOf(term) !== -1;

                button.hidden = !visible;

                if (visible) {
                    visibleCount += 1;
                }
            });

            if (status && term !== '') {
                status.textContent = visibleCount > 0
                    ? visibleCount + ' usuário(s) encontrado(s).'
                    : 'Nenhum usuário encontrado para esta busca.';
            }
        }

        options.forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.dataset.id || '';

                if (id === '' || selected.has(id)) {
                    return;
                }

                selected.set(id, {
                    label: button.dataset.label || button.textContent.trim(),
                    meta: button.dataset.meta || ''
                });

                if (search) {
                    search.value = '';
                    search.focus();
                }

                renderSelected();
                renderOptions();
            });
        });

        if (search) {
            search.addEventListener('input', renderOptions);
        }

        renderSelected();
        renderOptions();
    });
})();
