@extends('layouts.auth')

@section('content')
    <div class="mb-4">
        @if(!empty($branding?->logo_path))
            <img src="{{ asset('storage/' . $branding->logo_path) }}" alt="{{ $branding->display_name }}" height="36" class="mb-3">
        @endif
        <h1 class="h4 fw-semibold mb-1">{{ $branding->display_name ?? $organisation->name }}</h1>
        <p class="text-muted small mb-0">Sign in to continue to <strong>{{ $application->name }}</strong></p>
    </div>

    <div id="authErrorBox" class="alert alert-danger py-2 small d-none" role="alert"></div>

    @php
        $enabledCodes = collect($effectivePolicy['methods'] ?? [])->pluck('provider_code')->all();
    @endphp

    @if(in_array('email_password', $enabledCodes, true))
        <form id="passwordLoginForm" onsubmit="submitPasswordLogin(event)" class="mb-3">
            <div class="mb-3">
                <label for="email" class="form-label small fw-medium">Work Email</label>
                <input type="email" id="email" name="email" class="form-control" required autofocus autocomplete="username">
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label small fw-medium mb-1">Password</label>
                    <a href="{{ route('auth.password.forgot') }}" class="small text-decoration-none">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-brand w-100 py-2 fw-medium">Sign in with Password</button>
        </form>
    @endif

    @if(in_array('email_otp', $enabledCodes, true))
        <div class="border-top pt-3 mt-3">
            <div class="small fw-medium mb-2">Sign in with Email OTP</div>
            <div class="input-group input-group-sm mb-2">
                <input type="email" id="otpEmail" class="form-control" placeholder="name@company.com">
                <button type="button" class="btn btn-outline-secondary" onclick="sendEmailOtp()">Send Code</button>
            </div>
            <div id="otpVerifyBox" class="d-none">
                <input type="hidden" id="otpTxUuid">
                <div class="input-group input-group-sm">
                    <input type="text" id="otpCode" class="form-control" placeholder="6-digit code" maxlength="8">
                    <button type="button" class="btn btn-brand" onclick="verifyEmailOtp()">Verify OTP</button>
                </div>
            </div>
        </div>
    @endif

    @if(in_array('whatsapp_otp', $enabledCodes, true))
        <div class="border-top pt-3 mt-3">
            <div class="small fw-medium mb-2">Sign in with WhatsApp OTP</div>
            <div class="input-group input-group-sm mb-2">
                <input type="tel" id="waOtpPhone" class="form-control font-monospace" placeholder="919876543210">
                <button type="button" class="btn btn-outline-secondary" onclick="sendWhatsAppOtp()">Send Code</button>
            </div>
            <div id="waOtpVerifyBox" class="d-none">
                <input type="hidden" id="waOtpTxUuid">
                <div class="input-group input-group-sm">
                    <input type="text" id="waOtpCode" class="form-control font-monospace" placeholder="6-digit code" maxlength="8">
                    <button type="button" class="btn btn-brand" onclick="verifyWhatsAppOtp()">Verify OTP</button>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
const clientId = @json($clientId ?? '');
const oauthTransactionId = @json($transactionId ?? '');

function showAuthError(msg) {
    const box = document.getElementById('authErrorBox');
    box.textContent = msg;
    box.classList.remove('d-none');
}

async function submitPasswordLogin(e) {
    e.preventDefault();
    const res = await fetch('/api/v1/auth/email/password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            client_id: clientId,
            oauth_transaction_id: oauthTransactionId,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        })
    });
    const json = await res.json();
    if (!res.ok) return showAuthError(json.message || json.error || 'Authentication failed.');
    if (json.data?.redirect_uri) window.location.href = json.data.redirect_uri;
}

async function sendEmailOtp() {
    const res = await fetch('/api/v1/auth/email/otp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ client_id: clientId, email: document.getElementById('otpEmail').value })
    });
    const json = await res.json();
    if (!res.ok) return showAuthError(json.message || json.error || 'Unable to send OTP.');
    document.getElementById('otpTxUuid').value = json.data.transaction_uuid;
    document.getElementById('otpVerifyBox').classList.remove('d-none');
}

async function verifyEmailOtp() {
    const res = await fetch('/api/v1/auth/email/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            client_id: clientId,
            oauth_transaction_id: oauthTransactionId,
            transaction_uuid: document.getElementById('otpTxUuid').value,
            email: document.getElementById('otpEmail').value,
            otp: document.getElementById('otpCode').value
        })
    });
    const json = await res.json();
    if (!res.ok) return showAuthError(json.message || json.error || 'Invalid OTP.');
    if (json.data?.redirect_uri) window.location.href = json.data.redirect_uri;
}

async function sendWhatsAppOtp() {
    const res = await fetch('/api/v1/auth/whatsapp/otp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ client_id: clientId, phone: document.getElementById('waOtpPhone').value })
    });
    const json = await res.json();
    if (!res.ok) return showAuthError(json.message || json.error || 'Unable to send WhatsApp OTP.');
    document.getElementById('waOtpTxUuid').value = json.data.transaction_uuid;
    document.getElementById('waOtpVerifyBox').classList.remove('d-none');
}

async function verifyWhatsAppOtp() {
    const res = await fetch('/api/v1/auth/whatsapp/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            client_id: clientId,
            oauth_transaction_id: oauthTransactionId,
            transaction_uuid: document.getElementById('waOtpTxUuid').value,
            phone: document.getElementById('waOtpPhone').value,
            otp: document.getElementById('waOtpCode').value
        })
    });
    const json = await res.json();
    if (!res.ok) return showAuthError(json.message || json.error || 'Invalid WhatsApp OTP.');
    if (json.data?.redirect_uri) window.location.href = json.data.redirect_uri;
}
</script>
@endpush
