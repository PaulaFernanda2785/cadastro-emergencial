(function () {
    'use strict';

    var forms = document.querySelectorAll('.js-action-form');

    forms.forEach(function (form) {
        var payload = {};

        try {
            payload = JSON.parse(form.dataset.territory || '{}');
        } catch (error) {
            payload = {};
        }

        var municipalities = Array.isArray(payload.municipios) ? payload.municipios : [];
        var localities = payload.localidades || {};
        var stateSelect = form.querySelector('[data-state-select]');
        var municipalityInput = form.querySelector('[data-municipality-input]');
        var municipalityCode = form.querySelector('[data-municipality-code]');
        var municipalityList = form.querySelector('[data-municipality-list]');
        var localityInput = form.querySelector('[data-locality-input]');
        var localityList = form.querySelector('[data-locality-list]');

        if (!stateSelect || !municipalityInput || !municipalityCode || !municipalityList || !localityList) {
            return;
        }

        function labelFor(item) {
            return item.nome + ' / ' + item.uf;
        }

        function municipalitiesForState() {
            var uf = stateSelect.value;

            return municipalities.filter(function (item) {
                return item.uf === uf;
            });
        }

        function refreshMunicipalities() {
            var selectedCode = municipalityCode.value;
            var options = municipalitiesForState();

            municipalityList.innerHTML = '';

            options.forEach(function (item) {
                var option = document.createElement('option');
                option.value = labelFor(item);
                option.dataset.code = item.codigo_ibge;
                municipalityList.appendChild(option);
            });

            if (selectedCode) {
                var selected = municipalities.find(function (item) {
                    return item.codigo_ibge === selectedCode;
                });

                if (selected && selected.uf !== stateSelect.value) {
                    municipalityInput.value = '';
                    municipalityCode.value = '';
                    refreshLocalities();
                }
            }
        }

        function resolveMunicipality() {
            var value = municipalityInput.value.trim().toLocaleLowerCase('pt-BR');
            var selected = municipalitiesForState().find(function (item) {
                return labelFor(item).toLocaleLowerCase('pt-BR') === value ||
                    item.nome.toLocaleLowerCase('pt-BR') === value;
            });

            if (selected) {
                municipalityInput.value = labelFor(selected);
                municipalityCode.value = selected.codigo_ibge;
            } else {
                var currentByCode = municipalityCode.value
                    ? municipalities.find(function (item) {
                        return item.codigo_ibge === municipalityCode.value &&
                            labelFor(item).toLocaleLowerCase('pt-BR') === value;
                    })
                    : null;

                if (!currentByCode) {
                    municipalityCode.value = '';
                }
            }

            refreshLocalities();
        }

        function refreshLocalities() {
            var items = municipalityCode.value && Array.isArray(localities[municipalityCode.value])
                ? localities[municipalityCode.value]
                : [];

            localityList.innerHTML = '';

            items.forEach(function (name) {
                var option = document.createElement('option');
                option.value = name;
                localityList.appendChild(option);
            });
        }

        stateSelect.addEventListener('change', function () {
            municipalityInput.value = '';
            municipalityCode.value = '';
            refreshMunicipalities();
            refreshLocalities();
        });

        municipalityInput.addEventListener('change', resolveMunicipality);
        municipalityInput.addEventListener('blur', resolveMunicipality);

        form.addEventListener('submit', function (event) {
            resolveMunicipality();

            if (!municipalityCode.value) {
                event.preventDefault();
                municipalityInput.focus();
                form.dataset.processing = 'false';

                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = false;
                    button.classList.remove('is-processing');
                    button.removeAttribute('aria-busy');
                    var label = button.querySelector('.button-label');
                    if (label) {
                        label.textContent = 'Salvar ação';
                    }
                }
            }
        });

        refreshMunicipalities();

        if (municipalityCode.value) {
            var current = municipalities.find(function (item) {
                return item.codigo_ibge === municipalityCode.value;
            });

            if (current) {
                municipalityInput.value = labelFor(current);
            }
        } else {
            resolveMunicipality();
        }

        refreshLocalities();
    });
})();
