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
        <aside class="bg-white border-end p-3 d-none d-lg-flex flex-column justify-content-between" style="width: 260px;">
            <div>
                <div class="fw-bold fs-6 mb-4">TWSD Connect</div>
                <nav class="nav flex-column gap-1 small">
                    @yield('sidebar')
                </nav>
            </div>
            <div class="border-top pt-3 mt-4">
                <div class="text-uppercase text-muted fw-semibold mb-2" style="font-size: 0.7rem; letter-spacing: 0.04em;">Account</div>
                <nav class="nav flex-column gap-1 small">
                    <a class="nav-link {{ request()->routeIs('admin.profile') ? 'active fw-semibold text-dark' : 'text-secondary' }}" href="{{ route('admin.profile') }}">My Profile</a>
                    <a class="nav-link {{ request()->routeIs('admin.password') ? 'active fw-semibold text-dark' : 'text-secondary' }}" href="{{ route('admin.password') }}">Change Password</a>
                </nav>
            </div>
        </aside>
        <div class="flex-grow-1 d-flex flex-column">
            <header class="bg-white border-bottom px-4 py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span class="fw-semibold small text-secondary">@yield('breadcrumb', 'Admin Console')</span>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted me-1">{{ auth()->user()?->primary_email }}</span>
                    <a href="{{ route('admin.profile') }}" class="btn btn-outline-secondary btn-sm">My Profile</a>
                    <a href="{{ route('admin.password') }}" class="btn btn-outline-secondary btn-sm">Change Password</a>
                    <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-dark btn-sm">Sign Out</button>
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
