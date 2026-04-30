(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-residence-form]');

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR')
            .trim();
    }

    function parseJson(value, fallback) {
        try {
            var parsed = JSON.parse(value || '');
            return parsed || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function setupCommunitySearch(form) {
        var input = form.querySelector('[data-community-input]');
        var list = form.querySelector('[data-community-suggestions]');
        var options = parseJson(form.dataset.communityOptions, []);

        if (!input || !list || !Array.isArray(options)) {
            return;
        }

        function hide() {
            list.hidden = true;
            list.innerHTML = '';
        }

        function choose(value) {
            input.value = value;
            hide();
            input.focus();
        }

        function render() {
            var query = normalize(input.value);
            var matches = options
                .filter(function (item) {
                    return query === '' || normalize(item).indexOf(query) !== -1;
                })
                .sort(function (left, right) {
                    var leftStarts = normalize(left).indexOf(query) === 0 ? 0 : 1;
                    var rightStarts = normalize(right).indexOf(query) === 0 ? 0 : 1;
                    return leftStarts - rightStarts || String(left).localeCompare(String(right), 'pt-BR');
                })
                .slice(0, 8);

            list.innerHTML = '';

            if (matches.length === 0 || query === '' && options.length === 0) {
                hide();
                return;
            }

            matches.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = item;
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(item);
                });
                list.appendChild(button);
            });

            list.hidden = false;
        }

        input.addEventListener('input', render);
        input.addEventListener('focus', render);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hide();
            }
        });
        input.addEventListener('blur', function () {
            window.setTimeout(hide, 120);
        });
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

    function setupPhotoUpload(form) {
        var wrapper = form.querySelector('[data-photo-upload]');

        if (!wrapper) {
            return;
        }

        var input = wrapper.querySelector('[data-photo-input]');
        var dropzone = wrapper.querySelector('[data-photo-dropzone]');
        var status = wrapper.querySelector('[data-photo-status]');
        var preview = wrapper.querySelector('[data-photo-preview]');
        var previewImage = wrapper.querySelector('[data-photo-preview-image]');
        var previewName = wrapper.querySelector('[data-photo-preview-name]');
        var previewOpen = wrapper.querySelector('[data-photo-open-preview]');
        var title = wrapper.querySelector('[data-photo-title]');
        var description = wrapper.querySelector('[data-photo-description]');
        var logoSrc = wrapper.dataset.photoLogoSrc || '';
        var previewUrl = null;
        var metadataScanId = 0;
        var modal = null;
        var modalImage = null;
        var photoProcessingPromise = null;

        if (!input || !dropzone) {
            return;
        }

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function firstImage(files) {
            return Array.prototype.slice.call(files || []).find(function (file) {
                return file && /^image\//.test(file.type);
            }) || null;
        }

        function setInputFile(file) {
            if (!file) {
                return false;
            }

            if (typeof DataTransfer === 'undefined') {
                return false;
            }

            var transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        function field(selector) {
            return form.querySelector(selector);
        }

        function fieldValue(selector) {
            var element = field(selector);
            return element && element.value ? element.value.trim() : '';
        }

        function dispatchFieldEvents(element) {
            if (!element) {
                return;
            }

            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function createPreviewModal() {
            var closeButton;

            if (modal) {
                return;
            }

            modal = document.createElement('div');
            modal.className = 'photo-preview-modal';
            modal.hidden = true;
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-label', 'Foto georreferenciada ampliada');

            closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'photo-preview-modal-close';
            closeButton.textContent = 'Fechar';

            modalImage = document.createElement('img');
            modalImage.alt = 'Foto georreferenciada ampliada';

            modal.appendChild(closeButton);
            modal.appendChild(modalImage);
            document.body.appendChild(modal);

            function close() {
                modal.hidden = true;
                document.body.classList.remove('is-photo-preview-open');
            }

            closeButton.addEventListener('click', close);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    close();
                }
            });
        }

        function readAscii(view, offset, length) {
            var value = '';
            var index;

            if (offset < 0 || offset + length > view.byteLength) {
                return '';
            }

            for (index = 0; index < length; index += 1) {
                value += String.fromCharCode(view.getUint8(offset + index));
            }

            return value.replace(/\0+$/, '');
        }

        function readUint16(view, offset, littleEndian) {
            if (offset < 0 || offset + 2 > view.byteLength) {
                return 0;
            }

            return view.getUint16(offset, littleEndian);
        }

        function readUint32(view, offset, littleEndian) {
            if (offset < 0 || offset + 4 > view.byteLength) {
                return 0;
            }

            return view.getUint32(offset, littleEndian);
        }

        function readRational(view, offset, littleEndian) {
            var numerator = readUint32(view, offset, littleEndian);
            var denominator = readUint32(view, offset + 4, littleEndian);

            return denominator > 0 ? numerator / denominator : 0;
        }

        function valueOffset(view, tiffStart, entryOffset, littleEndian) {
            return tiffStart + readUint32(view, entryOffset + 8, littleEndian);
        }

        function readGpsCoordinate(view, tiffStart, entryOffset, littleEndian) {
            var offset = valueOffset(view, tiffStart, entryOffset, littleEndian);
            var degrees;
            var minutes;
            var seconds;

            if (offset < tiffStart || offset + 24 > view.byteLength) {
                return null;
            }

            degrees = readRational(view, offset, littleEndian);
            minutes = readRational(view, offset + 8, littleEndian);
            seconds = readRational(view, offset + 16, littleEndian);

            return degrees + minutes / 60 + seconds / 3600;
        }

        function parseGpsIfd(view, tiffStart, gpsIfdOffset, littleEndian) {
            var count = readUint16(view, gpsIfdOffset, littleEndian);
            var latRef = '';
            var lngRef = '';
            var latitude = null;
            var longitude = null;
            var index;
            var entryOffset;
            var tag;

            if (count < 1 || count > 256 || gpsIfdOffset + 2 + count * 12 > view.byteLength) {
                return null;
            }

            for (index = 0; index < count; index += 1) {
                entryOffset = gpsIfdOffset + 2 + index * 12;
                tag = readUint16(view, entryOffset, littleEndian);

                if (tag === 1) {
                    latRef = readAscii(view, entryOffset + 8, 2);
                } else if (tag === 2) {
                    latitude = readGpsCoordinate(view, tiffStart, entryOffset, littleEndian);
                } else if (tag === 3) {
                    lngRef = readAscii(view, entryOffset + 8, 2);
                } else if (tag === 4) {
                    longitude = readGpsCoordinate(view, tiffStart, entryOffset, littleEndian);
                }
            }

            if (latitude === null || longitude === null) {
                return null;
            }

            if (latRef.toUpperCase() === 'S') {
                latitude *= -1;
            }

            if (lngRef.toUpperCase() === 'W') {
                longitude *= -1;
            }

            return {
                latitude: latitude,
                longitude: longitude
            };
        }

        function parseExifGps(view, tiffStart) {
            var endian = readAscii(view, tiffStart, 2);
            var littleEndian = endian === 'II';
            var ifdOffset;
            var ifdStart;
            var count;
            var index;
            var entryOffset;
            var tag;
            var gpsOffset = 0;

            if (!littleEndian && endian !== 'MM') {
                return null;
            }

            if (readUint16(view, tiffStart + 2, littleEndian) !== 42) {
                return null;
            }

            ifdOffset = readUint32(view, tiffStart + 4, littleEndian);
            ifdStart = tiffStart + ifdOffset;
            count = readUint16(view, ifdStart, littleEndian);

            if (count < 1 || count > 256 || ifdStart + 2 + count * 12 > view.byteLength) {
                return null;
            }

            for (index = 0; index < count; index += 1) {
                entryOffset = ifdStart + 2 + index * 12;
                tag = readUint16(view, entryOffset, littleEndian);

                if (tag === 0x8825) {
                    gpsOffset = readUint32(view, entryOffset + 8, littleEndian);
                    break;
                }
            }

            return gpsOffset > 0 ? parseGpsIfd(view, tiffStart, tiffStart + gpsOffset, littleEndian) : null;
        }

        function extractPhotoGps(file) {
            if (!file || typeof file.arrayBuffer !== 'function') {
                return Promise.resolve(null);
            }

            return file.arrayBuffer().then(function (buffer) {
                var view = new DataView(buffer);
                var offset = 2;
                var marker;
                var segmentLength;
                var segmentStart;
                var segmentEnd;

                if (view.byteLength < 4 || view.getUint16(0, false) !== 0xFFD8) {
                    return null;
                }

                while (offset + 4 <= view.byteLength) {
                    if (view.getUint8(offset) !== 0xFF) {
                        return null;
                    }

                    marker = view.getUint8(offset + 1);
                    segmentLength = view.getUint16(offset + 2, false);
                    segmentStart = offset + 4;
                    segmentEnd = offset + 2 + segmentLength;

                    if (segmentLength < 2 || segmentEnd > view.byteLength) {
                        return null;
                    }

                    if (marker === 0xE1 && readAscii(view, segmentStart, 6) === 'Exif') {
                        return parseExifGps(view, segmentStart + 6);
                    }

                    offset = segmentEnd;
                }

                return null;
            }).catch(function () {
                return null;
            });
        }

        function fillAddressFromCoordinates(lat, lng, sourceLabel) {
            var address = field('[data-address]');
            var community = field('[data-community-input]');

            if (!window.CadastroGeo || typeof window.CadastroGeo.reverseGeocode !== 'function') {
                setStatus(sourceLabel + ' encontrado. Endereco pode ser preenchido manualmente.');
                return Promise.resolve();
            }

            setStatus(sourceLabel + ' encontrado. Buscando endereco real...');

            return window.CadastroGeo.reverseGeocode(lat, lng).then(function (result) {
                if (result.address !== '' && address) {
                    address.value = result.address;
                    dispatchFieldEvents(address);
                }

                if (result.community !== '' && community && community.value.trim() === '') {
                    community.value = result.community;
                    dispatchFieldEvents(community);
                }

                if (result.address !== '') {
                    setStatus(sourceLabel + ' encontrado. Campos preenchidos automaticamente.');
                    return;
                }

                setStatus(sourceLabel + ' encontrado. Endereco pode ser preenchido manualmente.');
            });
        }

        function applyPhotoGeolocation(file) {
            var latitude = field('[data-latitude]');
            var longitude = field('[data-longitude]');
            var scanId = metadataScanId += 1;

            return extractPhotoGps(file).then(function (coords) {
                if (scanId !== metadataScanId) {
                    return null;
                }

                if (!coords) {
                    setStatus('Foto sem GPS interno. Capturando localizacao atual do celular...');
                    return false;
                }

                if (latitude) {
                    latitude.value = coords.latitude.toFixed(7);
                    dispatchFieldEvents(latitude);
                }

                if (longitude) {
                    longitude.value = coords.longitude.toFixed(7);
                    dispatchFieldEvents(longitude);
                }

                return fillAddressFromCoordinates(coords.latitude.toFixed(7), coords.longitude.toFixed(7), 'GPS da foto').then(function () {
                    return true;
                });
            });
        }

        function geolocationErrorMessage(error) {
            if (!window.isSecureContext && !/^(localhost|127\.0\.0\.1|::1|\[::1\])$/.test(window.location.hostname)) {
                return 'Para capturar a localizacao pelo celular, acesse o sistema em HTTPS.';
            }

            switch (error && error.code) {
                case 1:
                    return 'Permita o acesso a localizacao do celular para preencher os dados da foto.';
                case 2:
                    return 'Nao foi possivel obter a localizacao atual do celular.';
                case 3:
                    return 'A localizacao demorou demais. Tente novamente em local com melhor sinal.';
                default:
                    return 'Nao foi possivel capturar a localizacao atual.';
            }
        }

        function captureDeviceGeolocationForPhoto() {
            var latitude = field('[data-latitude]');
            var longitude = field('[data-longitude]');

            if (!('geolocation' in navigator) || !latitude || !longitude) {
                setStatus('Foto pronta. Localizacao pode ser preenchida manualmente.');
                return Promise.resolve(false);
            }

            setStatus('Capturando localizacao atual do celular...');

            return new Promise(function (resolve) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    var lat = position.coords.latitude.toFixed(7);
                    var lng = position.coords.longitude.toFixed(7);

                    latitude.value = lat;
                    longitude.value = lng;
                    dispatchFieldEvents(latitude);
                    dispatchFieldEvents(longitude);

                    fillAddressFromCoordinates(lat, lng, 'Localizacao da foto').then(function () {
                        resolve(true);
                    });
                }, function (error) {
                    setStatus(geolocationErrorMessage(error));
                    resolve(false);
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                });
            });
        }

        function updatePreview() {
            var file = input.files && input.files[0] ? input.files[0] : null;

            if (form.dataset.photoProcessing !== 'true') {
                form.dataset.photoProcessed = '';
            }

            if (!file) {
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }

                if (preview) {
                    preview.hidden = true;
                }

                if (previewOpen) {
                    previewOpen.disabled = true;
                }

                dropzone.classList.remove('has-file');
                if (title) {
                    title.textContent = 'Selecionar foto';
                }
                if (description) {
                    description.textContent = 'Arraste, cole, busque nos arquivos ou tire uma foto pela camera do celular.';
                }
                setStatus('Ao enviar, a foto recebera localidade, endereco, latitude, longitude, data e hora.');
                return;
            }

            if (preview && previewImage && /^image\//.test(file.type)) {
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                }

                previewUrl = URL.createObjectURL(file);
                previewImage.src = previewUrl;
                preview.hidden = false;
            }

            if (previewName) {
                previewName.textContent = file.name;
            }

            if (previewOpen) {
                previewOpen.disabled = false;
            }

            dropzone.classList.add('has-file');
            if (title) {
                title.textContent = 'Foto selecionada';
            }
            if (description) {
                description.textContent = 'Clique para trocar a imagem ou cole outra foto no formulario.';
            }
            setStatus('Foto selecionada. Os dados de localizacao serao gravados na imagem antes do envio.');
        }

        function metadataPayload() {
            var now = new Date();

            return {
                sistema: 'Cadastro Emergencial',
                municipio: form.dataset.actionMunicipality || '',
                uf: form.dataset.actionState || '',
                localidade_acao: form.dataset.actionLocality || '',
                evento: form.dataset.actionEvent || '',
                bairro_comunidade: fieldValue('[data-community-input]'),
                endereco: fieldValue('[data-address]'),
                latitude: fieldValue('[data-latitude]'),
                longitude: fieldValue('[data-longitude]'),
                data_hora_iso: now.toISOString(),
                data_hora_br: now.toLocaleString('pt-BR')
            };
        }

        function textLines() {
            var metadata = metadataPayload();

            return [
                'Cadastro Emergencial',
                'Municipio: ' + [form.dataset.actionMunicipality, form.dataset.actionState].filter(Boolean).join(' / '),
                'Localidade: ' + (metadata.bairro_comunidade || metadata.localidade_acao || '-'),
                'Endereco: ' + (metadata.endereco || '-'),
                'Lat: ' + (metadata.latitude || '-') + ' | Long: ' + (metadata.longitude || '-'),
                'Data/hora: ' + metadata.data_hora_br
            ];
        }

        function loadImage(file) {
            return new Promise(function (resolve, reject) {
                var image = new Image();
                var url = URL.createObjectURL(file);

                image.onload = function () {
                    URL.revokeObjectURL(url);
                    resolve(image);
                };

                image.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error('Imagem invalida.'));
                };

                image.src = url;
            });
        }

        function loadLogoImage() {
            if (logoSrc === '') {
                return Promise.resolve(null);
            }

            return new Promise(function (resolve) {
                var image = new Image();

                image.onload = function () {
                    resolve(image);
                };

                image.onerror = function () {
                    resolve(null);
                };

                image.src = logoSrc;
            });
        }

        function canvasToBlob(canvas) {
            return new Promise(function (resolve) {
                canvas.toBlob(resolve, 'image/jpeg', 0.88);
            });
        }

        function blobToArrayBuffer(blob) {
            if (typeof blob.arrayBuffer === 'function') {
                return blob.arrayBuffer();
            }

            return new Promise(function (resolve, reject) {
                var reader = new FileReader();

                reader.onload = function () {
                    resolve(reader.result);
                };

                reader.onerror = function () {
                    reject(reader.error || new Error('Falha ao ler imagem.'));
                };

                reader.readAsArrayBuffer(blob);
            });
        }

        function addJpegCommentMetadata(blob, metadata) {
            return blobToArrayBuffer(blob).then(function (buffer) {
                var bytes = new Uint8Array(buffer);
                var encoder = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
                var comment = 'CadastroEmergencialGeo=' + JSON.stringify(metadata);
                var commentBytes;
                var output;
                var length;

                if (bytes.length < 2 || bytes[0] !== 0xFF || bytes[1] !== 0xD8 || !encoder) {
                    return blob;
                }

                commentBytes = encoder.encode(comment);

                if (commentBytes.length > 65000) {
                    return blob;
                }

                length = commentBytes.length + 2;
                output = new Uint8Array(bytes.length + commentBytes.length + 4);
                output[0] = 0xFF;
                output[1] = 0xD8;
                output[2] = 0xFF;
                output[3] = 0xFE;
                output[4] = (length >> 8) & 0xFF;
                output[5] = length & 0xFF;
                output.set(commentBytes, 6);
                output.set(bytes.slice(2), 6 + commentBytes.length);

                return new Blob([output], { type: 'image/jpeg' });
            });
        }

        function drawLogo(context, logo, width) {
            var padding;
            var maxLogoWidth;
            var logoWidth;
            var logoHeight;

            if (!logo || !logo.naturalWidth || !logo.naturalHeight) {
                return;
            }

            padding = Math.max(18, Math.round(width * 0.018));
            maxLogoWidth = Math.max(92, Math.min(210, Math.round(width * 0.18)));
            logoWidth = maxLogoWidth;
            logoHeight = Math.round(logoWidth * logo.naturalHeight / logo.naturalWidth);

            context.save();
            context.shadowColor = 'rgba(0, 0, 0, 0.28)';
            context.shadowBlur = Math.max(6, Math.round(width * 0.006));
            context.shadowOffsetY = Math.max(2, Math.round(width * 0.002));
            context.drawImage(logo, width - logoWidth - padding, padding * 1.2, logoWidth, logoHeight);
            context.restore();
        }

        function drawWatermark(image, file, logo) {
            var maxSide = 1920;
            var scale = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));
            var width = Math.max(1, Math.round(image.naturalWidth * scale));
            var height = Math.max(1, Math.round(image.naturalHeight * scale));
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d');
            var lines = textLines();
            var fontSize = Math.max(18, Math.round(width * 0.018));
            var padding = Math.max(18, Math.round(width * 0.018));
            var lineHeight = Math.round(fontSize * 1.35);
            var overlayHeight = padding * 2 + lineHeight * lines.length;

            canvas.width = width;
            canvas.height = height;
            context.drawImage(image, 0, 0, width, height);
            drawLogo(context, logo, width);
            context.fillStyle = 'rgba(0, 0, 0, 0.68)';
            context.fillRect(0, Math.max(0, height - overlayHeight), width, overlayHeight);
            context.fillStyle = '#ffffff';
            context.font = '700 ' + fontSize + 'px Arial, Helvetica, sans-serif';
            context.textBaseline = 'top';

            lines.forEach(function (line, index) {
                var value = String(line);
                var maxWidth = width - padding * 2;

                while (context.measureText(value).width > maxWidth && value.length > 12) {
                    value = value.slice(0, -2);
                }

                if (value !== String(line)) {
                    value += '...';
                }

                context.fillText(value, padding, height - overlayHeight + padding + index * lineHeight);
            });

            return canvasToBlob(canvas).then(function (blob) {
                if (!blob) {
                    return file;
                }

                return addJpegCommentMetadata(blob, metadataPayload());
            }).then(function (blob) {
                var baseName = file.name.replace(/\.[^.]+$/, '') || 'foto';
                return new File([blob], baseName + '-georreferenciada.jpg', { type: 'image/jpeg' });
            });
        }

        function processPhoto() {
            var file = input.files && input.files[0] ? input.files[0] : null;

            if (photoProcessingPromise) {
                return photoProcessingPromise;
            }

            if (!file || !/^image\//.test(file.type)) {
                return Promise.resolve();
            }

            form.dataset.photoProcessing = 'true';
            setStatus('Gravando dados de localizacao na foto...');

            photoProcessingPromise = Promise.all([loadImage(file), loadLogoImage()]).then(function (items) {
                return drawWatermark(items[0], file, items[1]);
            }).then(function (processedFile) {
                if (!setInputFile(processedFile)) {
                    form.dataset.photoProcessing = '';
                    setStatus('Nao foi possivel substituir a foto automaticamente. O envio seguira com o arquivo original.');
                    return;
                }

                form.dataset.photoProcessing = '';
                form.dataset.photoProcessed = 'true';
                setStatus('Foto georreferenciada pronta. Confira na previa ampliada antes de salvar.');
            }).catch(function () {
                form.dataset.photoProcessing = '';
                setStatus('Nao foi possivel gravar os dados na foto. O envio seguira com o arquivo original.');
            }).finally(function () {
                photoProcessingPromise = null;
            });

            return photoProcessingPromise;
        }

        input.addEventListener('change', function () {
            var file;

            updatePreview();

            if (form.dataset.photoProcessing === 'true') {
                return;
            }

            file = input.files && input.files[0] ? input.files[0] : null;

            if (!file || !/^image\//.test(file.type)) {
                return;
            }

            setStatus('Foto selecionada. Verificando GPS da imagem...');
            applyPhotoGeolocation(file).then(function (foundPhotoGps) {
                if (foundPhotoGps === false) {
                    return captureDeviceGeolocationForPhoto();
                }

                return foundPhotoGps;
            }).then(function () {
                return processPhoto();
            });
        });

        if (previewOpen) {
            previewOpen.disabled = true;
            previewOpen.addEventListener('click', function () {
                if (!previewUrl) {
                    return;
                }

                createPreviewModal();
                modalImage.src = previewUrl;
                modal.hidden = false;
                document.body.classList.add('is-photo-preview-open');
            });
        }

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
            var file = firstImage(event.dataTransfer ? event.dataTransfer.files : []);

            if (!setInputFile(file)) {
                setStatus('Nao foi possivel anexar a imagem arrastada neste navegador.');
            }
        });

        form.addEventListener('paste', function (event) {
            var file = firstImage(event.clipboardData ? event.clipboardData.files : []);

            if (file && !setInputFile(file)) {
                setStatus('Nao foi possivel colar a imagem neste navegador.');
            }
        });

        form.addEventListener('reset', function () {
            window.setTimeout(updatePreview, 0);
        });

        form.addEventListener('submit', function (event) {
            if (form.dataset.photoProcessed === 'true') {
                return;
            }

            var file = input.files && input.files[0] ? input.files[0] : null;

            if (!file || !/^image\//.test(file.type)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            processPhoto().finally(function () {
                form.dataset.photoProcessed = 'true';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }, true);
    }

    forms.forEach(function (form) {
        setupCommunitySearch(form);
        setupQuantityStepper(form);
        setupPhotoUpload(form);
    });
})();
