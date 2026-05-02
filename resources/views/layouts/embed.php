<?php
$app = require BASE_PATH . '/config/app.php';
$pageTitle = $title ?? $app['name'];
$assetVersion = '20260502-114';
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

            function resize() {
                target = target || document.querySelector('.dti-document');

                if (!target || printing) {
                    return;
                }

                target.style.transform = '';
                target.style.width = baseWidth + 'px';

                var viewportWidth = document.documentElement.clientWidth || window.innerWidth || baseWidth;
                var frameWidth = window.frameElement ? window.frameElement.clientWidth : 0;
                var rawAvailable = Math.min(viewportWidth, frameWidth || viewportWidth);
                var fitGutter = rawAvailable < baseWidth ? 32 : 0;
                var available = Math.max(280, rawAvailable - fitGutter);
                var scale = Math.min(1, available / baseWidth);
                target.style.transformOrigin = 'top left';
                target.style.transform = 'scale(' + scale + ')';
                target.style.marginLeft = scale < 1 ? '0' : 'auto';
                target.style.marginRight = scale < 1 ? '0' : 'auto';
                target.style.marginBottom = Math.ceil(target.scrollHeight * scale - target.scrollHeight) + 'px';
                document.body.style.minHeight = Math.ceil(target.scrollHeight * scale + 28) + 'px';
            }

            function preparePrint() {
                target = target || document.querySelector('.dti-document');
                printing = true;

                if (!target) {
                    return;
                }

                target.style.transform = 'none';
                target.style.transformOrigin = '';
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
            setTimeout(resize, 250);
        })();
    </script>
</body>
</html>
