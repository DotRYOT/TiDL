import { useState, useEffect } from 'react';

interface SystemStatus {
  system: {
    os: string;
    os_pretty: string;
    php_version: string;
    php_sapi: string;
    server_software: string;
  };
  dependencies: {
    all_ok: boolean;
    missing: string[];
    paths: Record<string, string>;
    os: string;
  };
  disk_space: {
    free_human: string;
    total_human: string;
    used_percent: number;
  };
  directories: {
    download_dir: { path: string; exists: boolean; writable: boolean };
    temp_dir: { path: string; exists: boolean; writable: boolean };
  };
  php_extensions: Record<string, boolean>;
  php_settings: Record<string, string>;
}

export default function SystemStatus() {
  const [status, setStatus] = useState<SystemStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [expanded, setExpanded] = useState(false);

  const fetchStatus = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await fetch('/api/status.php');
      const data = await response.json();
      if (data.success) {
        setStatus(data);
      } else {
        setError('Failed to fetch status');
      }
    } catch {
      setError('Backend not available');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStatus();
  }, []);

  if (!expanded) {
    return (
      <button
        onClick={() => setExpanded(true)}
        className="w-full oled-card p-3 flex items-center justify-between hover:bg-[#111] transition-colors"
      >
        <div className="flex items-center gap-2">
          <i className="fas fa-server text-[#555] text-sm" />
          <span className="text-xs text-[#666]">System Status</span>
        </div>
        <i className="fas fa-chevron-down text-[#444] text-xs" />
      </button>
    );
  }

  return (
    <div className="oled-card p-4 fade-in">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <i className="fas fa-server text-[#00ff88] text-sm" />
          <span className="text-sm font-medium">System Status</span>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={fetchStatus}
            className="text-[#555] hover:text-white transition-colors"
            title="Refresh"
          >
            <i className={`fas fa-sync-alt text-xs ${loading ? 'fa-spin' : ''}`} />
          </button>
          <button
            onClick={() => setExpanded(false)}
            className="text-[#555] hover:text-white transition-colors"
          >
            <i className="fas fa-chevron-up text-xs" />
          </button>
        </div>
      </div>

      {error && (
        <div className="mb-3 p-2 rounded bg-[#1a0a0a] border border-[#331111] text-[#ff3355] text-xs">
          <i className="fas fa-exclamation-triangle mr-1" />
          {error}
        </div>
      )}

      {status && (
        <div className="space-y-3">
          {/* OS Info */}
          <div className="grid grid-cols-2 gap-2">
            <StatusItem label="OS" value={status.system.os_pretty} />
            <StatusItem label="PHP" value={`v${status.system.php_version}`} />
            <StatusItem label="Server" value={status.system.server_software.split(' ')[0]} />
            <StatusItem label="SAPI" value={status.system.php_sapi} />
          </div>

          {/* Dependencies */}
          <div className="border-t border-[#1a1a1a] pt-3">
            <div className="flex items-center gap-2 mb-2">
              <span className="text-xs text-[#888] uppercase tracking-wider">Dependencies</span>
              {status.dependencies.all_ok ? (
                <span className="text-[10px] bg-[#0a1a10] text-[#00ff88] px-1.5 py-0.5 rounded font-bold">
                  ALL OK
                </span>
              ) : (
                <span className="text-[10px] bg-[#1a0a0a] text-[#ff3355] px-1.5 py-0.5 rounded font-bold">
                  MISSING
                </span>
              )}
            </div>
            <div className="grid grid-cols-2 gap-1">
              {Object.entries(status.dependencies.paths).map(([name, path]) => (
                <div key={name} className="flex items-center gap-1.5">
                  <span className={`w-1.5 h-1.5 rounded-full ${
                    status.dependencies.missing.includes(name) ? 'bg-[#ff3355]' : 'bg-[#00ff88]'
                  }`} />
                  <span className="text-xs text-[#888]">{name}</span>
                  <span className="text-[10px] text-[#444] truncate">{path}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Disk Space */}
          <div className="border-t border-[#1a1a1a] pt-3">
            <span className="text-xs text-[#888] uppercase tracking-wider block mb-2">Storage</span>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-[#666]">
                {status.disk_space.free_human} free / {status.disk_space.total_human} total
              </span>
              <span className="text-xs text-[#888]">{status.disk_space.used_percent}% used</span>
            </div>
            <div className="progress-bar h-1.5">
              <div
                className={`h-full rounded ${
                  status.disk_space.used_percent > 90
                    ? 'bg-[#ff3355]'
                    : status.disk_space.used_percent > 70
                    ? 'bg-[#ffaa00]'
                    : 'bg-[#00ff88]'
                }`}
                style={{ width: `${status.disk_space.used_percent}%` }}
              />
            </div>
          </div>

          {/* Directories */}
          <div className="border-t border-[#1a1a1a] pt-3">
            <span className="text-xs text-[#888] uppercase tracking-wider block mb-2">Directories</span>
            <div className="space-y-1">
              <DirStatus label="Downloads" info={status.directories.download_dir} />
              <DirStatus label="Temp" info={status.directories.temp_dir} />
            </div>
          </div>

          {/* PHP Extensions */}
          <div className="border-t border-[#1a1a1a] pt-3">
            <span className="text-xs text-[#888] uppercase tracking-wider block mb-2">PHP Extensions</span>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(status.php_extensions).map(([ext, loaded]) => (
                <span
                  key={ext}
                  className={`text-[10px] px-1.5 py-0.5 rounded font-mono ${
                    loaded
                      ? 'bg-[#0a1a10] text-[#00ff88]'
                      : 'bg-[#1a0a0a] text-[#ff3355]'
                  }`}
                >
                  {ext}
                </span>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function StatusItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span className="text-[10px] text-[#555] uppercase tracking-wider">{label}</span>
      <p className="text-xs text-[#ccc] font-mono">{value}</p>
    </div>
  );
}

function DirStatus({ label, info }: { label: string; info: { path: string; exists: boolean; writable: boolean } }) {
  return (
    <div className="flex items-center gap-2">
      <i className={`fas ${info.exists && info.writable ? 'fa-check-circle text-[#00ff88]' : 'fa-times-circle text-[#ff3355]'} text-xs`} />
      <span className="text-xs text-[#888]">{label}</span>
      <span className="text-[10px] text-[#444] truncate font-mono">{info.path}</span>
    </div>
  );
}
