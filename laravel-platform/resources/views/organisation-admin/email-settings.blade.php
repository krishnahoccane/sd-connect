@extends('layouts.admin')

@section('title', $organisation->name . ' — Email & Authentication')
@section('breadcrumb', $organisation->name . ' / Email & Auth Policies')

@section('sidebar')
    <a class="nav-link text-secondary" href="{{ route('org.applications', $organisation->uuid) }}">Applications</a>
    <a class="nav-link active fw-semibold text-dark" href="{{ route('org.email-settings', $organisation->uuid) }}">Email &amp; Auth Policies</a>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Organisation Permitted Email Methods (Level 2)</div>
            <div class="card-body">
                @foreach($providers->whereIn('code', ['email_password', 'email_otp']) as $provider)
                    @php $enabled = $orgMethods->get($provider->code)?->is_enabled ?? false; @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-medium">{{ $provider->name }}</div>
                            <div class="small text-muted">Global status: {{ $provider->is_globally_enabled ? 'Permitted' : 'Blocked by Super Admin' }}</div>
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
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Organisation Email Sender</div>
            <div class="card-body">
                <form onsubmit="saveOrgEmail(event, '{{ $organisation->uuid }}')">
                    <div class="mb-2">
                        <label class="form-label small">Driver</label>
                        <select name="driver" class="form-select form-select-sm">
                            <option value="platform_google_workspace" @selected(($emailConfig->driver ?? '') === 'platform_google_workspace')>Platform Google Workspace Default</option>
                            <option value="org_smtp" @selected(($emailConfig->driver ?? '') === 'org_smtp')>Organisation Custom SMTP</option>
                            <option value="org_google_workspace" @selected(($emailConfig->driver ?? '') === 'org_google_workspace')>Organisation Google Workspace</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Sender Name</label><input name="sender_name" class="form-control form-control-sm" value="{{ $emailConfig->sender_name ?? $organisation->name }}" required></div>
                    <div class="mb-2"><label class="form-label small">Sender Email</label><input name="sender_email" type="email" class="form-control form-control-sm" value="{{ $emailConfig->sender_email ?? '' }}" required></div>
                    <div class="mb-2"><label class="form-label small">SMTP Host</label><input name="smtp_host" class="form-control form-control-sm" value="{{ $emailConfig->smtp_settings['host'] ?? '' }}"></div>
                    <div class="mb-3"><label class="form-label small">SMTP Password / Workspace Secret (Encrypted at rest)</label><input name="smtp_password" type="password" class="form-control form-control-sm"></div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Sender Config</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function toggleOrgMethod(orgUuid, code, nextState) {
    const res = await fetch(`/api/v1/organisation/${orgUuid}/authentication/methods`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ methods: [{ provider_code: code, is_enabled: nextState }] })
    });
    if (res.ok) window.location.reload();
}

async function saveOrgEmail(e, orgUuid) {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await fetch(`/api/v1/organisation/${orgUuid}/authentication/email`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            driver: form.get('driver'),
            sender_name: form.get('sender_name'),
            sender_email: form.get('sender_email'),
            smtp_settings: form.get('smtp_host') ? { host: form.get('smtp_host'), port: 587, encryption: 'tls' } : null,
            secrets: form.get('smtp_password') ? { password: form.get('smtp_password') } : null
        })
    });
    if (res.ok) window.location.reload();
}
</script>
@endpush
