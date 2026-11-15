@extends('layouts.admin')

@section('title', 'Change Password — TWSD Connect')
@section('breadcrumb', 'Account / Change Password')

@section('sidebar')
    <a class="nav-link text-secondary" href="{{ $consoleHomeUrl }}">&larr; Back to Console</a>
@endsection

@section('content')
@if (session('status'))
    <div class="alert alert-success small mb-3" role="alert">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger small mb-3" role="alert">{{ $errors->first() }}</div>
@endif

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Update Account Password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.password.update') }}">
                    @csrf
                    @method('PUT')

                    @if($hasExistingPassword)
                        <div class="mb-3">
                            <label for="current_password" class="form-label small fw-medium">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="new_password" class="form-label small fw-medium">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control form-control-sm" required autocomplete="new-password">
                    </div>

                    <div class="mb-3">
                        <label for="new_password_confirmation" class="form-label small fw-medium">Confirm New Password</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control form-control-sm" required autocomplete="new-password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark btn-sm">Update Password</button>
                        <a href="{{ route('admin.profile') }}" class="btn btn-outline-secondary btn-sm">Back to Profile</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Password Security Policy</div>
            <div class="card-body small text-muted">
                <p class="mb-2">Passwords are hashed with Argon2id and must meet platform complexity rules:</p>
                <ul class="mb-0 ps-3">
                    <li>Minimum {{ (int) ($policy['min_length'] ?? 12) }} characters</li>
                    @if($policy['require_uppercase'] ?? true)<li>At least one uppercase letter (A&ndash;Z)</li>@endif
                    @if($policy['require_lowercase'] ?? true)<li>At least one lowercase letter (a&ndash;z)</li>@endif
                    @if($policy['require_number'] ?? true)<li>At least one number (0&ndash;9)</li>@endif
                    @if($policy['require_symbol'] ?? true)<li>At least one special symbol (!@#$%...)</li>@endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
