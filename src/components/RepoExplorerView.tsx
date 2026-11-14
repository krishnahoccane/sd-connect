import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Copy, Check, FileCode2, Folder, Search } from 'lucide-react';
import type { RepoFileContent, TreeNode } from '../types';

interface RepoExplorerViewProps {
  initialPath?: string;
}

function flattenFiles(nodes: TreeNode[]): string[] {
  const out: string[] = [];
  for (const n of nodes) {
    if (n.type === 'file') out.push(n.path);
    if (n.children) out.push(...flattenFiles(n.children));
  }
  return out;
}

export function RepoExplorerView({ initialPath = 'DEVELOPMENT_STATUS.md' }: RepoExplorerViewProps) {
  const [tree, setTree] = useState<TreeNode[]>([]);
  const [loadingTree, setLoadingTree] = useState(true);
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Record<string, boolean>>({
    app: true,
    'app/Models': true,
    'app/Modules': true,
    'app/Http': true,
    config: true,
    database: true,
    'database/migrations': true,
    'database/seeders': true,
    routes: true,
    tests: true,
  });
  const [selectedPath, setSelectedPath] = useState<string>(initialPath);
  const [fileData, setFileData] = useState<RepoFileContent | null>(null);
  const [loadingFile, setLoadingFile] = useState(false);
  const [fileError, setFileError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    setLoadingTree(true);
    fetch('/api/repo/tree')
      .then((r) => r.json())
      .then((data) => {
        setTree(Array.isArray(data.tree) ? data.tree : []);
      })
      .catch(() => setTree([]))
      .finally(() => setLoadingTree(false));
  }, []);

  useEffect(() => {
    if (!selectedPath) return;
    setLoadingFile(true);
    setFileError(null);
    fetch(`/api/repo/file?path=${encodeURIComponent(selectedPath)}`)
      .then(async (r) => {
        if (!r.ok) throw new Error('Unable to load file');
        return r.json();
      })
      .then((data: RepoFileContent) => setFileData(data))
      .catch((err) => {
        setFileData(null);
        setFileError(err instanceof Error ? err.message : 'Load failed');
      })
      .finally(() => setLoadingFile(false));
  }, [selectedPath]);

  const matchingFiles = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return null;
    return flattenFiles(tree).filter((p) => p.toLowerCase().includes(q));
  }, [search, tree]);

  const toggleDir = (dirPath: string) => {
    setExpanded((prev) => ({ ...prev, [dirPath]: !prev[dirPath] }));
  };

  const handleCopy = async () => {
    if (!fileData) return;
    await navigator.clipboard.writeText(fileData.content);
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  };

  const renderTreeNodes = (nodes: TreeNode[], depth = 0) => {
    return nodes.map((node) => {
      if (node.type === 'directory') {
        const isOpen = Boolean(expanded[node.path]);
        return (
          <div key={node.path}>
            <button
              type="button"
              onClick={() => toggleDir(node.path)}
              style={{ paddingLeft: `${depth * 14 + 10}px` }}
              className="flex w-full items-center gap-1.5 py-1.5 pr-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/70"
            >
              {isOpen ? (
                <ChevronDown className="h-3.5 w-3.5 shrink-0 text-slate-400" />
              ) : (
                <ChevronRight className="h-3.5 w-3.5 shrink-0 text-slate-400" />
              )}
              <Folder className="h-3.5 w-3.5 shrink-0 text-slate-400" />
              <span className="truncate">{node.name}</span>
            </button>
            {isOpen && node.children && <div>{renderTreeNodes(node.children, depth + 1)}</div>}
          </div>
        );
      }

      const active = selectedPath === node.path;
      return (
        <button
          key={node.path}
          type="button"
          onClick={() => setSelectedPath(node.path)}
          style={{ paddingLeft: `${depth * 14 + 26}px` }}
          className={`flex w-full items-center justify-between gap-2 py-1.5 pr-3 text-left text-xs transition-colors ${
            active
              ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800/70'
          }`}
        >
          <span className="flex min-w-0 items-center gap-1.5">
            <FileCode2 className="h-3.5 w-3.5 shrink-0 opacity-70" />
            <span className="truncate">{node.name}</span>
          </span>
          {typeof node.size === 'number' && (
            <span className="shrink-0 font-mono text-[11px] tabular-nums text-slate-400">
              {(node.size / 1024).toFixed(1)}k
            </span>
          )}
        </button>
      );
    });
  };

  return (
    <div className="grid min-h-[680px] grid-cols-1 overflow-hidden rounded-lg border border-slate-200 bg-white lg:grid-cols-[300px_1fr] dark:border-slate-800 dark:bg-slate-900">
      <aside className="flex flex-col border-b border-slate-200 bg-slate-50/70 lg:border-b-0 lg:border-r dark:border-slate-800 dark:bg-slate-950/50">
        <div className="border-b border-slate-200 p-3 dark:border-slate-800">
          <div className="relative">
            <Search className="pointer-events-none absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Filter repository files..."
              className="w-full rounded-md border border-slate-200 bg-white py-1.5 pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </div>
        </div>
        <div className="flex-1 overflow-y-auto py-2">
          {loadingTree ? (
            <div className="space-y-2 px-3 py-2">
              <div className="h-4 w-3/4 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
              <div className="h-4 w-1/2 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
              <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
            </div>
          ) : matchingFiles ? (
            matchingFiles.length > 0 ? (
              matchingFiles.map((filePath) => (
                <button
                  key={filePath}
                  type="button"
                  onClick={() => setSelectedPath(filePath)}
                  className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs ${
                    selectedPath === filePath
                      ? 'bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                      : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                  }`}
                >
                  <FileCode2 className="h-3.5 w-3.5 shrink-0" />
                  <span className="truncate font-mono text-[11px]">{filePath}</span>
                </button>
              ))
            ) : (
              <p className="px-4 py-6 text-xs text-slate-500">No files match "{search}".</p>
            )
          ) : (
            renderTreeNodes(tree)
          )}
        </div>
      </aside>

      <section className="flex min-w-0 flex-col">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
          <div className="min-w-0">
            <div className="truncate font-mono text-xs font-medium text-slate-900 dark:text-slate-100">
              laravel-platform/{selectedPath}
            </div>
            {fileData && (
              <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500 tabular-nums dark:text-slate-400">
                <span>{fileData.lines} lines</span>
                <span aria-hidden="true">·</span>
                <span>{fileData.size.toLocaleString()} bytes</span>
              </div>
            )}
          </div>
          <button
            type="button"
            onClick={handleCopy}
            disabled={!fileData}
            className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
            <span>{copied ? 'Copied' : 'Copy source'}</span>
          </button>
        </div>

        <div className="flex-1 overflow-auto bg-slate-950 p-4 text-slate-100">
          {loadingFile ? (
            <div className="space-y-2">
              <div className="h-3.5 w-2/3 animate-pulse rounded bg-slate-800" />
              <div className="h-3.5 w-1/2 animate-pulse rounded bg-slate-800" />
              <div className="h-3.5 w-4/5 animate-pulse rounded bg-slate-800" />
            </div>
          ) : fileError ? (
            <div className="rounded-md border border-red-900/50 bg-red-950/30 p-4 text-xs text-red-300">
              {fileError}
            </div>
          ) : fileData ? (
            <pre className="font-mono text-xs leading-6">
              {fileData.content.split('\n').map((line, idx) => (
                <div key={idx} className="table-row hover:bg-slate-900/80">
                  <span className="table-cell w-11 pr-4 text-right text-slate-600 select-none tabular-nums">
                    {idx + 1}
                  </span>
                  <span className="table-cell whitespace-pre">{line}</span>
                </div>
              ))}
            </pre>
          ) : null}
        </div>
      </section>
    </div>
  );
}
