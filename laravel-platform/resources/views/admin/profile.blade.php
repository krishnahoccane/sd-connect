@extends('layouts.admin')

@section('title', 'My Profile — TWSD Connect')
@section('breadcrumb', 'Account / My Profile')

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
    <div class="col-12 col-lg-4">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-white fw-semibold">Account Overview</div>
            <div class="card-body small">
                <div class="mb-3">
                    <div class="text-muted">Primary Email</div>
                    <div class="fw-semibold">{{ $user->primary_email }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted">Role</div>
                    <div class="mt-1">
                        @if($user->is_super_admin)
                            <span class="badge text-bg-dark">Super Admin</span>
                        @else
                            <span class="badge text-bg-secondary">Organisation Admin</span>
                        @endif
                        <span class="badge text-bg-success ms-1">{{ ucfirst($user->status) }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="text-muted">Password Last Changed</div>
                    <div>{{ $user->password_changed_at ? $user->password_changed_at->format('Y-m-d H:i') : 'Not recorded' }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted">Last Sign-In</div>
                    <div>{{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Current session' }}</div>
                    @if($user->last_login_ip)
                        <div class="font-monospace text-muted">{{ $user->last_login_ip }}</div>
                    @endif
                </div>
                <a href="{{ route('admin.password') }}" class="btn btn-outline-dark btn-sm w-100">Change Password</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Edit Profile Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Display Name</label>
                        <input type="text" name="display_name" class="form-control form-control-sm" value="{{ old('display_name', $profile->display_name ?? $user->primary_email) }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">First / Given Name</label>
                            <input type="text" name="given_name" class="form-control form-control-sm" value="{{ old('given_name', $profile->given_name ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Last / Family Name</label>
                            <input type="text" name="family_name" class="form-control form-control-sm" value="{{ old('family_name', $profile->family_name ?? '') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Mobile Phone Number (E.164 Format, Optional)</label>
                        <input type="text" name="primary_phone" class="form-control form-control-sm font-monospace" value="{{ old('primary_phone', $user->primary_phone ?? '') }}" placeholder="+919876543210">
                        <div class="form-text">Include country code, e.g. <code>+919876543210</code>. Used for SMS / WhatsApp OTP authentication.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Locale</label>
                            <select name="locale" class="form-select form-select-sm">
                                @foreach(['en' => 'English (en)', 'en-GB' => 'English UK (en-GB)', 'en-IN' => 'English India (en-IN)'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('locale', $profile->locale ?? 'en') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Timezone</label>
                            <select name="timezone" class="form-select form-select-sm">
                                @foreach($commonTimezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $profile->timezone ?? 'UTC') === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
