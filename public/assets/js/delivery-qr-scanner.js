(function () {
    'use strict';

    var panel = document.querySelector('[data-delivery-qr-scanner]');

    if (!panel) {
        return;
    }

    var startButton = panel.querySelector('[data-delivery-qr-start]');
    var stopButton = panel.querySelector('[data-delivery-qr-stop]');
    var video = panel.querySelector('[data-delivery-qr-video]');
    var status = panel.querySelector('[data-delivery-qr-status]');
    var validateBase = panel.dataset.validateBase || '';
    var detector = null;
    var stream = null;
    var scanning = false;

    function setStatus(text) {
        if (status) {
            status.textContent = text;
        }
    }

    function stopScanner() {
        scanning = false;

        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
        }

        stream = null;

        if (video) {
            video.hidden = true;
            video.srcObject = null;
        }

        if (startButton) {
            startButton.disabled = false;
        }

        if (stopButton) {
            stopButton.hidden = true;
        }
    }

    function goToValidation(value) {
        var rawValue = String(value || '').trim();

        if (rawValue === '') {
            return;
        }

        stopScanner();

        if (/^https?:\/\//i.test(rawValue)) {
            window.location.href = rawValue;
            return;
        }

        window.location.href = validateBase + '/' + encodeURIComponent(rawValue);
    }

    function scanFrame() {
        if (!scanning || !detector || !video) {
            return;
        }

        detector.detect(video).then(function (codes) {
            if (codes && codes.length > 0) {
                goToValidation(codes[0].rawValue || '');
                return;
            }

            window.requestAnimationFrame(scanFrame);
        }).catch(function () {
            setStatus('Nao foi possivel ler o QR Code. Tente aproximar o comprovante da camera.');
            window.requestAnimationFrame(scanFrame);
        });
    }

    if (!startButton || !video) {
        return;
    }

    if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        startButton.disabled = true;
        setStatus('Leitor automatico indisponivel neste navegador. Informe o codigo manualmente.');
        return;
    }

    try {
        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
    } catch (error) {
        startButton.disabled = true;
        setStatus('Leitor automatico indisponivel neste navegador. Informe o codigo manualmente.');
        return;
    }

    startButton.addEventListener('click', function () {
        startButton.disabled = true;
        setStatus('Abrindo camera para leitura do QR Code...');

        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' }
            },
            audio: false
        }).then(function (mediaStream) {
            stream = mediaStream;
            scanning = true;
            video.srcObject = stream;
            video.hidden = false;

            if (stopButton) {
                stopButton.hidden = false;
            }

            return video.play();
        }).then(function () {
            setStatus('Aponte a camera para o QR Code do comprovante.');
            scanFrame();
        }).catch(function () {
            stopScanner();
            setStatus('Nao foi possivel acessar a camera. Informe o codigo manualmente.');
        });
    });

    if (stopButton) {
        stopButton.addEventListener('click', function () {
            stopScanner();
            setStatus('Leitura cancelada.');
        });
    }
})();
