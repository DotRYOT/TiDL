import { useState, useCallback } from 'react';
import AuthSection from './components/AuthSection';
import SearchSection from './components/SearchSection';
import DownloadQueue from './components/DownloadQueue';
import Header from './components/Header';
import type { Track, DownloadItem, AuthState } from './types';

export default function App() {
  const [auth, setAuth] = useState<AuthState>({
    clientId: '',
    clientSecret: '',
    accessToken: '',
    isAuthenticated: false,
  });

  const [searchResults, setSearchResults] = useState<Track[]>([]);
  const [downloadQueue, setDownloadQueue] = useState<DownloadItem[]>([]);
  const [activeTab, setActiveTab] = useState<'search' | 'downloads'>('search');

  const handleAuth = useCallback((clientId: string, clientSecret: string) => {
    setAuth(prev => ({
      ...prev,
      clientId,
      clientSecret,
    }));
  }, []);

  const handleAuthSuccess = useCallback((accessToken: string) => {
    setAuth(prev => ({
      ...prev,
      accessToken,
      isAuthenticated: true,
    }));
  }, []);

  const handleAuthError = useCallback(() => {
    setAuth(prev => ({
      ...prev,
      accessToken: '',
      isAuthenticated: false,
    }));
  }, []);

  const handleAddToQueue = useCallback((track: Track) => {
    const newItem: DownloadItem = {
      id: `${track.id}-${Date.now()}`,
      track,
      status: 'queued',
      progress: 0,
    };
    setDownloadQueue(prev => [...prev, newItem]);
    setActiveTab('downloads');
  }, []);

  const handleDownloadAll = useCallback((tracks: Track[]) => {
    const newItems: DownloadItem[] = tracks.map((track, i) => ({
      id: `${track.id}-${Date.now()}-${i}`,
      track,
      status: 'queued' as const,
      progress: 0,
    }));
    setDownloadQueue(prev => [...prev, ...newItems]);
    setActiveTab('downloads');
  }, []);

  const handleRemoveFromQueue = useCallback((id: string) => {
    setDownloadQueue(prev => prev.filter(item => item.id !== id));
  }, []);

  const handleUpdateProgress = useCallback((id: string, progress: number, status: DownloadItem['status']) => {
    setDownloadQueue(prev => prev.map(item =>
      item.id === id ? { ...item, progress, status } : item
    ));
  }, []);

  return (
    <div className="min-h-screen bg-black text-white">
      <Header auth={auth} />
      
      <main className="max-w-6xl mx-auto px-4 py-6 space-y-6">
        {/* Auth Section */}
        <AuthSection
          auth={auth}
          onAuth={handleAuth}
          onAuthSuccess={handleAuthSuccess}
          onAuthError={handleAuthError}
        />

        {/* Tabs */}
        {auth.isAuthenticated && (
          <div className="fade-in">
            <div className="flex gap-1 mb-6 bg-[#0a0a0a] p-1 rounded-lg border border-[#1a1a1a] w-fit">
              <button
                onClick={() => setActiveTab('search')}
                className={`px-5 py-2 rounded-md text-sm font-medium transition-all ${
                  activeTab === 'search'
                    ? 'bg-[#1a1a1a] text-white'
                    : 'text-[#888] hover:text-white'
                }`}
              >
                <i className="fas fa-search mr-2" />
                Search
              </button>
              <button
                onClick={() => setActiveTab('downloads')}
                className={`px-5 py-2 rounded-md text-sm font-medium transition-all ${
                  activeTab === 'downloads'
                    ? 'bg-[#1a1a1a] text-white'
                    : 'text-[#888] hover:text-white'
                }`}
              >
                <i className="fas fa-download mr-2" />
                Downloads
                {downloadQueue.length > 0 && (
                  <span className="ml-2 bg-[#00ff88] text-black text-xs px-1.5 py-0.5 rounded-full font-bold">
                    {downloadQueue.length}
                  </span>
                )}
              </button>
            </div>

            {activeTab === 'search' && (
              <SearchSection
                auth={auth}
                results={searchResults}
                onResultsChange={setSearchResults}
                onAddToQueue={handleAddToQueue}
                onDownloadAll={handleDownloadAll}
              />
            )}

            {activeTab === 'downloads' && (
              <DownloadQueue
                items={downloadQueue}
                auth={auth}
                onRemove={handleRemoveFromQueue}
                onUpdateProgress={handleUpdateProgress}
              />
            )}
          </div>
        )}
      </main>
    </div>
  );
}
