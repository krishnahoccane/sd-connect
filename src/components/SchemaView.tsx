import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';

interface TableSpec {
  name: string;
  domain: 'Identity' | 'Organisation' | 'RBAC' | 'Application' | 'Providers' | 'OTP & Security';
  migration: string;
  purpose: string;
  securityNotes: string;
}

const TABLES: TableSpec[] = [
  { name: 'users', domain: 'Identity', migration: '000001', purpose: 'Central global user identity (UUID public ID, email/phone, Argon2id password)', securityNotes: 'Global suspension blocks all tenants' },
  { name: 'user_profiles', domain: 'Identity', migration: '000001', purpose: 'Display name, given/family name, locale, timezone', securityNotes: '1:1 with central user' },
  { name: 'user_identities', domain: 'Identity', migration: '000001', purpose: 'Immutable provider subject links (Google sub, Microsoft oid, Firebase UID)', securityNotes: 'Unique(provider_code, provider_subject, organisation_id)' },
  { name: 'identity_linking_challenges', domain: 'Identity', migration: '000001', purpose: 'Explicit proof-of-ownership challenges before linking external accounts', securityNotes: 'SHA-256 token hash + expiry' },
  { name: 'organisations', domain: 'Organisation', migration: '000002', purpose: 'Tenant boundary, JIT mode, MFA requirement, password/OTP policy JSON', securityNotes: 'Soft-deletable, suspension status' },
  { name: 'organisation_domains', domain: 'Organisation', migration: '000002', purpose: 'Verified corporate domains per organisation', securityNotes: 'DNS/TXT verification token' },
  { name: 'organisation_users', domain: 'Organisation', migration: '000002', purpose: 'Independent tenant memberships per central user', securityNotes: 'Suspending membership preserves central account' },
  { name: 'organisation_branding', domain: 'Organisation', migration: '000002', purpose: 'Active published vs draft branding pointers', securityNotes: 'Preview before publish' },
  { name: 'branding_versions', domain: 'Organisation', migration: '000002', purpose: 'Immutable versioned theme colours, logo, footer, terms/privacy URLs', securityNotes: 'Resolved server-side via client_id' },
  { name: 'roles', domain: 'RBAC', migration: '000003', purpose: 'Platform, organisation, and application scoped roles', securityNotes: 'Unique(scope, organisation_id, slug)' },
  { name: 'permissions', domain: 'RBAC', migration: '000003', purpose: 'Granular permission slugs by module', securityNotes: 'Enforced by policies & middleware' },
  { name: 'role_permissions', domain: 'RBAC', migration: '000003', purpose: 'Many-to-many role to permission mapping', securityNotes: 'Cascade delete on role' },
  { name: 'user_roles', domain: 'RBAC', migration: '000003', purpose: 'Contextual role assignment per user/organisation/application', securityNotes: 'Tenant-scoped authorization' },
  { name: 'applications', domain: 'Application', migration: '000004', purpose: 'Registered tenant OIDC/OAuth applications, PKCE & TTL configuration', securityNotes: 'Tenant-isolated via organisation_id' },
  { name: 'oauth_client_registry', domain: 'Application', migration: '000004', purpose: 'Maps tenant applications to native Passport oauth_clients.id', securityNotes: 'Does not mutate Passport tables' },
  { name: 'oauth_redirect_uris', domain: 'Application', migration: '000004', purpose: 'Exact redirect & post_logout URIs per application', securityNotes: 'Wildcard URIs strictly prohibited' },
  { name: 'application_auth_methods', domain: 'Application', migration: '000004', purpose: 'Level 3 auth method enablement & mandatory stage chains', securityNotes: 'Cannot override org/global disables' },
  { name: 'auth_providers', domain: 'Providers', migration: '000005', purpose: 'Level 1 global provider catalog & health state', securityNotes: 'Master platform kill switch' },
  { name: 'provider_configurations', domain: 'Providers', migration: '000005', purpose: 'Global & org-specific provider credentials and public settings', securityNotes: 'Encrypted at rest via Laravel Crypt' },
  { name: 'organisation_auth_methods', domain: 'Providers', migration: '000005', purpose: 'Level 2 organisation auth method toggles & second-stage MFA flags', securityNotes: 'Cannot enable globally disabled providers' },
  { name: 'email_configurations', domain: 'Providers', migration: '000005', purpose: 'Platform Google Workspace, Org Google Workspace, or Org SMTP transport', securityNotes: 'Encrypted transport secrets' },
  { name: 'otp_transactions', domain: 'OTP & Security', migration: '000006', purpose: 'Hashed 6-digit OTP challenges, expiry, attempt counters, resend counts', securityNotes: 'Single-use, hashed, 5-min TTL' },
  { name: 'otp_delivery_logs', domain: 'OTP & Security', migration: '000006', purpose: 'Sanitized delivery status logs for Email & WhatsApp OTP', securityNotes: 'Never logs auth keys or plaintext OTP' },
  { name: 'otp_attempts', domain: 'OTP & Security', migration: '000006', purpose: 'Per-attempt IP and outcome telemetry', securityNotes: 'Supports lockout enforcement' },
  { name: 'sso_sessions', domain: 'OTP & Security', migration: '000006', purpose: 'Central SSO session tokens, AMR claims, expiration & revocation', securityNotes: 'SHA-256 hashed session token' },
  { name: 'user_sessions', domain: 'OTP & Security', migration: '000006', purpose: 'Per-application active session records linked to SSO session', securityNotes: 'Supports back-channel/RP logout' },
  { name: 'login_attempts', domain: 'OTP & Security', migration: '000006', purpose: 'Authentication attempt audit trail (masked identifier, IP, outcome)', securityNotes: 'Brute-force analytics' },
  { name: 'security_events', domain: 'OTP & Security', migration: '000006', purpose: 'High-severity alerts (refresh token reuse, cross-tenant probes, key rotation)', securityNotes: 'Immutable event stream' },
  { name: 'audit_logs', domain: 'OTP & Security', migration: '000006', purpose: 'Before/after state snapshots for sensitive administrative actions', securityNotes: 'Actor role, IP, target UUID' },
  { name: 'invitations', domain: 'OTP & Security', migration: '000006', purpose: 'Time-limited tenant user invitations with role & app grants', securityNotes: 'Hashed invite token' },
  { name: 'consent_records', domain: 'OTP & Security', migration: '000006', purpose: 'OAuth scope grants per user and application', securityNotes: 'Revocable consent' },
  { name: 'provider_webhook_events', domain: 'OTP & Security', migration: '000006', purpose: 'Inbound provider delivery/status webhook payloads', securityNotes: 'Org-scoped tracking' },
  { name: 'signing_key_metadata', domain: 'OTP & Security', migration: '000006', purpose: 'RS256 key IDs (kid), PEM public keys, encrypted private keys, rotation state', securityNotes: 'Supports active/retiring JWKS' },
  { name: 'configuration_history', domain: 'OTP & Security', migration: '000006', purpose: 'Versioned policy & config change ledger across scopes', securityNotes: 'Tracks changed_by_user_id' },
  { name: 'oauth_transactions', domain: 'Application', migration: '000007', purpose: 'Persists OIDC state, nonce, PKCE S256 challenge, and single-use auth code hash', securityNotes: 'Row-locked replay protection' },
];

const DOMAINS = ['All', 'Identity', 'Organisation', 'RBAC', 'Application', 'Providers', 'OTP & Security'] as const;

export function SchemaView() {
  const [domainFilter, setDomainFilter] = useState<(typeof DOMAINS)[number]>('All');
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return TABLES.filter((t) => {
      const domainMatch = domainFilter === 'All' || t.domain === domainFilter;
      const queryMatch =
        !q ||
        t.name.toLowerCase().includes(q) ||
        t.purpose.toLowerCase().includes(q) ||
        t.securityNotes.toLowerCase().includes(q);
      return domainMatch && queryMatch;
    });
  }, [domainFilter, query]);

  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            MySQL 8 Multi-Tenant Schema ({TABLES.length} Platform Tables)
          </h2>
          <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            Internal BIGINT primary keys with public UUID identifiers. Laravel Passport native tables remain untouched and linked via oauth_client_registry.
          </p>
        </div>
        <div className="relative w-full sm:w-64">
          <Search className="pointer-events-none absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search tables or security rules..."
            className="w-full rounded-md border border-slate-200 bg-slate-50 py-1.5 pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
          />
        </div>
      </div>

      <div className="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
        {DOMAINS.map((domain) => (
          <button
            key={domain}
            type="button"
            onClick={() => setDomainFilter(domain)}
            className={`rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors ${
              domainFilter === domain
                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-800 dark:text-slate-100'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
            }`}
          >
            {domain}
          </button>
        ))}
      </div>

      <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table className="w-full border-collapse text-left text-xs">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
              <th className="px-4 py-2.5 font-medium">Table</th>
              <th className="px-4 py-2.5 font-medium">Domain</th>
              <th className="px-4 py-2.5 font-medium">Migration</th>
              <th className="px-4 py-2.5 font-medium">Responsibility</th>
              <th className="px-4 py-2.5 font-medium">Security &amp; Isolation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            {filtered.map((t) => (
              <tr key={t.name} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                <td className="px-4 py-2.5 font-mono font-medium text-slate-900 dark:text-slate-100">{t.name}</td>
                <td className="px-4 py-2.5 text-slate-600 dark:text-slate-300">{t.domain}</td>
                <td className="px-4 py-2.5 font-mono text-slate-500 tabular-nums dark:text-slate-400">
                  2026_01_01_{t.migration}
                </td>
                <td className="px-4 py-2.5 text-slate-700 dark:text-slate-300">{t.purpose}</td>
                <td className="px-4 py-2.5 text-slate-500 dark:text-slate-400">{t.securityNotes}</td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                  No tables match the current filter criteria.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
