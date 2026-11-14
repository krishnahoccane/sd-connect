export interface EnvironmentInfo {
  runtime: string;
  os: string;
  phpAvailable: boolean;
  composerAvailable: boolean;
  mysqlAvailable: boolean;
  redisAvailable: boolean;
  laravelRepositoryPath: string;
  targetProductionDomain: string;
}

export interface TreeNode {
  name: string;
  path: string;
  type: 'file' | 'directory';
  size?: number;
  children?: TreeNode[];
}

export interface RepoFileContent {
  path: string;
  size: number;
  lines: number;
  content: string;
}

export interface PolicyProviderItem {
  code: string;
  name: string;
  category: 'password' | 'otp' | 'federated';
  globalEnabled: boolean;
  orgEnabled: boolean;
  appEnabled: boolean;
  healthy: boolean;
  requireSecondStageMfa: boolean;
}
