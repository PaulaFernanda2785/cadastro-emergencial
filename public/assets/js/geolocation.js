(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-geolocation-form]');

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR')
            .trim();
    }

    function compact(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function isNoise(value) {
        var normalized = normalize(value);

        return normalized === '' ||
            normalized.indexOf('regiao geografica') !== -1 ||
            normalized === 'regiao norte' ||
            normalized === 'regiao nordeste' ||
            normalized === 'regiao centro-oeste' ||
            normalized === 'regiao sudeste' ||
            normalized === 'regiao sul' ||
            normalized === 'brasil';
    }

    function firstAddressValue(address, keys) {
        var index;
        var value;

        for (index = 0; index < keys.length; index += 1) {
            value = compact(address && address[keys[index]]);

            if (value !== '' && !isNoise(value)) {
                return value;
            }
        }

        return '';
    }

    function stateToCode(value) {
        var normalized = normalize(value);
        var states = {
            acre: 'AC',
            alagoas: 'AL',
            amapa: 'AP',
            amazonas: 'AM',
            bahia: 'BA',
            ceara: 'CE',
            'distrito federal': 'DF',
            'espirito santo': 'ES',
            goias: 'GO',
            maranhao: 'MA',
            'mato grosso': 'MT',
            'mato grosso do sul': 'MS',
            'minas gerais': 'MG',
            para: 'PA',
            paraiba: 'PB',
            parana: 'PR',
            pernambuco: 'PE',
            piaui: 'PI',
            'rio de janeiro': 'RJ',
            'rio grande do norte': 'RN',
            'rio grande do sul': 'RS',
            rondonia: 'RO',
            roraima: 'RR',
            'santa catarina': 'SC',
            'sao paulo': 'SP',
            sergipe: 'SE',
            tocantins: 'TO'
        };

        if (/^[a-z]{2}$/i.test(compact(value))) {
            return compact(value).toUpperCase();
        }

        return states[normalized] || compact(value);
    }

    function uniqueParts(parts) {
        var seen = {};

        return parts.filter(function (part) {
            var key = normalize(part);

            if (key === '' || seen[key]) {
                return false;
            }

            seen[key] = true;
            return true;
        });
    }

    function formatAddress(payload) {
        var address = payload && payload.address ? payload.address : {};
        var road = firstAddressValue(address, ['road', 'pedestrian', 'residential', 'path', 'footway', 'cycleway']);
        var houseNumber = firstAddressValue(address, ['house_number']);
        var place = firstAddressValue(address, ['amenity', 'building', 'shop', 'tourism', 'leisure']);
        var neighbourhood = firstAddressValue(address, ['suburb', 'neighbourhood', 'quarter', 'hamlet', 'village']);
        var city = firstAddressValue(address, ['city', 'town', 'municipality']);
        var district = firstAddressValue(address, ['city_district', 'district', 'county']);
        var state = stateToCode(firstAddressValue(address, ['state_code', 'state']));
        var postcode = firstAddressValue(address, ['postcode']);
        var firstLine = road || place || firstAddressValue(address, ['locality']);
        var parts = [];

        if (firstLine !== '') {
            parts.push(firstLine + (houseNumber !== '' ? ', n. ' + houseNumber : ''));
        }

        parts = parts.concat(uniqueParts([neighbourhood, city || district]).filter(Boolean));

        if (city !== '' && state !== '') {
            parts[parts.length - 1] = city + '/' + state;
        } else if (state !== '') {
            parts.push(state);
        }

        if (postcode !== '') {
            parts.push('CEP ' + postcode);
        }

        parts = uniqueParts(parts);

        if (parts.length === 0 && typeof payload.display_name === 'string') {
            parts = payload.display_name
                .split(',')
                .map(compact)
                .filter(function (part) { return !isNoise(part); })
                .slice(0, 4);
        }

        return {
            address: parts.join(', '),
            community: neighbourhood || district || city || ''
        };
    }

    function reverseGeocode(lat, lng) {
        if (!navigator.onLine || typeof fetch !== 'function') {
            return Promise.resolve({ address: '', community: '' });
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 7000) : null;
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=pt-BR&lat=' +
            encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);

        return fetch(url, {
            method: 'GET',
            signal: controller ? controller.signal : undefined,
            headers: {
                Accept: 'application/json'
            }
        }).then(function (response) {
            if (!response.ok) {
                return { address: '', community: '' };
            }

            return response.json();
        }).then(formatAddress).catch(function () {
            return { address: '', community: '' };
        }).finally(function () {
            if (timeout) {
                window.clearTimeout(timeout);
            }
        });
    }

    window.CadastroGeo = {
        formatAddress: formatAddress,
        reverseGeocode: reverseGeocode
    };

    forms.forEach(function (form) {
        var button = form.querySelector('[data-geolocation-button]');
        var status = form.querySelector('[data-geolocation-status]');
        var latitude = form.querySelector('[data-latitude]');
        var longitude = form.querySelector('[data-longitude]');
        var address = form.querySelector('[data-address]');
        var community = form.querySelector('[data-community-input]');

        if (!button || !latitude || !longitude) {
            return;
        }

        function insecureLocalMessage() {
            return 'No WAMP acessado por IP em HTTP, o celular pode bloquear a localizacao. Use HTTPS no WAMP ou servidor compartilhado com HTTPS.';
        }

        function geolocationErrorMessage(error) {
            if (!window.isSecureContext && !/^(localhost|127\.0\.0\.1|::1|\[::1\])$/.test(window.location.hostname)) {
                return insecureLocalMessage();
            }

            switch (error && error.code) {
                case 1:
                    return 'Permita o acesso a localizacao do dispositivo para preencher as coordenadas.';
                case 2:
                    return 'Nao foi possivel obter a localizacao atual do dispositivo.';
                case 3:
                    return 'A captura da localizacao demorou demais. Tente novamente em um local com melhor sinal.';
                default:
                    return 'Nao foi possivel obter a localizacao atual.';
            }
        }

        if (!('geolocation' in navigator)) {
            button.disabled = true;
            if (status) {
                status.textContent = window.isSecureContext
                    ? 'Geolocalizacao indisponivel neste navegador.'
                    : insecureLocalMessage();
            }
            return;
        }

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        if (!window.isSecureContext && !/^(localhost|127\.0\.0\.1|::1|\[::1\])$/.test(window.location.hostname)) {
            setStatus(insecureLocalMessage());
        }

        button.addEventListener('click', function () {
            if (String(form.dataset.photoLocationSource || '').indexOf('photo-') === 0) {
                setStatus('Foto anexada com localizacao propria. Remova ou troque a foto para capturar a localizacao atual.');
                return;
            }

            button.disabled = true;
            setStatus('Solicitando permissao de localizacao...');

            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = position.coords.latitude.toFixed(7);
                var lng = position.coords.longitude.toFixed(7);

                form.dataset.locationSource = 'device-current';
                latitude.value = lat;
                longitude.value = lng;
                setStatus('Localizacao capturada. Buscando endereco...');

                reverseGeocode(lat, lng).then(function (result) {
                    if (result.address !== '' && address) {
                        address.value = result.address;
                        form.dataset.addressSource = 'device-current';
                        address.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    if (result.community !== '' && community && community.value.trim() === '') {
                        community.value = result.community;
                        form.dataset.communitySource = 'device-current';
                        community.dispatchEvent(new Event('input', { bubbles: true }));
                        community.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (result.address !== '') {
                        setStatus('Localizacao, endereco e bairro preenchidos.');
                        return;
                    }

                    setStatus('Localizacao capturada. Endereco pode ser preenchido manualmente.');
                }).finally(function () {
                    button.disabled = false;
                });
            }, function (error) {
                button.disabled = false;
                setStatus(geolocationErrorMessage(error));
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            });
        });
    });
})();
