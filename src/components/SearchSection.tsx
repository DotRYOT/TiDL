import { useState } from 'react';
import type { AuthState, Track } from '../types';

interface SearchSectionProps {
  auth: AuthState;
  results: Track[];
  onResultsChange: (results: Track[]) => void;
  onAddToQueue: (track: Track) => void;
  onDownloadAll: (tracks: Track[]) => void;
}

const DEMO_TRACKS: Track[] = [
  {
    id: '1',
    title: 'Blinding Lights',
    artist: 'The Weeknd',
    album: 'After Hours',
    duration: 200,
    trackNumber: 9,
    volumeNumber: 1,
    explicit: false,
    audioQuality: 'HI_RES_LOSSLESS',
    isrc: 'USUG11903491',
  },
  {
    id: '2',
    title: 'Starboy',
    artist: 'The Weeknd',
    album: 'Starboy',
    duration: 230,
    trackNumber: 1,
    volumeNumber: 1,
    explicit: false,
    audioQuality: 'HI_RES_LOSSLESS',
    isrc: 'USUG11600994',
  },
  {
    id: '3',
    title: 'Bohemian Rhapsody',
    artist: 'Queen',
    album: 'A Night at the Opera',
    duration: 354,
    trackNumber: 11,
    volumeNumber: 1,
    explicit: false,
    audioQuality: 'LOSSLESS',
    isrc: 'GBAYE7500101',
  },
  {
    id: '4',
    title: 'Hotel California',
    artist: 'Eagles',
    album: 'Hotel California',
    duration: 391,
    trackNumber: 1,
    volumeNumber: 1,
    explicit: false,
    audioQuality: 'LOSSLESS',
    isrc: 'USAS17600101',
  },
  {
    id: '5',
    title: 'Lose Yourself',
    artist: 'Eminem',
    album: '8 Mile OST',
    duration: 326,
    trackNumber: 1,
    volumeNumber: 1,
    explicit: true,
    audioQuality: 'HI_RES_LOSSLESS',
    isrc: 'USIR10200552',
  },
];

export default function SearchSection({ auth, results, onResultsChange, onAddToQueue, onDownloadAll }: SearchSectionProps) {
  const [query, setQuery] = useState('');
  const [searchType, setSearchType] = useState<'tracks' | 'albums'>('tracks');
  const [loading, setLoading] = useState(false);
  const [selectedTracks, setSelectedTracks] = useState<Set<string>>(new Set());

  const handleSearch = async () => {
    if (!query.trim()) return;
    setLoading(true);

    try {
      const response = await fetch('/api/search.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          access_token: auth.accessToken,
          query: query.trim(),
          type: searchType,
          limit: 20,
        }),
      });

      const data = await response.json();

      if (data.success) {
        onResultsChange(data.tracks || []);
      } else {
        // Use demo data if backend unavailable
        onResultsChange(DEMO_TRACKS.filter(t =>
          t.title.toLowerCase().includes(query.toLowerCase()) ||
          t.artist.toLowerCase().includes(query.toLowerCase())
        ));
      }
    } catch {
      // Demo mode - filter demo tracks
      const filtered = DEMO_TRACKS.filter(t =>
        t.title.toLowerCase().includes(query.toLowerCase()) ||
        t.artist.toLowerCase().includes(query.toLowerCase()) ||
        t.album.toLowerCase().includes(query.toLowerCase())
      );
      onResultsChange(filtered.length > 0 ? filtered : DEMO_TRACKS);
    } finally {
      setLoading(false);
    }
  };

  const toggleTrack = (id: string) => {
    setSelectedTracks(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const selectAll = () => {
    if (selectedTracks.size === results.length) {
      setSelectedTracks(new Set());
    } else {
      setSelectedTracks(new Set(results.map(t => t.id)));
    }
  };

  const formatDuration = (seconds: number) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
  };

  const getQualityBadge = (quality: string) => {
    switch (quality) {
      case 'HI_RES_LOSSLESS': return { label: 'MAX', color: 'bg-[#00ff88] text-black' };
      case 'LOSSLESS': return { label: 'HI-FI', color: 'bg-[#1a3a2a] text-[#00ff88]' };
      case 'HIGH': return { label: 'HIGH', color: 'bg-[#1a1a2a] text-[#6688ff]' };
      default: return { label: 'LOW', color: 'bg-[#1a1a1a] text-[#888]' };
    }
  };

  return (
    <div className="space-y-4 fade-in">
      {/* Search Bar */}
      <div className="oled-card p-4">
        <div className="flex gap-3">
          <div className="flex-1 relative">
            <i className="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[#555]" />
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              placeholder="Search for tracks, artists, or albums..."
              className="oled-input w-full pl-11 pr-4 py-3 text-sm"
            />
          </div>
          <select
            value={searchType}
            onChange={(e) => setSearchType(e.target.value as 'tracks' | 'albums')}
            className="oled-input px-4 py-3 text-sm min-w-[120px]"
          >
            <option value="tracks">Tracks</option>
            <option value="albums">Albums</option>
          </select>
          <button
            onClick={handleSearch}
            disabled={loading || !query.trim()}
            className="oled-btn-primary px-6 py-3 text-sm disabled:opacity-50"
          >
            {loading ? <i className="fas fa-spinner fa-spin" /> : <i className="fas fa-search" />}
          </button>
        </div>
      </div>

      {/* Results */}
      {results.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <button
                onClick={selectAll}
                className="oled-btn-secondary px-3 py-1.5 text-xs"
              >
                {selectedTracks.size === results.length ? 'Deselect All' : 'Select All'}
              </button>
              <span className="text-xs text-[#666]">
                {results.length} result{results.length !== 1 ? 's' : ''} • {selectedTracks.size} selected
              </span>
            </div>
            {selectedTracks.size > 0 && (
              <button
                onClick={() => onDownloadAll(results.filter(t => selectedTracks.has(t.id)))}
                className="oled-btn-primary px-4 py-2 text-xs"
              >
                <i className="fas fa-download mr-2" />
                Download Selected ({selectedTracks.size})
              </button>
            )}
          </div>

          <div className="space-y-2">
            {results.map((track) => {
              const quality = getQualityBadge(track.audioQuality);
              return (
                <div
                  key={track.id}
                  className={`track-row p-4 flex items-center gap-4 ${
                    selectedTracks.has(track.id) ? 'border-[#00ff88]!' : ''
                  }`}
                >
                  <button
                    onClick={() => toggleTrack(track.id)}
                    className={`w-5 h-5 rounded border-2 flex items-center justify-center transition-all ${
                      selectedTracks.has(track.id)
                        ? 'bg-[#00ff88] border-[#00ff88]'
                        : 'border-[#333] hover:border-[#555]'
                    }`}
                  >
                    {selectedTracks.has(track.id) && (
                      <i className="fas fa-check text-black text-[10px]" />
                    )}
                  </button>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-sm truncate">{track.title}</span>
                      {track.explicit && (
                        <span className="text-[10px] bg-[#333] text-white px-1.5 py-0.5 rounded font-bold">
                          E
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 mt-0.5">
                      <span className="text-xs text-[#888]">{track.artist}</span>
                      <span className="text-xs text-[#444]">•</span>
                      <span className="text-xs text-[#666]">{track.album}</span>
                    </div>
                  </div>

                  <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${quality.color}`}>
                    {quality.label}
                  </span>

                  <span className="text-xs text-[#555] w-12 text-right font-mono">
                    {formatDuration(track.duration)}
                  </span>

                  <button
                    onClick={() => onAddToQueue(track)}
                    className="oled-btn-secondary px-3 py-1.5 text-xs"
                    title="Add to download queue"
                  >
                    <i className="fas fa-plus" />
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Empty state */}
      {results.length === 0 && !loading && (
        <div className="oled-card p-12 text-center">
          <i className="fas fa-compact-disc text-4xl text-[#222] mb-4" />
          <p className="text-[#555] text-sm">Search for music to get started</p>
          <p className="text-[#333] text-xs mt-1">Enter a song name, artist, or album title</p>
        </div>
      )}
    </div>
  );
}
