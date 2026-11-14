@extends('layouts.admin')

@section('title', 'Super Admin — Global Authentication & Email Settings')
@section('breadcrumb', 'Super Admin / Global Authentication')

@section('sidebar')
    <a class="nav-link active fw-semibold text-dark" href="{{ route('super-admin.auth-settings') }}">Global Auth &amp; Email</a>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Global Authentication Providers (Level 1)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Provider</th>
                            <th>Health</th>
                            <th class="text-end">Global Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $provider)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $provider->name }}</div>
                                    <code class="small text-muted">{{ $provider->code }}</code>
                                </td>
                                <td class="small">{{ ucfirst($provider->health_status) }}</td>
                                <td class="text-end">
                                    <button type="button"
                                        class="btn btn-sm {{ $provider->is_globally_enabled ? 'btn-success' : 'btn-outline-secondary' }}"
                                        onclick="toggleGlobalProvider('{{ $provider->code }}', {{ $provider->is_globally_enabled ? 'false' : 'true' }})">
                                        {{ $provider->is_globally_enabled ? 'Enabled' : 'Disabled' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border shadow-none">
            <div class="card-header bg-white fw-semibold">Platform Default Google Workspace Email Sender</div>
            <div class="card-body">
                <form id="platform-email-form" onsubmit="savePlatformEmail(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Sender Name</label>
                        <input type="text" name="sender_name" class="form-control form-control-sm" value="{{ $platformEmail->sender_name ?? 'TWSD Connect' }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Sender Email</label>
                        <input type="email" name="sender_email" class="form-control form-control-sm" value="{{ $platformEmail->sender_email ?? '' }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Workspace App Password / OAuth Token (Stored Encrypted)</label>
                        <input type="password" name="app_password" class="form-control form-control-sm" placeholder="Leave blank to keep existing secret">
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Platform Sender</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
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
@endsection

@push('scripts')
<script>
async function toggleGlobalProvider(code, nextState) {
    const res = await fetch(`/api/v1/super-admin/providers/${encodeURIComponent(code)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ is_globally_enabled: nextState })
    });
    if (res.ok) window.location.reload();
}

async function savePlatformEmail(e) {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await fetch('/api/v1/super-admin/providers/email/platform-default', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            driver: 'platform_google_workspace',
            sender_name: form.get('sender_name'),
            sender_email: form.get('sender_email'),
            secrets: form.get('app_password') ? { app_password: form.get('app_password') } : null
        })
    });
    if (res.ok) window.location.reload();
}
</script>
@endpush
