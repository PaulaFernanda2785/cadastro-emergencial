(function () {
    'use strict';

    var drawer = document.querySelector('[data-recomecar-docs-drawer]');
    var drawerBody = document.querySelector('[data-recomecar-docs-body]');
    var drawerTitle = document.querySelector('[data-recomecar-docs-title]');
    var drawerCount = document.querySelector('[data-recomecar-docs-count]');
    var drawerExternal = document.querySelector('[data-recomecar-docs-external]');
    var drawerPlaceholder = document.querySelector('[data-recomecar-docs-placeholder]');
    var drawerImage = document.querySelector('[data-recomecar-docs-image]');
    var frame = document.querySelector('[data-recomecar-docs-frame]');
    var drawerHomeParent = drawer ? drawer.parentNode : null;
    var drawerHomeNextSibling = drawer ? drawer.nextSibling : null;
    var activeDocsRecordId = null;
    var pendingInvalidForm = null;
    var analysisMaps = new WeakMap();
    var defaultMapPoint = {
        latitude: -1.455833,
        longitude: -48.503887
    };

    function decimalValue(value) {
        var normalized = String(value || '').replace(',', '.').trim();
        var parsed = parseFloat(normalized);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function validCoordinates(latitude, longitude) {
        return latitude !== null && longitude !== null &&
            latitude >= -90 && latitude <= 90 &&
            longitude >= -180 && longitude <= 180;
    }

    function dispatchFieldEvents(field) {
        if (!field) {
            return;
        }

        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function updateAnalysisMapStatus(form, text) {
        var status = form.querySelector('[data-analysis-map-status]');

        if (status) {
            status.textContent = text;
        }
    }

    function coordinatesFromForm(form) {
        var latitudeField = form.querySelector('[data-analysis-latitude]');
        var longitudeField = form.querySelector('[data-analysis-longitude]');
        var latitude = decimalValue(latitudeField ? latitudeField.value : '');
        var longitude = decimalValue(longitudeField ? longitudeField.value : '');

        return validCoordinates(latitude, longitude)
            ? { latitude: latitude, longitude: longitude }
            : null;
    }

    function writeCoordinates(form, latitude, longitude) {
        var latitudeField = form.querySelector('[data-analysis-latitude]');
        var longitudeField = form.querySelector('[data-analysis-longitude]');

        if (latitudeField) {
            latitudeField.value = latitude.toFixed(7);
            dispatchFieldEvents(latitudeField);
        }

        if (longitudeField) {
            longitudeField.value = longitude.toFixed(7);
            dispatchFieldEvents(longitudeField);
        }

        updateAnalysisMapStatus(form, 'Ponto ajustado no mapa');
    }

    function buildAnalysisMapMarker(L, map, form, latitude, longitude) {
        return L.marker([latitude, longitude], {
            draggable: true,
            title: 'Ponto da residência'
        }).addTo(map).on('dragend', function (event) {
            var point = event.target.getLatLng();
            writeCoordinates(form, point.lat, point.lng);
        });
    }

    function syncAnalysisMapFromFields(form) {
        var mapElement = form.querySelector('[data-analysis-map]');
        var state = mapElement ? analysisMaps.get(mapElement) : null;
        var coords = coordinatesFromForm(form);

        if (!state || !coords) {
            return;
        }

        if (state.marker) {
            state.marker.setLatLng([coords.latitude, coords.longitude]);
        } else {
            state.marker = buildAnalysisMapMarker(window.L, state.map, form, coords.latitude, coords.longitude);
        }

        state.map.panTo([coords.latitude, coords.longitude], { animate: true, duration: 0.25 });
        updateAnalysisMapStatus(form, 'Ponto carregado');
    }

    function initAnalysisMap(form) {
        var L = window.L;
        var mapElement = form.querySelector('[data-analysis-map]');
        var coords = coordinatesFromForm(form);
        var center = coords || defaultMapPoint;
        var state;

        if (!mapElement) {
            return;
        }

        state = analysisMaps.get(mapElement);
        if (state) {
            window.setTimeout(function () {
                state.map.invalidateSize({ animate: false });
                syncAnalysisMapFromFields(form);
            }, 80);
            return;
        }

        if (!L) {
            updateAnalysisMapStatus(form, 'Mapa indisponível');
            return;
        }

        state = {
            map: L.map(mapElement, {
                center: [center.latitude, center.longitude],
                zoom: coords ? 16 : 12,
                scrollWheelZoom: false
            }),
            marker: null
        };

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(state.map);

        if (coords) {
            state.marker = buildAnalysisMapMarker(L, state.map, form, coords.latitude, coords.longitude);
        }

        state.map.on('click', function (event) {
            var point = event.latlng;

            if (!state.marker) {
                state.marker = buildAnalysisMapMarker(L, state.map, form, point.lat, point.lng);
            } else {
                state.marker.setLatLng(point);
            }

            writeCoordinates(form, point.lat, point.lng);
        });

        form.querySelectorAll('[data-analysis-latitude], [data-analysis-longitude]').forEach(function (field) {
            field.addEventListener('change', function () {
                syncAnalysisMapFromFields(form);
            });
        });

        analysisMaps.set(mapElement, state);
        updateAnalysisMapStatus(form, coords ? 'Ponto carregado' : 'Clique no mapa para definir o ponto');

        window.setTimeout(function () {
            state.map.invalidateSize({ animate: false });
        }, 80);
    }

    function initAnalysisMaps(container) {
        container.querySelectorAll('form.recomecar-analysis-form').forEach(initAnalysisMap);
    }

    function applyBenefitPanel(toggle) {
        var form = toggle.closest('form');
        var panel = form ? form.querySelector('[data-analysis-benefit-panel]') : null;
        var input = panel ? panel.querySelector('[data-analysis-benefit-name]') : null;
        var shouldOpen = toggle.checked || (input && input.value.trim() !== '');

        if (!panel) {
            return;
        }

        panel.hidden = !shouldOpen;

        if (input) {
            input.disabled = !shouldOpen;
            if (!shouldOpen) {
                input.value = '';
            }
        }
    }

    function initBenefitPanels(container) {
        container.querySelectorAll('[data-analysis-benefit-toggle]').forEach(applyBenefitPanel);
    }

    function selectedAnalystCount(form) {
        return form.querySelectorAll('input[name="analistas_usuarios[]"]').length;
    }

    function selectedAnalystIds(form) {
        return Array.prototype.map.call(
            form.querySelectorAll('input[name="analistas_usuarios[]"]'),
            function (input) {
                return input.value;
            }
        ).filter(Boolean);
    }

    function setFieldsDisabled(container, disabled) {
        if (!container) {
            return;
        }

        container.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = disabled;
        });
    }

    function writeAssignmentPreview(form, payload) {
        var preview = form.querySelector('[data-assignment-preview]');
        var total = form.querySelector('[data-assignment-preview-total]');
        var list = form.querySelector('[data-assignment-preview-list]');
        var data = payload && payload.preview ? payload.preview : null;

        if (!preview || !total || !list) {
            return;
        }

        if (!payload || payload.ready === false) {
            preview.hidden = true;
            total.textContent = 'Nenhum registro calculado.';
            list.innerHTML = '';
            return;
        }

        preview.hidden = false;
        list.innerHTML = '';

        if (!payload.ok) {
            total.textContent = payload.message || 'Não foi possível calcular a prévia.';
            return;
        }

        if (typeof data.total !== 'number') {
            total.textContent = String(data.total || 'Calculando...');
            return;
        }

        total.textContent = (data.total === 1 ? '1 registro no período' : data.total + ' registros no período');

        if (!data.by_user || data.by_user.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'recomecar-assignment-preview-item';
            empty.textContent = 'Nenhum analista válido para este cálculo.';
            list.appendChild(empty);
            return;
        }

        data.by_user.forEach(function (item) {
            var card = document.createElement('div');
            var name = document.createElement('strong');
            var count = document.createElement('span');

            card.className = 'recomecar-assignment-preview-item';
            name.textContent = item.nome || 'Analista';
            count.textContent = item.total === 1 ? '1 registro' : item.total + ' registros';
            card.appendChild(name);
            card.appendChild(count);
            list.appendChild(card);
        });
    }

    function requestAssignmentPreview(form) {
        var previewUrl = form.getAttribute('data-assignment-preview-url');
        var strategy = form.querySelector('[data-assignment-strategy]');
        var periodStart = form.querySelector('input[name="periodo_inicio"]');
        var periodEnd = form.querySelector('input[name="periodo_fim"]');
        var actionId = form.querySelector('[data-assignment-action-id]');
        var ids = selectedAnalystIds(form);
        var params;

        if (!previewUrl || ids.length === 0 || !actionId || actionId.value === '' || !strategy || strategy.value === '' || !periodStart || !periodEnd || periodStart.value === '' || periodEnd.value === '') {
            writeAssignmentPreview(form, { ready: false });
            return;
        }

        params = new URLSearchParams(new FormData(form));
        params.delete('_csrf_token');
        params.delete('_idempotency_token');

        writeAssignmentPreview(form, {
            ok: true,
            ready: true,
            preview: {
                total: 'Calculando...',
                by_user: []
            }
        });

        fetch(previewUrl + '?' + params.toString(), {
            headers: {
                Accept: 'application/json'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    payload.ok = false;
                }

                writeAssignmentPreview(form, payload);
            });
        }).catch(function () {
            writeAssignmentPreview(form, {
                ok: false,
                ready: true,
                message: 'Não foi possível calcular a prévia agora.',
                preview: {
                    total: 0,
                    by_user: []
                }
            });
        });
    }

    function scheduleAssignmentPreview(form) {
        var timer = form.__assignmentPreviewTimer || null;

        if (timer) {
            window.clearTimeout(timer);
        }

        form.__assignmentPreviewTimer = window.setTimeout(function () {
            requestAssignmentPreview(form);
        }, 250);
    }

    function syncAssignmentStrategy(form) {
        var config = form.querySelector('[data-assignment-config]');
        var strategy = form.querySelector('[data-assignment-strategy]');
        var periodFields = form.querySelector('[data-assignment-period-fields]');
        var ruleNote = form.querySelector('[data-assignment-rule-note]');
        var status = form.querySelector('[data-assignment-rule-status]');
        var submit = form.querySelector('button[type="submit"]');
        var periodStart = form.querySelector('input[name="periodo_inicio"]');
        var periodEnd = form.querySelector('input[name="periodo_fim"]');
        var actionId = form.querySelector('[data-assignment-action-id]');
        var selectedCount = selectedAnalystCount(form);
        var hasAnalysts = selectedCount > 0;
        var hasAction = actionId && actionId.value !== '';
        var hasPeriod = periodStart && periodEnd && periodStart.value !== '' && periodEnd.value !== '';
        var value = strategy ? strategy.value : '';

        if (config) {
            config.hidden = !hasAnalysts;
            setFieldsDisabled(config, !hasAnalysts);
        }

        if (periodFields) {
            periodFields.hidden = !hasAnalysts;
        }

        if (ruleNote) {
            if (value === 'pares_impares') {
                ruleNote.textContent = 'O sistema busca os registros da ação no período e alterna a atribuição para equilibrar a carga entre os analistas selecionados.';
            } else if (value === 'blocos') {
                ruleNote.textContent = 'O sistema busca os registros da ação no período e monta blocos sequenciais equilibrados entre os analistas selecionados.';
            } else {
                ruleNote.textContent = 'A distribuição calcula automaticamente os registros encontrados na ação e no período selecionados.';
            }
        }

        if (submit) {
            submit.disabled = !hasAnalysts || !hasAction || !hasPeriod || value === '';
        }

        if (status) {
            if (!hasAnalysts) {
                status.textContent = 'Selecione os analistas para abrir as regras de distribuição.';
            } else if (!hasAction) {
                status.textContent = 'Selecione a ação emergencial que receberá a distribuição.';
            } else if (!hasPeriod) {
                status.textContent = 'Informe o período de cadastro antes de escolher a forma de distribuição.';
            } else if (value === '') {
                status.textContent = 'Selecione como os registros do período serão distribuídos.';
            } else if (value === 'pares_impares') {
                status.textContent = 'Pares e ímpares será distribuído automaticamente entre ' + selectedCount + ' analista(s).';
            } else {
                status.textContent = 'Blocos sequenciais serão divididos automaticamente entre ' + selectedCount + ' analista(s).';
            }
        }

        scheduleAssignmentPreview(form);
    }

    function initAssignmentStrategies(container) {
        container.querySelectorAll('[data-recomecar-assignment-form]').forEach(function (form) {
            var strategy = form.querySelector('[data-assignment-strategy]');
            var actionSearch = form.querySelector('[data-assignment-action-search]');

            if (strategy) {
                strategy.addEventListener('change', function () {
                    syncAssignmentStrategy(form);
                });
            }

            form.querySelectorAll('input[name="periodo_inicio"], input[name="periodo_fim"]').forEach(function (field) {
                field.addEventListener('input', function () {
                    syncAssignmentStrategy(form);
                });
                field.addEventListener('change', function () {
                    syncAssignmentStrategy(form);
                });
            });

            if (actionSearch) {
                ['input', 'change'].forEach(function (eventName) {
                    actionSearch.addEventListener(eventName, function () {
                        window.setTimeout(function () {
                            syncAssignmentStrategy(form);
                        }, 0);
                    });
                });
            }

            form.addEventListener('dti-cosigner-change', function () {
                syncAssignmentStrategy(form);
            });

            syncAssignmentStrategy(form);
        });
    }

    function toggleRepresentativePanel(button) {
        var section = button.closest('.recomecar-representative-section');
        var panel = section ? section.querySelector('[data-analysis-representative-panel]') : null;
        var expanded = button.getAttribute('aria-expanded') === 'true';

        if (!panel) {
            return;
        }

        panel.hidden = expanded;
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        button.textContent = expanded ? 'Adicionar representante' : 'Recolher representante';
    }

    function closeOtherEditPanels(currentRecordId) {
        document.querySelectorAll('[data-analysis-edit-panel]').forEach(function (panel) {
            var recordId = panel.getAttribute('data-analysis-edit-panel');
            var button;

            if (recordId === currentRecordId) {
                return;
            }

            panel.hidden = true;
            button = document.querySelector('[data-analysis-edit-toggle="' + recordId + '"]');
            if (button) {
                button.setAttribute('aria-expanded', 'false');
                button.textContent = 'Editar campos';
                button.classList.remove('is-active');
            }
        });
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }

        if (typeof modal.showModal === 'function') {
            modal.showModal();
            return;
        }

        modal.hidden = false;
        modal.setAttribute('open', 'open');
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function applyDistributionHistoryFilters(modal) {
        var search = modal.querySelector('[data-distribution-history-search]');
        var status = modal.querySelector('[data-distribution-history-status]');
        var strategy = modal.querySelector('[data-distribution-history-strategy]');
        var empty = modal.querySelector('[data-distribution-history-empty]');
        var prompt = modal.querySelector('[data-distribution-history-prompt]');
        var query = normalizeText(search ? search.value : '');
        var statusValue = status ? status.value : '';
        var strategyValue = strategy ? strategy.value : '';
        var hasFilter = query !== '' || statusValue !== '' || strategyValue !== '';
        var visible = 0;

        modal.querySelectorAll('[data-distribution-history-item]').forEach(function (item) {
            var itemSearch = normalizeText(item.getAttribute('data-search') || '');
            var matches = true;

            if (!hasFilter) {
                item.hidden = true;
                return;
            }

            if (query !== '' && itemSearch.indexOf(query) === -1) {
                matches = false;
            }

            if (statusValue !== '' && item.getAttribute('data-status') !== statusValue) {
                matches = false;
            }

            if (strategyValue !== '' && item.getAttribute('data-strategy') !== strategyValue) {
                matches = false;
            }

            item.hidden = !matches;
            if (matches) {
                visible++;
            }
        });

        if (prompt) {
            prompt.hidden = hasFilter;
        }

        if (empty) {
            empty.hidden = !hasFilter || visible > 0;
        }
    }

    function initDistributionHistoryFilters(container) {
        container.querySelectorAll('[data-distribution-history-modal]').forEach(function (modal) {
            modal.querySelectorAll('[data-distribution-history-search], [data-distribution-history-status], [data-distribution-history-strategy]').forEach(function (field) {
                field.addEventListener('input', function () {
                    applyDistributionHistoryFilters(modal);
                });
                field.addEventListener('change', function () {
                    applyDistributionHistoryFilters(modal);
                });
            });

            applyDistributionHistoryFilters(modal);
        });
    }

    function fieldLabel(field) {
        var wrapper = field.closest('.field, .checkbox-card');
        var label = wrapper ? wrapper.querySelector('span') : null;
        var text = label ? label.textContent.trim() : '';

        return text || field.getAttribute('name') || 'Campo';
    }

    function validationMessage(field) {
        var label = fieldLabel(field);
        var validity = field.validity;

        if (validity.valueMissing) {
            return label + ' é obrigatório.';
        }

        if (validity.typeMismatch) {
            return label + ' está digitado incorretamente.';
        }

        if (validity.rangeUnderflow && field.getAttribute('min')) {
            return label + ' deve ser no mínimo ' + field.getAttribute('min') + '.';
        }

        if (validity.rangeOverflow && field.getAttribute('max')) {
            return label + ' deve ser no máximo ' + field.getAttribute('max') + '.';
        }

        if (validity.badInput) {
            return label + ' possui valor inválido.';
        }

        return field.validationMessage || (label + ' possui valor inválido.');
    }

    function invalidFields(form) {
        var messages = [];

        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.disabled || field.type === 'hidden' || field.checkValidity()) {
                return;
            }

            var message = validationMessage(field);
            if (messages.indexOf(message) === -1) {
                messages.push(message);
            }
        });

        return messages;
    }

    function ensureValidationModal() {
        var modal = document.querySelector('[data-analysis-client-validation-modal]');

        if (modal) {
            return modal;
        }

        modal = document.createElement('dialog');
        modal.className = 'recomecar-analysis-modal recomecar-validation-modal';
        modal.setAttribute('data-analysis-client-validation-modal', 'true');
        modal.innerHTML = [
            '<div class="recomecar-analysis-modal-header">',
            '<span class="recomecar-analysis-modal-icon recomecar-analysis-modal-icon-warning" aria-hidden="true">!</span>',
            '<div><span class="eyebrow">Salvar alterações</span><h3>Revise os campos do registro</h3><p>Foram encontradas pendências antes de gravar as alterações.</p></div>',
            '<button type="button" class="recomecar-analysis-modal-close" data-modal-close aria-label="Fechar">Fechar</button>',
            '</div>',
            '<div class="recomecar-analysis-modal-body recomecar-validation-modal-body">',
            '<p>Existem informações faltando ou digitadas incorretamente. Corrija os campos abaixo e salve novamente.</p>',
            '<ul data-analysis-validation-list></ul>',
            '</div>',
            '<div class="recomecar-analysis-modal-footer">',
            '<button type="button" class="primary-button" data-modal-close>Corrigir campos</button>',
            '</div>'
        ].join('');

        document.body.appendChild(modal);

        return modal;
    }

    function showValidationModal(messages) {
        var modal = ensureValidationModal();
        var list = modal.querySelector('[data-analysis-validation-list]');

        if (!list) {
            return;
        }

        list.innerHTML = '';
        messages.forEach(function (message) {
            var item = document.createElement('li');
            item.textContent = message;
            list.appendChild(item);
        });

        openModal(modal);
    }

    function openServerValidationModal() {
        openModal(document.querySelector('[data-analysis-validation-modal]'));
    }

    function setDocsButtonsState(recordId) {
        document.querySelectorAll('[data-recomecar-docs-open]').forEach(function (button) {
            var active = recordId !== null && button.getAttribute('data-recomecar-docs-open') === String(recordId);

            button.setAttribute('aria-expanded', active ? 'true' : 'false');
            button.classList.toggle('is-active', active);
        });
    }

    function isInlineDocsLayout() {
        return window.matchMedia && window.matchMedia('(max-width: 1180px)').matches;
    }

    function hideDocsSlot(recordId) {
        var row = document.querySelector('[data-recomecar-docs-slot-row="' + recordId + '"]');

        if (row) {
            row.hidden = true;
        }
    }

    function hideOtherDocsSlots(activeRecordId) {
        document.querySelectorAll('[data-recomecar-docs-slot-row]').forEach(function (row) {
            if (row.getAttribute('data-recomecar-docs-slot-row') !== String(activeRecordId)) {
                row.hidden = true;
            }
        });
    }

    function restoreDrawerHome() {
        if (!drawer || !drawerHomeParent) {
            return;
        }

        if (drawer.parentNode !== drawerHomeParent) {
            drawerHomeParent.insertBefore(drawer, drawerHomeNextSibling);
        }
    }

    function placeDrawer(recordId) {
        var slot = document.querySelector('[data-recomecar-docs-slot="' + recordId + '"]');
        var row = document.querySelector('[data-recomecar-docs-slot-row="' + recordId + '"]');

        if (!drawer) {
            return;
        }

        hideOtherDocsSlots(recordId);

        if (isInlineDocsLayout() && slot && row) {
            slot.appendChild(drawer);
            row.hidden = false;
            return;
        }

        hideDocsSlot(recordId);
        restoreDrawerHome();
    }

    function setDocumentPreview(link) {
        var mimeType = link ? (link.getAttribute('data-recomecar-doc-mime') || '') : '';
        var isImage = mimeType.indexOf('image/') === 0;

        if (drawerBody) {
            drawerBody.querySelectorAll('[data-recomecar-doc-link]').forEach(function (item) {
                item.classList.toggle('is-active', item === link);
            });
        }

        if (drawerImage) {
            drawerImage.hidden = !link || !isImage;
            drawerImage.src = link && isImage ? link.href : '';
            drawerImage.alt = link && isImage ? (link.querySelector('strong') ? link.querySelector('strong').textContent : 'Documento anexado') : 'Documento anexado';
        }

        if (frame) {
            frame.hidden = !link || isImage;
            frame.src = link && !isImage ? link.href : 'about:blank';
        }

        if (drawerPlaceholder) {
            drawerPlaceholder.hidden = !!link;
        }

        if (drawerExternal) {
            if (link) {
                drawerExternal.hidden = false;
                drawerExternal.href = link.href;
            } else {
                drawerExternal.hidden = true;
                drawerExternal.removeAttribute('href');
            }
        }
    }

    function openDrawer(recordId) {
        var template = document.querySelector('[data-recomecar-docs-template="' + recordId + '"]');

        if (!drawer || !drawerBody || !template) {
            return;
        }

        activeDocsRecordId = String(recordId);
        placeDrawer(activeDocsRecordId);

        drawerBody.innerHTML = '';
        drawerBody.appendChild(template.content.cloneNode(true));

        if (drawerTitle) {
            drawerTitle.textContent = 'Família #' + recordId;
        }

        drawer.hidden = false;
        drawer.setAttribute('open', 'open');

        var firstLink = drawerBody.querySelector('[data-recomecar-doc-link]');
        var totalDocs = drawerBody.querySelectorAll('[data-recomecar-doc-link]').length;

        if (drawerCount) {
            drawerCount.textContent = totalDocs === 1 ? '1 documento anexado' : totalDocs + ' documentos anexados';
        }

        setDocumentPreview(firstLink || null);
        setDocsButtonsState(recordId);

        if (isInlineDocsLayout()) {
            window.setTimeout(function () {
                drawer.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }, 40);
        }
    }

    function closeDrawer() {
        if (!drawer) {
            return;
        }

        drawer.hidden = true;
        drawer.removeAttribute('open');
        setDocsButtonsState(null);
        hideOtherDocsSlots(null);
        restoreDrawerHome();
        activeDocsRecordId = null;

        if (drawerBody) {
            drawerBody.innerHTML = '';
        }

        if (drawerCount) {
            drawerCount.textContent = 'Nenhum documento';
        }

        setDocumentPreview(null);
    }

    window.addEventListener('resize', function () {
        if (drawer && drawer.hasAttribute('open') && activeDocsRecordId !== null) {
            placeDrawer(activeDocsRecordId);
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        var submitButton = target.closest('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            var form = submitButton.closest('form.recomecar-analysis-form');

            if (form && !form.checkValidity()) {
                event.preventDefault();
                showValidationModal(invalidFields(form));
                return;
            }
        }

        var docsButton = target.closest('[data-recomecar-docs-open]');
        if (docsButton) {
            var docsRecordId = docsButton.getAttribute('data-recomecar-docs-open');

            if (drawer && drawer.hasAttribute('open') && activeDocsRecordId === String(docsRecordId)) {
                closeDrawer();
            } else {
                openDrawer(docsRecordId);
            }
            return;
        }

        var editButton = target.closest('[data-analysis-edit-toggle]');
        if (editButton) {
            var recordId = editButton.getAttribute('data-analysis-edit-toggle');
            var panel = document.querySelector('[data-analysis-edit-panel="' + recordId + '"]');
            var expanded = editButton.getAttribute('aria-expanded') === 'true';

            if (!panel) {
                return;
            }

            if (!expanded) {
                if (drawer && drawer.hasAttribute('open')) {
                    closeDrawer();
                }
                closeOtherEditPanels(recordId);
            }

            panel.hidden = expanded;
            editButton.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            editButton.textContent = expanded ? 'Editar campos' : 'Recolher edição';
            editButton.classList.toggle('is-active', !expanded);
            if (!expanded) {
                initAnalysisMaps(panel);
                initBenefitPanels(panel);
            }
            return;
        }

        var benefitToggle = target.closest('[data-analysis-benefit-toggle]');
        if (benefitToggle) {
            applyBenefitPanel(benefitToggle);
            return;
        }

        var representativeToggle = target.closest('[data-analysis-representative-toggle]');
        if (representativeToggle) {
            toggleRepresentativePanel(representativeToggle);
            return;
        }

        var backButton = target.closest('[data-history-back]');
        if (backButton) {
            var fallback = backButton.getAttribute('data-history-fallback') || '/gestor/recomecar';

            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = fallback;
            }
            return;
        }

        if (target.closest('[data-recomecar-docs-close]')) {
            closeDrawer();
            return;
        }

        var docLink = target.closest('[data-recomecar-doc-link]');
        if (docLink && drawerBody) {
            event.preventDefault();
            setDocumentPreview(docLink);
            return;
        }

        var historyButton = target.closest('[data-analysis-history-open]');
        if (historyButton) {
            var modal = document.querySelector('[data-analysis-history-modal="' + historyButton.getAttribute('data-analysis-history-open') + '"]');

            openModal(modal);
            return;
        }

        var distributionHistoryButton = target.closest('[data-distribution-history-open]');
        if (distributionHistoryButton) {
            openModal(document.querySelector('[data-distribution-history-modal]'));
            return;
        }
    });

    document.addEventListener('invalid', function (event) {
        var field = event.target;

        if (!(field instanceof Element)) {
            return;
        }

        var form = field.closest('form.recomecar-analysis-form');
        if (!form) {
            return;
        }

        pendingInvalidForm = form;
        window.setTimeout(function () {
            if (!pendingInvalidForm) {
                return;
            }

            showValidationModal(invalidFields(pendingInvalidForm));
            pendingInvalidForm = null;
        }, 0);
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    openServerValidationModal();
    initAnalysisMaps(document);
    initBenefitPanels(document);
    initAssignmentStrategies(document);
    initDistributionHistoryFilters(document);
})();
