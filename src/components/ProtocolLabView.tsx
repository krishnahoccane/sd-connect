import { useEffect, useState } from 'react';
import { RefreshCw, ShieldCheck, ShieldX } from 'lucide-react';
import type { PolicyProviderItem } from '../types';

const INITIAL_PROVIDERS: PolicyProviderItem[] = [
  { code: 'email_password', name: 'Email & Password', category: 'password', globalEnabled: true, orgEnabled: true, appEnabled: true, healthy: true, requireSecondStageMfa: false },
  { code: 'email_otp', name: 'Email OTP', category: 'otp', globalEnabled: true, orgEnabled: true, appEnabled: true, healthy: true, requireSecondStageMfa: true },
  { code: 'firebase_sms', name: 'Firebase SMS OTP', category: 'otp', globalEnabled: true, orgEnabled: false, appEnabled: true, healthy: true, requireSecondStageMfa: false },
  { code: 'whatsapp_otp', name: 'WhatsApp OTP (Webglit)', category: 'otp', globalEnabled: false, orgEnabled: true, appEnabled: true, healthy: false, requireSecondStageMfa: false },
  { code: 'google', name: 'Google Sign-In', category: 'federated', globalEnabled: true, orgEnabled: true, appEnabled: true, healthy: true, requireSecondStageMfa: false },
  { code: 'microsoft', name: 'Microsoft Entra ID', category: 'federated', globalEnabled: true, orgEnabled: true, appEnabled: false, healthy: true, requireSecondStageMfa: false },
];

export function ProtocolLabView() {
  const [orgActive, setOrgActive] = useState(true);
  const [appActive, setAppActive] = useState(true);
  const [orgMfaRequired, setOrgMfaRequired] = useState(true);
  const [providers, setProviders] = useState<PolicyProviderItem[]>(INITIAL_PROVIDERS);
  const [policyResult, setPolicyResult] = useState<{
    effectiveMethods: Array<{ code: string; name: string; category: string; mandatoryStages: string[] }>;
    rejected: Record<string, string>;
  } | null>(null);

  const [codeVerifier, setCodeVerifier] = useState('');
  const [codeChallenge, setCodeChallenge] = useState('');
  const [pkceResult, setPkceResult] = useState<{
    validLength: boolean;
    computedChallenge: string | null;
    matches: boolean;
    error: string | null;
  } | null>(null);

  useEffect(() => {
    fetch('/api/protocol/policy-resolve', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orgActive, appActive, orgMfaRequired, providers }),
    })
      .then((r) => r.json())
      .then((data) => setPolicyResult(data))
      .catch(() => setPolicyResult(null));
  }, [orgActive, appActive, orgMfaRequired, providers]);

  const generatePkcePair = async () => {
    const res = await fetch('/api/protocol/pkce-generate', { method: 'POST' });
    const data = await res.json();
    setCodeVerifier(data.codeVerifier);
    setCodeChallenge(data.codeChallenge);
    verifyPkcePair(data.codeVerifier, data.codeChallenge);
  };

  const verifyPkcePair = async (verifier = codeVerifier, challenge = codeChallenge) => {
    const res = await fetch('/api/protocol/pkce-verify', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codeVerifier: verifier, codeChallenge: challenge }),
    });
    const data = await res.json();
    setPkceResult(data);
  };

  useEffect(() => {
    generatePkcePair();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const toggleProviderFlag = (code: string, field: keyof Pick<PolicyProviderItem, 'globalEnabled' | 'orgEnabled' | 'appEnabled' | 'healthy' | 'requireSecondStageMfa'>) => {
    setProviders((prev) =>
      prev.map((p) => (p.code === code ? { ...p, [field]: !p[field] } : p))
    );
  };

  return (
    <div className="space-y-6">
      <section className="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
          <div>
            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
              3-Level Hierarchical Authentication Policy Engine
            </h2>
            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              Computes FINAL = GLOBAL_ENABLED ∩ ORGANISATION_ENABLED ∩ APPLICATION_ENABLED ∩ PROVIDER_HEALTHY via Node reference endpoint matching AuthenticationPolicyService.php.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-4 text-xs">
            <label className="flex cursor-pointer items-center gap-2 font-medium text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={orgActive} onChange={(e) => setOrgActive(e.target.checked)} className="rounded border-slate-300 text-blue-600" />
              <span>Organisation active</span>
            </label>
            <label className="flex cursor-pointer items-center gap-2 font-medium text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={appActive} onChange={(e) => setAppActive(e.target.checked)} className="rounded border-slate-300 text-blue-600" />
              <span>Application active</span>
            </label>
            <label className="flex cursor-pointer items-center gap-2 font-medium text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={orgMfaRequired} onChange={(e) => setOrgMfaRequired(e.target.checked)} className="rounded border-slate-300 text-blue-600" />
              <span>Org MFA required</span>
            </label>
          </div>
        </div>

        <div className="mt-4 overflow-x-auto">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <th className="py-2 pr-4 font-medium">Provider</th>
                <th className="px-3 py-2 text-center font-medium">L1 Global</th>
                <th className="px-3 py-2 text-center font-medium">L2 Organisation</th>
                <th className="px-3 py-2 text-center font-medium">L3 Application</th>
                <th className="px-3 py-2 text-center font-medium">Config Healthy</th>
                <th className="px-3 py-2 text-center font-medium">2nd-Stage MFA</th>
                <th className="py-2 pl-4 font-medium">Resolved Outcome</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {providers.map((p) => {
                const effective = policyResult?.effectiveMethods.find((m) => m.code === p.code);
                const rejectedReason = policyResult?.rejected[p.code] ?? policyResult?.rejected['tenant'];

                return (
                  <tr key={p.code} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                    <td className="py-2.5 pr-4">
                      <div className="font-medium text-slate-900 dark:text-slate-100">{p.name}</div>
                      <div className="font-mono text-[11px] text-slate-500">{p.code}</div>
                    </td>
                    <td className="px-3 py-2.5 text-center">
                      <input type="checkbox" checked={p.globalEnabled} onChange={() => toggleProviderFlag(p.code, 'globalEnabled')} aria-label={`${p.name} Global`} />
                    </td>
                    <td className="px-3 py-2.5 text-center">
                      <input type="checkbox" checked={p.orgEnabled} onChange={() => toggleProviderFlag(p.code, 'orgEnabled')} aria-label={`${p.name} Organisation`} />
                    </td>
                    <td className="px-3 py-2.5 text-center">
                      <input type="checkbox" checked={p.appEnabled} onChange={() => toggleProviderFlag(p.code, 'appEnabled')} aria-label={`${p.name} Application`} />
                    </td>
                    <td className="px-3 py-2.5 text-center">
                      <input type="checkbox" checked={p.healthy} onChange={() => toggleProviderFlag(p.code, 'healthy')} aria-label={`${p.name} Healthy`} />
                    </td>
                    <td className="px-3 py-2.5 text-center">
                      <input type="checkbox" checked={p.requireSecondStageMfa} onChange={() => toggleProviderFlag(p.code, 'requireSecondStageMfa')} aria-label={`${p.name} MFA`} />
                    </td>
                    <td className="py-2.5 pl-4">
                      {effective ? (
                        <span className="inline-flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400">
                          <ShieldCheck className="h-3.5 w-3.5 shrink-0" />
                          <span>Permitted · Stages: {effective.mandatoryStages.join(' → ')}</span>
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 text-red-700 dark:text-red-400">
                          <ShieldX className="h-3.5 w-3.5 shrink-0" />
                          <span className="font-mono text-[11px]">{rejectedReason ?? 'blocked'}</span>
                        </span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
          <div>
            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
              RFC 7636 PKCE S256 Cryptographic Verifier
            </h2>
            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              Live SHA-256 + Base64URL verification using constant-time comparison (matching OidcService::verifyPkceS256).
            </p>
          </div>
          <button
            type="button"
            onClick={generatePkcePair}
            className="inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500"
          >
            <RefreshCw className="h-3.5 w-3.5" />
            <span>Generate fresh PKCE pair</span>
          </button>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div>
            <label htmlFor="pkce-verifier" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
              code_verifier (43–128 chars)
            </label>
            <input
              id="pkce-verifier"
              type="text"
              value={codeVerifier}
              onChange={(e) => {
                setCodeVerifier(e.target.value);
                verifyPkcePair(e.target.value, codeChallenge);
              }}
              className="mt-1.5 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
            />
          </div>
          <div>
            <label htmlFor="pkce-challenge" className="block text-xs font-medium text-slate-700 dark:text-slate-300">
              code_challenge (S256)
            </label>
            <input
              id="pkce-challenge"
              type="text"
              value={codeChallenge}
              onChange={(e) => {
                setCodeChallenge(e.target.value);
                verifyPkcePair(codeVerifier, e.target.value);
              }}
              className="mt-1.5 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
            />
          </div>
        </div>

        {pkceResult && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs dark:border-slate-800 dark:bg-slate-950">
            <div className="flex items-center gap-2">
              {pkceResult.matches ? (
                <span className="inline-flex items-center gap-1.5 font-semibold text-emerald-700 dark:text-emerald-400">
                  <ShieldCheck className="h-4 w-4" />
                  <span>PKCE S256 Verified (Timing-safe match)</span>
                </span>
              ) : (
                <span className="inline-flex items-center gap-1.5 font-semibold text-red-700 dark:text-red-400">
                  <ShieldX className="h-4 w-4" />
                  <span>{pkceResult.error ?? 'PKCE S256 Mismatch — Token exchange rejected'}</span>
                </span>
              )}
            </div>
            {pkceResult.computedChallenge && (
              <div className="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                Computed: {pkceResult.computedChallenge}
              </div>
            )}
          </div>
        )}
      </section>
    </div>
  );
}
