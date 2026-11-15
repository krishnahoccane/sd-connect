import { useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Database,
  FileCode2,
  Lock,
  Server,
  ShieldCheck,
  UserPlus,
  XCircle,
} from 'lucide-react';
import type { EnvironmentInfo } from '../types';

interface InstallerPreviewViewProps {
  env: EnvironmentInfo | null;
  onInspectFile: (path: string) => void;
}

const REQUIRED_EXTENSIONS = [
  'openssl',
  'pdo_mysql',
  'mbstring',
  'xml',
  'bcmath',
  'intl',
  'gmp',
  'sodium',
  'ctype',
  'fileinfo',
  'json',
  'zip',
];

const WRITABLE_PATHS = [
  'storage',
  'storage/app',
  'storage/framework',
  'storage/logs',
  'bootstrap/cache',
  '.env',
];

const LARAVEL_INSTALLER_FILES = [
  { label: 'Blade Wizard UI', path: 'resources/views/installer/wizard.blade.php' },
  { label: 'InstallationController', path: 'app/Http/Controllers/Installer/InstallationController.php' },
  { label: 'InstallationCheckService', path: 'app/Modules/Installation/Services/InstallationCheckService.php' },
  { label: 'InstallationExecutionService', path: 'app/Modules/Installation/Services/InstallationExecutionService.php' },
  { label: 'EnvFileWriter', path: 'app/Modules/Installation/Services/EnvFileWriter.php' },
  { label: 'EnsureNotInstalled Middleware', path: 'app/Http/Middleware/EnsureNotInstalled.php' },
  { label: 'Installer Routes', path: 'routes/installer.php' },
  { label: 'PHPUnit Wizard Suite', path: 'tests/Feature/InstallationWizardTest.php' },
];

export function InstallerPreviewView({ env, onInspectFile }: InstallerPreviewViewProps) {
  const [step, setStep] = useState<1 | 2 | 3 | 4>(1);
  const [simulateTargetHostReady, setSimulateTargetHostReady] = useState(true);

  const [appUrl, setAppUrl] = useState('https://connect.twsd.me');
  const [dbHost, setDbHost] = useState('127.0.0.1');
  const [dbPort, setDbPort] = useState('3306');
  const [dbDatabase, setDbDatabase] = useState('twsd_connect');
  const [dbUsername, setDbUsername] = useState('twsd_user');
  const [dbPassword, setDbPassword] = useState('');
  const [redisHost, setRedisHost] = useState('127.0.0.1');
  const [redisPort, setRedisPort] = useState('6379');
  const [showDbPassword, setShowDbPassword] = useState(false);
  const [dbTested, setDbTested] = useState(false);
  const [dbTestError, setDbTestError] = useState<string | null>(null);

  const [adminName, setAdminName] = useState('Platform Super Admin');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPassword, setAdminPassword] = useState('');
  const [adminConfirm, setAdminConfirm] = useState('');
  const [orgName, setOrgName] = useState('TWSD Enterprise');
  const [orgSlug, setOrgSlug] = useState('twsd-enterprise');
  const [showAdminPassword, setShowAdminPassword] = useState(false);

  const pwdRules = {
    length: adminPassword.length >= 12,
    upper: /[A-Z]/.test(adminPassword),
    lower: /[a-z]/.test(adminPassword),
    digit: /[0-9]/.test(adminPassword),
    symbol: /[^A-Za-z0-9]/.test(adminPassword),
    match: adminPassword.length > 0 && adminPassword === adminConfirm,
  };
  const passwordValid = Object.values(pwdRules).every(Boolean);
  const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminEmail.trim());
  const canCreateAdmin = adminName.trim().length > 0 && emailValid && passwordValid;

  const handleTestDbPreview = () => {
    if (!dbHost.trim() || !dbDatabase.trim() || !dbUsername.trim()) {
      setDbTested(false);
      setDbTestError('MySQL host, database name, and username are required.');
      return;
    }
    setDbTestError(null);
    setDbTested(true);
  };

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-amber-300/80 bg-amber-50 p-4 text-xs text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
        <div className="flex items-start gap-3">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-700 dark:text-amber-400" />
          <div>
            <div className="font-semibold">Interactive Preview of Laravel 12 Web Installer (/install)</div>
            <p className="mt-1 leading-relaxed text-amber-900/90 dark:text-amber-200/90">
              This workspace mirrors the real Bootstrap 5 + Blade wizard at{' '}
              <code className="font-mono">/laravel-platform/resources/views/installer/wizard.blade.php</code>. Because this container runs{' '}
              <strong>{env?.runtime ?? 'Node.js'}</strong> without PHP 8.3+ or MySQL 8, database PDO verification, migrations, and Argon2id Super Admin persistence execute inside the Laravel runtime when deployed on PHP 8.3+ or Docker Compose.
            </p>
          </div>
        </div>
      </div>

      {/* Stepper */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        {[
          { num: 1, title: '1. Requirements Detection', desc: 'PHP 8.3+, extensions & paths', icon: Server },
          { num: 2, title: '2. Database & Environment', desc: 'MySQL 8, migrations & OIDC keys', icon: Database },
          { num: 3, title: '3. Super Admin Setup', desc: 'Argon2id root user & lockfile', icon: UserPlus },
        ].map((item) => {
          const Icon = item.icon;
          const active = step === item.num;
          const completed = step > item.num;
          return (
            <button
              key={item.num}
              type="button"
              onClick={() => {
                if (item.num <= step || (item.num === 2 && simulateTargetHostReady)) {
                  setStep(item.num as 1 | 2 | 3);
                }
              }}
              className={`flex items-center gap-3 rounded-lg border p-3.5 text-left transition-colors ${
                active
                  ? 'border-slate-900 bg-white shadow-xs dark:border-blue-500 dark:bg-slate-900'
                  : completed
                    ? 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-800 dark:bg-emerald-950/30'
                    : 'border-slate-200 bg-white/70 dark:border-slate-800 dark:bg-slate-900/60'
              }`}
            >
              <div
                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-md ${
                  completed
                    ? 'bg-emerald-600 text-white'
                    : active
                      ? 'bg-slate-900 text-white dark:bg-blue-600'
                      : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                }`}
              >
                <Icon className="h-4 w-4" />
              </div>
              <div className="min-w-0">
                <div className="truncate text-xs font-semibold">{item.title}</div>
                <div className="truncate text-[11px] text-slate-500 dark:text-slate-400">{item.desc}</div>
              </div>
            </button>
          );
        })}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Main Wizard Card */}
        <section className="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
          {step === 1 && (
            <div className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
                <div>
                  <h2 className="text-sm font-semibold">Step 1: System Requirements Detection</h2>
                  <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Backed by <code className="font-mono">InstallationCheckService::inspect()</code>
                  </p>
                </div>
                <label className="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs dark:border-slate-700">
                  <input
                    type="checkbox"
                    checked={simulateTargetHostReady}
                    onChange={(e) => setSimulateTargetHostReady(e.target.checked)}
                    className="rounded border-slate-300 text-blue-600"
                  />
                  <span>Preview Target PHP 8.3+ Host Ready</span>
                </label>
              </div>

              <div className="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950/60">
                <div>
                  <div className="font-semibold">PHP Runtime (min 8.3.0)</div>
                  <div className="text-slate-500 dark:text-slate-400">
                    Current sandbox: {env?.runtime ?? 'Node.js'} · Target host check:{' '}
                    {simulateTargetHostReady ? 'PHP 8.3.14' : 'PHP 8.1.x (Failing)'}
                  </div>
                </div>
                {simulateTargetHostReady ? (
                  <span className="inline-flex items-center gap-1 font-mono font-medium text-emerald-700 dark:text-emerald-400">
                    <CheckCircle2 className="h-4 w-4" /> Ready
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 font-mono font-medium text-red-700 dark:text-red-400">
                    <XCircle className="h-4 w-4" /> Blocked
                  </span>
                )}
              </div>

              <div>
                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                  Required PHP Extensions (12 mandatory + Redis)
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  {REQUIRED_EXTENSIONS.map((ext) => {
                    const ok = simulateTargetHostReady || (ext !== 'sodium' && ext !== 'gmp');
                    return (
                      <div
                        key={ext}
                        className="flex items-center justify-between rounded border border-slate-200 px-2.5 py-1.5 text-xs dark:border-slate-800"
                      >
                        <span className="font-mono">{ext}</span>
                        {ok ? (
                          <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                        ) : (
                          <XCircle className="h-3.5 w-3.5 text-red-600" />
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>

              <div>
                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                  Writable Directories &amp; Security Primitives
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {WRITABLE_PATHS.map((p) => (
                    <div
                      key={p}
                      className="flex items-center justify-between rounded border border-slate-200 px-2.5 py-1.5 text-xs dark:border-slate-800"
                    >
                      <span className="font-mono">{p}</span>
                      <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                    </div>
                  ))}
                </div>
                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                  {['Argon2id Hashing', 'OpenSSL RSA-2048', 'CSPRNG random_bytes()'].map((sec) => (
                    <div
                      key={sec}
                      className="flex items-center justify-between rounded border border-slate-200 px-2.5 py-1.5 text-xs dark:border-slate-800"
                    >
                      <span>{sec}</span>
                      <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                    </div>
                  ))}
                </div>
              </div>

              <div className="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-800">
                <span className={`text-xs font-medium ${simulateTargetHostReady ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'}`}>
                  {simulateTargetHostReady
                    ? 'All mandatory checks satisfied in target environment preview.'
                    : 'Installer blocks Step 2 until PHP 8.3+ and sodium/gmp pass.'}
                </span>
                <button
                  type="button"
                  disabled={!simulateTargetHostReady}
                  onClick={() => setStep(2)}
                  className="rounded-md bg-slate-900 px-4 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-45 dark:bg-blue-600 dark:hover:bg-blue-500"
                >
                  Continue to Database Setup
                </button>
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-4">
              <div className="border-b border-slate-200 pb-3 dark:border-slate-800">
                <h2 className="text-sm font-semibold">Step 2: Database &amp; Environment Configuration</h2>
                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                  Tests MySQL PDO connection, writes sanitized <code className="font-mono">.env</code> via{' '}
                  <code className="font-mono">EnvFileWriter</code>, runs migrations, generates Passport + OIDC RS256 keys, and seeds RBAC roles.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <label className="mb-1 block text-xs font-medium">Public Platform URL (APP_URL)</label>
                  <input
                    type="url"
                    value={appUrl}
                    onChange={(e) => setAppUrl(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">MySQL Host</label>
                  <input
                    type="text"
                    value={dbHost}
                    onChange={(e) => { setDbHost(e.target.value); setDbTested(false); }}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">MySQL Port</label>
                  <input
                    type="number"
                    value={dbPort}
                    onChange={(e) => { setDbPort(e.target.value); setDbTested(false); }}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Database Name</label>
                  <input
                    type="text"
                    value={dbDatabase}
                    onChange={(e) => { setDbDatabase(e.target.value); setDbTested(false); }}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Database Username</label>
                  <input
                    type="text"
                    value={dbUsername}
                    onChange={(e) => { setDbUsername(e.target.value); setDbTested(false); }}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="mb-1 block text-xs font-medium">Database Password</label>
                  <div className="flex gap-2">
                    <input
                      type={showDbPassword ? 'text' : 'password'}
                      value={dbPassword}
                      onChange={(e) => setDbPassword(e.target.value)}
                      placeholder="Protected against newline injection in EnvFileWriter"
                      className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                    />
                    <button
                      type="button"
                      onClick={() => setShowDbPassword((v) => !v)}
                      className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                      {showDbPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Redis Host</label>
                  <input
                    type="text"
                    value={redisHost}
                    onChange={(e) => setRedisHost(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Redis Port</label>
                  <input
                    type="number"
                    value={redisPort}
                    onChange={(e) => setRedisPort(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
              </div>

              {dbTestError && (
                <div className="rounded-md border border-red-300 bg-red-50 p-3 text-xs text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">
                  {dbTestError}
                </div>
              )}

              {dbTested && !dbTestError && (
                <div className="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-xs text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                  Preview validation passed. On a PHP 8.3+ host, <code className="font-mono">POST /install/database/test</code> connects via PDO and returns <code className="font-mono">SELECT VERSION()</code> before writing <code className="font-mono">.env</code>.
                </div>
              )}

              <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                  Back
                </button>
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={handleTestDbPreview}
                    className="rounded-md border border-blue-600 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/30"
                  >
                    Validate DB Form
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      handleTestDbPreview();
                      if (dbHost.trim() && dbDatabase.trim() && dbUsername.trim()) {
                        setStep(3);
                      }
                    }}
                    className="rounded-md bg-slate-900 px-4 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                  >
                    Proceed to Super Admin Setup
                  </button>
                </div>
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-4">
              <div className="border-b border-slate-200 pb-3 dark:border-slate-800">
                <h2 className="text-sm font-semibold">Step 3: Create Platform Super Admin</h2>
                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                  Enforces Argon2id 12+ character policy, assigns <code className="font-mono">super_admin</code> role, optionally creates the first organisation, and locks <code className="font-mono">/install</code>.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="mb-1 block text-xs font-medium">Display Name</label>
                  <input
                    type="text"
                    value={adminName}
                    onChange={(e) => setAdminName(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Super Admin Email</label>
                  <input
                    type="email"
                    value={adminEmail}
                    onChange={(e) => setAdminEmail(e.target.value)}
                    placeholder="admin@connect.twsd.me"
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Password</label>
                  <div className="flex gap-2">
                    <input
                      type={showAdminPassword ? 'text' : 'password'}
                      value={adminPassword}
                      onChange={(e) => setAdminPassword(e.target.value)}
                      className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950"
                    />
                    <button
                      type="button"
                      onClick={() => setShowAdminPassword((v) => !v)}
                      className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium dark:border-slate-700"
                    >
                      {showAdminPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Confirm Password</label>
                  <input
                    type={showAdminPassword ? 'text' : 'password'}
                    value={adminConfirm}
                    onChange={(e) => setAdminConfirm(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
              </div>

              <div className="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60">
                <div className="mb-2 text-xs font-semibold">Password Policy Verification</div>
                <div className="grid grid-cols-2 gap-1.5 text-xs sm:grid-cols-3">
                  {[
                    ['length', '12+ characters'],
                    ['upper', 'Uppercase letter'],
                    ['lower', 'Lowercase letter'],
                    ['digit', 'Number (0-9)'],
                    ['symbol', 'Special symbol'],
                    ['match', 'Passwords match'],
                  ].map(([key, label]) => {
                    const met = pwdRules[key as keyof typeof pwdRules];
                    return (
                      <div key={key} className={`flex items-center gap-1.5 ${met ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-slate-500'}`}>
                        {met ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5 text-slate-400" />}
                        <span>{label}</span>
                      </div>
                    );
                  })}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2 dark:border-slate-800">
                <div>
                  <label className="mb-1 block text-xs font-medium">Initial Organisation (Optional)</label>
                  <input
                    type="text"
                    value={orgName}
                    onChange={(e) => {
                      setOrgName(e.target.value);
                      setOrgSlug(
                        e.target.value
                          .toLowerCase()
                          .replace(/[^a-z0-9]+/g, '-')
                          .replace(/^-+|-+$/g, '')
                      );
                    }}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium">Organisation Slug</label>
                  <input
                    type="text"
                    value={orgSlug}
                    onChange={(e) => setOrgSlug(e.target.value)}
                    className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  />
                </div>
              </div>

              <div className="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                  Back
                </button>
                <button
                  type="button"
                  disabled={!canCreateAdmin}
                  onClick={() => setStep(4)}
                  className="rounded-md bg-slate-900 px-4 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-45 dark:bg-blue-600 dark:hover:bg-blue-500"
                >
                  Preview Super Admin &amp; Lock Completion
                </button>
              </div>
            </div>
          )}

          {step === 4 && (
            <div className="space-y-4">
              <div className="rounded-md border border-emerald-300 bg-emerald-50 p-4 text-xs text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                <div className="flex items-center gap-2 font-semibold">
                  <ShieldCheck className="h-4 w-4" />
                  <span>Installation Finalization Preview Ready</span>
                </div>
                <p className="mt-1 leading-relaxed">
                  On PHP 8.3+ / MySQL 8, submitting Step 3 creates the Argon2id Super Admin, records{' '}
                  <code className="font-mono">platform.installation_completed</code> in <code className="font-mono">audit_logs</code>, writes{' '}
                  <code className="font-mono">storage/app/twsd_installed.lock</code>, sets <code className="font-mono">TWSD_INSTALLER_ENABLED=false</code>, and locks all <code className="font-mono">/install/*</code> routes with HTTP 403.
                </p>
              </div>

              <dl className="divide-y divide-slate-100 text-xs dark:divide-slate-800">
                <div className="flex justify-between py-2">
                  <dt className="text-slate-500">Super Admin</dt>
                  <dd className="font-mono font-medium">{adminName} &lt;{adminEmail}&gt;</dd>
                </div>
                <div className="flex justify-between py-2">
                  <dt className="text-slate-500">Target MySQL Endpoint</dt>
                  <dd className="font-mono">{dbUsername}@{dbHost}:{dbPort}/{dbDatabase}</dd>
                </div>
                <div className="flex justify-between py-2">
                  <dt className="text-slate-500">Initial Organisation</dt>
                  <dd className="font-mono">{orgName ? `${orgName} (${orgSlug})` : 'None'}</dd>
                </div>
              </dl>

              <div className="flex justify-between border-t border-slate-200 pt-4 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                  Reset Preview
                </button>
                <button
                  type="button"
                  onClick={() => onInspectFile('resources/views/installer/wizard.blade.php')}
                  className="rounded-md bg-slate-900 px-4 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                >
                  Inspect Real Blade Wizard Source
                </button>
              </div>
            </div>
          )}
        </section>

        {/* Right Column: Security & Source Artifacts */}
        <aside className="space-y-4">
          <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-center gap-2 border-b border-slate-200 pb-2.5 text-xs font-semibold dark:border-slate-800">
              <Lock className="h-4 w-4 text-slate-500" />
              <span>Installer Security Controls</span>
            </div>
            <ul className="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
              <li>• Triple-check lock: <code className="font-mono">twsd_installed.lock</code>, <code className="font-mono">TWSD_INSTALLER_ENABLED=false</code>, and DB <code className="font-mono">is_super_admin</code> lookup.</li>
              <li>• Pre-install <code className="font-mono">AppServiceProvider</code> falls back session/cache to <code className="font-mono">file</code> until Redis is configured.</li>
              <li>• <code className="font-mono">EnvFileWriter</code> blocks newline, CR, null-byte, and malformed key injection.</li>
              <li>• Argon2id hashing + 12+ char uppercase/lowercase/digit/symbol policy enforced server-side.</li>
            </ul>
          </div>

          <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-center gap-2 border-b border-slate-200 pb-2.5 text-xs font-semibold dark:border-slate-800">
              <FileCode2 className="h-4 w-4 text-slate-500" />
              <span>Laravel Installer Artifacts</span>
            </div>
            <div className="mt-2 divide-y divide-slate-100 text-xs dark:divide-slate-800">
              {LARAVEL_INSTALLER_FILES.map((file) => (
                <button
                  key={file.path}
                  type="button"
                  onClick={() => onInspectFile(file.path)}
                  className="flex w-full items-center justify-between py-2 text-left hover:text-blue-600 dark:hover:text-blue-400"
                >
                  <span className="truncate pr-2">{file.label}</span>
                  <span className="shrink-0 font-mono text-[11px] text-slate-400">Open</span>
                </button>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}
