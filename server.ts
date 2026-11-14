import express from 'express';
import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { createServer as createViteServer } from 'vite';

const PORT = 3000;
const ROOT_DIR = process.cwd();
const LARAVEL_ROOT = path.resolve(ROOT_DIR, 'laravel-platform');

interface TreeNode {
  name: string;
  path: string;
  type: 'file' | 'directory';
  size?: number;
  children?: TreeNode[];
}

async function buildTree(dir: string, relativeBase = ''): Promise<TreeNode[]> {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const sorted = entries.sort((a, b) => {
    if (a.isDirectory() && !b.isDirectory()) return -1;
    if (!a.isDirectory() && b.isDirectory()) return 1;
    return a.name.localeCompare(b.name);
  });

  const nodes: TreeNode[] = [];
  for (const entry of sorted) {
    const relPath = relativeBase ? `${relativeBase}/${entry.name}` : entry.name;
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      nodes.push({
        name: entry.name,
        path: relPath,
        type: 'directory',
        children: await buildTree(fullPath, relPath),
      });
    } else {
      const stat = await fs.stat(fullPath);
      nodes.push({
        name: entry.name,
        path: relPath,
        type: 'file',
        size: stat.size,
      });
    }
  }
  return nodes;
}

function base64UrlEncode(buf: Buffer): string {
  return buf.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

async function startServer() {
  const app = express();
  app.use(express.json());

  app.get('/api/environment', (_req, res) => {
    res.json({
      runtime: 'Node.js ' + process.version,
      os: `${process.platform} (${process.arch})`,
      phpAvailable: false,
      composerAvailable: false,
      mysqlAvailable: false,
      redisAvailable: false,
      laravelRepositoryPath: '/laravel-platform',
      targetProductionDomain: 'https://connect.twsd.me',
    });
  });

  app.get('/api/repo/tree', async (_req, res) => {
    try {
      const tree = await buildTree(LARAVEL_ROOT);
      res.json({ root: 'laravel-platform', tree });
    } catch (err) {
      res.status(500).json({ error: 'Unable to read Laravel repository tree', detail: String(err) });
    }
  });

  app.get('/api/repo/file', async (req, res) => {
    const requestedPath = String(req.query.path ?? '');
    if (!requestedPath || requestedPath.includes('..')) {
      res.status(400).json({ error: 'Invalid file path' });
      return;
    }

    const resolved = path.resolve(LARAVEL_ROOT, requestedPath);
    if (!resolved.startsWith(LARAVEL_ROOT + path.sep)) {
      res.status(403).json({ error: 'Path traversal blocked' });
      return;
    }

    try {
      const content = await fs.readFile(resolved, 'utf-8');
      const stat = await fs.stat(resolved);
      res.json({
        path: requestedPath,
        size: stat.size,
        lines: content.split('\n').length,
        content,
      });
    } catch {
      res.status(404).json({ error: 'File not found' });
    }
  });

  app.post('/api/protocol/pkce-verify', (req, res) => {
    const codeVerifier = typeof req.body?.codeVerifier === 'string' ? req.body.codeVerifier : '';
    const codeChallenge = typeof req.body?.codeChallenge === 'string' ? req.body.codeChallenge : '';

    if (codeVerifier.length < 43 || codeVerifier.length > 128) {
      res.json({
        validLength: false,
        matches: false,
        computedChallenge: null,
        error: 'RFC 7636 requires code_verifier length between 43 and 128 characters.',
      });
      return;
    }

    const digest = crypto.createHash('sha256').update(codeVerifier, 'utf8').digest();
    const computedChallenge = base64UrlEncode(digest);
    const matches =
      codeChallenge.length > 0 &&
      computedChallenge.length === codeChallenge.length &&
      crypto.timingSafeEqual(Buffer.from(computedChallenge), Buffer.from(codeChallenge));

    res.json({
      validLength: true,
      computedChallenge,
      matches,
      method: 'S256',
      error: null,
    });
  });

  app.post('/api/protocol/pkce-generate', (_req, res) => {
    const codeVerifier = base64UrlEncode(crypto.randomBytes(48));
    const codeChallenge = base64UrlEncode(crypto.createHash('sha256').update(codeVerifier, 'utf8').digest());
    res.json({ codeVerifier, codeChallenge, method: 'S256' });
  });

  app.post('/api/protocol/policy-resolve', (req, res) => {
    interface ProviderInput {
      code: string;
      name: string;
      category: string;
      globalEnabled: boolean;
      orgEnabled: boolean;
      appEnabled: boolean;
      healthy: boolean;
      requireSecondStageMfa?: boolean;
    }

    const orgActive = Boolean(req.body?.orgActive ?? true);
    const appActive = Boolean(req.body?.appActive ?? true);
    const orgMfaRequired = Boolean(req.body?.orgMfaRequired ?? false);
    const providers: ProviderInput[] = Array.isArray(req.body?.providers) ? req.body.providers : [];

    if (!orgActive || !appActive) {
      res.json({
        effectiveMethods: [],
        rejected: {
          tenant: !orgActive ? 'organisation_suspended' : 'application_suspended',
        },
      });
      return;
    }

    const effectiveMethods: Array<{
      code: string;
      name: string;
      category: string;
      mandatoryStages: string[];
    }> = [];
    const rejected: Record<string, string> = {};

    const mfaCandidate = providers.find(
      (p) => p.requireSecondStageMfa && p.globalEnabled && p.orgEnabled && p.healthy
    );

    for (const p of providers) {
      if (!p.globalEnabled) {
        rejected[p.code] = 'disabled_globally';
        continue;
      }
      if (!p.orgEnabled) {
        rejected[p.code] = 'disabled_by_organisation';
        continue;
      }
      if (!p.appEnabled) {
        rejected[p.code] = 'disabled_by_application';
        continue;
      }
      if (!p.healthy) {
        rejected[p.code] = 'provider_configuration_unhealthy';
        continue;
      }

      const stages =
        orgMfaRequired && mfaCandidate && mfaCandidate.code !== p.code
          ? [p.code, mfaCandidate.code]
          : [p.code];

      effectiveMethods.push({
        code: p.code,
        name: p.name,
        category: p.category,
        mandatoryStages: stages,
      });
    }

    res.json({ effectiveMethods, rejected });
  });

  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    app.use(express.static(path.resolve(ROOT_DIR, 'dist')));
    app.get('*', (_req, res) => {
      res.sendFile(path.resolve(ROOT_DIR, 'dist/index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`TWSD Connect Workspace listening on http://localhost:${PORT}`);
  });
}

startServer();
