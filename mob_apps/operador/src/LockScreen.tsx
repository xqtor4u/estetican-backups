import React, { useEffect, useState } from 'react';
import { useAuth } from './AuthContext';
import { hasRegisteredCredential, isWebAuthnAvailable, verifyBiometric } from './lib/webauthnLock';

export function LockScreen({ onUnlock }: { onUnlock: () => void }) {
  const { user, logout } = useAuth();
  const canBiometric = !!user && isWebAuthnAvailable() && hasRegisteredCredential(user.id);

  const [mode, setMode] = useState<'biometric' | 'password'>(canBiometric ? 'biometric' : 'password');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [checking, setChecking] = useState(false);

  const tryBiometric = async () => {
    if (!user) return;
    setChecking(true);
    setError(null);
    const ok = await verifyBiometric(user.id);
    setChecking(false);
    if (ok) onUnlock();
    else setError('No se pudo verificar. Intenta de nuevo o usa tu contraseña.');
  };

  useEffect(() => {
    if (canBiometric) tryBiometric();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submitPassword = async () => {
    if (!password) return;
    setChecking(true);
    setError(null);
    try {
      const res = await fetch('/api/me/verify-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ password }),
      });
      if (res.ok) {
        setPassword('');
        onUnlock();
        return;
      }
      const data = await res.json();
      setError(data.errors ? Object.values<string[]>(data.errors).flat().join(' ') : 'Contraseña incorrecta.');
    } finally {
      setChecking(false);
    }
  };

  return (
    <div className="theme-admin fixed inset-0 z-[60] bg-background flex flex-col items-center justify-center px-6 gap-6">
      <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
        <span className="material-symbols-outlined text-3xl text-primary">lock</span>
      </div>
      <div className="text-center">
        <p className="text-sm text-on-surface-variant">Sesión bloqueada</p>
        <p className="text-lg font-semibold text-on-surface">{user?.name}</p>
      </div>

      {mode === 'biometric' && canBiometric ? (
        <div className="flex flex-col items-center gap-3 w-full max-w-[24rem] mx-auto">
          <button
            onClick={tryBiometric}
            disabled={checking}
            className="flex items-center justify-center gap-2 whitespace-nowrap bg-primary text-on-primary px-6 py-3 rounded-2xl font-semibold disabled:opacity-60"
          >
            <span className="material-symbols-outlined">fingerprint</span>
            {checking ? 'Verificando…' : 'Toca para desbloquear'}
          </button>
          <button onClick={() => { setMode('password'); setError(null); }} className="text-xs text-on-surface-variant underline">
            Usar contraseña
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-3 w-full max-w-[24rem] mx-auto">
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && submitPassword()}
            placeholder="Contraseña"
            autoFocus
            className="w-full bg-surface-container border border-outline-variant rounded-xl px-4 py-3 text-base text-on-surface outline-none focus:border-primary"
          />
          <button
            onClick={submitPassword}
            disabled={checking || !password}
            className="w-full flex items-center justify-center whitespace-nowrap bg-primary text-on-primary px-6 py-3 rounded-2xl font-semibold text-center disabled:opacity-60"
          >
            {checking ? 'Verificando…' : 'Desbloquear'}
          </button>
          {canBiometric && (
            <button onClick={() => { setMode('biometric'); setError(null); tryBiometric(); }} className="text-xs text-on-surface-variant underline">
              Usar Face ID / huella
            </button>
          )}
        </div>
      )}

      {error && <p className="text-xs text-error text-center max-w-[20rem]">{error}</p>}

      <button onClick={() => logout()} className="text-xs text-on-surface-variant/60 underline mt-4">
        Cerrar sesión
      </button>
    </div>
  );
}
