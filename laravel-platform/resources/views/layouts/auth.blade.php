<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $branding->display_name ?? 'TWSD Connect' }} — Single Sign-On</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --twsd-primary: {{ $branding->primary_color ?? '#0F172A' }};
            --twsd-secondary: {{ $branding->secondary_color ?? '#2563EB' }};
        }
        body {
            background-color: #F8FAFC;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .auth-shell {
            width: 100%;
            max-width: 440px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 2rem;
        }
        .btn-brand {
            background-color: var(--twsd-primary);
            border-color: var(--twsd-primary);
            color: #FFFFFF;
        }
        .btn-brand:hover {
            opacity: 0.92;
            color: #FFFFFF;
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        @yield('content')
        <footer class="mt-4 pt-3 border-top text-center text-muted small">
            <div>{{ $branding->footer_text ?? 'Secured by TWSD Connect Identity Platform' }}</div>
            @if(!empty($branding?->terms_url) || !empty($branding?->privacy_url))
                <div class="mt-1">
                    @if(!empty($branding->terms_url))
                        <a href="{{ $branding->terms_url }}" class="text-decoration-none text-secondary me-2">Terms</a>
                    @endif
                    @if(!empty($branding->privacy_url))
                        <a href="{{ $branding->privacy_url }}" class="text-decoration-none text-secondary">Privacy</a>
                    @endif
                </div>
            @endif
        </footer>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
