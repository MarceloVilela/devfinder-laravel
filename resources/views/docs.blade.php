<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DevFinder API</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.17.14/swagger-ui.min.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.17.14/swagger-ui-bundle.min.js"></script>
<script>
    // A produção (AWS) é a primeira opção do seletor de servidores; ao abrir o /docs de outro host (ex.: localhost:8082), seleciona o servidor desse host.
    window.ui = SwaggerUIBundle({
        url: '/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        onComplete: function () {
            var servers = (window.ui.specSelectors.servers() || []).map(function (s) { return s.get('url'); });
            var here = location.origin + '/v1';
            if (servers.indexOf(here) !== -1) { window.ui.specActions.setSelectedServer(here); }
        },
    });
</script>
</body>
</html>
