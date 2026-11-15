@extends('layouts.admin')

@section('title', $organisation->name . ' — Applications')
@section('breadcrumb', $organisation->name . ' / Applications')

@section('sidebar')
    <a class="nav-link active fw-semibold text-dark" href="{{ route('org.applications', $organisation->uuid) }}">Applications</a>
    <a class="nav-link text-secondary" href="{{ route('org.email-settings', $organisation->uuid) }}">Email &amp; Auth Policies</a>
    @if(auth()->user()?->is_super_admin)
        <a class="nav-link text-secondary border-top mt-2 pt-2" href="{{ route('super-admin.auth-settings') }}">&larr; Super Admin Console</a>
    @endif
@endsection

@section('content')
@php
    $permittedProviders = $providers->filter(function ($provider) use ($orgMethods) {
        return $provider->is_globally_enabled && ($orgMethods->get($provider->code)?->is_enabled ?? false);
    });
@endphp

<div id="appFeedback" class="alert d-none small mb-3" role="alert"></div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-0">Registered Applications (Level 3)</h1>
        <div class="small text-muted">Each application selects from authentication methods permitted by {{ $organisation->name }}.</div>
    </div>
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
                    <th>Enabled Login Methods</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $app)
                    @php
                        $appMethodCodes = $app->authMethods->where('is_enabled', true)->pluck('provider_code')->values()->all();
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $app->name }}</div>
                            <div class="small text-muted">{{ $app->app_url }}</div>
                        </td>
                        <td class="font-monospace small">{{ $app->custom_domain ?? $app->twsd_subdomain }}</td>
                        <td class="small">{{ $app->oauthClientRegistry?->client_type ?? '—' }}</td>
                        <td class="font-monospace small">{{ $app->oauthClientRegistry?->passport_client_id ?? '—' }}</td>
                        <td>
                            @forelse($appMethodCodes as $code)
                                <span class="badge text-bg-light border font-monospace">{{ $code }}</span>
                            @empty
                                <span class="small text-muted">None</span>
                            @endforelse
                        </td>
                        <td class="small">{{ ucfirst($app->status) }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button"
                                    class="btn btn-outline-dark btn-sm"
                                    onclick='openAppMethodsModal(@json($app->uuid), @json($app->name), @json($appMethodCodes))'>
                                    Auth Methods
                                </button>
                                @if($app->oauthClientRegistry?->client_type === 'confidential')
                                    <button type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="rotateAppSecret('{{ $organisation->uuid }}', '{{ $app->uuid }}')">
                                        Rotate Secret
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No applications registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Create Application Modal -->
<div class="modal fade" id="createAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="createApp(event, '{{ $organisation->uuid }}')">
            <div class="modal-header">
                <h2 class="modal-title h6">Register OIDC Application</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-medium">Name</label>
                    <input name="name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Website URL</label>
                    <input name="app_url" type="url" class="form-control form-control-sm" placeholder="https://app.example.com" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Application Type</label>
                    <select name="application_type" class="form-select form-select-sm">
                        <option value="web_confidential">Confidential Web Application</option>
                        <option value="spa_public">Public SPA (PKCE S256)</option>
                        <option value="mobile_public">Public Mobile App (PKCE S256)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Exact Redirect URI (No Wildcards)</label>
                    <input name="redirect_uri" type="url" class="form-control form-control-sm" placeholder="https://app.example.com/callback" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Allowed Login Methods (From Level 2)</label>
                    @forelse($permittedProviders as $provider)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auth_methods[]" value="{{ $provider->code }}" id="createMethod_{{ $provider->code }}" @checked($provider->code === 'email_password')>
                            <label class="form-check-label small" for="createMethod_{{ $provider->code }}">
                                {{ $provider->name }} (<code>{{ $provider->code }}</code>)
                            </label>
                        </div>
                    @empty
                        <div class="small text-danger">No auth methods enabled at Organisation level. Enable at least one under Email &amp; Auth Policies first.</div>
                    @endforelse
                </div>
                <div id="secretOnceBanner" class="alert alert-warning d-none small mt-3 mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="createDoneBtn" class="btn btn-outline-secondary btn-sm d-none" onclick="window.location.reload()">Done &amp; Reload</button>
                <button type="submit" id="createSubmitBtn" class="btn btn-dark btn-sm">Create Application</button>
            </div>
        </form>
    </div>
</div>

<!-- Configure Application Auth Methods Modal (Level 3) -->
<div class="modal fade" id="appMethodsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" onsubmit="saveAppMethods(event, '{{ $organisation->uuid }}')">
            <div class="modal-header">
                <h2 class="modal-title h6" id="appMethodsTitle">Application Login Methods</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="appMethodsAppUuid" value="">
                <p class="small text-muted mb-2">
                    Select which methods permitted by {{ $organisation->name }} are active on this application&rsquo;s login screen:
                </p>
                @forelse($permittedProviders as $provider)
                    <div class="form-check mb-1">
                        <input class="form-check-input app-method-checkbox" type="checkbox" value="{{ $provider->code }}" id="editMethod_{{ $provider->code }}">
                        <label class="form-check-label small" for="editMethod_{{ $provider->code }}">
                            {{ $provider->name }} (<code>{{ $provider->code }}</code>)
                        </label>
                    </div>
                @empty
                    <div class="small text-danger">No providers are permitted by this organisation yet.</div>
                @endforelse
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark btn-sm">Save App Methods</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showAppFeedback(message, isError = false) {
    const el = document.getElementById('appFeedback');
    el.className = `alert small mb-3 ${isError ? 'alert-danger' : 'alert-warning'}`;
    el.textContent = message;
    el.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function parseAppError(res) {
    try {
        const data = await res.json();
        if (data.errors) return Object.values(data.errors).flat().join(' ');
        return data.message || data.error || `Request failed (${res.status})`;
    } catch {
        return `Request failed (${res.status})`;
    }
}

async function createApp(e, orgUuid) {
    e.preventDefault();
    const form = new FormData(e.target);
    const methods = form.getAll('auth_methods[]');

    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/applications`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            name: form.get('name'),
            app_url: form.get('app_url'),
            application_type: form.get('application_type'),
            redirect_uris: [form.get('redirect_uri')],
            auth_methods: methods
        })
    });
    const json = await res.json().catch(() => ({}));
    if (res.ok && json.data?.oauth_client?.client_secret_once) {
        const banner = document.getElementById('secretOnceBanner');
        banner.classList.remove('d-none');
        banner.textContent = `Client ID: ${json.data.oauth_client.client_id} | Secret (shown once, copy now): ${json.data.oauth_client.client_secret_once}`;
        document.getElementById('createSubmitBtn').classList.add('d-none');
        document.getElementById('createDoneBtn').classList.remove('d-none');
    } else if (res.ok) {
        window.location.reload();
    } else {
        showAppFeedback(json.message || json.error || `Failed to create application (${res.status})`, true);
    }
}

function openAppMethodsModal(appUuid, appName, enabledCodes) {
    document.getElementById('appMethodsTitle').textContent = `Login Methods — ${appName}`;
    document.getElementById('appMethodsAppUuid').value = appUuid;
    document.querySelectorAll('.app-method-checkbox').forEach((cb) => {
        cb.checked = enabledCodes.includes(cb.value);
    });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('appMethodsModal')).show();
}

async function saveAppMethods(e, orgUuid) {
    e.preventDefault();
    const appUuid = document.getElementById('appMethodsAppUuid').value;
    const selected = Array.from(document.querySelectorAll('.app-method-checkbox'))
        .filter((cb) => cb.checked)
        .map((cb) => cb.value);

    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/applications/${encodeURIComponent(appUuid)}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ auth_methods: selected })
    });
    if (res.ok) {
        window.location.reload();
        return;
    }
    showAppFeedback(await parseAppError(res), true);
}

async function rotateAppSecret(orgUuid, appUuid) {
    const res = await fetch(`/org/${encodeURIComponent(orgUuid)}/applications/${encodeURIComponent(appUuid)}/rotate-secret`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    });
    if (res.ok) {
        const json = await res.json();
        showAppFeedback(`New Client Secret (copy now — shown once): ${json.data.client_secret_once}`, false);
        return;
    }
    showAppFeedback(await parseAppError(res), true);
}
</script>
@endpush
