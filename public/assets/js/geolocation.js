(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-geolocation-form]');

    forms.forEach(function (form) {
        var button = form.querySelector('[data-geolocation-button]');
        var status = form.querySelector('[data-geolocation-status]');
        var latitude = form.querySelector('[data-latitude]');
        var longitude = form.querySelector('[data-longitude]');

        if (!button || !latitude || !longitude) {
            return;
        }

        if (!navigator.geolocation) {
            button.disabled = true;
            if (status) {
                status.textContent = 'Geolocalizacao indisponivel neste navegador.';
            }
            return;
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            if (status) {
                status.textContent = 'Solicitando permissao de localizacao...';
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                latitude.value = position.coords.latitude.toFixed(7);
                longitude.value = position.coords.longitude.toFixed(7);
                button.disabled = false;
                if (status) {
                    status.textContent = 'Localizacao capturada com sucesso.';
                }
            }, function () {
                button.disabled = false;
                if (status) {
                    status.textContent = 'Nao foi possivel capturar a localizacao. Preencha manualmente.';
                }
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        });
    });
})();
