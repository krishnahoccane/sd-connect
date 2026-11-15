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
    $liveRuntimeCodes = ['email_password', 'email_otp'];
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
                <strong>Live:</strong> <code>email_password</code> and <code>email_otp</code> are wired to end-user login &amp; OIDC token issuance. <strong>Credentials Only:</strong> <code>firebase_sms</code>, <code>whatsapp_otp</code>, <code>google</code>, and <code>microsoft</code> store encrypted platform keys cleanly here while interactive OAuth/SMS login flows are slated for Phases 6&ndash;7.
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
                <div id="providerModalNotice" class="alert alert-info small py-2 mb-3">
                    Secrets entered below are encrypted at rest and never sent back to the browser. Leave secret inputs blank to retain saved credentials.
                </div>
                <div id="providerModalFields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark btn-sm">Save Credentials</button>
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
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const existingProviderSettings = @json($globalSettingsMap);

const providerSchemas = {
    firebase_sms: [
        { name: 'project_id', label: 'Firebase Project ID', type: 'text', secret: false, required: true, placeholder: 'my-firebase-project' },
        { name: 'api_key', label: 'Firebase Web API Key', type: 'text', secret: false, required: true, placeholder: 'AIza...' },
        { name: 'service_account_json', label: 'Service Account JSON (Encrypted)', type: 'textarea', secret: true, required: false, placeholder: 'Paste Firebase Admin JSON or leave blank to retain' }
    ],
    whatsapp_otp: [
        { name: 'api_base_url', label: 'Webglit / WhatsApp API Base URL', type: 'url', secret: false, required: true, placeholder: 'https://api.webglit.example/v1' },
        { name: 'sender_id', label: 'Sender / Phone Number ID', type: 'text', secret: false, required: true, placeholder: '1092837465' },
        { name: 'template_name', label: 'Approved OTP Template Name', type: 'text', secret: false, required: true, placeholder: 'twsd_login_otp' },
        { name: 'api_key', label: 'API Bearer Token / Secret (Encrypted)', type: 'password', secret: true, required: false, placeholder: 'Leave blank to retain existing secret' }
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

function showFeedback(message, isError = false) {
    const el = document.getElementById('globalFeedback');
    el.className = `alert small mb-3 ${isError ? 'alert-danger' : 'alert-success'}`;
    el.textContent = message;
    el.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
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
            input.rows = 3;
        } else {
            input = document.createElement('input');
            input.type = field.type;
        }
        input.className = 'form-control form-control-sm';
        input.name = field.name;
        input.dataset.isSecret = field.secret ? '1' : '0';
        input.placeholder = field.placeholder || '';
        if (field.required) input.required = true;
        if (!field.secret && current[field.name] !== undefined && current[field.name] !== null) {
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
    const code = document.getElementById('providerModalCode').value;
    const inputs = document.getElementById('providerModalFields').querySelectorAll('input, textarea');
    const public_settings = {};
    const credentials = {};

    inputs.forEach((el) => {
        if (el.dataset.isSecret === '1') {
            if (el.value.trim() !== '') {
                credentials[el.name] = el.value.trim();
            }
        } else {
            public_settings[el.name] = el.value.trim();
        }
    });

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
    showFeedback(await parseError(res), true);
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
</script>
@endpush
