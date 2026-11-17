<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hacé tu pregunta · Aging</title>
    <script>
        document.documentElement.dataset.embedded = window.self !== window.top;
    </script>
    <style>
        html { box-sizing: border-box; }
        body { box-sizing: border-box; min-width: 0; min-height: 100svh; margin: 0; padding: clamp(12px, 3vw, 32px); background: #faf7fa; }
        body #aging-question { margin: 0 auto; }
        html[data-embedded="true"] body { min-height: 0; padding: 0; background: transparent; }
    </style>
</head>
<body>
    {!! $formHtml !!}
    <script>
        if (window.self !== window.top) {
            const allowedOrigins = [window.location.origin, 'https://eventos.huesped.org.ar'];
            let parentOrigin = document.referrer ? new URL(document.referrer).origin : '';
            const reportHeight = () => {
                if (!allowedOrigins.includes(parentOrigin)) return;
                const height = Math.ceil(document.getElementById('aging-question').getBoundingClientRect().height);
                window.parent.postMessage({ type: 'aging-question:resize', height }, parentOrigin);
            };
            window.addEventListener('message', event => {
                if (event.source !== window.parent || !allowedOrigins.includes(event.origin) || event.data?.type !== 'aging-question:measure') return;
                parentOrigin = event.origin;
                reportHeight();
            });
            new ResizeObserver(reportHeight).observe(document.getElementById('aging-question'));
            window.addEventListener('load', reportHeight);
        }
    </script>
</body>
</html>
