import type { AuthState } from '../types';

interface HeaderProps {
  auth: AuthState;
}

export default function Header({ auth }: HeaderProps) {
  return (
    <header className="border-b border-[#1a1a1a] bg-[#050505]">
      <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-[#00ff88] flex items-center justify-center">
            <i className="fas fa-music text-black text-lg" />
          </div>
          <div>
            <h1 className="text-xl font-bold tracking-tight">TidalDL</h1>
            <p className="text-xs text-[#666]">High-Quality Music Downloader</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          {auth.isAuthenticated ? (
            <div className="flex items-center gap-2">
              <span className="pulse-dot w-2 h-2 rounded-full bg-[#00ff88] inline-block" />
              <span className="text-sm text-[#00ff88] font-medium">Connected</span>
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-[#ff3355] inline-block" />
              <span className="text-sm text-[#ff3355] font-medium">Not Connected</span>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
