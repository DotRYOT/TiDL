export interface Track {
  id: string;
  title: string;
  artist: string;
  album: string;
  duration: number;
  trackNumber: number;
  volumeNumber: number;
  explicit: boolean;
  audioQuality: string;
  coverUrl?: string;
  isrc?: string;
  copyright?: string;
}

export interface Album {
  id: string;
  title: string;
  artist: string;
  numberOfTracks: number;
  duration: number;
  coverUrl?: string;
  releaseDate: string;
  audioQuality: string;
}

export interface DownloadItem {
  id: string;
  track: Track;
  status: 'queued' | 'downloading' | 'tagging' | 'completed' | 'error';
  progress: number;
  error?: string;
}

export interface AuthState {
  clientId: string;
  clientSecret: string;
  accessToken: string;
  isAuthenticated: boolean;
}

export interface SearchResult {
  tracks: Track[];
  albums: Album[];
}
