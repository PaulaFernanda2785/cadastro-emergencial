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
        var previewClear = wrapper.querySelector('[data-photo-clear]');
        var title = wrapper.querySelector('[data-photo-title]');
        var description = wrapper.querySelector('[data-photo-description]');
        var logoSrc = wrapper.dataset.photoLogoSrc || '';
        var previewUrl = null;
        var metadataScanId = 0;
        var photoMetadataPrefix = 'CadastroEmergencialGeo=';
        var photoFilledFields = {};
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

        function clearField(element) {
            if (!element || element.value === '') {
                return;
            }

            element.value = '';
            dispatchFieldEvents(element);
        }

        function fieldKey(element) {
            return element && element.name ? element.name : '';
        }

        function trackPhotoField(element) {
            var key = fieldKey(element);

            if (key === '') {
                return;
            }

            photoFilledFields[key] = element.value;
        }

        function clearPhotoFilledFields() {
            Object.keys(photoFilledFields).forEach(function (key) {
                var element = form.querySelector('[name="' + key.replace(/"/g, '\\"') + '"]');

                if (element && element.value === photoFilledFields[key]) {
                    clearField(element);
                }
            });

            photoFilledFields = {};

            if (String(form.dataset.locationSource || '').indexOf('photo-') === 0) {
                form.dataset.locationSource = '';
            }

            form.dataset.photoLocationSource = '';
        }

        function clearDeviceLocationFields() {
            if (form.dataset.locationSource !== 'device-current') {
                return;
            }

            clearField(field('[data-latitude]'));
            clearField(field('[data-longitude]'));

            if (form.dataset.addressSource === 'device-current') {
                clearField(field('[data-address]'));
                form.dataset.addressSource = '';
            }

            if (form.dataset.communitySource === 'device-current') {
                clearField(field('[data-community-input]'));
                form.dataset.communitySource = '';
            }

            form.dataset.locationSource = '';
        }

        function requestCurrentPosition() {
            return new Promise(function (resolve) {
                if (!('geolocation' in navigator)) {
                    resolve(null);
                    return;
                }

                navigator.geolocation.getCurrentPosition(function (position) {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    });
                }, function () {
                    resolve(null);
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                });
            });
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

        function readText(view, offset, length) {
            var bytes;

            if (offset < 0 || length < 0 || offset + length > view.byteLength) {
                return '';
            }

            if (typeof TextDecoder !== 'undefined') {
                bytes = new Uint8Array(view.buffer, view.byteOffset + offset, length);
                return new TextDecoder('utf-8').decode(bytes).replace(/\0+$/, '');
            }

            return readAscii(view, offset, length);
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

        function parseStoredPhotoMetadata(comment) {
            var prefixIndex = String(comment || '').indexOf(photoMetadataPrefix);
            var payload;
            var metadata;

            if (prefixIndex === -1) {
                return null;
            }

            payload = String(comment).slice(prefixIndex + photoMetadataPrefix.length).trim();

            try {
                metadata = JSON.parse(payload);
            } catch (error) {
                return null;
            }

            return metadata && typeof metadata === 'object' && !Array.isArray(metadata) ? metadata : null;
        }

        function decimalValue(value) {
            var number;

            if (value === null || typeof value === 'undefined') {
                return null;
            }

            number = Number(String(value).trim().replace(',', '.'));

            return Number.isFinite(number) ? number : null;
        }

        function validCoordinates(latitude, longitude) {
            return latitude !== null && longitude !== null &&
                latitude >= -90 && latitude <= 90 &&
                longitude >= -180 && longitude <= 180;
        }

        function coordinatesFromMetadata(metadata) {
            var latitude = decimalValue(metadata && metadata.latitude);
            var longitude = decimalValue(metadata && metadata.longitude);

            if (!validCoordinates(latitude, longitude)) {
                return null;
            }

            return {
                latitude: latitude,
                longitude: longitude
            };
        }

        function normalizedText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function parseCoordinatesFromText(text) {
            var normalized = String(text || '')
                .replace(/\s*([\.,])\s*/g, '$1')
                .replace(/(-?\d{1,3})\s+(?=[\.,]\d)/g, '$1');
            var matches = normalized.match(/-?\d{1,3}[\.,]\d{4,}/g) || [];
            var latitude;
            var longitude;

            matches.some(function (match, index) {
                var first = decimalValue(match);
                var second = index + 1 < matches.length ? decimalValue(matches[index + 1]) : null;

                if (validCoordinates(first, second)) {
                    latitude = first;
                    longitude = second;
                    return true;
                }

                return false;
            });

            return validCoordinates(latitude, longitude)
                ? { latitude: latitude, longitude: longitude }
                : null;
        }

        function extractPhotoGeodata(file) {
            if (!file || typeof file.arrayBuffer !== 'function') {
                return Promise.resolve({ metadata: null, coords: null });
            }

            return file.arrayBuffer().then(function (buffer) {
                var view = new DataView(buffer);
                var offset = 2;
                var marker;
                var segmentLength;
                var segmentStart;
                var segmentEnd;
                var metadata = null;
                var coords = null;
                var parsedCoords;
                var parsedMetadata;

                if (view.byteLength < 4 || view.getUint16(0, false) !== 0xFFD8) {
                    return { metadata: null, coords: null };
                }

                while (offset + 4 <= view.byteLength) {
                    if (view.getUint8(offset) !== 0xFF) {
                        break;
                    }

                    marker = view.getUint8(offset + 1);

                    if (marker === 0xDA || marker === 0xD9) {
                        break;
                    }

                    segmentLength = view.getUint16(offset + 2, false);
                    segmentStart = offset + 4;
                    segmentEnd = offset + 2 + segmentLength;

                    if (segmentLength < 2 || segmentEnd > view.byteLength) {
                        break;
                    }

                    if (marker === 0xFE && metadata === null) {
                        parsedMetadata = parseStoredPhotoMetadata(readText(view, segmentStart, segmentLength - 2));

                        if (parsedMetadata !== null) {
                            metadata = parsedMetadata;
                        }
                    } else if (marker === 0xE1 && coords === null && readAscii(view, segmentStart, 6) === 'Exif') {
                        parsedCoords = parseExifGps(view, segmentStart + 6);

                        if (parsedCoords && validCoordinates(parsedCoords.latitude, parsedCoords.longitude)) {
                            coords = parsedCoords;
                        }
                    }

                    offset = segmentEnd;
                }

                return {
                    metadata: metadata,
                    coords: coordinatesFromMetadata(metadata) || coords
                };
            }).catch(function () {
                return { metadata: null, coords: null };
            });
        }

        function fillAddressFromCoordinates(lat, lng, sourceLabel, scanId) {
            var address = field('[data-address]');
            var community = field('[data-community-input]');

            if (!window.CadastroGeo || typeof window.CadastroGeo.reverseGeocode !== 'function') {
                setStatus(sourceLabel + ' encontrado. Endereco pode ser preenchido manualmente.');
                return Promise.resolve();
            }

            setStatus(sourceLabel + ' encontrado. Buscando endereco real...');

            return window.CadastroGeo.reverseGeocode(lat, lng).then(function (result) {
                if (scanId && scanId !== metadataScanId) {
                    return;
                }

                if (result.address !== '' && address) {
                    address.value = result.address;
                    dispatchFieldEvents(address);
                    trackPhotoField(address);
                }

                if (result.community !== '' && community && community.value.trim() === '') {
                    community.value = result.community;
                    dispatchFieldEvents(community);
                    trackPhotoField(community);
                }

                if (result.address !== '') {
                    setStatus(sourceLabel + ' encontrado. Campos preenchidos automaticamente.');
                    return;
                }

                setStatus(sourceLabel + ' encontrado. Endereco pode ser preenchido manualmente.');
            });
        }

        function cropBinaryBounds(mask, width, height) {
            var minX = width;
            var minY = height;
            var maxX = -1;
            var maxY = -1;
            var x;
            var y;

            for (y = 0; y < height; y += 1) {
                for (x = 0; x < width; x += 1) {
                    if (!mask[y * width + x]) {
                        continue;
                    }

                    minX = Math.min(minX, x);
                    minY = Math.min(minY, y);
                    maxX = Math.max(maxX, x);
                    maxY = Math.max(maxY, y);
                }
            }

            if (maxX < minX || maxY < minY) {
                return null;
            }

            return {
                minX: minX,
                minY: minY,
                maxX: maxX,
                maxY: maxY,
                width: maxX - minX + 1,
                height: maxY - minY + 1
            };
        }

        function normalizeMask(mask, width, height, bounds, gridWidth, gridHeight) {
            var grid = [];
            var x;
            var y;
            var sourceX;
            var sourceY;

            if (!bounds) {
                return grid;
            }

            for (y = 0; y < gridHeight; y += 1) {
                sourceY = bounds.minY + Math.min(bounds.height - 1, Math.floor((y + 0.5) * bounds.height / gridHeight));

                for (x = 0; x < gridWidth; x += 1) {
                    sourceX = bounds.minX + Math.min(bounds.width - 1, Math.floor((x + 0.5) * bounds.width / gridWidth));
                    grid.push(mask[sourceY * width + sourceX] ? 1 : 0);
                }
            }

            return grid;
        }

        function ocrTemplates(gridWidth, gridHeight) {
            var chars = '0123456789';
            var canvas = document.createElement('canvas');
            var context;
            var templates = {};
            var data;
            var mask;
            var bounds;
            var index;
            var offset;

            canvas.width = 72;
            canvas.height = 96;
            context = canvas.getContext('2d');

            chars.split('').forEach(function (char) {
                context.clearRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = '#000000';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = '#ffffff';
                context.font = '700 72px Arial, Helvetica, sans-serif';
                context.textBaseline = 'alphabetic';
                context.fillText(char, 8, 76);

                data = context.getImageData(0, 0, canvas.width, canvas.height).data;
                mask = new Uint8Array(canvas.width * canvas.height);

                for (index = 0; index < mask.length; index += 1) {
                    offset = index * 4;
                    mask[index] = data[offset] > 20 || data[offset + 1] > 20 || data[offset + 2] > 20 ? 1 : 0;
                }

                bounds = cropBinaryBounds(mask, canvas.width, canvas.height);
                templates[char] = normalizeMask(mask, canvas.width, canvas.height, bounds, gridWidth, gridHeight);
            });

            return templates;
        }

        function compareGrid(left, right) {
            var score = 0;
            var index;

            for (index = 0; index < left.length; index += 1) {
                if (left[index] === right[index]) {
                    score += 1;
                }
            }

            return score / Math.max(1, left.length);
        }

        function recognizeDigit(mask, width, height, bounds, lineHeight, templates) {
            var gridWidth = 20;
            var gridHeight = 32;
            var grid;
            var bestChar = '';
            var bestScore = -1;

            if (bounds.height < lineHeight * 0.22) {
                return bounds.width > bounds.height * 1.9 ? '-' : '.';
            }

            grid = normalizeMask(mask, width, height, bounds, gridWidth, gridHeight);

            Object.keys(templates).forEach(function (char) {
                var score = compareGrid(grid, templates[char]);

                if (score > bestScore) {
                    bestScore = score;
                    bestChar = char;
                }
            });

            return bestScore >= 0.52 ? bestChar : '';
        }

        function recognizeCoordinateLine(mask, width, height, templates) {
            var colCounts = [];
            var segments = [];
            var text = '';
            var x;
            var y;
            var active = false;
            var start = 0;
            var gap = 0;
            var index;
            var segmentMask;
            var segmentBounds;

            for (x = 0; x < width; x += 1) {
                colCounts[x] = 0;

                for (y = 0; y < height; y += 1) {
                    if (mask[y * width + x]) {
                        colCounts[x] += 1;
                    }
                }
            }

            for (x = 0; x <= width; x += 1) {
                if (x < width && colCounts[x] > 0) {
                    if (!active) {
                        active = true;
                        start = x;
                    }
                    gap = 0;
                    continue;
                }

                if (!active) {
                    continue;
                }

                gap += 1;

                if (gap <= 1 && x < width) {
                    continue;
                }

                segments.push({ start: start, end: x - gap });
                active = false;
                gap = 0;
            }

            segments.forEach(function (segment, segmentIndex) {
                var segmentWidth = segment.end - segment.start + 1;

                if (segmentIndex > 0 && segment.start - segments[segmentIndex - 1].end > Math.max(6, Math.round(height * 0.32))) {
                    text += ' ';
                }

                if (segmentWidth <= 0) {
                    return;
                }

                segmentMask = new Uint8Array(segmentWidth * height);

                for (y = 0; y < height; y += 1) {
                    for (index = 0; index < segmentWidth; index += 1) {
                        segmentMask[y * segmentWidth + index] = mask[y * width + segment.start + index];
                    }
                }

                segmentBounds = cropBinaryBounds(segmentMask, segmentWidth, height);

                if (!segmentBounds) {
                    return;
                }

                text += recognizeDigit(segmentMask, segmentWidth, height, segmentBounds, height, templates);
            });

            return text;
        }

        function buildWhiteTextMask(context, cropX, cropY, width, height) {
            var imageData = context.getImageData(cropX, cropY, width, height).data;
            var mask = new Uint8Array(width * height);
            var index;
            var offset;
            var red;
            var green;
            var blue;
            var max;
            var min;

            for (index = 0; index < mask.length; index += 1) {
                offset = index * 4;
                red = imageData[offset];
                green = imageData[offset + 1];
                blue = imageData[offset + 2];
                max = Math.max(red, green, blue);
                min = Math.min(red, green, blue);

                if ((red > 178 && green > 178 && blue > 178 && max - min < 92) || (red > 225 && green > 225 && blue > 225)) {
                    mask[index] = 1;
                }
            }

            return mask;
        }

        function candidateTextLineMasks(mask, width, height) {
            var rowCounts = [];
            var threshold = Math.max(4, Math.round(width * 0.01));
            var bands = [];
            var active = false;
            var start = 0;
            var gap = 0;
            var x;
            var y;

            for (y = 0; y < height; y += 1) {
                rowCounts[y] = 0;

                for (x = 0; x < width; x += 1) {
                    if (mask[y * width + x]) {
                        rowCounts[y] += 1;
                    }
                }
            }

            for (y = 0; y <= height; y += 1) {
                if (y < height && rowCounts[y] >= threshold) {
                    if (!active) {
                        active = true;
                        start = y;
                    }
                    gap = 0;
                    continue;
                }

                if (!active) {
                    continue;
                }

                gap += 1;

                if (gap <= 3 && y < height) {
                    continue;
                }

                bands.push({ start: start, end: y - gap });
                active = false;
                gap = 0;
            }

            return bands.map(function (band) {
                var bandHeight = band.end - band.start + 1;
                var bandMask;
                var bounds;
                var lineMask;
                var lineWidth;
                var paddingX;
                var paddingY;
                var minX;
                var maxX;
                var minY;
                var maxY;

                if (bandHeight < 12 || bandHeight > 90) {
                    return null;
                }

                bandMask = new Uint8Array(width * bandHeight);

                for (y = 0; y < bandHeight; y += 1) {
                    for (x = 0; x < width; x += 1) {
                        bandMask[y * width + x] = mask[(band.start + y) * width + x];
                    }
                }

                bounds = cropBinaryBounds(bandMask, width, bandHeight);

                if (!bounds || bounds.width < 90 || bounds.height < 10) {
                    return null;
                }

                paddingX = Math.max(2, Math.round(bounds.height * 0.15));
                paddingY = Math.max(1, Math.round(bounds.height * 0.08));
                minX = Math.max(0, bounds.minX - paddingX);
                maxX = Math.min(width - 1, bounds.maxX + paddingX);
                minY = Math.max(0, bounds.minY - paddingY);
                maxY = Math.min(bandHeight - 1, bounds.maxY + paddingY);
                lineWidth = maxX - minX + 1;
                lineMask = new Uint8Array(lineWidth * (maxY - minY + 1));

                for (y = minY; y <= maxY; y += 1) {
                    for (x = minX; x <= maxX; x += 1) {
                        lineMask[(y - minY) * lineWidth + (x - minX)] = bandMask[y * width + x];
                    }
                }

                return {
                    mask: lineMask,
                    width: lineWidth,
                    height: maxY - minY + 1,
                    y: band.start + minY
                };
            }).filter(Boolean).sort(function (left, right) {
                return right.y - left.y;
            });
        }

        function extractStampedCoordinates(file) {
            return loadImage(file).then(function (image) {
                var maxWidth = 1200;
                var scale = Math.min(1, maxWidth / Math.max(1, image.naturalWidth));
                var canvas = document.createElement('canvas');
                var context = canvas.getContext('2d');
                var cropX;
                var cropY;
                var cropWidth;
                var cropHeight;
                var mask;
                var lines;
                var templates = ocrTemplates(20, 32);
                var found = null;

                canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                context.drawImage(image, 0, 0, canvas.width, canvas.height);

                cropX = Math.floor(canvas.width * 0.35);
                cropY = Math.floor(canvas.height * 0.48);
                cropWidth = canvas.width - cropX;
                cropHeight = canvas.height - cropY;
                mask = buildWhiteTextMask(context, cropX, cropY, cropWidth, cropHeight);
                lines = candidateTextLineMasks(mask, cropWidth, cropHeight);

                lines.some(function (line) {
                    var text = recognizeCoordinateLine(line.mask, line.width, line.height, templates);
                    var coords = parseCoordinatesFromText(text);

                    if (coords) {
                        found = coords;
                        return true;
                    }

                    return false;
                });

                return found;
            }).catch(function () {
                return null;
            });
        }

        function applyPhotoGeolocation(file) {
            var latitude = field('[data-latitude]');
            var longitude = field('[data-longitude]');
            var scanId = metadataScanId += 1;

            function setFieldValue(element, value) {
                value = normalizedText(value);

                if (!element || value === '') {
                    return false;
                }

                if (element.maxLength > 0 && value.length > element.maxLength) {
                    value = value.slice(0, element.maxLength);
                }

                element.value = value;
                dispatchFieldEvents(element);
                trackPhotoField(element);
                return true;
            }

            function applyPhotoCoordinates(coords, sourceKey, sourceLabel) {
                form.dataset.photoLocationSource = sourceKey;
                form.dataset.locationSource = sourceKey;

                if (latitude) {
                    latitude.value = coords.latitude.toFixed(7);
                    dispatchFieldEvents(latitude);
                    trackPhotoField(latitude);
                }

                if (longitude) {
                    longitude.value = coords.longitude.toFixed(7);
                    dispatchFieldEvents(longitude);
                    trackPhotoField(longitude);
                }

                return fillAddressFromCoordinates(coords.latitude.toFixed(7), coords.longitude.toFixed(7), sourceLabel, scanId).then(function () {
                    return true;
                });
            }

            function applyCurrentLocationForPhoto() {
                form.dataset.photoLocationSource = 'pending-device';
                setStatus('Foto sem metadados ou coordenadas carimbadas. Buscando localizacao atual do aparelho...');

                return requestCurrentPosition().then(function (coords) {
                    if (scanId !== metadataScanId) {
                        return null;
                    }

                    if (!coords || !validCoordinates(coords.latitude, coords.longitude)) {
                        form.dataset.photoLocationSource = 'missing';
                        setStatus('Foto sem coordenadas legiveis e sem permissao de localizacao atual.');
                        return false;
                    }

                    return applyPhotoCoordinates(coords, 'photo-device-current', 'Localizacao atual do aparelho');
                });
            }

            function applyStoredMetadata(metadata, coords) {
                var address = field('[data-address]');
                var community = field('[data-community-input]');
                var changed = false;

                if (!metadata) {
                    return false;
                }

                if (coords) {
                    form.dataset.locationSource = 'photo-metadata';
                    form.dataset.photoLocationSource = 'photo-metadata';
                    changed = setFieldValue(latitude, coords.latitude.toFixed(7)) || changed;
                    changed = setFieldValue(longitude, coords.longitude.toFixed(7)) || changed;
                } else {
                    clearDeviceLocationFields();
                }

                changed = setFieldValue(address, metadata.endereco) || changed;
                changed = setFieldValue(community, metadata.bairro_comunidade || metadata.localidade_acao) || changed;

                return changed;
            }

            return extractPhotoGeodata(file).then(function (geodata) {
                var coords = geodata && geodata.coords ? geodata.coords : null;

                if (scanId !== metadataScanId) {
                    return null;
                }

                if (geodata && geodata.metadata) {
                    if (applyStoredMetadata(geodata.metadata, coords) && coords) {
                        if (normalizedText(geodata.metadata.endereco) === '') {
                            return fillAddressFromCoordinates(coords.latitude.toFixed(7), coords.longitude.toFixed(7), 'Metadados da foto', scanId).then(function () {
                                return true;
                            });
                        }

                        setStatus('Metadados da foto encontrados. Campos preenchidos automaticamente.');
                        return true;
                    }

                    setStatus('Metadados da foto sem coordenadas. Tentando ler coordenadas carimbadas na imagem...');

                    return extractStampedCoordinates(file).then(function (ocrCoords) {
                        if (scanId !== metadataScanId) {
                            return null;
                        }

                        if (ocrCoords) {
                            return applyPhotoCoordinates(ocrCoords, 'photo-ocr', 'Coordenadas lidas da foto');
                        }

                        form.dataset.photoLocationSource = 'missing';
                        return applyCurrentLocationForPhoto();
                    });
                }

                if (!coords) {
                    form.dataset.photoLocationSource = 'missing';
                    clearDeviceLocationFields();
                    setStatus('Foto sem metadados de localizacao. Tentando ler coordenadas carimbadas na imagem...');

                    return extractStampedCoordinates(file).then(function (ocrCoords) {
                        if (scanId !== metadataScanId) {
                            return null;
                        }

                        if (!ocrCoords) {
                            return applyCurrentLocationForPhoto();
                        }

                        return applyPhotoCoordinates(ocrCoords, 'photo-ocr', 'Coordenadas lidas da foto');
                    });
                }

                form.dataset.photoLocationSource = 'photo-metadata';
                form.dataset.locationSource = 'photo-metadata';

                if (latitude) {
                    latitude.value = coords.latitude.toFixed(7);
                    dispatchFieldEvents(latitude);
                    trackPhotoField(latitude);
                }

                if (longitude) {
                    longitude.value = coords.longitude.toFixed(7);
                    dispatchFieldEvents(longitude);
                    trackPhotoField(longitude);
                }

                return fillAddressFromCoordinates(coords.latitude.toFixed(7), coords.longitude.toFixed(7), 'GPS da foto', scanId).then(function () {
                    return true;
                });
            });
        }

        function updatePreview() {
            var file = input.files && input.files[0] ? input.files[0] : null;

            if (form.dataset.photoProcessing !== 'true') {
                form.dataset.photoProcessed = '';
            }

            if (!file) {
                metadataScanId += 1;
                clearPhotoFilledFields();

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

                if (previewClear) {
                    previewClear.disabled = true;
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

            if (previewClear) {
                previewClear.disabled = false;
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
                imovel: fieldValue('input[name="imovel"]:checked'),
                condicao_residencia: fieldValue('input[name="condicao_residencia"]:checked'),
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
                'Imovel: ' + (metadata.imovel || '-') + ' | Condicao: ' + (metadata.condicao_residencia || '-'),
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

            clearPhotoFilledFields();
            form.dataset.photoLocationSource = 'pending';
            setStatus('Foto selecionada. Verificando metadados e coordenadas da imagem...');
            applyPhotoGeolocation(file).then(function (foundPhotoGps) {
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

        if (previewClear) {
            previewClear.disabled = true;
            previewClear.addEventListener('click', function () {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
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
