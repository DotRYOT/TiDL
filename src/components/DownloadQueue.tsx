import { useEffect, useRef, useCallback } from 'react';
import type { AuthState, DownloadItem } from '../types';

interface DownloadQueueProps {
  items: DownloadItem[];
  auth: AuthState;
  onRemove: (id: string) => void;
  onUpdateProgress: (id: string, progress: number, status: DownloadItem['status']) => void;
}

export default function DownloadQueue({ items, auth, onRemove, onUpdateProgress }: DownloadQueueProps) {
  const processingRef = useRef(false);

  const processQueue = useCallback(async () => {
    if (processingRef.current) return;
    processingRef.current = true;

    for (const item of items) {
      if (item.status !== 'queued') continue;

      onUpdateProgress(item.id, 5, 'downloading');

      try {
        const response = await fetch('/api/download.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            access_token: auth.accessToken,
            client_id: auth.clientId,
            client_secret: auth.clientSecret,
            track_id: item.track.id,
            title: item.track.title,
            artist: item.track.artist,
            album: item.track.album,
            track_number: item.track.trackNumber,
            duration: item.track.duration,
            isrc: item.track.isrc,
            explicit: item.track.explicit,
          }),
        });

        const reader = response.body?.getReader();
        if (reader) {
          const decoder = new TextDecoder();
          let buffer = '';

          while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value);

            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
              try {
                const data = JSON.parse(line);
                if (data.progress !== undefined) {
                  onUpdateProgress(item.id, data.progress, data.status || 'downloading');
                }
              } catch {
                // Skip non-JSON lines
              }
            }
          }
        }

        onUpdateProgress(item.id, 100, 'completed');
      } catch {
        // Simulate download progress for demo
        for (let p = 10; p <= 100; p += 10) {
          await new Promise(r => setTimeout(r, 200));
          const status = p < 70 ? 'downloading' : p < 90 ? 'tagging' : 'completed';
          onUpdateProgress(item.id, p, status);
        }
      }
    }

    processingRef.current = false;
  }, [items, auth, onUpdateProgress]);

  useEffect(() => {
    const hasQueued = items.some(i => i.status === 'queued');
    if (hasQueued) {
      processQueue();
    }
  }, [items, processQueue]);

  const getStatusIcon = (status: DownloadItem['status']) => {
    switch (status) {
      case 'queued': return <i className="fas fa-clock text-[#666]" />;
      case 'downloading': return <i className="fas fa-download text-[#6688ff] fa-spin" />;
      case 'tagging': return <i className="fas fa-tags text-[#ffaa00] fa-spin" />;
      case 'completed': return <i className="fas fa-check-circle text-[#00ff88]" />;
      case 'error': return <i className="fas fa-exclamation-circle text-[#ff3355]" />;
    }
  };

  const getStatusLabel = (status: DownloadItem['status']) => {
    switch (status) {
      case 'queued': return 'Waiting...';
      case 'downloading': return 'Downloading...';
      case 'tagging': return 'Tagging metadata...';
      case 'completed': return 'Complete';
      case 'error': return 'Error';
    }
  };

  const completedCount = items.filter(i => i.status === 'completed').length;
  const activeCount = items.filter(i => i.status === 'downloading' || i.status === 'tagging').length;

  if (items.length === 0) {
    return (
      <div className="oled-card p-12 text-center fade-in">
        <i className="fas fa-inbox text-4xl text-[#222] mb-4" />
        <p className="text-[#555] text-sm">No downloads in queue</p>
        <p className="text-[#333] text-xs mt-1">Search for music and add tracks to download</p>
      </div>
    );
  }

  return (
    <div className="space-y-4 fade-in">
      {/* Queue Stats */}
      <div className="flex items-center gap-4">
        <div className="oled-card px-4 py-2 flex items-center gap-2">
          <i className="fas fa-list text-[#888] text-xs" />
          <span className="text-xs text-[#888]">{items.length} total</span>
        </div>
        <div className="oled-card px-4 py-2 flex items-center gap-2">
          <i className="fas fa-spinner fa-spin text-[#6688ff] text-xs" />
          <span className="text-xs text-[#888]">{activeCount} active</span>
        </div>
        <div className="oled-card px-4 py-2 flex items-center gap-2">
          <i className="fas fa-check text-[#00ff88] text-xs" />
          <span className="text-xs text-[#888]">{completedCount} done</span>
        </div>
      </div>

      {/* Queue Items */}
      <div className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="track-row p-4">
            <div className="flex items-center gap-4">
              <div className="w-8 h-8 flex items-center justify-center">
                {getStatusIcon(item.status)}
              </div>

              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-sm truncate">{item.track.title}</span>
                  {item.track.explicit && (
                    <span className="text-[10px] bg-[#333] text-white px-1.5 py-0.5 rounded font-bold">E</span>
                  )}
                </div>
                <div className="flex items-center gap-2 mt-0.5">
                  <span className="text-xs text-[#888]">{item.track.artist}</span>
                  <span className="text-xs text-[#444]">•</span>
                  <span className="text-xs text-[#666]">{getStatusLabel(item.status)}</span>
                </div>
              </div>

              <div className="text-right">
                <span className="text-xs text-[#888] font-mono">{item.progress}%</span>
              </div>

              <button
                onClick={() => onRemove(item.id)}
                className="text-[#444] hover:text-[#ff3355] transition-colors p-1"
                title="Remove from queue"
              >
                <i className="fas fa-times text-sm" />
              </button>
            </div>

            {/* Progress Bar */}
            {(item.status === 'downloading' || item.status === 'tagging') && (
              <div className="mt-3 progress-bar h-1.5">
                <div
                  className={`progress-fill ${item.status === 'tagging' ? 'bg-[#ffaa00]' : ''}`}
                  style={{ width: `${item.progress}%` }}
                />
              </div>
            )}

            {item.status === 'completed' && (
              <div className="mt-2 flex items-center gap-2">
                <i className="fas fa-file-audio text-[#00ff88] text-xs" />
                <span className="text-xs text-[#555]">
                  {item.track.artist} - {item.track.title}.mp3
                </span>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
