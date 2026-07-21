import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ScreenHeader } from '../ScreenHeader';

interface Unavailability {
  id: number;
  starts_at: string;
  ends_at: string;
  reason: string | null;
}

type PageState =
  | { status: 'loading' }
  | { status: 'forbidden'; message: string }
  | { status: 'ready'; items: Unavailability[] }
  | { status: 'error'; message: string };

function fmtDateTime(iso: string) {
  return new Date(iso.replace(' ', 'T')).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  });
}

interface FormSheetProps {
  onClose: () => void;
  onSaved: (u: Unavailability) => void;
}

function fmtRange(startsAt: string, endsAt: string) {
  const fmt = (v: string) => new Date(v).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  return `${fmt(startsAt)} → ${fmt(endsAt)}`;
}

function UnavailabilityFormSheet({ onClose, onSaved }: FormSheetProps) {
  const [step, setStep] = useState<'form' | 'confirm'>('form');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [reason, setReason] = useState('');
  const [password, setPassword] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canContinue = startsAt !== '' && endsAt !== '';
  const canConfirm = password !== '' && !saving;

  const handleConfirm = async () => {
    if (!canConfirm) return;
    setSaving(true);
    setError(null);
    try {
      const verifyRes = await fetch('/api/me/verify-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ password }),
      });
      if (!verifyRes.ok) {
        const verifyData = await verifyRes.json().catch(() => ({}));
        throw new Error(verifyData.errors ? Object.values<string[]>(verifyData.errors).flat().join(' ') : 'Contraseña incorrecta.');
      }

      const res = await fetch('/api/me/unavailabilities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          starts_at: startsAt.replace('T', ' '),
          ends_at: endsAt.replace('T', ' '),
          reason: reason.trim() || null,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message ?? 'No se pudo guardar');
      }
      onSaved(data as Unavailability);
    } catch (e: any) {
      setError(e.message ?? 'No se pudo guardar');
      setSaving(false);
    }
  };

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl"
           style={{ maxHeight: '90vh', overflowY: 'auto' }}>
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>

        {step === 'form' ? (
          <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
            <h2 className="text-base font-semibold text-on-surface">Nuevo bloqueo</h2>

            <div className="flex flex-col gap-1">
              <label className="text-xs text-on-surface-variant font-medium">Desde</label>
              <input
                type="datetime-local"
                value={startsAt}
                onChange={e => setStartsAt(e.target.value)}
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
              />
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-xs text-on-surface-variant font-medium">Hasta</label>
              <input
                type="datetime-local"
                value={endsAt}
                onChange={e => setEndsAt(e.target.value)}
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
              />
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-xs text-on-surface-variant font-medium">Motivo (opcional)</label>
              <input
                type="text"
                value={reason}
                onChange={e => setReason(e.target.value)}
                placeholder="Vacaciones, permiso..."
                maxLength={255}
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
              />
            </div>

            <button
              onClick={() => setStep('confirm')}
              disabled={!canContinue}
              className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform disabled:opacity-40"
            >
              Continuar
            </button>
          </div>
        ) : (
          <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
            <h2 className="text-base font-semibold text-on-surface">Confirma con tu contraseña</h2>
            <p className="text-sm text-on-surface-variant bg-surface-container rounded-xl px-3 py-2.5">
              Vas a bloquear: <strong className="text-on-surface">{fmtRange(startsAt, endsAt)}</strong>
              {reason.trim() && <> — {reason.trim()}</>}
            </p>
            <p className="text-xs text-on-surface-variant">
              Es un paso extra para evitar bloqueos accidentales — no cambia tu contraseña ni tu sesión.
            </p>

            <div className="flex flex-col gap-1">
              <label className="text-xs text-on-surface-variant font-medium">Contraseña</label>
              <input
                type="password"
                value={password}
                onChange={e => setPassword(e.target.value)}
                autoFocus
                className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
              />
            </div>

            {error && (
              <p className="text-sm text-error bg-error/10 rounded-xl px-3 py-2">{error}</p>
            )}

            <div className="flex gap-2">
              <button
                onClick={() => { setStep('form'); setError(null); }}
                disabled={saving}
                className="flex-1 py-3.5 rounded-2xl font-semibold text-sm border border-outline-variant text-on-surface active:scale-[0.98] transition-transform disabled:opacity-40"
              >
                Atrás
              </button>
              <button
                onClick={handleConfirm}
                disabled={!canConfirm}
                className="flex-1 py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform disabled:opacity-40"
              >
                {saving ? 'Confirmando…' : 'Confirmar bloqueo'}
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

export function MobUnavailability() {
  const navigate = useNavigate();
  const [page, setPage] = useState<PageState>({ status: 'loading' });
  const [showForm, setShowForm] = useState(false);

  const load = async () => {
    setPage({ status: 'loading' });
    try {
      const res = await fetch('/api/me/unavailabilities');
      const data = await res.json();
      if (!res.ok) {
        setPage({ status: 'forbidden', message: data.message ?? 'No tienes permiso para gestionar tu disponibilidad.' });
        return;
      }
      setPage({ status: 'ready', items: data as Unavailability[] });
    } catch {
      setPage({ status: 'error', message: 'No se pudo conectar con el servidor.' });
    }
  };

  useEffect(() => { load(); }, []);

  const remove = async (id: number) => {
    if (page.status !== 'ready') return;
    const prevItems = page.items;
    setPage({ status: 'ready', items: prevItems.filter(i => i.id !== id) });
    const res = await fetch(`/api/me/unavailabilities/${id}`, { method: 'DELETE' });
    if (!res.ok) {
      setPage({ status: 'ready', items: prevItems });
    }
  };

  const handleSaved = (u: Unavailability) => {
    setShowForm(false);
    setPage(prev => prev.status === 'ready' ? { status: 'ready', items: [u, ...prev.items] } : prev);
  };

  return (
    <div className="min-h-screen bg-background pb-24">
      <ScreenHeader
        title="Mi disponibilidad"
        screenTag="MobUnavail"
        onBack={() => navigate(-1)}
        noCrumbs
      />

      {page.status === 'loading' && (
        <div className="flex items-center justify-center pt-24">
          <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
        </div>
      )}

      {page.status === 'error' && (
        <div className="flex flex-col items-center gap-4 px-6 pt-16 text-center">
          <span className="material-symbols-outlined text-5xl text-error">error</span>
          <p className="text-sm text-on-surface-variant">{page.message}</p>
          <button
            onClick={load}
            className="px-6 py-2.5 rounded-2xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform"
          >
            Reintentar
          </button>
        </div>
      )}

      {page.status === 'forbidden' && (
        <div className="flex flex-col items-center gap-4 px-6 pt-16 text-center">
          <span className="material-symbols-outlined text-5xl text-on-surface-variant">lock</span>
          <p className="text-sm text-on-surface-variant">{page.message}</p>
        </div>
      )}

      {page.status === 'ready' && (
        <>
          <p className="mx-4 mt-4 text-xs text-on-surface-variant">
            Vacaciones o permisos — mientras estén registrados aquí, no se te podrá agendar ninguna cita en ese periodo.
          </p>

          <div className="mx-4 mt-3">
            {page.items.length === 0 ? (
              <div className="bg-surface-container rounded-2xl px-4 py-6 text-center">
                <span className="material-symbols-outlined text-3xl text-on-surface-variant">event_available</span>
                <p className="text-sm text-on-surface-variant mt-2">No tienes bloqueos registrados</p>
              </div>
            ) : (
              <div className="bg-surface-container rounded-2xl overflow-hidden">
                {page.items.map((item, i) => (
                  <div
                    key={item.id}
                    className={`flex items-center gap-3 px-4 py-3 ${i < page.items.length - 1 ? 'border-b border-outline-variant' : ''}`}
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-on-surface">
                        {fmtDateTime(item.starts_at)} → {fmtDateTime(item.ends_at)}
                      </p>
                      {item.reason && (
                        <p className="text-xs text-on-surface-variant truncate">{item.reason}</p>
                      )}
                    </div>
                    <button
                      onClick={() => remove(item.id)}
                      className="p-2 rounded-full text-error active:bg-error/10 transition-colors shrink-0"
                    >
                      <span className="material-symbols-outlined text-xl">delete</span>
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <button
            onClick={() => setShowForm(true)}
            className="fixed bottom-20 right-4 z-30 w-14 h-14 rounded-full bg-primary text-on-primary shadow-lg flex items-center justify-center active:scale-95 transition-transform"
          >
            <span className="material-symbols-outlined text-2xl">add</span>
          </button>

          {showForm && (
            <UnavailabilityFormSheet onClose={() => setShowForm(false)} onSaved={handleSaved} />
          )}
        </>
      )}
    </div>
  );
}
