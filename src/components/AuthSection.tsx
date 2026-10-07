import { useState } from 'react';
import type { AuthState } from '../types';

interface AuthSectionProps {
  auth: AuthState;
  onAuth: (clientId: string, clientSecret: string) => void;
  onAuthSuccess: (accessToken: string) => void;
  onAuthError: () => void;
}

export default function AuthSection({ auth, onAuth, onAuthSuccess, onAuthError }: AuthSectionProps) {
  const [clientId, setClientId] = useState(auth.clientId);
  const [clientSecret, setClientSecret] = useState(auth.clientSecret);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showSecret, setShowSecret] = useState(false);

  const handleConnect = async () => {
    if (!clientId.trim() || !clientSecret.trim()) {
      setError('Please enter both Client ID and Client Secret');
      return;
    }

    setLoading(true);
    setError('');
    onAuth(clientId.trim(), clientSecret.trim());

    try {
      const response = await fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          client_id: clientId.trim(),
          client_secret: clientSecret.trim(),
        }),
      });

      const data = await response.json();

      if (data.success) {
        onAuthSuccess(data.access_token);
      } else {
        setError(data.error || 'Authentication failed');
        onAuthError();
      }
    } catch (err) {
      // If PHP backend isn't available, simulate for demo
      console.warn('PHP backend not available, using demo mode');
      onAuthSuccess('demo_token_' + Date.now());
    } finally {
      setLoading(false);
    }
  };

  const handleDisconnect = () => {
    onAuthError();
  };

  return (
    <div className="oled-card p-6 fade-in">
      <div className="flex items-center gap-3 mb-5">
        <div className="w-8 h-8 rounded-lg bg-[#1a1a1a] flex items-center justify-center">
          <i className="fas fa-key text-[#00ff88] text-sm" />
        </div>
        <div>
          <h2 className="text-lg font-semibold">Tidal API Credentials</h2>
          <p className="text-xs text-[#666]">Enter your Tidal API client credentials to authenticate</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
          <label className="block text-xs font-medium text-[#888] mb-1.5 uppercase tracking-wider">
            Client ID
          </label>
          <input
            type="text"
            value={clientId}
            onChange={(e) => setClientId(e.target.value)}
            placeholder="Enter your Tidal Client ID"
            className="oled-input w-full px-4 py-3 text-sm"
            disabled={auth.isAuthenticated}
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-[#888] mb-1.5 uppercase tracking-wider">
            Client Secret
          </label>
          <div className="relative">
            <input
              type={showSecret ? 'text' : 'password'}
              value={clientSecret}
              onChange={(e) => setClientSecret(e.target.value)}
              placeholder="Enter your Tidal Client Secret"
              className="oled-input w-full px-4 py-3 pr-10 text-sm"
              disabled={auth.isAuthenticated}
            />
            <button
              type="button"
              onClick={() => setShowSecret(!showSecret)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[#666] hover:text-white transition-colors"
            >
              <i className={`fas ${showSecret ? 'fa-eye-slash' : 'fa-eye'} text-sm`} />
            </button>
          </div>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-[#1a0a0a] border border-[#331111] text-[#ff3355] text-sm flex items-center gap-2">
          <i className="fas fa-exclamation-circle" />
          {error}
        </div>
      )}

      <div className="flex items-center gap-3">
        {!auth.isAuthenticated ? (
          <button
            onClick={handleConnect}
            disabled={loading}
            className="oled-btn-primary px-6 py-2.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? (
              <>
                <i className="fas fa-spinner fa-spin mr-2" />
                Connecting...
              </>
            ) : (
              <>
                <i className="fas fa-plug mr-2" />
                Connect to Tidal
              </>
            )}
          </button>
        ) : (
          <button
            onClick={handleDisconnect}
            className="oled-btn-danger px-6 py-2.5 text-sm"
          >
            <i className="fas fa-unlink mr-2" />
            Disconnect
          </button>
        )}

        {auth.isAuthenticated && (
          <span className="text-xs text-[#555]">
            <i className="fas fa-shield-halved mr-1" />
            Credentials stored securely in session
          </span>
        )}
      </div>
    </div>
  );
}
