<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>FileServer Integration API — Swagger</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; }
        header { padding: 24px; max-width: 1460px; margin: auto; }
        header h1 { margin: 0 0 12px; }
        header p { line-height: 1.6; }
    </style>
</head>
<body>
<header>
    <h1>FileServer integration API</h1>
    <p>Create a dedicated token in the dashboard under <strong>Tokens → New token</strong>,
        with the required abilities and bucket/path scope. Click <strong>Authorize</strong>
        below and paste the raw token. Swagger adds the Bearer prefix automatically.</p>
    <p>Start with the health endpoint, then list buckets, upload a file, and retrieve its metadata
        or create a presigned download URL. Keep integration tokens on your application server.
        Requests made here use the live API and can change or delete data.</p>
    <a href="{{ route('docs.openapi', [], false) }}">Download the OpenAPI specification</a>
</header>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
<script>
    window.onload = () => {
        window.ui = SwaggerUIBundle({
            url: @json(route('docs.openapi', [], false)),
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout',
            deepLinking: true,
            filter: true,
            persistAuthorization: false,
        });
    };
</script>
</body>
</html>
