(function () {
    'use strict';

    function number(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function classKey(value) {
        return ['perda_total', 'perda_parcial', 'nao_atingida'].indexOf(value) !== -1 ? value : 'sem_condicao';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function safeHref(value) {
        try {
            var parsed = new URL(value || '#', window.location.origin);

            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                return parsed.href;
            }
        } catch (error) {
            return '#';
        }

        return '#';
    }

    function readPoints(element) {
        try {
            return JSON.parse(element.dataset.mapPoints || '[]').map(function (point) {
                point.latitude = number(point.latitude);
                point.longitude = number(point.longitude);
                return point;
            }).filter(function (point) {
                return point.latitude !== null && point.longitude !== null;
            });
        } catch (error) {
            return [];
        }
    }

    function markerHtml(condition) {
        return '<span class="ops-leaflet-house ops-leaflet-house-' + classKey(condition) + '">' +
            '<span></span>' +
        '</span>';
    }

    function popupHtml(point) {
        var delivered = Number(point.familias_atendidas || 0);
        var families = Number(point.familias || 0);

        return '' +
            '<article class="ops-map-popup">' +
                '<strong>' + escapeHtml(point.protocolo || 'Residência') + '</strong>' +
                '<span>' + escapeHtml(point.bairro || '-') + ' - ' + escapeHtml(point.municipio || '-') + '</span>' +
                '<dl>' +
                    '<div><dt>Condição</dt><dd>' + escapeHtml(point.condicao_label || '-') + '</dd></div>' +
                    '<div><dt>Imóvel</dt><dd>' + escapeHtml(point.imovel || '-') + '</dd></div>' +
                    '<div><dt>Famílias</dt><dd>' + families + '</dd></div>' +
                    '<div><dt>Atendidas</dt><dd>' + delivered + '</dd></div>' +
                '</dl>' +
                '<a href="' + escapeHtml(safeHref(point.url)) + '">Abrir residência</a>' +
            '</article>';
    }

    function detailsHtml(point) {
        var delivered = Number(point.familias_atendidas || 0);
        var families = Number(point.familias || 0);

        return '' +
            '<span class="eyebrow">Ponto selecionado</span>' +
            '<h3>' + escapeHtml(point.protocolo || 'Residência') + '</h3>' +
            '<p>' + escapeHtml(point.bairro || '-') + ' - ' + escapeHtml(point.municipio || '-') + '</p>' +
            '<dl>' +
                '<div><dt>Condição</dt><dd>' + escapeHtml(point.condicao_label || '-') + '</dd></div>' +
                '<div><dt>Imóvel</dt><dd>' + escapeHtml(point.imovel || '-') + '</dd></div>' +
                '<div><dt>Famílias</dt><dd>' + families + '</dd></div>' +
                '<div><dt>Atendidas</dt><dd>' + delivered + '</dd></div>' +
                '<div><dt>Coordenadas</dt><dd>' + point.latitude.toFixed(6) + ', ' + point.longitude.toFixed(6) + '</dd></div>' +
                '<div><dt>Ação</dt><dd>' + escapeHtml(point.acao || '-') + '</dd></div>' +
            '</dl>' +
            '<a class="primary-link-button" href="' + escapeHtml(safeHref(point.url)) + '">Abrir residência</a>';
    }

    function buildTileLayers(L) {
        return {
            'Ruas': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }),
            'Operacional': L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap, Humanitarian OpenStreetMap Team'
            }),
            'Satélite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri'
            })
        };
    }

    function createMarker(L, point) {
        var icon = L.divIcon({
            className: 'ops-leaflet-marker',
            html: markerHtml(point.condicao),
            iconSize: [36, 38],
            iconAnchor: [18, 34],
            popupAnchor: [0, -32]
        });

        return L.marker([point.latitude, point.longitude], {
            icon: icon,
            title: (point.protocolo || 'Residência') + ' - ' + (point.condicao_label || ''),
            keyboard: true,
            riseOnHover: true
        }).bindPopup(popupHtml(point), {
            maxWidth: 300,
            className: 'ops-map-popup-shell'
        });
    }

    function createMap(stage) {
        var L = window.L;
        var empty = stage.querySelector('[data-map-empty]');
        var details = document.querySelector('[data-map-details]');
        var list = document.querySelector('[data-map-list]');
        var points = readPoints(stage);
        var pointById = {};
        var markerById = {};
        var placeholder;
        var canvas;
        var map;
        var layers;
        var markerGroup;
        var bounds;
        var resizeTimer = null;
        var resizeObserver = null;

        stage.querySelectorAll('.ops-map-placeholder, .ops-leaflet-canvas').forEach(function (node) {
            node.remove();
        });

        if (points.length === 0) {
            stage.classList.add('is-empty');
            if (empty) {
                empty.hidden = false;
            }
            return;
        }

        if (!L) {
            stage.classList.add('is-empty');
            if (empty) {
                empty.hidden = false;
                if (empty.querySelector('strong')) {
                    empty.querySelector('strong').textContent = 'Biblioteca Leaflet não carregada.';
                }
                if (empty.querySelector('span')) {
                    empty.querySelector('span').textContent = 'Verifique a conexão com a internet ou o bloqueio do CDN.';
                }
            }
            return;
        }

        stage.classList.remove('is-empty');
        stage.classList.remove('is-map-ready');
        stage.classList.add('is-map-loading');
        if (empty) {
            empty.hidden = true;
        }

        placeholder = document.createElement('div');
        placeholder.className = 'ops-map-placeholder';
        placeholder.innerHTML = '<div><strong>Carregando mapa operacional</strong><span>Preparando camadas, zoom e residências georreferenciadas.</span></div>';
        stage.appendChild(placeholder);

        canvas = document.createElement('div');
        canvas.className = 'ops-leaflet-canvas';
        stage.appendChild(canvas);

        layers = buildTileLayers(L);
        map = L.map(canvas, {
            layers: [layers.Ruas],
            zoomControl: true,
            scrollWheelZoom: true,
            dragging: true,
            doubleClickZoom: true,
            touchZoom: true,
            boxZoom: true,
            keyboard: true,
            preferCanvas: true
        });
        layers.Ruas.on('load', function () {
            stage.classList.add('is-map-ready');
            stage.classList.remove('is-map-loading');
        });
        markerGroup = L.featureGroup().addTo(map);

        function activate(point) {
            var marker = markerById[String(point.id)];

            if (details) {
                details.innerHTML = detailsHtml(point);
            }

            if (list) {
                list.querySelectorAll('[data-map-focus]').forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.mapFocus === String(point.id));
                });
            }

            if (marker) {
                marker.openPopup();
                map.panTo(marker.getLatLng(), { animate: true, duration: 0.35 });
            }
        }

        points.forEach(function (point) {
            var marker = createMarker(L, point);

            pointById[String(point.id)] = point;
            markerById[String(point.id)] = marker;
            marker.on('click', function () {
                activate(point);
            });
            marker.addTo(markerGroup);
        });

        bounds = markerGroup.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, {
                padding: [34, 34],
                maxZoom: points.length === 1 ? 17 : 16
            });
        }

        L.control.layers(layers, { 'Residências': markerGroup }, {
            collapsed: true,
            position: 'topright'
        }).addTo(map);
        L.control.scale({
            metric: true,
            imperial: false,
            position: 'bottomleft'
        }).addTo(map);

        function refreshSize() {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                map.invalidateSize({ animate: false });
            }, 90);
        }

        setTimeout(function () {
            map.invalidateSize();
        }, 80);
        setTimeout(function () {
            if (!stage.classList.contains('is-map-ready')) {
                map.invalidateSize({ animate: false });
            }
        }, 1200);

        window.addEventListener('resize', refreshSize);
        document.addEventListener('transitionend', refreshSize);

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(refreshSize);
            resizeObserver.observe(stage);
        }

        window.addEventListener('beforeunload', function () {
            window.removeEventListener('resize', refreshSize);
            document.removeEventListener('transitionend', refreshSize);
            if (resizeObserver) {
                resizeObserver.disconnect();
            }
        });

        if (list) {
            list.querySelectorAll('[data-map-focus]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var point = pointById[button.dataset.mapFocus];
                    if (point) {
                        activate(point);
                    }
                });
            });
        }

        activate(points[0]);
    }

    document.querySelectorAll('[data-dashboard-map]').forEach(createMap);
})();
