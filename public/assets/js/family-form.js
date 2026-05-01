(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-family-form]');

    function setupRepresentative(form) {
        var toggle = form.querySelector('[data-representative-toggle]');
        var fields = form.querySelector('[data-representative-fields]');
        var inputs = form.querySelectorAll('[data-representative-input]');

        if (!toggle || !fields) {
            return;
        }

        function applyState() {
            var enabled = toggle.checked;

            fields.hidden = !enabled;
            inputs.forEach(function (input) {
                input.disabled = !enabled;
            });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    }

    function enableRepresentative(form) {
        var toggle = form.querySelector('[data-representative-toggle]');

        if (toggle && !toggle.checked) {
            toggle.checked = true;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function setupBenefit(form) {
        var toggle = form.querySelector('[data-benefit-toggle]');
        var fields = form.querySelector('[data-benefit-fields]');
        var inputs = form.querySelectorAll('[data-benefit-input]');

        if (!toggle || !fields) {
            return;
        }

        function applyState() {
            fields.hidden = !toggle.checked;
            inputs.forEach(function (input) {
                input.disabled = !toggle.checked;
            });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    }

    function setupQuantityStepper(form) {
        var stepper = form.querySelector('[data-quantity-stepper]');

        if (!stepper) {
            return;
        }

        var input = stepper.querySelector('[data-quantity-input]');
        var decrement = stepper.querySelector('[data-quantity-decrement]');
        var increment = stepper.querySelector('[data-quantity-increment]');

        if (!input || !decrement || !increment) {
            return;
        }

        function currentValue() {
            var value = parseInt(input.value, 10);
            return Number.isFinite(value) && value > 0 ? value : 1;
        }

        function setValue(value) {
            input.value = String(Math.max(1, value));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        decrement.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setValue(currentValue() - 1);
        });

        increment.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setValue(currentValue() + 1);
        });

        input.addEventListener('blur', function () {
            setValue(currentValue());
        });
    }

    function setupAutofillTracking(form) {
        form.querySelectorAll('[data-family-autofill-target]').forEach(function (field) {
            field.addEventListener('input', function () {
                if (field.dataset.familyAutofilling === '1') {
                    return;
                }

                delete field.dataset.familyAutofilled;
                delete field.dataset.familyAutofillValue;
            });
        });
    }

    function setupDocumentUpload(form, wrapper) {

        if (!wrapper) {
            return;
        }

        var input = wrapper.querySelector('[data-family-doc-input]');
        var dropzone = wrapper.querySelector('[data-family-doc-dropzone]');
        var list = wrapper.querySelector('[data-family-doc-list]');
        var status = wrapper.querySelector('[data-family-doc-status]');
        var target = wrapper.getAttribute('data-doc-target') || 'responsavel';
        var files = [];
        var syncing = false;
        var previewUrls = [];

        if (!input || !dropzone || !list) {
            return;
        }

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function syncInput() {
            var transfer;

            if (typeof DataTransfer === 'undefined') {
                return false;
            }

            transfer = new DataTransfer();
            files.forEach(function (file) {
                transfer.items.add(file);
            });

            syncing = true;
            input.files = transfer.files;
            syncing = false;
            return true;
        }

        function clearPreviewUrls() {
            previewUrls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            previewUrls = [];
        }

        function createPreview(file) {
            var preview = document.createElement('div');

            preview.className = 'family-doc-preview';

            if (/^image\//.test(file.type)) {
                var image = document.createElement('img');
                var url = URL.createObjectURL(file);

                previewUrls.push(url);
                image.src = url;
                image.alt = 'Previa de ' + file.name;
                preview.appendChild(image);
                return preview;
            }

            var icon = document.createElement('span');
            icon.className = 'family-doc-preview-icon';
            icon.textContent = (file.type || '').indexOf('pdf') !== -1 ? 'PDF' : 'DOC';
            preview.appendChild(icon);
            return preview;
        }

        function render() {
            clearPreviewUrls();
            list.innerHTML = '';

            if (files.length === 0) {
                list.hidden = true;
                dropzone.classList.remove('has-file');
                setStatus('JPG, PNG ou PDF. Tamanho maximo por arquivo: 5 MB.');
                syncInput();
                return;
            }

            files.forEach(function (file, index) {
                var item = document.createElement('div');
                var preview = createPreview(file);
                var info = document.createElement('div');
                var name = document.createElement('span');
                var meta = document.createElement('small');
                var remove = document.createElement('button');

                info.className = 'family-doc-info';
                name.textContent = file.name;
                meta.textContent = Math.max(1, Math.round(file.size / 1024)) + ' KB';
                remove.type = 'button';
                remove.textContent = 'Remover';
                remove.addEventListener('click', function () {
                    files.splice(index, 1);
                    render();
                });

                info.appendChild(name);
                info.appendChild(meta);
                item.appendChild(preview);
                item.appendChild(info);
                item.appendChild(remove);
                list.appendChild(item);
            });

            list.hidden = false;
            dropzone.classList.add('has-file');
            setStatus(files.length + ' documento(s) selecionado(s).');
            syncInput();
        }

        function addFiles(fileList) {
            var added = [];

            Array.prototype.slice.call(fileList || []).forEach(function (file) {
                if (!file || files.some(function (item) {
                    return item.name === file.name && item.size === file.size && item.lastModified === file.lastModified;
                })) {
                    return;
                }

                files.push(file);
                added.push(file);
            });

            render();

            added.forEach(function (file) {
                readDocumentAndFill(form, target, file, setStatus);
            });
        }

        input.addEventListener('change', function () {
            if (!syncing) {
                addFiles(input.files);
            }
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function () {
                dropzone.classList.remove('is-dragging');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            addFiles(event.dataTransfer ? event.dataTransfer.files : []);
        });

        form.addEventListener('reset', function () {
            files = [];
            window.setTimeout(render, 0);
        });

        window.addEventListener('beforeunload', clearPreviewUrls);

        return {
            addFiles: addFiles
        };
    }

    function setupDocumentUploads(form) {
        var wrappers = form.querySelectorAll('[data-family-doc-upload]');
        var uploads = [];

        wrappers.forEach(function (wrapper) {
            var upload = setupDocumentUpload(form, wrapper);

            if (upload) {
                uploads.push({
                    wrapper: wrapper,
                    upload: upload
                });
            }
        });

        if (uploads.length === 0) {
            return;
        }

        form.addEventListener('paste', function (event) {
            var files = event.clipboardData ? event.clipboardData.files : [];
            var activeWrapper = event.target instanceof Element ? event.target.closest('[data-family-doc-upload]') : null;
            var targetUpload = uploads.find(function (item) {
                return item.wrapper === activeWrapper;
            }) || uploads[0];

            targetUpload.upload.addFiles(files);
        });
    }

    function readDocumentAndFill(form, target, file, setStatus) {
        if (!file || !/^image\//.test(file.type)) {
            if (file && /pdf/i.test(file.type || '')) {
                setStatus('PDF anexado. A leitura automatica esta disponivel apenas para imagens JPG ou PNG.');
            }

            return;
        }

        setStatus('Documento anexado. Lendo dados no servidor...');

        extractTextOnServer(form, file)
            .catch(function () {
                return extractTextInBrowser(file, setStatus);
            })
            .then(function (ocrResult) {
                var text = typeof ocrResult === 'string' ? ocrResult : (ocrResult.text || '');
                var confidence = typeof ocrResult === 'object' && ocrResult !== null && Number.isFinite(ocrResult.confidence)
                    ? ocrResult.confidence
                    : null;
                var data = parseDocumentText(text);
                var filled = fillDocumentData(form, target, data, confidence);

                if (filled > 0) {
                    setStatus(filled + ' campo(s) preenchido(s) automaticamente. Confira antes de salvar.');
                    return;
                }

                if (String(text || '').trim() === '') {
                    setStatus('Documento anexado. OCR indisponivel ou sem texto legivel.');
                    return;
                }

                setStatus('Documento anexado. Texto lido, mas nenhum campo conhecido foi identificado.');
            })
            .catch(function () {
                setStatus('Documento anexado. Nao foi possivel executar a leitura automatica.');
            });
    }

    function extractTextOnServer(form, file) {
        var token = form.querySelector('input[name="_csrf_token"]');
        var endpoint = form.getAttribute('data-ocr-endpoint') || buildOcrEndpoint(form);
        var data = new FormData();

        if (!endpoint || !window.fetch) {
            return Promise.reject(new Error('OCR do servidor indisponivel.'));
        }

        data.append('documento', file);
        data.append('_csrf_token', token ? token.value : '');

        return fetch(endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'OCR do servidor falhou.');
                }

                return {
                    text: payload.text || '',
                    confidence: Number.isFinite(Number(payload.confidence)) ? Number(payload.confidence) : null
                };
            });
        });
    }

    function buildOcrEndpoint(form) {
        var action = form.getAttribute('action') || window.location.href;
        var url = new URL(action, window.location.href);
        var path = url.pathname;
        var marker = '/cadastros/residencias/';
        var index = path.indexOf(marker);

        if (index === -1) {
            url.pathname = '/cadastros/familias/ocr-documento';
            url.search = '';
            return url.toString();
        }

        url.pathname = path.slice(0, index) + '/cadastros/familias/ocr-documento';
        url.search = '';
        return url.toString();
    }

    function extractTextInBrowser(file, setStatus) {
        if (!('TextDetector' in window) || typeof window.createImageBitmap !== 'function') {
            setStatus('Servidor OCR nao configurado. Leitura automatica indisponivel neste navegador.');
            return Promise.resolve({ text: '', confidence: null });
        }

        setStatus('Servidor OCR indisponivel. Tentando leitura pelo navegador...');
        return extractTextFromImage(file).then(function (text) {
            return { text: text, confidence: null };
        });
    }

    function extractTextFromImage(file) {
        return createImageBitmap(file).then(function (bitmap) {
            var detector = new window.TextDetector();
            var processed = createOcrCanvas(bitmap);
            var detections = [
                detector.detect(bitmap).catch(function () {
                    return [];
                })
            ];

            if (processed) {
                detections.push(detector.detect(processed).catch(function () {
                    return [];
                }));
            }

            return Promise.all(detections).then(function (results) {
                if (typeof bitmap.close === 'function') {
                    bitmap.close();
                }

                return mergeDetectedText([].concat.apply([], results));
            });
        });
    }

    function createOcrCanvas(bitmap) {
        var maxSide = 1800;
        var scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        var width = Math.max(1, Math.round(bitmap.width * scale));
        var height = Math.max(1, Math.round(bitmap.height * scale));
        var canvas = document.createElement('canvas');
        var context;
        var imageData;
        var data;
        var index;
        var average;
        var contrast;

        canvas.width = width;
        canvas.height = height;
        context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            return null;
        }

        context.drawImage(bitmap, 0, 0, width, height);
        imageData = context.getImageData(0, 0, width, height);
        data = imageData.data;

        for (index = 0; index < data.length; index += 4) {
            average = (data[index] * 0.299) + (data[index + 1] * 0.587) + (data[index + 2] * 0.114);
            contrast = Math.max(0, Math.min(255, ((average - 128) * 1.45) + 128));
            data[index] = contrast;
            data[index + 1] = contrast;
            data[index + 2] = contrast;
        }

        context.putImageData(imageData, 0, 0);
        return canvas;
    }

    function mergeDetectedText(items) {
        var seen = {};

        return items
            .filter(function (item) {
                var text = item && (item.rawValue || item.text || '');

                return String(text).trim() !== '';
            })
            .sort(function (a, b) {
                var boxA = a.boundingBox || {};
                var boxB = b.boundingBox || {};
                var topA = Number.isFinite(boxA.y) ? boxA.y : 0;
                var topB = Number.isFinite(boxB.y) ? boxB.y : 0;
                var leftA = Number.isFinite(boxA.x) ? boxA.x : 0;
                var leftB = Number.isFinite(boxB.x) ? boxB.x : 0;

                if (Math.abs(topA - topB) > 12) {
                    return topA - topB;
                }

                return leftA - leftB;
            })
            .map(function (item) {
                return String(item.rawValue || item.text || '').replace(/\s+/g, ' ').trim();
            })
            .filter(function (text) {
                var key = normalizeText(text);

                if (seen[key]) {
                    return false;
                }

                seen[key] = true;
                return true;
            })
            .join('\n');
    }

    function parseDocumentText(text) {
        var lines = String(text || '').split(/\r?\n/)
            .map(function (line) {
                return line.replace(/\s+/g, ' ').trim();
            })
            .filter(Boolean);
        var joined = lines.join('\n');
        var normalized = normalizeText(joined);
        var cpf = findCpf(joined);
        var birthDate = findBirthDate(normalized);
        var rg = findRg(lines, cpf, birthDate);
        var orgao = findOrgaoExpedidor(lines);
        var sexoMatch = normalized.match(/\bSEXO[^A-Z]{0,8}(MASCULINO|FEMININO|M|F)\b/);

        return {
            nome: cleanPersonName(findName(lines)),
            cpf: cpf,
            rg: rg,
            orgao_expedidor: orgao,
            data_nascimento: birthDate,
            sexo: sexoMatch ? normalizeSex(sexoMatch[1]) : ''
        };
    }

    function findCpf(text) {
        var source = String(text || '');
        var matches = source.match(/(?:\d[\s.\-]*){11}/g) || [];
        var cpfContext = normalizeText(source).match(/(?:CPF|CADASTRO DE PESSOA FISICA)[^0-9]{0,48}((?:\d[\s.\-]*){11})/);
        var index;
        var formatted;

        if (cpfContext) {
            formatted = formatCpf(cpfContext[1]);

            if (isValidCpf(formatted)) {
                return formatted;
            }
        }

        for (index = 0; index < matches.length; index += 1) {
            formatted = formatCpf(matches[index]);

            if (isValidCpf(formatted)) {
                return formatted;
            }
        }

        return '';
    }

    function findRg(lines, cpf, birthDate) {
        var normalizedLines = lines.map(normalizeText);
        var candidates = [];
        var index;
        var line;
        var previousLine;
        var nextLine;
        var directMatch;
        var context;

        for (index = 0; index < normalizedLines.length; index += 1) {
            line = normalizedLines[index];
            previousLine = normalizedLines[index - 1] || '';
            nextLine = normalizedLines[index + 1] || '';
            context = previousLine + ' ' + line + ' ' + nextLine;

            if (!hasRgContext(context) && !hasIssuer(line)) {
                continue;
            }

            directMatch = extractRgFromText(stripRgLabels(line), cpf, birthDate);

            if (directMatch !== '') {
                candidates.push(directMatch);
            }

            directMatch = extractRgFromText(nextLine, cpf, birthDate);

            if (directMatch !== '') {
                candidates.push(directMatch);
            }
        }

        for (index = 0; index < candidates.length; index += 1) {
            if (!looksLikeCpfOrDate(candidates[index], cpf, birthDate)) {
                return candidates[index];
            }
        }

        return '';
    }

    function hasRgContext(value) {
        return /(^|[^A-Z])(RG|REGISTRO GERAL|IDENTIDADE|DOC\.?\s*IDENTIDADE|DOCUMENTO DE IDENTIDADE|C\.?I\.?|CARTEIRA DE IDENTIDADE)([^A-Z]|$)/.test(value);
    }

    function hasIssuer(value) {
        return /\b(SSP|SESP|SEGUP|PC|IFP|DETRAN|SJS|SDS|MEX|MAER|MD|DGPC|SECC|SSPDS)(?:\s*[-/]?\s*[A-Z]{2})?\b/.test(value);
    }

    function stripRgLabels(value) {
        return String(value || '')
            .replace(/(?:REGISTRO GERAL|DOCUMENTO DE IDENTIDADE|DOC\.?\s*IDENTIDADE|CARTEIRA DE IDENTIDADE|IDENTIDADE|C\.?I\.?|RG)/gi, ' ')
            .replace(/(?:N[ºO]|NUMERO|NÚMERO|NO)\s*[:\-]?/gi, ' ');
    }

    function extractRgFromText(value, cpf, birthDate) {
        var source = normalizeText(value)
            .replace(/(?:CPF|CADASTRO DE PESSOA FISICA).*$/g, ' ')
            .replace(/(?:DATA DE NASCIMENTO|NASCIMENTO|DT NASC|DATA NASC|VALIDADE|EXPEDICAO|EMISSAO).*$/g, ' ')
            .replace(/(?:ORGAO EXPEDIDOR|ORGAO EMISSOR|ORG\.?\s*EMISSOR|EXPEDIDOR|EMISSOR|UF)\b/g, ' ');
        var matches = source.match(/\b(?:[A-Z]{1,2}[-\s]?)?\d{1,2}[.\s]?\d{3}[.\s]?\d{3}[-\s]?[0-9X]?\b|\b[A-Z]{1,2}[-\s]?\d{5,10}[0-9X]?\b|\b\d{5,10}[0-9X]?\b/g) || [];
        var index;
        var cleaned;

        for (index = 0; index < matches.length; index += 1) {
            cleaned = cleanRg(matches[index]);

            if (cleaned !== '' && !looksLikeCpfOrDate(cleaned, cpf, birthDate)) {
                return cleaned;
            }
        }

        return '';
    }

    function looksLikeCpfOrDate(value, cpf, birthDate) {
        var digits = String(value || '').replace(/\D+/g, '');
        var cpfDigits = String(cpf || '').replace(/\D+/g, '');
        var dateDigits = String(birthDate || '').replace(/\D+/g, '');

        return digits === ''
            || (cpfDigits !== '' && digits === cpfDigits)
            || (dateDigits !== '' && dateDigits.indexOf(digits) !== -1)
            || digits.length === 11;
    }

    function findBirthDate(normalizedText) {
        var labeled = normalizedText.match(/(?:DATA DE NASCIMENTO|NASCIMENTO|DT NASC|DATA NASC)[^\d]{0,24}(\d{2}[./-]\d{2}[./-]\d{4})/);
        var dates;
        var index;
        var before;

        if (labeled) {
            return formatDate(labeled[1]);
        }

        dates = normalizedText.match(/\b\d{2}[./-]\d{2}[./-]\d{4}\b/g) || [];

        for (index = 0; index < dates.length; index += 1) {
            before = normalizedText.slice(Math.max(0, normalizedText.indexOf(dates[index]) - 32), normalizedText.indexOf(dates[index]));

            if (!/(VALIDADE|EXPEDICAO|EMISSAO|REGISTRO|ASSINATURA)/.test(before)) {
                return formatDate(dates[index]);
            }
        }

        return '';
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase();
    }

    function findName(lines) {
        var ignored = /^(CPF|RG|REGISTRO|IDENTIDADE|CARTEIRA|NASCIMENTO|DATA|VALIDADE|SEXO|FILIACAO|NATURALIDADE|ORGAO|EXPEDIDOR|ASSINATURA|REPUBLICA|BRASIL|ESTADO|SECRETARIA|MINISTERIO|DOCUMENTO)/;
        var best = '';
        var bestScore = -999;
        var index;
        var lookAhead;
        var normalizedLine;
        var afterLabel;
        var score;

        for (index = 0; index < lines.length; index += 1) {
            normalizedLine = normalizeText(lines[index]);

            if (isParentLabelLine(normalizedLine)) {
                continue;
            }

            if (isNameLabelLine(normalizedLine)) {
                afterLabel = cleanNameCandidateText(lines[index].replace(/.*NOME(?:\s+(?:CIVIL|SOCIAL|DO\s+TITULAR|E\s+SOBRENOME))?\s*[:\-]?\s*/i, ''));

                if (isPlausiblePersonName(afterLabel) && normalizeText(afterLabel) !== 'NOME' && !isLikelyParentName(lines, index)) {
                    return afterLabel;
                }

                for (lookAhead = index + 1; lookAhead <= Math.min(lines.length - 1, index + 3); lookAhead += 1) {
                    if (isParentLabelLine(normalizeText(lines[lookAhead]))) {
                        break;
                    }

                    afterLabel = cleanNameCandidateText(lines[lookAhead]);

                    if (isPlausiblePersonName(afterLabel) && !isLikelyParentName(lines, lookAhead)) {
                        return afterLabel;
                    }
                }
            }
        }

        for (index = 0; index < lines.length; index += 1) {
            normalizedLine = normalizeText(lines[index]);

            if (isPlausiblePersonName(lines[index]) && !ignored.test(normalizedLine) && !isLikelyParentName(lines, index)) {
                score = scoreNameCandidate(lines, index);

                if (score > bestScore) {
                    best = lines[index];
                    bestScore = score;
                }
            }
        }

        return best;
    }

    function isNameLabelLine(normalizedLine) {
        return /(^|[^A-Z])(NOME CIVIL|NOME SOCIAL|NOME DO TITULAR|NOME E SOBRENOME|NOME)([^A-Z]|$)/.test(normalizedLine);
    }

    function isParentLabelLine(normalizedLine) {
        return /(FILIACAO|FILIA[CG][AÃ]O|NOME DO PAI|NOME DA MAE|PAI\b|MAE\b|GENITOR|GENITORA)/.test(normalizedLine);
    }

    function isLikelyParentName(lines, index) {
        var start = Math.max(0, index - 5);
        var end = Math.min(lines.length - 1, index);
        var cursor;
        var context = '';

        for (cursor = start; cursor <= end; cursor += 1) {
            context += ' ' + normalizeText(lines[cursor] || '');
        }

        if (!/(FILIACAO|FILIA[CG][AÃ]O|NOME DO PAI|NOME DA MAE|PAI\b|MAE\b|GENITOR|GENITORA)/.test(context)) {
            return false;
        }

        return !/(^| )(NOME CIVIL|NOME SOCIAL|NOME:|NOME DO TITULAR|TITULAR)( |$)/.test(context);
    }

    function cleanNameCandidateText(value) {
        return String(value || '')
            .replace(/\b(CPF|RG|REGISTRO|IDENTIDADE|NASCIMENTO|DATA|VALIDADE|SEXO|FILIACAO|NATURALIDADE|ORGAO|EXPEDIDOR|ASSINATURA)\b.*$/i, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function scoreNameCandidate(lines, index) {
        var normalized = normalizeText(lines[index]);
        var previous = normalizeText(lines[index - 1] || '');
        var next = normalizeText(lines[index + 1] || '');
        var score = 0;

        if (isNameLabelLine(previous)) {
            score += 80;
        }

        if (/(CPF|DATA DE NASCIMENTO|NASCIMENTO|RG|IDENTIDADE)/.test(next)) {
            score += 20;
        }

        if (/(ASSINATURA|VALIDADE|EXPEDICAO|EMISSAO)/.test(previous + ' ' + next + ' ' + normalized)) {
            score -= 35;
        }

        score -= index;

        return score;
    }

    function cleanPersonName(value) {
        var cleaned = String(value || '')
            .replace(/[^A-Za-zÀ-ÖØ-öø-ÿ' -]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase();

        return isPlausiblePersonName(cleaned) ? cleaned : '';
    }

    function isPlausiblePersonName(value) {
        var text = String(value || '').replace(/\s+/g, ' ').trim();
        var normalized = normalizeText(text);
        var letters = text.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ]/g, '');
        var tokens = text.split(' ').filter(Boolean);

        if (text.length < 8 || text.length > 120 || tokens.length < 2) {
            return false;
        }

        if (/\d/.test(text) || /[|_[\]{}<>@#$%*+=]/.test(text)) {
            return false;
        }

        if (/^(CPF|RG|REGISTRO|IDENTIDADE|CARTEIRA|NASCIMENTO|DATA|VALIDADE|SEXO|FILIACAO|NATURALIDADE|ORGAO|EXPEDIDOR|ASSINATURA|REPUBLICA|BRASIL|ESTADO|SECRETARIA|MINISTERIO|DOCUMENTO)/.test(normalized)) {
            return false;
        }

        return letters.length / Math.max(1, text.length) >= 0.72;
    }

    function cleanToken(value) {
        return String(value || '').replace(/\s+/g, '').replace(/[^\w./-]/g, '').toUpperCase();
    }

    function findOrgaoExpedidor(lines) {
        var normalizedLines = (lines || []).map(normalizeText);
        var match;
        var index;
        var context;

        for (index = 0; index < normalizedLines.length; index += 1) {
            context = normalizedLines[index] + ' ' + (normalizedLines[index + 1] || '');

            if (!/(ORGAO|EXPEDIDOR|EMISSOR|IDENTIDADE|REGISTRO GERAL|RG)/.test(context)) {
                continue;
            }

            match = context.match(/\b(SSP|SESP|SEGUP|PC|IFP|DETRAN|SJS|SDS|MEX|MAER|MD|DGPC|SECC|SSPDS)(?:\s*[-/]?\s*([A-Z]{2}))?\b/);

            if (match) {
                return match[2] ? match[1] + '-' + match[2] : match[1];
            }
        }

        match = normalizedLines.join('\n').match(/\b(SSP|SESP|SEGUP|PC|IFP|SJS|SDS|MEX|MAER|MD|DGPC|SECC|SSPDS)(?:\s*[-/]?\s*([A-Z]{2}))?\b/);

        if (!match) {
            return '';
        }

        return match[2] ? match[1] + '-' + match[2] : match[1];
    }

    function cleanRg(value) {
        var cleaned = cleanToken(value)
            .replace(/^(NO|Nº|NUMERO|NÚMERO)/, '')
            .replace(/\b(SSP|SESP|SEGUP|PC|IFP|DETRAN|SJS|SDS|MEX|MAER|MD|DGPC|SECC|SSPDS)([-/]?[A-Z]{2})?\b/g, '')
            .replace(/[./-]+$/g, '');
        var digits = cleaned.replace(/\D+/g, '');

        if (cleaned.length < 4 || cleaned.length > 20 || digits.length < 4 || digits.length > 12) {
            return '';
        }

        if (/^(CPF|NOME|DATA|NASC|SEXO|VALIDADE|EXPEDICAO|EMISSAO)/.test(cleaned)) {
            return '';
        }

        return cleaned;
    }

    function formatCpf(value) {
        var digits = String(value || '').replace(/\D+/g, '');

        if (digits.length !== 11) {
            return value;
        }

        return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    function isValidCpf(value) {
        var digits = String(value || '').replace(/\D+/g, '');
        var position;
        var index;
        var sum;
        var checkDigit;

        if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) {
            return false;
        }

        for (position = 9; position <= 10; position += 1) {
            sum = 0;

            for (index = 0; index < position; index += 1) {
                sum += parseInt(digits[index], 10) * ((position + 1) - index);
            }

            checkDigit = (sum * 10) % 11;
            checkDigit = checkDigit === 10 ? 0 : checkDigit;

            if (checkDigit !== parseInt(digits[position], 10)) {
                return false;
            }
        }

        return true;
    }

    function formatDate(value) {
        var match = String(value || '').match(/^(\d{2})[./-](\d{2})[./-](\d{4})$/);

        if (!match) {
            return '';
        }

        return match[3] + '-' + match[2] + '-' + match[1];
    }

    function normalizeSex(value) {
        var normalized = normalizeText(value);

        if (normalized === 'F' || normalized === 'FEMININO') {
            return 'feminino';
        }

        if (normalized === 'M' || normalized === 'MASCULINO') {
            return 'masculino';
        }

        return '';
    }

    function fillDocumentData(form, target, data, confidence) {
        var prefix = target === 'representante' ? 'representante' : 'responsavel';
        var fieldMap = {
            nome: prefix + '_nome',
            cpf: prefix + '_cpf',
            rg: prefix + '_rg',
            orgao_expedidor: prefix + '_orgao_expedidor',
            data_nascimento: prefix === 'representante' ? 'representante_data_nascimento' : 'data_nascimento',
            sexo: prefix + '_sexo'
        };
        var filled = 0;

        if (prefix === 'representante') {
            enableRepresentative(form);
        }

        Object.keys(fieldMap).forEach(function (key) {
            if (setFieldValue(form, fieldMap[key], data[key], confidence)) {
                filled += 1;
            }
        });

        return filled;
    }

    function setFieldValue(form, name, value, confidence) {
        var field;

        if (!value) {
            return false;
        }

        field = form.querySelector('[name="' + name + '"]');

        if (!field || (!canReplaceFieldValue(field) && field.value.trim() !== '') || !isSafeAutofillValue(name, value, confidence)) {
            return false;
        }

        field.dataset.familyAutofilling = '1';
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        delete field.dataset.familyAutofilling;
        field.dataset.familyAutofilled = '1';
        field.dataset.familyAutofillValue = value;

        return true;
    }

    function canReplaceFieldValue(field) {
        return field.dataset.familyAutofilled === '1'
            && field.value.trim() === String(field.dataset.familyAutofillValue || '').trim();
    }

    function isSafeAutofillValue(name, value, confidence) {
        if (confidence !== null && confidence < 45 && name.indexOf('_cpf') === -1 && name !== 'responsavel_cpf' && name.indexOf('data_nascimento') === -1 && name !== 'data_nascimento') {
            return false;
        }

        if (name.indexOf('_nome') !== -1 || name === 'responsavel_nome') {
            return isPlausiblePersonName(value);
        }

        if (name.indexOf('_cpf') !== -1) {
            return isValidCpf(value);
        }

        if (name.indexOf('_rg') !== -1) {
            return cleanRg(value) !== '';
        }

        if (name.indexOf('orgao_expedidor') !== -1) {
            return /^(SSP|SESP|SEGUP|PC|IFP|DETRAN|SJS|SDS|MEX|MAER|MD)([-/][A-Z]{2})?$/.test(value);
        }

        if (name.indexOf('data_nascimento') !== -1 || name === 'data_nascimento') {
            return /^\d{4}-\d{2}-\d{2}$/.test(value);
        }

        if (name.indexOf('_sexo') !== -1) {
            return /^(feminino|masculino|outro|nao_informado)$/.test(value);
        }

        return true;
    }

    function setupExistingDocuments(form) {
        form.querySelectorAll('[data-family-doc-remove]').forEach(function (input) {
            var item = input.closest('.family-existing-doc-item');
            var label = input.closest('.family-doc-remove-action');
            var text = label ? label.querySelector('span') : null;

            input.addEventListener('change', function () {
                if (item) {
                    item.classList.toggle('is-marked-for-removal', input.checked);
                }

                if (text) {
                    text.textContent = input.checked ? 'Manter' : 'Remover';
                }
            });
        });
    }

    function setupDocumentModal() {
        var modal = document.querySelector('[data-family-doc-modal]');

        if (!modal) {
            return;
        }

        var title = modal.querySelector('[data-family-doc-modal-title]');
        var image = modal.querySelector('[data-family-doc-modal-image]');
        var frame = modal.querySelector('[data-family-doc-modal-frame]');

        function clearContent() {
            if (image) {
                image.hidden = true;
                image.removeAttribute('src');
            }

            if (frame) {
                frame.hidden = true;
                frame.removeAttribute('src');
            }
        }

        document.querySelectorAll('[data-family-doc-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                var src = button.getAttribute('data-doc-src') || '';
                var kind = button.getAttribute('data-doc-kind') || 'document';

                if (!src) {
                    return;
                }

                clearContent();

                if (title) {
                    title.textContent = button.getAttribute('data-doc-title') || 'Documento';
                }

                if (kind === 'image' && image) {
                    image.src = src;
                    image.hidden = false;
                } else if (frame) {
                    frame.src = src;
                    frame.hidden = false;
                }

                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                } else {
                    window.open(src, '_blank', 'noopener');
                }
            });
        });

        modal.addEventListener('close', clearContent);
    }

    forms.forEach(function (form) {
        setupRepresentative(form);
        setupBenefit(form);
        setupQuantityStepper(form);
        setupAutofillTracking(form);
        setupDocumentUploads(form);
        setupExistingDocuments(form);
    });

    setupDocumentModal();
})();
