@extends('layouts.auth')

@section('content')
    <div class="mb-4">
        <h1 class="h5 fw-bold mb-1">TWSD Connect Admin Console</h1>
        <p class="text-secondary small mb-0">Sign in with your Super Admin or Organisation Admin credentials.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small py-2" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.submit') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                placeholder="admin@connect.twsd.me"
                required
                autofocus
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                required
            >
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label small text-secondary" for="remember">Keep session signed in</label>
        </div>

        <button type="submit" class="btn btn-brand w-100 fw-medium">Sign In to Console</button>
    </form>
@endsection
