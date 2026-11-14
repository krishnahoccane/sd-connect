import { useEffect, useState } from 'react';
import {
  AlertTriangle,
  Database,
  FileCode2,
  FolderGit2,
  KeyRound,
  LayoutGrid,
  Moon,
  Server,
  Shield,
  Sun,
} from 'lucide-react';
import type { EnvironmentInfo } from './types';
import { RepoExplorerView } from './components/RepoExplorerView';
import { SchemaView } from './components/SchemaView';
import { ProtocolLabView } from './components/ProtocolLabView';

type NavSection = 'overview' | 'repository' | 'schema' | 'protocol';

const PHASES = [
  { phase: 'Phase 1', title: 'Foundation, Laravel 12, Passport & OIDC Core', status: 'Source Complete + Audited (PHPUnit pending PHP host)' },
  { phase: 'Phase 2', title: 'MySQL 8 Schema, Central Identity, Organisations & RBAC', status: 'Source Complete + Audited (7 Migrations & Seeders ready)' },
  { phase: 'Phase 3', title: 'Application Registration & OAuth Client Lifecycle', status: 'Source Complete (OAuthClientService & ApplicationController)' },
  { phase: 'Phase 4', title: 'Email/Password & Email OTP Providers + Sender Isolation', status: 'Source Complete (OtpVerificationService & EmailConfigService)' },
  { phase: 'Phase 5', title: 'Super Admin & Organisation Admin Auth Consoles (Blade + BS5)', status: 'Source Complete (REST APIs + Blade Views + PHPUnit Tests)' },
  { phase: 'Phase 6', title: 'Firebase SMS & WhatsApp OTP (Webglit)', status: 'Not Implemented (Next)' },
  { phase: 'Phase 7', title: 'Google & Microsoft Sign-In, Identity Linking & JIT', status: 'Not Implemented' },
  { phase: 'Phase 8', title: 'Full Super Admin & Organisation Admin Consoles', status: 'Partially Implemented (Auth & Apps Complete)' },
  { phase: 'Phase 9', title: 'Security Hardening, Audit & Conformance Suite', status: 'Partially Implemented (6 PHPUnit Suites Written)' },
  { phase: 'Phase 10', title: 'Laravel SDK, Integration Docs & Production Deploy', status: 'Not Implemented' },
];

export default function App() {
  const [section, setSection] = useState<NavSection>('overview');
  const [repoTargetFile, setRepoTargetFile] = useState('DEVELOPMENT_STATUS.md');
  const [dark, setDark] = useState(false);
  const [env, setEnv] = useState<EnvironmentInfo | null>(null);

  useEffect(() => {
    document.documentElement.classList.toggle('dark', dark);
  }, [dark]);

  useEffect(() => {
    fetch('/api/environment')
      .then((r) => r.json())
      .then((data: EnvironmentInfo) => setEnv(data))
      .catch(() => setEnv(null));
  }, []);

  const openRepoFile = (filePath: string) => {
    setRepoTargetFile(filePath);
    setSection('repository');
  };

  const navItems: Array<{ id: NavSection; label: string; icon: typeof LayoutGrid }> = [
    { id: 'overview', label: 'Overview & Status', icon: LayoutGrid },
    { id: 'repository', label: 'Laravel 12 Source', icon: FolderGit2 },
    { id: 'schema', label: 'MySQL 8 Schema', icon: Database },
    { id: 'protocol', label: 'Policy & PKCE Lab', icon: KeyRound },
  ];

  const sectionTitle: Record<NavSection, string> = {
    overview: 'Platform Overview & Environment Audit',
    repository: 'Laravel 12 Repository Explorer',
    schema: 'MySQL 8 Multi-Tenant Schema',
    protocol: 'Policy Engine & PKCE S256 Lab',
  };

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex dark:border-slate-800 dark:bg-slate-900">
        <div className="flex h-14 items-center gap-2.5 border-b border-slate-200 px-5 dark:border-slate-800">
          <Shield className="h-5 w-5 text-blue-600 dark:text-blue-400" />
          <span className="text-sm font-bold tracking-tight whitespace-nowrap">TWSD Connect</span>
        </div>

        <nav className="flex-1 space-y-1 p-3" aria-label="Workspace navigation">
          {navItems.map((item) => {
            const Icon = item.icon;
            const active = section === item.id;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => setSection(item.id)}
                className={`flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-xs font-medium transition-colors ${
                  active
                    ? 'bg-slate-900 text-white dark:bg-blue-600 dark:text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'
                }`}
              >
                <Icon className="h-4 w-4 shrink-0" />
                <span className="whitespace-nowrap">{item.label}</span>
              </button>
            );
          })}
        </nav>

        <div className="border-t border-slate-200 p-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
          <div className="font-medium text-slate-700 dark:text-slate-300">Target Host</div>
          <div className="mt-0.5 font-mono text-[11px]">connect.twsd.me</div>
          <div className="mt-2 font-medium text-slate-700 dark:text-slate-300">Stack</div>
          <div className="mt-0.5">Laravel 12 · PHP 8.3+ · MySQL 8</div>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-between gap-8 border-b border-slate-200 bg-white px-6 dark:border-slate-800 dark:bg-slate-900">
          <div className="flex min-w-0 items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span className="font-semibold text-slate-900 md:hidden dark:text-slate-100">TWSD Connect</span>
            <span className="hidden md:inline">TWSD Connect</span>
            <span aria-hidden="true">/</span>
            <span className="truncate font-medium text-slate-900 dark:text-slate-100">
              {sectionTitle[section]}
            </span>
          </div>

          <div className="flex shrink-0 items-center gap-3">
            <button
              type="button"
              onClick={() => setDark((d) => !d)}
              aria-label="Toggle color theme"
              className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 whitespace-nowrap dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
              {dark ? <Sun className="h-3.5 w-3.5" /> : <Moon className="h-3.5 w-3.5" />}
              <span>{dark ? 'Light theme' : 'Dark theme'}</span>
            </button>
          </div>
        </header>

        <div className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-4 py-2 md:hidden dark:border-slate-800 dark:bg-slate-900">
          {navItems.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setSection(item.id)}
              className={`rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap ${
                section === item.id
                  ? 'bg-slate-900 text-white dark:bg-blue-600'
                  : 'text-slate-600 dark:text-slate-400'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>

        <main className="mx-auto w-full max-w-7xl flex-1 p-6">
          {section === 'overview' && (
            <div className="space-y-6">
              <div className="rounded-lg border border-amber-300/80 bg-amber-50 p-4 text-xs text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
                <div className="flex items-start gap-3">
                  <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-700 dark:text-amber-400" />
                  <div>
                    <div className="font-semibold">Sandbox Runtime Limitation Report</div>
                    <p className="mt-1 leading-relaxed text-amber-900/90 dark:text-amber-200/90">
                      This container provides <strong>{env?.runtime ?? 'Node.js'}</strong> and Nginx, without PHP 8.3+, Composer, MySQL 8, or Redis. In accordance with Section 1 and Section 20 of the architecture specification, complete Laravel 12 production source artifacts (migrations, models, Passport + OIDC Core layer, 3-level policy engine, middleware, seeders, Blade + Bootstrap 5 templates, and PHPUnit tests) have been generated inside <code className="font-mono">/laravel-platform</code> instead of pretending a React mockup is Laravel.
                    </p>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                  <div className="text-xs text-slate-500 dark:text-slate-400">MySQL 8 Platform Tables</div>
                  <div className="mt-1 font-mono text-2xl font-semibold tabular-nums">35</div>
                  <div className="mt-1 text-xs text-slate-500">Across 7 schema migrations</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                  <div className="text-xs text-slate-500 dark:text-slate-400">Auth Providers Seeded</div>
                  <div className="mt-1 font-mono text-2xl font-semibold tabular-nums">6</div>
                  <div className="mt-1 text-xs text-slate-500">Password, Email/SMS/WA OTP, Google, MS</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                  <div className="text-xs text-slate-500 dark:text-slate-400">Policy Hierarchy Levels</div>
                  <div className="mt-1 font-mono text-2xl font-semibold tabular-nums">3</div>
                  <div className="mt-1 text-xs text-slate-500">Global ∩ Org ∩ App ∩ Healthy</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                  <div className="text-xs text-slate-500 dark:text-slate-400">PHPUnit Execution</div>
                  <div className="mt-1 font-mono text-2xl font-semibold tabular-nums">Pending</div>
                  <div className="mt-1 text-xs text-slate-500">Requires PHP 8.3+ target host</div>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section className="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                  <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div className="flex items-center gap-2">
                      <Server className="h-4 w-4 text-slate-500" />
                      <h2 className="text-sm font-semibold">Environment Inspection</h2>
                    </div>
                    <button
                      type="button"
                      onClick={() => openRepoFile('INSTALLATION.md')}
                      className="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                      View INSTALLATION.md
                    </button>
                  </div>
                  <dl className="mt-3 divide-y divide-slate-100 text-xs dark:divide-slate-800">
                    <div className="flex justify-between py-2">
                      <dt className="text-slate-500 dark:text-slate-400">Container Runtime</dt>
                      <dd className="font-mono">{env?.runtime ?? 'Detecting...'} · {env?.os ?? ''}</dd>
                    </div>
                    <div className="flex justify-between py-2">
                      <dt className="text-slate-500 dark:text-slate-400">PHP 8.3+ / Composer</dt>
                      <dd className="font-medium text-red-700 dark:text-red-400">Not installed in sandbox</dd>
                    </div>
                    <div className="flex justify-between py-2">
                      <dt className="text-slate-500 dark:text-slate-400">MySQL 8 / Redis 7</dt>
                      <dd className="font-medium text-red-700 dark:text-red-400">Not installed in sandbox</dd>
                    </div>
                    <div className="flex justify-between py-2">
                      <dt className="text-slate-500 dark:text-slate-400">Laravel 12 Repository</dt>
                      <dd className="font-mono text-emerald-700 dark:text-emerald-400">/laravel-platform (Generated)</dd>
                    </div>
                  </dl>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                  <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div className="flex items-center gap-2">
                      <FileCode2 className="h-4 w-4 text-slate-500" />
                      <h2 className="text-sm font-semibold">Key Architecture Artifacts</h2>
                    </div>
                    <button
                      type="button"
                      onClick={() => openRepoFile('DEVELOPMENT_STATUS.md')}
                      className="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                      DEVELOPMENT_STATUS.md
                    </button>
                  </div>
                  <div className="mt-3 divide-y divide-slate-100 text-xs dark:divide-slate-800">
                    {[
                      { label: 'Phase 1–2 Security & Architecture Audit', path: 'AUDIT_PHASE_1_2.md' },
                      { label: 'OAuthClientService & ApplicationService (Phase 3)', path: 'app/Modules/OAuth/Services/OAuthClientService.php' },
                      { label: 'Email & OTP Providers + Sender Isolation (Phase 4)', path: 'app/Modules/OTP/Services/OtpVerificationService.php' },
                      { label: 'API Documentation & Security Specification', path: 'API_DOCUMENTATION.md' },
                      { label: 'Laravel PHPUnit Feature & Security Suites', path: 'tests/Feature/EmailPasswordAndOtpAuthenticationTest.php' },
                    ].map((artifact) => (
                      <button
                        key={artifact.path}
                        type="button"
                        onClick={() => openRepoFile(artifact.path)}
                        className="flex w-full items-center justify-between py-2 text-left hover:text-blue-600 dark:hover:text-blue-400"
                      >
                        <span>{artifact.label}</span>
                        <span className="font-mono text-[11px] text-slate-400">Inspect</span>
                      </button>
                    ))}
                  </div>
                </section>
              </div>

              <section className="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div className="border-b border-slate-200 px-5 py-3.5 dark:border-slate-800">
                  <h2 className="text-sm font-semibold">10-Phase Delivery Roadmap</h2>
                </div>
                <table className="w-full border-collapse text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                      <th className="px-5 py-2.5 font-medium">Phase</th>
                      <th className="px-5 py-2.5 font-medium">Scope</th>
                      <th className="px-5 py-2.5 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {PHASES.map((p) => (
                      <tr key={p.phase} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                        <td className="px-5 py-2.5 font-mono font-medium whitespace-nowrap">{p.phase}</td>
                        <td className="px-5 py-2.5">{p.title}</td>
                        <td className="px-5 py-2.5 text-slate-600 dark:text-slate-300">{p.status}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </section>
            </div>
          )}

          {section === 'repository' && <RepoExplorerView key={repoTargetFile} initialPath={repoTargetFile} />}
          {section === 'schema' && <SchemaView />}
          {section === 'protocol' && <ProtocolLabView />}
        </main>
      </div>
    </div>
  );
}
