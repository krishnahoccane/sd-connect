<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TWSD Connect — Installation Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --twsd-primary: #0F172A;
            --twsd-accent: #2563EB;
            --twsd-border: #E2E8F0;
        }
        body {
            background-color: #F8FAFC;
            color: #0F172A;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .installer-container {
            max-width: 860px;
            margin: 2.5rem auto;
            padding: 0 1rem;
        }
        .installer-card {
            background: #FFFFFF;
            border: 1px solid var(--twsd-border);
            border-radius: 10px;
        }
        .stepper {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }
        .step-pill {
            border: 1px solid var(--twsd-border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            background: #F8FAFC;
            transition: all 0.15s ease;
        }
        .step-pill.active {
            border-color: var(--twsd-primary);
            background: #FFFFFF;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .step-pill.completed {
            border-color: #16A34A;
            background: #F0FDF4;
        }
        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #E2E8F0;
            color: #334155;
        }
        .step-pill.active .step-num {
            background: var(--twsd-primary);
            color: #FFFFFF;
        }
        .step-pill.completed .step-num {
            background: #16A34A;
            color: #FFFFFF;
        }
        .btn-brand {
            background-color: var(--twsd-primary);
            border-color: var(--twsd-primary);
            color: #FFFFFF;
        }
        .btn-brand:hover:not(:disabled) {
            background-color: #1E293B;
            border-color: #1E293B;
            color: #FFFFFF;
        }
        .btn-brand:disabled {
            opacity: 0.55;
            color: #FFFFFF;
        }
        .check-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.5rem;
        }
        .check-item {
            border: 1px solid var(--twsd-border);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rule-item {
            font-size: 0.8rem;
            color: #64748B;
        }
        .rule-item.met {
            color: #15803D;
            font-weight: 600;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
    </style>
</head>
<body>
<div class="installer-container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0">TWSD Connect</h1>
            <p class="text-secondary small mb-0">Enterprise Multi-Tenant SSO &amp; OIDC Platform Installation</p>
        </div>
        <span class="badge text-bg-light border mono">v12 · Setup Wizard</span>
    </div>

    <div class="stepper mb-4" id="stepper">
        <div class="step-pill active" data-step-indicator="1">
            <div class="d-flex align-items-center gap-2">
                <span class="step-num">1</span>
                <div>
                    <div class="small fw-semibold">Requirements</div>
                    <div class="text-secondary" style="font-size: 0.72rem;">PHP, extensions &amp; paths</div>
                </div>
            </div>
        </div>
        <div class="step-pill" data-step-indicator="2">
            <div class="d-flex align-items-center gap-2">
                <span class="step-num">2</span>
                <div>
                    <div class="small fw-semibold">Database &amp; Env</div>
                    <div class="text-secondary" style="font-size: 0.72rem;">MySQL 8, migrations &amp; keys</div>
                </div>
            </div>
        </div>
        <div class="step-pill" data-step-indicator="3">
            <div class="d-flex align-items-center gap-2">
                <span class="step-num">3</span>
                <div>
                    <div class="small fw-semibold">Super Admin</div>
                    <div class="text-secondary" style="font-size: 0.72rem;">Root identity &amp; lock</div>
                </div>
            </div>
        </div>
    </div>

    <div class="installer-card p-4">
        <div id="globalAlert" class="alert d-none small mb-3" role="alert"></div>

        {{-- STEP 1: REQUIREMENTS CHECK --}}
        <section id="step-1">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">1. Server Requirements Detection</h2>
                    <p class="text-secondary small mb-0">Verifies PHP 8.3+, cryptographic extensions, and writable directories before database configuration.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecheck">
                    Re-check Requirements
                </button>
            </div>

            <div class="mb-3 p-3 rounded border bg-light d-flex align-items-center justify-content-between" id="phpRuntimeBox">
                <div>
                    <div class="small fw-semibold">PHP Runtime Version</div>
                    <div class="text-secondary small">Minimum required: <span class="mono">{{ $checks['php']['minimum'] }}</span></div>
                </div>
                <div class="text-end">
                    <div class="mono fw-bold" id="phpCurrent">{{ $checks['php']['current'] }}</div>
                    <span class="badge {{ $checks['php']['passed'] ? 'text-bg-success' : 'text-bg-danger' }}" id="phpStatusBadge">
                        {{ $checks['php']['passed'] ? 'Supported' : 'Upgrade Required' }}
                    </span>
                </div>
            </div>

            <h3 class="small fw-bold text-uppercase text-secondary mb-2">PHP Extensions</h3>
            <div class="check-grid mb-3" id="extensionsGrid">
                @foreach($checks['extensions'] as $ext)
                    <div class="check-item">
                        <span class="mono">{{ $ext['name'] }}{{ $ext['required'] ? '' : ' (opt)' }}</span>
                        <span class="badge {{ $ext['loaded'] ? 'text-bg-success' : ($ext['required'] ? 'text-bg-danger' : 'text-bg-warning') }}">
                            {{ $ext['loaded'] ? 'Loaded' : 'Missing' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <h3 class="small fw-bold text-uppercase text-secondary mb-2">Directory &amp; Environment Permissions</h3>
            <div class="check-grid mb-3" id="permissionsGrid">
                @foreach($checks['permissions'] as $perm)
                    <div class="check-item">
                        <span class="mono">{{ $perm['path'] }}</span>
                        <span class="badge {{ $perm['writable'] ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $perm['writable'] ? 'Writable' : 'Read-only' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <h3 class="small fw-bold text-uppercase text-secondary mb-2">Cryptographic Capabilities</h3>
            <div class="list-group mb-4" id="securityList">
                @foreach($checks['security'] as $sec)
                    <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                        <div>
                            <div class="small fw-semibold">{{ $sec['check'] }}</div>
                            <div class="text-secondary" style="font-size: 0.78rem;">{{ $sec['detail'] }}</div>
                        </div>
                        <span class="badge {{ $sec['passed'] ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $sec['passed'] ? 'Pass' : 'Fail' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <span class="small {{ $checks['ready'] ? 'text-success fw-semibold' : 'text-danger fw-semibold' }}" id="readySummary">
                    {{ $checks['ready'] ? 'All mandatory system checks passed.' : 'Resolve failing checks above before proceeding.' }}
                </span>
                <button type="button" class="btn btn-brand" id="btnToStep2" {{ $checks['ready'] ? '' : 'disabled' }}>
                    Continue to Database Setup
                </button>
            </div>
        </section>

        {{-- STEP 2: DATABASE & ENV CONFIGURATION --}}
        <section id="step-2" class="d-none">
            <div class="mb-3">
                <h2 class="h5 fw-bold mb-1">2. Database &amp; Environment Setup</h2>
                <p class="text-secondary small mb-0">Verify MySQL 8 connectivity, persist <code class="mono">.env</code>, generate Passport &amp; OIDC RS256 keys, run migrations, and seed RBAC roles.</p>
            </div>

            <form id="dbForm" novalidate>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="app_url" class="form-label small fw-semibold">Public Platform URL (<code class="mono">APP_URL</code>)</label>
                        <input type="url" class="form-control mono" id="app_url" name="app_url" value="{{ $defaults['app_url'] }}" required>
                    </div>

                    <div class="col-md-8">
                        <label for="db_host" class="form-label small fw-semibold">MySQL / MariaDB Host</label>
                        <input type="text" class="form-control mono" id="db_host" name="db_host" value="{{ $defaults['db_host'] }}" required>
                        <div class="form-text" style="font-size: 0.75rem;">Use <code class="mono">localhost</code> on cPanel (UNIX socket) or <code class="mono">127.0.0.1</code> / remote IP for TCP.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="db_port" class="form-label small fw-semibold">Port</label>
                        <input type="number" class="form-control mono" id="db_port" name="db_port" value="{{ $defaults['db_port'] }}" min="1" max="65535" required>
                    </div>

                    <div class="col-md-6">
                        <label for="db_database" class="form-label small fw-semibold">Database Name</label>
                        <input type="text" class="form-control mono" id="db_database" name="db_database" value="{{ $defaults['db_database'] }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="db_username" class="form-label small fw-semibold">Database Username</label>
                        <input type="text" class="form-control mono" id="db_username" name="db_username" value="{{ $defaults['db_username'] }}" required>
                    </div>

                    <div class="col-md-7">
                        <label for="db_password" class="form-label small fw-semibold">Database Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control mono" id="db_password" name="db_password" autocomplete="new-password" placeholder="Leave blank if using passwordless local socket/user">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="db_password">Show</button>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label for="db_socket" class="form-label small fw-semibold">UNIX Socket <span class="text-secondary fw-normal">(optional)</span></label>
                        <input type="text" class="form-control mono" id="db_socket" name="db_socket" value="{{ $defaults['db_socket'] ?? '' }}" placeholder="Auto-detected (e.g. /var/lib/mysql/mysql.sock)">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-link btn-sm px-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#redisCollapse">
                            + Redis Cache &amp; Session Settings (optional)
                        </button>
                        <div class="collapse mt-2" id="redisCollapse">
                            <div class="card card-body bg-light border">
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label for="redis_host" class="form-label small">Redis Host</label>
                                        <input type="text" class="form-control form-control-sm mono" id="redis_host" name="redis_host" value="{{ $defaults['redis_host'] }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="redis_port" class="form-label small">Redis Port</label>
                                        <input type="number" class="form-control form-control-sm mono" id="redis_port" name="redis_port" value="{{ $defaults['redis_port'] }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="redis_password" class="form-label small">Redis Password</label>
                                        <input type="password" class="form-control form-control-sm mono" id="redis_password" name="redis_password" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="dbTestStatus" class="alert d-none small mt-3 mb-0"></div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                    <button type="button" class="btn btn-outline-secondary" id="btnBackStep1">Back</button>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" id="btnTestDb">Test Database Connection</button>
                        <button type="submit" class="btn btn-brand" id="btnMigrate">Save Config, Migrate &amp; Seed</button>
                    </div>
                </div>
            </form>
        </section>

        {{-- STEP 3: SUPER ADMIN CREATION --}}
        <section id="step-3" class="d-none">
            <div class="mb-3">
                <h2 class="h5 fw-bold mb-1">3. Create Platform Super Admin</h2>
                <p class="text-secondary small mb-0">Creates the initial Argon2id Super Admin account, optional first organisation, and permanently locks <code class="mono">/install</code>.</p>
            </div>

            <form id="adminForm" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="admin_name" class="form-label small fw-semibold">Display Name</label>
                        <input type="text" class="form-control" id="admin_name" name="name" placeholder="Platform Super Admin" required>
                    </div>
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label small fw-semibold">Super Admin Email</label>
                        <input type="email" class="form-control mono" id="admin_email" name="email" placeholder="admin@connect.twsd.me" required>
                    </div>

                    <div class="col-md-6">
                        <label for="admin_password" class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="admin_password" name="password" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="admin_password">Show</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="admin_password_confirmation" class="form-label small fw-semibold">Confirm Password</label>
                        <input type="password" class="form-control" id="admin_password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded border bg-light">
                            <div class="small fw-semibold mb-2">Super Admin Password Policy (Argon2id)</div>
                            <div class="row g-1" id="passwordChecklist">
                                <div class="col-sm-6 rule-item" data-rule="length">• At least 12 characters</div>
                                <div class="col-sm-6 rule-item" data-rule="upper">• At least one uppercase letter</div>
                                <div class="col-sm-6 rule-item" data-rule="lower">• At least one lowercase letter</div>
                                <div class="col-sm-6 rule-item" data-rule="digit">• At least one number</div>
                                <div class="col-sm-6 rule-item" data-rule="symbol">• At least one symbol (!@#$%^&amp;*)</div>
                                <div class="col-sm-6 rule-item" data-rule="match">• Passwords match</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 pt-2 border-top">
                        <div class="small fw-semibold mb-1">Initial Organisation (Optional)</div>
                        <p class="text-secondary mb-2" style="font-size: 0.78rem;">Bootstrap your first tenant organisation immediately or create organisations later from the Super Admin Console.</p>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <input type="text" class="form-control form-control-sm" id="organisation_name" name="organisation_name" placeholder="Organisation Name (e.g. TWSD Enterprise)">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm mono" id="organisation_slug" name="organisation_slug" placeholder="Slug (e.g. twsd)">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <button type="button" class="btn btn-outline-secondary" id="btnBackStep2">Back</button>
                    <button type="submit" class="btn btn-brand" id="btnFinishInstall" disabled>
                        Create Super Admin &amp; Lock Installer
                    </button>
                </div>
            </form>
        </section>

        {{-- STEP 4: COMPLETION --}}
        <section id="step-complete" class="d-none">
            <div class="alert alert-success mb-3">
                <div class="fw-bold">TWSD Connect Installation Complete</div>
                <div class="small mt-1">The platform database is migrated, Passport &amp; OIDC signing keys are active, and <code class="mono">/install</code> is now permanently locked via <code class="mono">storage/app/twsd_installed.lock</code>.</div>
            </div>

            <dl class="row small mb-4">
                <dt class="col-sm-4 text-secondary">Super Admin Email</dt>
                <dd class="col-sm-8 mono fw-semibold" id="summaryEmail">—</dd>
                <dt class="col-sm-4 text-secondary">Super Admin UUID</dt>
                <dd class="col-sm-8 mono" id="summaryUuid">—</dd>
                <dt class="col-sm-4 text-secondary">Initial Organisation UUID</dt>
                <dd class="col-sm-8 mono" id="summaryOrgUuid">None (created later)</dd>
            </dl>

            <div class="d-flex flex-wrap gap-2">
                <a href="/" class="btn btn-brand">Open Identity Portal</a>
                <a href="/super-admin/auth-settings" class="btn btn-outline-secondary">Open Super Admin Auth Settings</a>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let currentStep = 1;

    const showAlert = (type, msg) => {
        const el = document.getElementById('globalAlert');
        el.className = `alert alert-${type} small mb-3`;
        el.textContent = msg;
        el.classList.remove('d-none');
    };

    const clearAlert = () => {
        const el = document.getElementById('globalAlert');
        el.classList.add('d-none');
    };

    const goToStep = (step) => {
        clearAlert();
        currentStep = step;
        ['step-1', 'step-2', 'step-3', 'step-complete'].forEach((id) => {
            document.getElementById(id).classList.add('d-none');
        });
        document.getElementById(step === 4 ? 'step-complete' : `step-${step}`).classList.remove('d-none');

        document.querySelectorAll('[data-step-indicator]').forEach((el) => {
            const num = Number(el.getAttribute('data-step-indicator'));
            el.classList.remove('active', 'completed');
            if (num < step) el.classList.add('completed');
            else if (num === step) el.classList.add('active');
        });
    };

    const apiRequest = async (url, method = 'GET', body = null) => {
        const opts = {
            method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        };
        if (body !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
            throw new Error(firstError || json.message || `Request failed (${res.status})`);
        }
        return json;
    };

    const renderRequirements = (checks) => {
        document.getElementById('phpCurrent').textContent = checks.php.current;
        const phpBadge = document.getElementById('phpStatusBadge');
        phpBadge.textContent = checks.php.passed ? 'Supported' : 'Upgrade Required';
        phpBadge.className = `badge ${checks.php.passed ? 'text-bg-success' : 'text-bg-danger'}`;

        document.getElementById('extensionsGrid').innerHTML = checks.extensions.map((ext) => `
            <div class="check-item">
                <span class="mono">${ext.name}${ext.required ? '' : ' (opt)'}</span>
                <span class="badge ${ext.loaded ? 'text-bg-success' : (ext.required ? 'text-bg-danger' : 'text-bg-warning')}">
                    ${ext.loaded ? 'Loaded' : 'Missing'}
                </span>
            </div>
        `).join('');

        document.getElementById('permissionsGrid').innerHTML = checks.permissions.map((perm) => `
            <div class="check-item">
                <span class="mono">${perm.path}</span>
                <span class="badge ${perm.writable ? 'text-bg-success' : 'text-bg-danger'}">
                    ${perm.writable ? 'Writable' : 'Read-only'}
                </span>
            </div>
        `).join('');

        document.getElementById('securityList').innerHTML = checks.security.map((sec) => `
            <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                <div>
                    <div class="small fw-semibold">${sec.check}</div>
                    <div class="text-secondary" style="font-size: 0.78rem;">${sec.detail}</div>
                </div>
                <span class="badge ${sec.passed ? 'text-bg-success' : 'text-bg-danger'}">${sec.passed ? 'Pass' : 'Fail'}</span>
            </div>
        `).join('');

        const btnNext = document.getElementById('btnToStep2');
        const summary = document.getElementById('readySummary');
        btnNext.disabled = !checks.ready;
        summary.textContent = checks.ready
            ? 'All mandatory system checks passed.'
            : 'Resolve failing checks above before proceeding.';
        summary.className = `small ${checks.ready ? 'text-success fw-semibold' : 'text-danger fw-semibold'}`;
    };

    document.getElementById('btnRecheck').addEventListener('click', async () => {
        clearAlert();
        try {
            const payload = await apiRequest('/install/requirements', 'GET');
            renderRequirements(payload.data);
        } catch (err) {
            showAlert('danger', err.message);
        }
    });

    document.getElementById('btnToStep2').addEventListener('click', () => goToStep(2));
    document.getElementById('btnBackStep1').addEventListener('click', () => goToStep(1));
    document.getElementById('btnBackStep2').addEventListener('click', () => goToStep(2));

    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.getAttribute('data-toggle-password'));
            const show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    const getDbPayload = () => ({
        app_url: document.getElementById('app_url').value.trim(),
        db_host: document.getElementById('db_host').value.trim(),
        db_port: Number(document.getElementById('db_port').value),
        db_socket: document.getElementById('db_socket').value.trim() || null,
        db_database: document.getElementById('db_database').value.trim(),
        db_username: document.getElementById('db_username').value.trim(),
        db_password: document.getElementById('db_password').value,
        redis_host: document.getElementById('redis_host').value.trim(),
        redis_port: Number(document.getElementById('redis_port').value || 6379),
        redis_password: document.getElementById('redis_password').value,
    });

    document.getElementById('btnTestDb').addEventListener('click', async () => {
        clearAlert();
        const box = document.getElementById('dbTestStatus');
        box.className = 'alert alert-info small mt-3 mb-0';
        box.textContent = 'Testing MySQL / MariaDB PDO connection...';
        box.classList.remove('d-none');

        try {
            const payload = await apiRequest('/install/database/test', 'POST', getDbPayload());
            box.className = 'alert alert-success small mt-3 mb-0';
            const engineLabel = payload.data.engine === 'mariadb' ? 'MariaDB' : 'MySQL';
            const socketNote = payload.data.resolved_socket ? ` via UNIX socket (${payload.data.resolved_socket})` : '';
            box.textContent = `Connection verified (${engineLabel} ${payload.data.server_version}${socketNote}).`;
        } catch (err) {
            box.className = 'alert alert-danger small mt-3 mb-0';
            box.textContent = err.message;
        }
    });

    document.getElementById('dbForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        clearAlert();
        const btn = document.getElementById('btnMigrate');
        btn.disabled = true;
        btn.textContent = 'Writing .env & Migrating...';
        try {
            const payload = await apiRequest('/install/database/migrate', 'POST', getDbPayload());
            const box = document.getElementById('dbTestStatus');
            box.className = 'alert alert-success small mt-3 mb-0';
            const engineLabel = payload.data.engine === 'mariadb' ? 'MariaDB' : 'MySQL';
            box.textContent = `Migrated & seeded successfully (${engineLabel} ${payload.data.mysql_version}).`;
            box.classList.remove('d-none');
            goToStep(3);
        } catch (err) {
            showAlert('danger', err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Config, Migrate & Seed';
        }
    });

    const pwdInput = document.getElementById('admin_password');
    const confirmInput = document.getElementById('admin_password_confirmation');
    const finishBtn = document.getElementById('btnFinishInstall');

    const updatePasswordChecklist = () => {
        const pwd = pwdInput.value;
        const confirm = confirmInput.value;
        const rules = {
            length: pwd.length >= 12,
            upper: /[A-Z]/.test(pwd),
            lower: /[a-z]/.test(pwd),
            digit: /[0-9]/.test(pwd),
            symbol: /[^A-Za-z0-9]/.test(pwd),
            match: pwd.length > 0 && pwd === confirm,
        };

        let allMet = true;
        Object.entries(rules).forEach(([key, met]) => {
            const el = document.querySelector(`[data-rule="${key}"]`);
            el.classList.toggle('met', met);
            if (!met) allMet = false;
        });

        finishBtn.disabled = !allMet;
    };

    pwdInput.addEventListener('input', updatePasswordChecklist);
    confirmInput.addEventListener('input', updatePasswordChecklist);

    document.getElementById('organisation_name').addEventListener('input', (e) => {
        const slugInput = document.getElementById('organisation_slug');
        if (!slugInput.dataset.touched) {
            slugInput.value = e.target.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
    document.getElementById('organisation_slug').addEventListener('input', (e) => {
        e.target.dataset.touched = '1';
    });

    document.getElementById('adminForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        clearAlert();
        finishBtn.disabled = true;
        finishBtn.textContent = 'Creating Super Admin...';

        try {
            const payload = await apiRequest('/install/super-admin', 'POST', {
                name: document.getElementById('admin_name').value.trim(),
                email: document.getElementById('admin_email').value.trim(),
                password: pwdInput.value,
                password_confirmation: confirmInput.value,
                organisation_name: document.getElementById('organisation_name').value.trim() || null,
                organisation_slug: document.getElementById('organisation_slug').value.trim() || null,
            });

            document.getElementById('summaryEmail').textContent = payload.data.super_admin_email;
            document.getElementById('summaryUuid').textContent = payload.data.super_admin_uuid;
            document.getElementById('summaryOrgUuid').textContent = payload.data.organisation_uuid || 'None (created later)';
            goToStep(4);
        } catch (err) {
            showAlert('danger', err.message);
            updatePasswordChecklist();
        } finally {
            finishBtn.textContent = 'Create Super Admin & Lock Installer';
        }
    });
})();
</script>
</body>
</html>
