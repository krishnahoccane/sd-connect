@extends('layouts.admin')

@section('title', $organisation->name . ' — Applications')
@section('breadcrumb', $organisation->name . ' / Applications')

@section('sidebar')
    <a class="nav-link active fw-semibold text-dark" href="{{ route('org.applications', $organisation->uuid) }}">Applications</a>
    <a class="nav-link text-secondary" href="{{ route('org.email-settings', $organisation->uuid) }}">Email &amp; Auth Policies</a>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Registered Applications</h1>
    <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#createAppModal">Register Application</button>
</div>

<div class="card border shadow-none">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Application</th>
                    <th>Domain</th>
                    <th>Client Type</th>
                    <th>Client ID</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $app)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $app->name }}</div>
                            <div class="small text-muted">{{ $app->app_url }}</div>
                        </td>
                        <td class="font-mono small">{{ $app->custom_domain ?? $app->twsd_subdomain }}</td>
                        <td class="small">{{ $app->oauthClientRegistry?->client_type ?? '—' }}</td>
                        <td class="font-monospace small">{{ $app->oauthClientRegistry?->passport_client_id ?? '—' }}</td>
                        <td class="small">{{ ucfirst($app->status) }}</td>
                        <td class="text-end">
                            @if($app->oauthClientRegistry?->client_type === 'confidential')
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateAppSecret('{{ $organisation->uuid }}', '{{ $app->uuid }}')">Rotate Secret</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No applications registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createAppModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="createApp(event, '{{ $organisation->uuid }}')">
            <div class="modal-header"><h2 class="modal-title h6">Register OIDC Application</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Website URL</label><input name="app_url" type="url" class="form-control form-control-sm" required></div>
                <div class="mb-2">
                    <label class="form-label small">Application Type</label>
                    <select name="application_type" class="form-select form-select-sm">
                        <option value="web_confidential">Confidential Web Application</option>
                        <option value="spa_public">Public SPA (PKCE S256)</option>
                        <option value="mobile_public">Public Mobile App (PKCE S256)</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small">Exact Redirect URI (No Wildcards)</label><input name="redirect_uri" type="url" class="form-control form-control-sm" required></div>
                <div id="secretOnceBanner" class="alert alert-warning d-none small mt-3 mb-0"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark btn-sm">Create Application</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function createApp(e, orgUuid) {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await fetch(`/api/v1/organisation/${orgUuid}/applications`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            name: form.get('name'),
            app_url: form.get('app_url'),
            application_type: form.get('application_type'),
            redirect_uris: [form.get('redirect_uri')]
        })
    });
    const json = await res.json();
    if (res.ok && json.data?.oauth_client?.client_secret_once) {
        const banner = document.getElementById('secretOnceBanner');
        banner.classList.remove('d-none');
        banner.textContent = `Client ID: ${json.data.oauth_client.client_id} | Secret (shown once): ${json.data.oauth_client.client_secret_once}`;
    } else if (res.ok) {
        window.location.reload();
    }
}

async function rotateAppSecret(orgUuid, appUuid) {
    const res = await fetch(`/api/v1/organisation/${orgUuid}/applications/${appUuid}/rotate-secret`, {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (res.ok) {
        alert(`New Secret (copy now, shown once): ${json.data.client_secret_once}`);
    }
}
</script>
@endpush
