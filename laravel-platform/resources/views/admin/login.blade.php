@extends('layouts.auth')

@section('content')
    @php
        $showPassword = $emailPasswordEnabled || $emergencyFallback;
        $showOtp = $emailOtpEnabled;
        $bothShown = $showPassword && $showOtp;
        $defaultTab = $showPassword ? 'password' : 'otp';
    @endphp

    <div class="mb-3">
        <h1 class="h5 fw-bold mb-1">TWSD Connect Admin Console</h1>
        <p class="text-secondary small mb-2">Sign in with your Super Admin or Organisation Admin account.</p>
        @if($enabledLiveProviders->isNotEmpty())
            <div class="small text-muted">
                Active sign-in methods:
                @foreach($enabledLiveProviders as $provider)
                    <span class="badge text-bg-light border fw-normal">{{ $provider->name }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if ($emergencyFallback)
        <div class="alert alert-warning small py-2" role="alert">
            No live global authentication providers are currently active. Emergency password sign-in is enabled to prevent console lockout.
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger small py-2" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div id="otpFeedback" class="alert d-none small py-2" role="alert"></div>

    @if ($bothShown)
        <ul class="nav nav-pills nav-fill bg-light p-1 rounded mb-3 small" id="adminLoginTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-1" id="tab-password-btn" data-bs-toggle="tab" data-bs-target="#tab-password-pane" type="button" role="tab">
                    Email &amp; Password
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-1" id="tab-otp-btn" data-bs-toggle="tab" data-bs-target="#tab-otp-pane" type="button" role="tab">
                    Email OTP
                </button>
            </li>
        </ul>
    @endif

    <div class="tab-content">
        @if ($showPassword)
            <div class="tab-pane fade {{ $defaultTab === 'password' ? 'show active' : '' }}" id="tab-password-pane" role="tabpanel">
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
            </div>
        @endif

        @if ($showOtp)
            <div class="tab-pane fade {{ $defaultTab === 'otp' ? 'show active' : '' }}" id="tab-otp-pane" role="tabpanel">
                @if (!$emailOtpSenderReady)
                    <div class="alert alert-warning small py-2 mb-3">
                        <strong>Email OTP is enabled globally</strong>, but the Platform Default Email Sender is not verified yet. Sign in with your password and verify delivery via <em>Send Test</em> in Global Auth &amp; Email settings.
                    </div>
                @endif

                <form id="otpRequestForm" onsubmit="requestAdminOtp(event)" novalidate>
                    <div class="mb-3">
                        <label for="otp_email" class="form-label small fw-semibold">Admin Email Address</label>
                        <input
                            type="email"
                            id="otp_email"
                            name="email"
                            class="form-control"
                            value="{{ old('email') }}"
                            placeholder="admin@connect.twsd.me"
                            required
                            @disabled(!$emailOtpSenderReady)
                        >
                    </div>
                    <button type="submit" id="sendOtpBtn" class="btn btn-brand w-100 fw-medium" @disabled(!$emailOtpSenderReady)>
                        Send One-Time Code
                    </button>
                </form>

                <form id="otpVerifyForm" class="d-none" onsubmit="verifyAdminOtp(event)" novalidate>
                    <input type="hidden" id="otpChallengeUuid" value="">
                    <div class="small text-muted mb-2" id="otpMaskedNotice"></div>
                    <div class="mb-3">
                        <label for="otp_code" class="form-label small fw-semibold">6-Digit Verification Code</label>
                        <input
                            type="text"
                            id="otp_code"
                            name="otp"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="8"
                            class="form-control font-monospace text-center fs-5 tracking-widest"
                            placeholder="000000"
                            required
                        >
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="otpRemember" value="1">
                        <label class="form-check-label small text-secondary" for="otpRemember">Keep session signed in</label>
                    </div>
                    <button type="submit" id="verifyOtpBtn" class="btn btn-brand w-100 fw-medium mb-2">
                        Verify &amp; Sign In
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetOtpFlow()">
                        Use Different Email / Resend Code
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showOtpFeedback(message, isError = false) {
    const el = document.getElementById('otpFeedback');
    el.className = `alert small py-2 ${isError ? 'alert-danger' : 'alert-success'}`;
    el.textContent = message;
    el.classList.remove('d-none');
}

function clearOtpFeedback() {
    const el = document.getElementById('otpFeedback');
    el.classList.add('d-none');
}

function resetOtpFlow() {
    clearOtpFeedback();
    document.getElementById('otpVerifyForm').classList.add('d-none');
    document.getElementById('otpRequestForm').classList.remove('d-none');
    document.getElementById('otp_code').value = '';
    document.getElementById('otpChallengeUuid').value = '';
}

async function parseOtpError(res) {
    try {
        const data = await res.json();
        if (data.errors) return Object.values(data.errors).flat().join(' ');
        return data.message || data.error || `Request failed (${res.status})`;
    } catch {
        return `Request failed (${res.status})`;
    }
}

async function requestAdminOtp(e) {
    e.preventDefault();
    clearOtpFeedback();
    const email = document.getElementById('otp_email').value.trim();
    const btn = document.getElementById('sendOtpBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending Code...';

    try {
        const res = await fetch('{{ route('admin.login.otp.send') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ email })
        });
        if (!res.ok) {
            showOtpFeedback(await parseOtpError(res), true);
            return;
        }
        const json = await res.json();
        document.getElementById('otpChallengeUuid').value = json.data.challenge_uuid;
        document.getElementById('otpMaskedNotice').textContent = `Enter the verification code sent to ${json.data.recipient_masked}.`;
        document.getElementById('otpRequestForm').classList.add('d-none');
        document.getElementById('otpVerifyForm').classList.remove('d-none');
        document.getElementById('otp_code').focus();
        showOtpFeedback('Verification code sent. Check your inbox.', false);
    } catch (err) {
        showOtpFeedback(`Network error: ${err?.message || 'Unable to send OTP'}`, true);
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function verifyAdminOtp(e) {
    e.preventDefault();
    clearOtpFeedback();
    const btn = document.getElementById('verifyOtpBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    try {
        const res = await fetch('{{ route('admin.login.otp.verify') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                challenge_uuid: document.getElementById('otpChallengeUuid').value,
                email: document.getElementById('otp_email').value.trim(),
                otp: document.getElementById('otp_code').value.trim(),
                remember: document.getElementById('otpRemember').checked
            })
        });
        if (!res.ok) {
            showOtpFeedback(await parseOtpError(res), true);
            return;
        }
        const json = await res.json();
        window.location.href = json.data.redirect_to || '{{ route('super-admin.auth-settings') }}';
    } catch (err) {
        showOtpFeedback(`Network error: ${err?.message || 'Unable to verify OTP'}`, true);
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}
</script>
@endpush
