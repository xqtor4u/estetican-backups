import React, { useState } from 'react';
import { useAuth } from './AuthContext';
import { useBranding } from './lib/useBusinessName';

export function LoginScreen() {
  const { login } = useAuth();
  const { businessName, logoUrl } = useBranding();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPass, setShowPass] = useState(false);
  const [error, setError]       = useState<string | null>(null);
  const [loading, setLoading]   = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username.trim() || !password.trim()) return;
    setLoading(true);
    setError(null);
    const err = await login(username.trim(), password);
    if (err) setError(err);
    setLoading(false);
  };

  return (
    <div className="theme-admin min-h-screen bg-background flex flex-col px-6 pt-20 pb-10">

      {/* Logo */}
      <div className="flex flex-col items-center gap-3 mb-12">
        <div className={`w-24 h-24 rounded-3xl flex items-center justify-center shadow-xl overflow-hidden ${logoUrl ? 'bg-white' : 'bg-primary'}`}>
          {logoUrl
            ? <img src={logoUrl} alt={businessName} className="w-full h-full object-contain p-2" />
            : <span className="material-symbols-outlined text-on-primary" style={{ fontSize: 56, fontVariationSettings: "'FILL' 1" }}>
                content_cut
              </span>}
        </div>
        <h1 className="text-3xl font-bold text-on-background">{businessName}</h1>
        <p className="text-base text-on-surface-variant">Acceso al sistema</p>
      </div>

      <form onSubmit={handleSubmit} className="flex flex-col gap-6">

        {/* Usuario */}
        <div className="flex flex-col gap-2">
          <label className="text-base font-medium text-on-surface">Usuario</label>
          <input
            type="text"
            value={username}
            onChange={e => setUsername(e.target.value)}
            placeholder="Nombre de usuario"
            autoComplete="off"
            autoCapitalize="none"
            autoCorrect="off"
            spellCheck={false}
            inputMode="text"
            className="bg-surface border-2 border-outline-variant rounded-2xl px-5 py-5 text-lg text-on-surface outline-none focus:border-primary placeholder:text-on-surface-variant/50"
          />
        </div>

        {/* Contraseña */}
        <div className="flex flex-col gap-2">
          <label className="text-base font-medium text-on-surface">Contraseña</label>
          <div className="relative">
            <input
              type={showPass ? 'text' : 'password'}
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              autoComplete="current-password"
              className="w-full bg-surface border-2 border-outline-variant rounded-2xl px-5 py-5 text-lg text-on-surface outline-none focus:border-primary pr-16 placeholder:text-on-surface-variant/50"
            />
            <button
              type="button"
              onClick={() => setShowPass(v => !v)}
              className="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-on-surface-variant"
            >
              <span className="material-symbols-outlined" style={{ fontSize: 28 }}>
                {showPass ? 'visibility_off' : 'visibility'}
              </span>
            </button>
          </div>
        </div>

        {/* Error */}
        {error && (
          <div className="bg-error/10 border border-error/30 rounded-2xl px-5 py-4 flex items-center gap-3">
            <span className="material-symbols-outlined text-error" style={{ fontVariationSettings: "'FILL' 1", fontSize: 24 }}>error</span>
            <p className="text-base text-error">{error}</p>
          </div>
        )}

        {/* Botón */}
        <button
          type="submit"
          disabled={loading || !username.trim() || !password.trim()}
          className="bg-primary text-on-primary rounded-2xl py-5 text-lg font-bold active:scale-95 transition-transform disabled:opacity-50 flex items-center justify-center gap-3 mt-2"
        >
          {loading
            ? <span className="material-symbols-outlined animate-spin" style={{ fontSize: 26 }}>progress_activity</span>
            : <span className="material-symbols-outlined" style={{ fontSize: 26 }}>login</span>
          }
          {loading ? 'Ingresando…' : 'Ingresar'}
        </button>

      </form>
      <p className="text-center text-[9px] font-mono text-on-surface-variant/30 mt-8">MobLog</p>
    </div>
  );
}
