<?php
$app = require BASE_PATH . '/config/app.php';
$pageTitle = $title ?? $app['name'];
$assetVersion = '20260502-149';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= h($pageTitle) ?> | <?= h($app['name']) ?></title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css') . '?v=' . $assetVersion) ?>">
</head>
<body class="document-embed-body">
    <?= $content ?>
    <script>
        (function () {
            var target = null;
            var baseWidth = 794;
            var printing = false;

            function visibleHeight(scale, usesZoom) {
                if (!target) {
                    return 0;
                }

                var rectHeight = target.getBoundingClientRect ? target.getBoundingClientRect().height : 0;
                var layoutHeight = target.scrollHeight || 0;

                if (usesZoom && rectHeight > 0) {
                    return Math.ceil(Math.min(layoutHeight || rectHeight, rectHeight));
                }

                return Math.ceil(layoutHeight * scale);
            }

            function resize() {
                target = target || document.querySelector('.dti-document');

                if (!target || printing) {
                    return;
                }

                baseWidth = target.classList.contains('recomecar-document') ? 1123 : 794;

                target.style.transform = '';
                target.style.zoom = '1';
                target.style.width = baseWidth + 'px';

                var viewportWidth = document.documentElement.clientWidth || window.innerWidth || baseWidth;
                var frameWidth = window.frameElement ? window.frameElement.clientWidth : 0;
                var rawAvailable = Math.min(viewportWidth, frameWidth || viewportWidth);
                var fitGutter = rawAvailable < baseWidth ? 32 : 0;
                var available = Math.max(280, rawAvailable - fitGutter);
                var scale = Math.min(1, available / baseWidth);
                var usesZoom = target.classList.contains('recomecar-document') && 'zoom' in target.style;

                if (usesZoom) {
                    target.style.zoom = String(scale);
                    target.style.transform = 'none';
                    target.style.marginBottom = '0';
                } else {
                    target.style.transformOrigin = 'top left';
                    target.style.transform = 'scale(' + scale + ')';
                    target.style.marginBottom = Math.ceil(target.scrollHeight * scale - target.scrollHeight) + 'px';
                }

                target.style.marginLeft = scale < 1 ? '0' : 'auto';
                target.style.marginRight = scale < 1 ? '0' : 'auto';

                var height = visibleHeight(scale, usesZoom);
                document.body.style.minHeight = Math.ceil(height + 28) + 'px';
            }

            function preparePrint() {
                target = target || document.querySelector('.dti-document');
                printing = true;

                if (!target) {
                    return;
                }

                target.style.transform = 'none';
                target.style.transformOrigin = '';
                target.style.zoom = '1';
                target.style.width = 'auto';
                target.style.marginLeft = '0';
                target.style.marginRight = '0';
                target.style.marginBottom = '0';
                document.body.style.minHeight = '0';
            }

            function restoreScreen() {
                printing = false;
                resize();
            }

            window.addEventListener('load', resize);
            window.addEventListener('resize', resize);
            window.addEventListener('beforeprint', preparePrint);
            window.addEventListener('afterprint', restoreScreen);

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(resize).catch(function () {});
            }

            document.querySelectorAll('img').forEach(function (image) {
                if (!image.complete) {
                    image.addEventListener('load', resize, { once: true });
                    image.addEventListener('error', resize, { once: true });
                }
            });

            setTimeout(resize, 250);
            setTimeout(resize, 700);
            setTimeout(resize, 1400);
        })();
    </script>
</body>
</html>
