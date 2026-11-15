@extends('layouts.admin')

@section('title', 'Super Admin — Global Authentication & Email Settings')
@section('breadcrumb', 'Super Admin / Global Authentication')

@section('sidebar')
    <a class="nav-link active fw-semibold text-dark" href="{{ route('super-admin.auth-settings') }}">Global Auth &amp; Email</a>
    <a class="nav-link text-secondary" href="#organisations-section">Organisations</a>
    <a class="nav-link text-secondary" href="#platform-email-section">Platform Email</a>
    <a class="nav-link text-secondary" href="#security-events-section">Security Events</a>
@endsection

@section('content')
@php
    $liveRuntimeCodes = ['email_password', 'email_otp', 'whatsapp_otp'];
    $configurableCodes = ['firebase_sms', 'whatsapp_otp', 'google', 'microsoft'];
    $globalSettingsMap = [];
    foreach ($configurableCodes as $c) {
        $globalSettingsMap[$c] = $globalConfigs->get($c)?->public_settings ?? [];
    }
    $smtpSettings = $platformEmail?->smtp_settings ?? [];
@endphp

<div id="globalFeedback" class="alert d-none small mb-3" role="alert"></div>

<div class="card border shadow-none mb-4">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-1">How Authentication Providers Are Configured (3-Level Policy Hierarchy)</div>
        <div class="row g-2 small text-muted">
            <div class="col-12 col-md-4">
                <strong class="text-dark">1. Global Platform (Here):</strong> Enable/disable providers globally, configure encrypted provider credentials, and verify the Platform Default Email Sender for Email OTP.
            </div>
            <div class="col-12 col-md-4">
                <strong class="text-dark">2. Organisation Level:</strong> Open an Organisation&rsquo;s <em>Auth &amp; Email Policies</em> below to permit providers for that tenant and optionally override SMTP/Workspace settings.
            </div>
            <div class="col-12 col-md-4">
                <strong class="text-dark">3. Application Level:</strong> Open an Organisation&rsquo;s <em>Applications</em> to register OIDC apps and select which permitted login methods appear on `/oauth/authorize`.
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Global Authentication Providers (Level 1)</span>
                <span class="small fw-normal text-muted">{{ $providers->where('is_globally_enabled', true)->count() }} of {{ $providers->count() }} enabled</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Provider</th>
                            <th>Runtime</th>
                            <th>Health</th>
                            <th class="text-end">Credentials &amp; Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $provider)
                            @php
                                $isLive = in_array($provider->code, $liveRuntimeCodes, true);
                                $isConfigurable = in_array($provider->code, $configurableCodes, true);
                                $hasConfig = $globalConfigs->has($provider->code);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $provider->name }}</div>
                                    <code class="small text-muted">{{ $provider->code }}</code>
                                </td>
                                <td>
                                    @if($isLive)
                                        <span class="badge text-bg-success">Live</span>
                                    @else
                                        <span class="badge text-bg-warning">Credentials Only</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <span class="badge {{ $provider->health_status === 'healthy' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border' }}">
                                        {{ ucfirst($provider->health_status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        @if($isConfigurable)
                                            <button type="button"
                                                class="btn btn-sm btn-outline-dark"
                                                onclick="openProviderModal('{{ $provider->code }}', '{{ e($provider->name) }}')">
                                                {{ $hasConfig ? 'Edit Credentials' : 'Configure' }}
                                            </button>
                                            @if($provider->code === 'firebase_sms' && ($hasConfig || $provider->is_globally_enabled))
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    onclick="openFirebaseSmsTestModal()">
                                                    Test SMS
                                                </button>
                                            @elseif($provider->code === 'whatsapp_otp' && ($hasConfig || $provider->is_globally_enabled))
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    onclick="openWhatsAppTestModal()">
                                                    Test WhatsApp
                                                </button>
                                            @endif
                                        @elseif($provider->code === 'email_otp')
                                            <a href="#platform-email-section" class="btn btn-sm btn-outline-secondary">Email Form &rarr;</a>
                                        @endif
                                        <button type="button"
                                            class="btn btn-sm {{ $provider->is_globally_enabled ? 'btn-success' : 'btn-outline-secondary' }}"
                                            onclick="toggleGlobalProvider('{{ $provider->code }}', {{ $provider->is_globally_enabled ? 'false' : 'true' }})">
                                            {{ $provider->is_globally_enabled ? 'Enabled' : 'Disabled' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                <strong>Live:</strong> <code>email_password</code>, <code>email_otp</code>, and <code>whatsapp_otp</code> are wired to live login &amp; OTP delivery. <strong>Credentials Only:</strong> <code>firebase_sms</code>, <code>google</code>, and <code>microsoft</code> store platform config &amp; keys cleanly while end-user OAuth/SMS login flows are slated for Phases 6&ndash;7.
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5" id="platform-email-section">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Platform Default Email Sender (<code>email_otp</code>)</span>
                @if($platformEmail?->is_verified)
                    <span class="badge text-bg-success">Verified</span>
                @else
                    <span class="badge text-bg-secondary">Unverified</span>
                @endif
            </div>
            <div class="card-body">
                <form id="platform-email-form" onsubmit="savePlatformEmail(event)">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">Sender Name</label>
                            <input type="text" name="sender_name" class="form-control form-control-sm" value="{{ $platformEmail->sender_name ?? 'TWSD Connect' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">Sender Email</label>
                            <input type="email" name="sender_email" class="form-control form-control-sm" value="{{ $platformEmail->sender_email ?? '' }}" placeholder="auth@twsd.me" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control form-control-sm" value="{{ $smtpSettings['host'] ?? 'smtp.gmail.com' }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Port</label>
                            <input type="number" name="smtp_port" class="form-control form-control-sm" value="{{ $smtpSettings['port'] ?? 587 }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Encryption</label>
                            <select name="smtp_encryption" class="form-select form-select-sm">
                                <option value="tls" @selected(($smtpSettings['encryption'] ?? 'tls') === 'tls')>TLS</option>
                                <option value="ssl" @selected(($smtpSettings['encryption'] ?? '') === 'ssl')>SSL</option>
                                <option value="none" @selected(($smtpSettings['encryption'] ?? '') === 'none')>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">SMTP Username (Optional)</label>
                            <input type="text" name="smtp_username" class="form-control form-control-sm" value="{{ $smtpSettings['username'] ?? '' }}" placeholder="Defaults to Sender Email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">App / SMTP Password (Encrypted)</label>
                            <input type="password" name="app_password" class="form-control form-control-sm" placeholder="{{ $platformEmail ? 'Leave blank to keep secret' : 'Enter SMTP / App Password' }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Platform Sender</button>
                </form>

                <hr class="my-3">

                <form onsubmit="testPlatformEmail(event)" class="row g-2 align-items-end">
                    <div class="col-8">
                        <label class="form-label small fw-medium mb-1">Test Delivery &amp; Verify Provider</label>
                        <input type="email" name="recipient_email" class="form-control form-control-sm" placeholder="admin@yourdomain.com" value="{{ auth()->user()?->primary_email }}" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-outline-dark btn-sm w-100">Send Test</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12" id="organisations-section">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Organisations Directory (Configure Level 2 Policies &amp; Level 3 Applications)</span>
                <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#createOrgModal">Create Organisation</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Organisation</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th class="text-end">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($organisations as $org)
                            <tr>
                                <td class="fw-medium">{{ $org->name }}</td>
                                <td class="font-monospace small text-muted">{{ $org->slug }}</td>
                                <td><span class="badge text-bg-light border">{{ ucfirst($org->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('org.email-settings', $org->uuid) }}" class="btn btn-outline-dark btn-sm">Auth &amp; Email Policies</a>
                                    <a href="{{ route('org.applications', $org->uuid) }}" class="btn btn-dark btn-sm">Applications</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No organisations created yet. Click <strong>Create Organisation</strong> to configure Level 2 tenant policies and register OIDC applications.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12" id="security-events-section">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Recent Security Events</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Event</th>
                            <th>Severity</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($securityEvents as $event)
                            <tr>
                                <td class="font-monospace small">{{ $event->occurred_at }}</td>
                                <td class="font-monospace small">{{ $event->event_type }}</td>
                                <td>{{ ucfirst($event->severity) }}</td>
                                <td class="font-monospace small">{{ $event->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No security events recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Provider Credentials Modal -->
<div class="modal fade" id="providerConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="saveProviderConfig(event)">
            <div class="modal-header">
                <h2 class="modal-title h6" id="providerModalTitle">Configure Provider</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="providerModalCode" value="">
                <div id="providerModalInstructions" class="alert alert-light border small py-2 mb-3 d-none"></div>
                <div id="providerModalNotice" class="alert alert-info small py-2 mb-3">
                    Secrets entered below are encrypted at rest and never sent back to the browser. Leave secret inputs blank to retain saved credentials.
                </div>
                <div id="providerModalError" class="alert alert-danger small py-2 mb-3 d-none"></div>
                <div id="providerModalFields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark btn-sm">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Organisation Modal -->
<div class="modal fade" id="createOrgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="createOrganisation(event)">
            <div class="modal-header">
                <h2 class="modal-title h6">Create Organisation</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Organisation Name</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Acme Corp" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Slug (Optional)</label>
                    <input type="text" name="slug" class="form-control form-control-sm" placeholder="acme-corp">
                    <div class="form-text">Used in tenant subdomains. Defaults to a slugified name.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark btn-sm">Create Organisation</button>
            </div>
        </form>
    </div>
</div>

<!-- Test Firebase SMS OTP Modal -->
<div class="modal fade" id="testFirebaseSmsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6">Test Firebase SMS OTP Delivery</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small py-2 mb-3">
                    Uses your saved Firebase Web Config (<code>Phone Auth</code> + <code>reCAPTCHA</code>). Make sure <strong>Phone</strong> sign-in is enabled in Firebase Console and <code>{{ request()->getHost() }}</code> is in <strong>Authorized domains</strong>.
                </div>
                <div id="fbTestFeedback" class="alert small py-2 mb-3 d-none"></div>

                <form id="fbSendSmsForm" onsubmit="sendFirebaseTestSms(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Phone Number (E.164 Format)</label>
                        <input type="tel" id="fbTestPhone" class="form-control form-control-sm font-monospace" placeholder="+919876543210" required>
                        <div class="form-text">Include country code (or use a Firebase Console test phone number).</div>
                    </div>
                    <div id="firebaseRecaptchaContainer" class="mb-3"></div>
                    <button type="submit" id="fbSendSmsBtn" class="btn btn-dark btn-sm w-100">Send Test SMS OTP</button>
                </form>

                <form id="fbVerifySmsForm" class="d-none" onsubmit="verifyFirebaseTestSms(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Enter 6-Digit SMS Code</label>
                        <input type="text" id="fbTestCode" class="form-control form-control-sm font-monospace text-center" inputmode="numeric" maxlength="6" placeholder="123456" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" id="fbVerifySmsBtn" class="btn btn-dark btn-sm flex-grow-1">Verify Code &amp; Mark Healthy</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFirebaseSmsTest()">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Test WhatsApp OTP Modal -->
<div class="modal fade" id="testWhatsAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="sendWhatsAppTest(event)">
            <div class="modal-header">
                <h2 class="modal-title h6">Test WhatsApp OTP Delivery</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small py-2 mb-3">
                    Sends a multipart POST request to <code>{{ config('twsd_connect.providers.whatsapp.endpoint') }}</code> using your saved encrypted <code>appkey</code> and <code>authkey</code>.
                </div>
                <div id="waTestFeedback" class="alert small py-2 mb-3 d-none"></div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Receiver Phone Number (International Format Without +)</label>
                    <input type="tel" id="waTestPhone" class="form-control form-control-sm font-monospace" placeholder="919876543210" required>
                    <div class="form-text">Digits only with country code (any <code>+</code> or spaces are stripped automatically).</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Test Message (Optional)</label>
                    <textarea id="waTestMessage" rows="2" class="form-control form-control-sm" placeholder="TWSD Connect — Test WhatsApp OTP verification message."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="waSendTestBtn" class="btn btn-dark btn-sm">Send Test WhatsApp</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const existingProviderSettings = @json($globalSettingsMap);

const providerInstructions = {
    firebase_sms: `
        <div class="fw-semibold text-dark mb-1">How to get your Firebase Config:</div>
        <ol class="ps-3 mb-2">
            <li>Open the <strong>Firebase Console</strong> and select your project.</li>
            <li>Go to <strong>Project settings</strong> (gear icon next to <em>Project Overview</em>).</li>
            <li>Scroll to <strong>Your apps</strong> and select your <strong>Web app (&lt;/&gt;)</strong> (or click <em>Add app</em> &rarr; Web).</li>
            <li>Under <strong>SDK setup and configuration</strong>, choose the <strong>Config</strong> radio option.</li>
            <li>Copy the entire <code>const firebaseConfig = { ... };</code> block and paste it below.</li>
        </ol>
        <div class="text-muted">Also enable <strong>Authentication &rarr; Sign-in method &rarr; Phone</strong> in Firebase Console and add <code>{{ request()->getHost() }}</code> to <strong>Authorized domains</strong>.</div>
    `,
    whatsapp_otp: `
        <div class="fw-semibold text-dark mb-1">Default Webglit WhatsApp API</div>
        <div class="text-muted mb-1">Endpoint: <code>{{ config('twsd_connect.providers.whatsapp.endpoint') }}</code></div>
        <div class="text-muted">Only your <code>appkey</code> and <code>authkey</code> are needed. Receiver numbers are automatically formatted in international digits without the <code>+</code> sign.</div>
    `
};

const providerSchemas = {
    firebase_sms: [
        {
            name: 'firebase_config_raw',
            label: 'SDK setup and configuration > Config',
            type: 'textarea',
            rows: 9,
            monospace: true,
            secret: false,
            required: true,
            placeholder: 'const firebaseConfig = {\n  apiKey: "...",\n  authDomain: "your-project.firebaseapp.com",\n  databaseURL: "https://your-project.firebaseio.com",\n  projectId: "your-project",\n  storageBucket: "your-project.firebasestorage.app",\n  messagingSenderId: "...",\n  appId: "1:...:web:..."\n};'
        }
    ],
    whatsapp_otp: [
        { name: 'appkey', label: 'App Key (appkey — Encrypted)', type: 'password', secret: true, required: false, placeholder: 'Leave blank to retain existing appkey' },
        { name: 'authkey', label: 'Auth Key (authkey — Encrypted)', type: 'password', secret: true, required: false, placeholder: 'Leave blank to retain existing authkey' }
    ],
    google: [
        { name: 'client_id', label: 'Google OAuth Client ID', type: 'text', secret: false, required: true, placeholder: '12345-abc.apps.googleusercontent.com' },
        { name: 'hosted_domain', label: 'Allowed Google Workspace Domain (Optional)', type: 'text', secret: false, required: false, placeholder: 'twsd.me' },
        { name: 'client_secret', label: 'Google Client Secret (Encrypted)', type: 'password', secret: true, required: false, placeholder: 'Leave blank to retain existing secret' }
    ],
    microsoft: [
        { name: 'tenant_id', label: 'Microsoft Entra Tenant ID', type: 'text', secret: false, required: true, placeholder: 'common or directory UUID' },
        { name: 'client_id', label: 'Application (Client) ID', type: 'text', secret: false, required: true, placeholder: '00000000-0000-0000-0000-000000000000' },
        { name: 'client_secret', label: 'Client Secret Value (Encrypted)', type: 'password', secret: true, required: false, placeholder: 'Leave blank to retain existing secret' }
    ]
};

function parseFirebaseConfigInput(rawText) {
    const startIdx = rawText.indexOf('{');
    const endIdx = rawText.lastIndexOf('}');
    if (startIdx === -1 || endIdx === -1 || endIdx <= startIdx) {
        throw new Error('Paste the full Firebase configuration object containing { apiKey, authDomain, projectId, ... }.');
    }

    // String-aware conversion from JS object literal (Firebase Console Config snippet) to strict JSON
    let objText = rawText.slice(startIdx, endIdx + 1)
        .replace(/"([^"\\]*(?:\\.[^"\\]*)*)"|'([^'\\]*(?:\\.[^'\\]*)*)'|\/\/[^\n]*|\/\*[\s\S]*?\*\//g, (match, dq, sq) => {
            if (dq !== undefined) return match;
            if (sq !== undefined) return JSON.stringify(sq.replace(/\\'/g, "'"));
            return '';
        })
        .replace(/([{,]\s*)([A-Za-z_$][A-Za-z0-9_$]*)\s*:/g, '$1"$2":')
        .replace(/,\s*([}\]])/g, '$1');

    let parsed;
    try {
        parsed = JSON.parse(objText);
    } catch {
        throw new Error('Could not parse the Firebase Config snippet. Copy the exact const firebaseConfig = { ... }; block from Firebase Console.');
    }

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('Invalid Firebase Config object.');
    }
    if (!parsed.apiKey || !parsed.projectId) {
        throw new Error('Firebase Config must include at least apiKey and projectId.');
    }

    return parsed;
}

function showFeedback(message, isError = false) {
    const el = document.getElementById('globalFeedback');
    el.className = `alert small mb-3 ${isError ? 'alert-danger' : 'alert-success'}`;
    el.textContent = message;
    el.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showModalError(message) {
    const el = document.getElementById('providerModalError');
    if (!message) {
        el.classList.add('d-none');
        el.textContent = '';
        return;
    }
    el.textContent = message;
    el.classList.remove('d-none');
}

async function parseError(res) {
    try {
        const data = await res.json();
        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }
        return data.message || data.error || `Request failed (${res.status})`;
    } catch {
        return `Request failed (${res.status})`;
    }
}

function openProviderModal(code, name) {
    document.getElementById('providerModalTitle').textContent = `Configure ${name}`;
    document.getElementById('providerModalCode').value = code;
    showModalError('');

    const instructionsEl = document.getElementById('providerModalInstructions');
    if (providerInstructions[code]) {
        instructionsEl.innerHTML = providerInstructions[code];
        instructionsEl.classList.remove('d-none');
    } else {
        instructionsEl.innerHTML = '';
        instructionsEl.classList.add('d-none');
    }

    const noticeEl = document.getElementById('providerModalNotice');
    if (code === 'firebase_sms') {
        noticeEl.classList.add('d-none');
    } else {
        noticeEl.classList.remove('d-none');
    }

    const schema = providerSchemas[code] || [];
    const current = existingProviderSettings[code] || {};
    const container = document.getElementById('providerModalFields');
    container.innerHTML = '';

    schema.forEach((field) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';

        const label = document.createElement('label');
        label.className = 'form-label small fw-medium';
        label.textContent = field.label;
        wrapper.appendChild(label);

        let input;
        if (field.type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = field.rows || 3;
        } else {
            input = document.createElement('input');
            input.type = field.type;
        }
        input.className = `form-control form-control-sm${field.monospace ? ' font-monospace' : ''}`;
        input.name = field.name;
        input.dataset.isSecret = field.secret ? '1' : '0';
        input.placeholder = field.placeholder || '';
        if (field.required) input.required = true;

        if (code === 'firebase_sms' && field.name === 'firebase_config_raw') {
            if (current.firebase_config && typeof current.firebase_config === 'object') {
                input.value = `const firebaseConfig = ${JSON.stringify(current.firebase_config, null, 2)};`;
            } else if (current.api_key || current.project_id) {
                input.value = `const firebaseConfig = ${JSON.stringify({
                    apiKey: current.api_key || '',
                    projectId: current.project_id || ''
                }, null, 2)};`;
            }
        } else if (!field.secret && current[field.name] !== undefined && current[field.name] !== null) {
            input.value = current[field.name];
        }

        wrapper.appendChild(input);
        container.appendChild(wrapper);
    });

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('providerConfigModal'));
    modal.show();
}

async function saveProviderConfig(e) {
    e.preventDefault();
    showModalError('');
    const code = document.getElementById('providerModalCode').value;
    const inputs = document.getElementById('providerModalFields').querySelectorAll('input, textarea');
    let public_settings = {};
    const credentials = {};

    if (code === 'firebase_sms') {
        const rawInput = document.getElementById('providerModalFields').querySelector('textarea[name="firebase_config_raw"]');
        try {
            const cfg = parseFirebaseConfigInput(rawInput ? rawInput.value : '');
            public_settings = {
                project_id: cfg.projectId,
                api_key: cfg.apiKey,
                auth_domain: cfg.authDomain || null,
                app_id: cfg.appId || null,
                firebase_config: cfg
            };
        } catch (err) {
            showModalError(err.message);
            return;
        }
    } else {
        inputs.forEach((el) => {
            if (el.dataset.isSecret === '1') {
                if (el.value.trim() !== '') {
                    credentials[el.name] = el.value.trim();
                }
            } else {
                public_settings[el.name] = el.value.trim();
            }
        });
    }

    const res = await fetch(`/super-admin/providers/${encodeURIComponent(code)}/config`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ public_settings, credentials, is_active: true })
    });

    if (res.ok) {
        window.location.reload();
        return;
    }
    showModalError(await parseError(res));
}

async function toggleGlobalProvider(code, nextState) {
    const res = await fetch(`/super-admin/providers/${encodeURIComponent(code)}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ is_globally_enabled: nextState })
    });
    if (res.ok) {
        window.location.reload();
        return;
    }
    showFeedback(await parseError(res), true);
}

async function savePlatformEmail(e) {
    e.preventDefault();
    const form = new FormData(e.target);
    const password = (form.get('app_password') || '').toString();
    const res = await fetch('/super-admin/providers/email/platform-default', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            driver: 'platform_google_workspace',
            sender_name: form.get('sender_name'),
            sender_email: form.get('sender_email'),
            smtp_settings: {
                host: form.get('smtp_host'),
                port: Number(form.get('smtp_port') || 587),
                encryption: form.get('smtp_encryption') || 'tls',
                username: form.get('smtp_username') || null
            },
            secrets: password ? { app_password: password } : null
        })
    });
    if (res.ok) {
        showFeedback('Platform email sender saved. Use "Send Test" below to verify delivery.');
        setTimeout(() => window.location.reload(), 700);
        return;
    }
    showFeedback(await parseError(res), true);
}

async function testPlatformEmail(e) {
    e.preventDefault();
    const formEl = e.target;
    const btn = formEl.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent : 'Send Test';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending...';
    }
    try {
        const form = new FormData(formEl);
        const res = await fetch('/super-admin/providers/email/platform-default/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ recipient_email: form.get('recipient_email') })
        });
        if (res.ok) {
            showFeedback('Test email sent successfully! Platform email_otp sender is now marked Verified.');
            setTimeout(() => window.location.reload(), 900);
            return;
        }
        showFeedback(await parseError(res), true);
    } catch (err) {
        showFeedback(`Network or server error: ${err?.message || 'Unable to reach mail endpoint'}`, true);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
}

async function createOrganisation(e) {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await fetch('/super-admin/organisations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            name: form.get('name'),
            slug: form.get('slug') || null
        })
    });
    if (res.ok) {
        window.location.reload();
        return;
    }
    showFeedback(await parseError(res), true);
}

let fbScriptsLoaded = false;
let fbRecaptchaVerifier = null;
let fbConfirmationResult = null;

function showFbTestFeedback(message, isError = false) {
    const el = document.getElementById('fbTestFeedback');
    el.className = `alert small py-2 mb-3 ${isError ? 'alert-danger' : 'alert-success'}`;
    el.textContent = message;
    el.classList.remove('d-none');
}

function loadScriptOnce(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = resolve;
        s.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(s);
    });
}

function resetFirebaseSmsTest() {
    fbConfirmationResult = null;
    document.getElementById('fbTestCode').value = '';
    document.getElementById('fbVerifySmsForm').classList.add('d-none');
    document.getElementById('fbSendSmsForm').classList.remove('d-none');
    document.getElementById('fbTestFeedback').classList.add('d-none');
    if (fbRecaptchaVerifier) {
        try { fbRecaptchaVerifier.clear(); } catch {}
        fbRecaptchaVerifier = null;
    }
    const container = document.getElementById('firebaseRecaptchaContainer');
    if (container) container.innerHTML = '';
    initFirebaseRecaptcha();
}

function getSavedFirebaseConfig() {
    const current = existingProviderSettings.firebase_sms || {};
    if (current.firebase_config && typeof current.firebase_config === 'object') {
        return current.firebase_config;
    }
    if (current.api_key && current.project_id) {
        return {
            apiKey: current.api_key,
            projectId: current.project_id,
            authDomain: current.auth_domain || `${current.project_id}.firebaseapp.com`
        };
    }
    return null;
}

async function ensureFirebaseSdkInitialized() {
    const cfg = getSavedFirebaseConfig();
    if (!cfg || !cfg.apiKey) {
        throw new Error('Firebase SDK Config is not saved yet. Click Configure first and paste your firebaseConfig.');
    }

    if (!fbScriptsLoaded) {
        await loadScriptOnce('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
        await loadScriptOnce('https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js');
        fbScriptsLoaded = true;
    }

    if (firebase.apps.length > 0) {
        return firebase.app();
    }
    return firebase.initializeApp(cfg);
}

async function initFirebaseRecaptcha() {
    try {
        const app = await ensureFirebaseSdkInitialized();
        const container = document.getElementById('firebaseRecaptchaContainer');
        container.innerHTML = '<div id="firebaseRecaptchaBox"></div>';
        fbRecaptchaVerifier = new firebase.auth.RecaptchaVerifier('firebaseRecaptchaBox', { size: 'normal' }, app);
        await fbRecaptchaVerifier.render();
        document.getElementById('fbSendSmsBtn').disabled = false;
    } catch (err) {
        document.getElementById('fbSendSmsBtn').disabled = true;
        showFbTestFeedback(err?.message || 'Unable to initialize Firebase SDK.', true);
    }
}

function openFirebaseSmsTestModal() {
    fbConfirmationResult = null;
    document.getElementById('fbTestCode').value = '';
    document.getElementById('fbVerifySmsForm').classList.add('d-none');
    document.getElementById('fbSendSmsForm').classList.remove('d-none');
    document.getElementById('fbTestFeedback').classList.add('d-none');
    if (fbRecaptchaVerifier) {
        try { fbRecaptchaVerifier.clear(); } catch {}
        fbRecaptchaVerifier = null;
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('testFirebaseSmsModal')).show();
    initFirebaseRecaptcha();
}

async function sendFirebaseTestSms(e) {
    e.preventDefault();
    const btn = document.getElementById('fbSendSmsBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending SMS...';
    document.getElementById('fbTestFeedback').classList.add('d-none');

    try {
        const app = await ensureFirebaseSdkInitialized();
        const phone = document.getElementById('fbTestPhone').value.trim();
        fbConfirmationResult = await app.auth().signInWithPhoneNumber(phone, fbRecaptchaVerifier);
        document.getElementById('fbSendSmsForm').classList.add('d-none');
        document.getElementById('fbVerifySmsForm').classList.remove('d-none');
        document.getElementById('fbTestCode').focus();
        showFbTestFeedback(`SMS OTP dispatched to ${phone}. Enter the code below to complete verification.`, false);
    } catch (err) {
        showFbTestFeedback(err?.message || 'Failed to send Firebase SMS OTP.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

async function verifyFirebaseTestSms(e) {
    e.preventDefault();
    const btn = document.getElementById('fbVerifySmsBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    try {
        if (!fbConfirmationResult) {
            throw new Error('Request a test SMS OTP first.');
        }
        const code = document.getElementById('fbTestCode').value.trim();
        const cred = await fbConfirmationResult.confirm(code);
        const idToken = await cred.user.getIdToken();

        const res = await fetch('/super-admin/providers/firebase_sms/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ firebase_id_token: idToken })
        });
        if (!res.ok) {
            throw new Error(await parseError(res));
        }
        try { await ensureFirebaseSdkInitialized().then((app) => app.auth().signOut()); } catch {}
        showFbTestFeedback('Firebase SMS OTP verified end-to-end! Provider marked Healthy.', false);
        setTimeout(() => window.location.reload(), 900);
    } catch (err) {
        showFbTestFeedback(err?.message || 'Verification failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

function openWhatsAppTestModal() {
    const feedback = document.getElementById('waTestFeedback');
    feedback.classList.add('d-none');
    feedback.textContent = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('testWhatsAppModal')).show();
}

async function sendWhatsAppTest(e) {
    e.preventDefault();
    const feedback = document.getElementById('waTestFeedback');
    const btn = document.getElementById('waSendTestBtn');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    feedback.classList.add('d-none');

    try {
        const res = await fetch('/super-admin/providers/whatsapp_otp/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                phone_number: document.getElementById('waTestPhone').value.trim(),
                message: document.getElementById('waTestMessage').value.trim() || null
            })
        });
        if (!res.ok) {
            throw new Error(await parseError(res));
        }
        feedback.className = 'alert alert-success small py-2 mb-3';
        feedback.textContent = 'WhatsApp message dispatched successfully! Provider marked Healthy.';
        feedback.classList.remove('d-none');
        setTimeout(() => window.location.reload(), 900);
    } catch (err) {
        feedback.className = 'alert alert-danger small py-2 mb-3';
        feedback.textContent = err?.message || 'WhatsApp test delivery failed.';
        feedback.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}
</script>
@endpush
