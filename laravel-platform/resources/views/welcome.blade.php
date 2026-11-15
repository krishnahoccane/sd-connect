<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TWSD Connect — Enterprise SSO &amp; OIDC Identity Provider</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light text-dark min-vh-100 d-flex align-items-center">
<div class="container" style="max-width: 720px;">
    <div class="card shadow-sm border">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h4 fw-bold mb-0">TWSD Connect</h1>
                <span class="badge text-bg-success">Identity Provider Active</span>
            </div>
            <p class="text-secondary small mb-4">
                Centralized Multi-Tenant SSO, OAuth 2.0, and OpenID Connect Identity Platform.
            </p>

            <div class="list-group list-group-flush border rounded mb-4 small">
                <a href="/.well-known/openid-configuration" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span>OIDC Discovery Metadata</span>
                    <code class="text-primary">/.well-known/openid-configuration</code>
                </a>
                <a href="/.well-known/jwks.json" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span>JSON Web Key Set (JWKS)</span>
                    <code class="text-primary">/.well-known/jwks.json</code>
                </a>
                <a href="/up" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span>Platform Health Probe</span>
                    <code class="text-primary">/up</code>
                </a>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @auth
                    <a href="/super-admin/auth-settings" class="btn btn-dark btn-sm">Super Admin Console</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-dark btn-sm">Admin Console Sign In</a>
                @endauth
            </div>
        </div>
    </div>
</div>
</body>
</html>
