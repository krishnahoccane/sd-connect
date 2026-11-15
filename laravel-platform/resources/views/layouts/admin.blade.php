<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TWSD Connect Admin')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="d-flex min-vh-100">
        <aside class="bg-white border-end p-3 d-none d-lg-block" style="width: 260px;">
            <div class="fw-bold fs-6 mb-4">TWSD Connect</div>
            <nav class="nav flex-column gap-1 small">
                @yield('sidebar')
            </nav>
        </aside>
        <div class="flex-grow-1 d-flex flex-column">
            <header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold small text-secondary">@yield('breadcrumb', 'Admin Console')</span>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-muted">{{ auth()->user()?->primary_email }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Sign Out</button>
                    </form>
                </div>
            </header>
            <main class="container-fluid p-4">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
