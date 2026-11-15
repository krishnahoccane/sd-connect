@extends('layouts.admin')

@section('title', $organisation->name . ' — Email & Authentication')
@section('breadcrumb', $organisation->name . ' / Email & Auth Policies')

@section('sidebar')
    <a class="nav-link text-secondary" href="{{ route('org.applications', $organisation->uuid) }}">Applications</a>
    <a class="nav-link active fw-semibold text-dark" href="{{ route('org.email-settings', $organisation->uuid) }}">Email &amp; Auth Policies</a>
    @if(auth()->user()?->is_super_admin)
        <a class="nav-link text-secondary border-top mt-2 pt-2" href="{{ route('super-admin.auth-settings') }}">&larr; Super Admin Console</a>
    @endif
@endsection

@section('content')
@php
    $liveRuntimeCodes = ['email_password', 'email_otp'];
    $orgSmtp = $emailConfig?->smtp_settings ?? [];
@endphp

<div id="orgFeedback" class="alert d-none small mb-3" role="alert"></div>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-white fw-semibold">Organisation Permitted Auth Methods (Level 2)</div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Organisations can only enable authentication providers that are globally enabled by the Super Admin. Applications under this organisation can then choose from the methods permitted here.
                </p>
                @foreach($providers as $provider)
                    @php
                        $enabled = $orgMethods->get($provider->code)?->is_enabled ?? false;
                        $isLive = in_array($provider->code, $liveRuntimeCodes, true);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-medium">{{ $provider->name }}</span>
                                @if($isLive)
                                    <span class="badge text-bg-success">Live</span>
                                @else
                                    <span class="badge text-bg-warning">Phase 6&ndash;7 Pending</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                <code>{{ $provider->code }}</code> &middot; Global: {{ $provider->is_globally_enabled ? 'Permitted' : 'Blocked by Super Admin' }}
                            </div>
                        </div>
                        <button type="button"
                            class="btn btn-sm {{ $enabled ? 'btn-success' : 'btn-outline-secondary' }}"
                            @disabled(!$provider->is_globally_enabled)
                            onclick="toggleOrgMethod('{{ $organisation->uuid }}', '{{ $provider->code }}', {{ $enabled ? 'false' : 'true' }})">
                            {{ $enabled ? 'Enabled' : 'Disabled' }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border shadow-none h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Organisation Email Sender</span>
                @if($emailConfig?->is_verified)
                    <span class="badge text-bg-success">Verified</span>
                @elseif($emailConfig)
                    <span class="badge text-bg-secondary">Unverified</span>
                @else
                    <span class="badge text-bg-light border">Inheriting Platform Default</span>
                @endif
            </div>
            <div class="card-body">
                <form onsubmit="saveOrgEmail(event, '{{ $organisation->uuid }}')">
                    <div class="mb-2">
                        <label class="form-label small fw-medium mb-1">Driver</label>
                        <select name="driver" class="form-select form-select-sm">
                            <option value="platform_google_workspace" @selected(($emailConfig->driver ?? 'platform_google_workspace') === 'platform_google_workspace')>Platform Google Workspace Default</option>
                            <option value="org_smtp" @selected(($emailConfig->driver ?? '') === 'org_smtp')>Organisation Custom SMTP</option>
                            <option value="org_google_workspace" @selected(($emailConfig->driver ?? '') === 'org_google_workspace')>Organisation Google Workspace</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">Sender Name</label>
                            <input name="sender_name" class="form-control form-control-sm" value="{{ $emailConfig->sender_name ?? $organisation->name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">Sender Email</label>
                            <input name="sender_email" type="email" class="form-control form-control-sm" value="{{ $emailConfig->sender_email ?? '' }}" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">SMTP Host</label>
                            <input name="smtp_host" class="form-control form-control-sm" value="{{ $orgSmtp['host'] ?? '' }}" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Port</label>
                            <input name="smtp_port" type="number" class="form-control form-control-sm" value="{{ $orgSmtp['port'] ?? 587 }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Encryption</label>
                            <select name="smtp_encryption" class="form-select form-select-sm">
                                <option value="tls" @selected(($orgSmtp['encryption'] ?? 'tls') === 'tls')>TLS</option>
                                <option value="ssl" @selected(($orgSmtp['encryption'] ?? '') === 'ssl')>SSL</option>
                                <option value="none" @selected(($orgSmtp['encryption'] ?? '') === 'none')>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">SMTP Username</label>
                            <input name="smtp_username" class="form-control form-control-sm" value="{{ $orgSmtp['username'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium mb-1">SMTP Password / Secret (Encrypted)</label>
                            <input name="smtp_password" type="password" class="form-control form-control-sm" placeholder="Leave blank to keep secret">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Sender Config</button>
                </form>

                @if($emailConfig)
                    <hr class="my-3">
                    <form onsubmit="testOrgEmail(event, '{{ $organisation->uuid }}')" class="row g-2 align-items-end">
                        <div class="col-8">
                            <label class="form-label small fw-medium mb-1">Send Test Email</label>
                            <input type="email" name="recipient_email" class="form-control form-control-sm" value="{{ auth()->user()?->primary_email }}" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-outline-dark btn-sm w-100">Send Test</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Recent Organisation Login Attempts</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Identifier</th>
                            <th>Method</th>
                            <th>Outcome</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLogs ?? [] as $log)
                            <tr>
                                <td class="font-monospace small">{{ $log->attempted_at }}</td>
                                <td class="small">{{ $log->identifier }}</td>
                                <td class="font-monospace small">{{ $log->provider_code }}</td>
                                <td class="small">{{ ucfirst($log->outcome) }}</td>
                                <td class="font-monospace small">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No login attempts recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showOrgFeedback(message, isError = false) {
    const el = document.getElementById('orgFeedback');
    el.className = `alert small mb-3 ${isError ? 'alert-danger' : 'alert-success'}`;
    el.textContent = message;
    el.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function parseOrgError(res) {
    try {
        const data = await res.json();
        if (data.errors) return Object.values(data.errors).flat().join(' ');
        return data.message || data.error || `Request failed (${res.status})`;
    } catch {
        return `Request failed (${res.status})`;
    }
}

async function toggleOrgMethod(orgUuid, code, nextState) {
    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/authentication/methods`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ methods: [{ provider_code: code, is_enabled: nextState }] })
    });
    if (res.ok) {
        window.location.reload();
        return;
    }
    showOrgFeedback(await parseOrgError(res), true);
}

async function saveOrgEmail(e, orgUuid) {
    e.preventDefault();
    const form = new FormData(e.target);
    const host = (form.get('smtp_host') || '').toString().trim();
    const password = (form.get('smtp_password') || '').toString();

    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/authentication/email`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            driver: form.get('driver'),
            sender_name: form.get('sender_name'),
            sender_email: form.get('sender_email'),
            smtp_settings: host ? {
                host,
                port: Number(form.get('smtp_port') || 587),
                encryption: form.get('smtp_encryption') || 'tls',
                username: form.get('smtp_username') || null
            } : null,
            secrets: password ? { password, app_password: password } : null
        })
    });
    if (res.ok) {
        window.location.reload();
        return;
    }
    showOrgFeedback(await parseOrgError(res), true);
}

async function testOrgEmail(e, orgUuid) {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/authentication/email/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ recipient_email: form.get('recipient_email') })
    });
    if (res.ok) {
        showOrgFeedback('Organisation test email sent successfully!');
        setTimeout(() => window.location.reload(), 900);
        return;
    }
    showOrgFeedback(await parseOrgError(res), true);
}
</script>
@endpush
