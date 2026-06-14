import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

/* ── Tipos ────────────────────────────────────────────────── */
interface BookingSummary {
  id: number;
  time: string;
  end_time: string | null;
  total: number;
  pet: { id: number; name: string; species: string | null; photo: string | null };
  client: { id: number; name: string } | null;
  services: { name: string; price: number }[];
  operator: { name: string } | null;
  status: string;
}
interface ExistingPayment {
  id: number;
  amount: number;
  payment_method: string;
  category: string;
  destination: 'caja' | 'banco';
  notes: string | null;
  created_at: string;
}

/* ── Constantes ──────────────────────────────────────────── */
const METHODS = [
  { value: 'Efectivo',       icon: 'payments',         dest: 'caja'  },
  { value: 'Tarjeta débito', icon: 'credit_card',      dest: 'banco' },
  { value: 'Tarjeta crédito',icon: 'credit_score',     dest: 'banco' },
  { value: 'Transferencia',  icon: 'account_balance',  dest: 'banco' },
] as const;
type MethodValue = typeof METHODS[number]['value'];

/* ── Estados de la pantalla ──────────────────────────────── */
// form      → captura de método/monto
// confirm   → "¿Se cobró exitosamente?"
// saving    → enviando al servidor
// done      → cobro registrado con éxito
type Screen = 'form' | 'confirm' | 'saving' | 'done';

/* ── Helpers ─────────────────────────────────────────────── */
function fmtMoney(n: number) {
  return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function fmtTime(iso: string) {
  return new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

/* ══════════════════════════════════════════════════════════ */
export function MobCobro() {
  const { id }   = useParams<{ id: string }>();
  const navigate = useNavigate();

  /* Datos remotos */
  const [booking,  setBooking]  = useState<BookingSummary | null>(null);
  const [existing, setExisting] = useState<ExistingPayment[]>([]);
  const [loading,  setLoading]  = useState(true);
  const [loadErr,  setLoadErr]  = useState<string | null>(null);

  /* Formulario */
  const [method,    setMethod]    = useState<MethodValue>('Efectivo');
  const [dest,      setDest]      = useState<'caja' | 'banco'>('caja');
  const [amountStr, setAmountStr] = useState('');
  const [notes,     setNotes]     = useState('');

  /* Flujo de pantallas */
  const [screen,  setScreen]  = useState<Screen>('form');
  const [saveErr, setSaveErr] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0); // cuántas veces falló el método

  /* ── Sincronizar destino al elegir método ─────────────── */
  useEffect(() => {
    const m = METHODS.find(m => m.value === method);
    if (m) setDest(m.dest);
  }, [method]);

  /* ── Carga inicial ────────────────────────────────────── */
  useEffect(() => {
    if (!id) return;
    const load = async () => {
      try {
        const [bRes, pRes] = await Promise.all([
          fetch(`/api/bookings/${id}`),
          fetch(`/api/bookings/${id}/payments`),
        ]);
        if (!bRes.ok) { setLoadErr('No se pudo cargar la cita.'); return; }
        const [b, p]: [BookingSummary, { payments: ExistingPayment[]; paid: number }] =
          await Promise.all([bRes.json(), pRes.json()]);
        setBooking(b);
        setExisting(p.payments);
        const balance = Math.max(0, b.total - p.paid);
        setAmountStr(balance > 0 ? balance.toFixed(2) : b.total.toFixed(2));
      } catch {
        setLoadErr('No se pudo conectar con el servidor.');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id]);

  /* ── Cálculos ─────────────────────────────────────────── */
  const totalPaid = useMemo(() => existing.reduce((s, p) => s + p.amount, 0), [existing]);
  const balance   = useMemo(() => Math.max(0, (booking?.total ?? 0) - totalPaid), [booking, totalPaid]);
  const amount    = parseFloat(amountStr.replace(',', '.')) || 0;

  /* ── Paso 1: ir a confirmación ────────────────────────── */
  const goConfirm = () => {
    if (amount <= 0 || !booking) return;
    setSaveErr(null);
    setScreen('confirm');
  };

  /* ── Paso 2: el pago falló — volver a form ────────────── */
  const paymentFailed = () => {
    setAttempt(a => a + 1);
    setSaveErr(null);
    setScreen('form');
    // Limpiar el método para que el usuario elija otro conscientemente
    setMethod('Efectivo');
    setDest('caja');
  };

  /* ── Paso 2: pago exitoso → registrar en BD ──────────── */
  const confirm = async () => {
    if (!booking) return;
    setScreen('saving');
    setSaveErr(null);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/payments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount:          amount,
          payment_method:  method,
          destination:     dest,
          notes:           notes.trim() || null,
          mark_completed:  true,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        setSaveErr(data.message ?? `Error ${res.status}`);
        setScreen('confirm');
        return;
      }
      setScreen('done');
    } catch {
      setSaveErr('No se pudo conectar con el servidor.');
      setScreen('confirm');
    }
  };

  /* ══════════════════════════════════════════════════════ */
  /* ── Pantalla de éxito ────────────────────────────────── */
  if (screen === 'done' && booking) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-6 px-8 text-center pb-20">
        <div className="w-24 h-24 rounded-full bg-tertiary-container flex items-center justify-center">
          <span className="material-symbols-outlined text-6xl text-on-tertiary-container"
            style={{ fontVariationSettings: "'FILL' 1" }}>check_circle</span>
        </div>
        <div>
          <p className="text-2xl font-bold text-on-surface">{fmtMoney(amount)}</p>
          <p className="text-sm text-on-surface-variant mt-1">{method} · {dest === 'caja' ? 'Caja' : 'Banco'}</p>
          <p className="text-xs text-on-surface-variant/60 mt-2">
            Cita #{booking.id} · {booking.pet.name} · completada
          </p>
        </div>
        <div className="flex flex-col gap-3 w-full max-w-xs">
          <button onClick={() => navigate(`/mascotas/${booking.pet.id}`, { replace: true })}
            className="w-full py-3.5 rounded-2xl bg-primary text-on-primary font-semibold text-sm">
            Ver ficha de {booking.pet.name}
          </button>
          <button onClick={() => navigate('/agenda', { replace: true })}
            className="w-full py-3.5 rounded-2xl bg-surface-container border border-outline-variant text-on-surface font-semibold text-sm">
            Ir a la agenda
          </button>
        </div>
      </div>
    );
  }

  /* ── Pantalla de confirmación ─────────────────────────── */
  if ((screen === 'confirm' || screen === 'saving') && booking) {
    const isSaving = screen === 'saving';
    const methodIcon = METHODS.find(m => m.value === method)?.icon ?? 'payments';
    return (
      <div className="min-h-screen bg-background flex flex-col pb-20">
        <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
          <button onClick={() => !isSaving && setScreen('form')} disabled={isSaving}
            className="p-2 rounded-full hover:bg-surface-container-high transition-colors disabled:opacity-40">
            <span className="material-symbols-outlined text-on-surface">arrow_back</span>
          </button>
          <p className="font-bold text-on-surface text-base flex-1">¿Se cobró exitosamente?</p>
        </header>

        <div className="flex flex-col items-center gap-6 px-6 pt-12">

          {/* Resumen visual del cobro intentado */}
          <div className={`w-28 h-28 rounded-full flex items-center justify-center ${
            isSaving ? 'bg-surface-container' : 'bg-secondary-container'
          }`}>
            <span className="material-symbols-outlined text-6xl text-on-secondary-container"
              style={{ fontVariationSettings: `'FILL' ${isSaving ? 0 : 1}` }}>
              {isSaving ? 'progress_activity' : methodIcon}
            </span>
          </div>

          <div className="text-center">
            <p className="text-3xl font-bold text-on-surface">{fmtMoney(amount)}</p>
            <p className="text-base text-on-surface-variant mt-1">{method}</p>
            <p className="text-sm text-on-surface-variant">
              {dest === 'caja' ? 'Se registrará en Caja' : 'Se registrará en Banco'}
            </p>
          </div>

          {attempt > 0 && (
            <div className="w-full bg-error/8 border border-error/30 rounded-2xl px-4 py-3 text-center">
              <p className="text-sm text-error font-semibold">
                {attempt === 1 ? 'Intento anterior fallido' : `${attempt} intentos fallidos`}
              </p>
              <p className="text-xs text-error/80 mt-0.5">
                Asegúrate de que el cliente tenga fondos o prueba con otro método.
              </p>
            </div>
          )}

          {saveErr && (
            <div className="w-full bg-error/10 border border-error/30 rounded-2xl px-4 py-3 flex items-start gap-2">
              <span className="material-symbols-outlined text-error text-xl shrink-0 mt-0.5"
                style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
              <p className="text-sm text-error">{saveErr}</p>
            </div>
          )}

          {!isSaving && (
            <div className="flex flex-col gap-3 w-full mt-4">
              {/* Botón principal: confirmar éxito → registrar */}
              <button onClick={confirm}
                className="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary py-4 rounded-2xl font-bold text-base active:scale-[0.98] transition-transform">
                <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>check_circle</span>
                Sí, el cobro fue exitoso
              </button>

              {/* Botón secundario: el pago falló → volver a elegir */}
              <button onClick={paymentFailed}
                className="w-full flex items-center justify-center gap-2 bg-error/10 text-error border border-error/30 py-4 rounded-2xl font-semibold text-base active:scale-[0.98] transition-transform">
                <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>cancel</span>
                No, el cobro falló — cambiar método
              </button>
            </div>
          )}

          {isSaving && (
            <p className="text-sm text-on-surface-variant animate-pulse">Registrando cobro…</p>
          )}
        </div>
      </div>
    );
  }

  /* ── Estados de carga / error inicial ────────────────── */
  if (loading) return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
    </div>
  );
  if (loadErr || !booking) return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-4 px-8 text-center">
      <span className="material-symbols-outlined text-5xl text-error/60"
        style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
      <p className="text-on-surface-variant text-sm">{loadErr ?? 'Cita no encontrada'}</p>
      <button onClick={() => navigate(-1)}
        className="flex items-center gap-2 px-4 py-2 bg-surface-container rounded-full text-sm border border-outline-variant">
        <span className="material-symbols-outlined text-base">arrow_back</span>Regresar
      </button>
    </div>
  );

  /* ══════════════════════════════════════════════════════ */
  /* ── Formulario de cobro (screen === 'form') ─────────── */
  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-32">

      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button onClick={() => navigate(-1)}
          className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <div className="flex-1 min-w-0">
          <p className="font-bold text-on-surface text-base leading-tight">
            Cobro de servicio{' '}
            <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal">MobCobro</span>
          </p>
          <p className="text-xs text-on-surface-variant">Cita #{booking.id} · {booking.pet.name}</p>
        </div>
      </header>

      <div className="flex flex-col gap-5 px-4 pt-5">

        {/* ── Aviso si hubo intentos fallidos ────────────── */}
        {attempt > 0 && (
          <div className="bg-error/8 border border-error/30 rounded-2xl px-4 py-3 flex items-center gap-3">
            <span className="material-symbols-outlined text-error text-2xl"
              style={{ fontVariationSettings: "'FILL' 1" }}>warning</span>
            <div>
              <p className="text-sm font-semibold text-error">
                {attempt === 1 ? '1 intento fallido' : `${attempt} intentos fallidos`}
              </p>
              <p className="text-xs text-error/80">Selecciona un método diferente e intenta de nuevo.</p>
            </div>
          </div>
        )}

        {/* ── Resumen de la cita ──────────────────────── */}
        <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
          <div className="flex items-center gap-3 px-4 py-3">
            <div className="w-12 h-12 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
              {booking.pet.photo
                ? <img src={booking.pet.photo} className="w-full h-full object-cover" alt="" />
                : <span className="material-symbols-outlined text-primary text-2xl"
                    style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-semibold text-on-surface">{booking.pet.name}</p>
              {booking.client && <p className="text-xs text-on-surface-variant">{booking.client.name}</p>}
            </div>
            <div className="text-right shrink-0">
              <p className="text-xs font-mono text-on-surface-variant">
                {booking.time}{booking.end_time ? ` → ${booking.end_time}` : ''}
              </p>
              {booking.operator && (
                <p className="text-xs text-on-surface-variant">{booking.operator.name.split(' ')[0]}</p>
              )}
            </div>
          </div>
          {booking.services.length > 0 && (
            <div className="border-t border-outline-variant px-4 py-3 flex flex-col gap-1">
              {booking.services.map((s, i) => (
                <div key={i} className="flex items-center justify-between">
                  <span className="text-sm text-on-surface">{s.name}</span>
                  {s.price > 0 && <span className="text-sm font-semibold">{fmtMoney(s.price)}</span>}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* ── Resumen financiero ──────────────────────── */}
        <div className="bg-surface-container rounded-2xl px-4 py-4 flex flex-col gap-2">
          <div className="flex justify-between text-sm">
            <span className="text-on-surface-variant">Total del servicio</span>
            <span className="font-semibold text-on-surface">{fmtMoney(booking.total)}</span>
          </div>
          {existing.map((p, i) => (
            <div key={i} className="flex justify-between text-sm">
              <span className="flex items-center gap-1 text-on-surface-variant">
                <span className="material-symbols-outlined text-sm text-tertiary">check</span>
                {p.category === 'liquidacion' ? 'Pago' : 'Anticipo'} · {p.payment_method}
                <span className="text-[10px] opacity-60">{fmtTime(p.created_at)}</span>
              </span>
              <span className="text-tertiary font-semibold">−{fmtMoney(p.amount)}</span>
            </div>
          ))}
          <div className="border-t border-outline-variant pt-2 mt-1 flex justify-between">
            <span className="text-sm font-bold text-on-surface">
              {existing.length > 0 ? 'Saldo pendiente' : 'A cobrar'}
            </span>
            <span className={`text-base font-bold ${balance <= 0 ? 'text-tertiary' : 'text-on-surface'}`}>
              {fmtMoney(balance)}
            </span>
          </div>
        </div>

        {/* ── Método de pago ──────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
            Método de pago
          </p>
          <div className="grid grid-cols-2 gap-2">
            {METHODS.map(m => {
              const sel = method === m.value;
              return (
                <button key={m.value} onClick={() => setMethod(m.value)}
                  className={`flex items-center gap-3 px-4 py-3.5 rounded-2xl border transition-colors text-left ${
                    sel
                      ? 'bg-primary/10 border-primary/40 text-primary'
                      : 'bg-surface-container border-outline-variant text-on-surface'
                  }`}>
                  <span className="material-symbols-outlined text-xl"
                    style={{ fontVariationSettings: `'FILL' ${sel ? 1 : 0}` }}>{m.icon}</span>
                  <span className="text-sm font-medium leading-tight">{m.value}</span>
                </button>
              );
            })}
          </div>
        </section>

        {/* ── Destino ─────────────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
            Destino
            <span className="font-normal normal-case ml-1 text-on-surface-variant/50">
              — {dest === 'caja' ? 'se registra en caja' : 'se registra en banco'}
            </span>
          </p>
          <div className="flex gap-2">
            {(['caja', 'banco'] as const).map(d => (
              <button key={d} onClick={() => setDest(d)}
                className={`flex-1 flex items-center justify-center gap-2 py-3 rounded-2xl border font-semibold text-sm transition-colors ${
                  dest === d
                    ? 'bg-secondary-container text-on-secondary-container border-secondary-fixed'
                    : 'bg-surface-container border-outline-variant text-on-surface-variant'
                }`}>
                <span className="material-symbols-outlined text-base"
                  style={{ fontVariationSettings: `'FILL' ${dest === d ? 1 : 0}` }}>
                  {d === 'caja' ? 'point_of_sale' : 'account_balance'}
                </span>
                {d === 'caja' ? 'Caja' : 'Banco'}
              </button>
            ))}
          </div>
        </section>

        {/* ── Monto ───────────────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Monto</p>
          <div className="relative">
            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-on-surface-variant">$</span>
            <input
              type="number" inputMode="decimal" step="0.01" min="0.01"
              value={amountStr}
              onChange={e => setAmountStr(e.target.value)}
              className="w-full bg-surface-container border-2 border-primary/40 rounded-2xl pl-9 pr-4 py-4 text-2xl font-bold text-on-surface outline-none focus:border-primary text-right"
            />
          </div>
          {balance > 0 && Math.abs(amount - balance) > 0.01 && (
            <button onClick={() => setAmountStr(balance.toFixed(2))}
              className="mt-1.5 text-xs text-primary flex items-center gap-1">
              <span className="material-symbols-outlined text-sm">restart_alt</span>
              Usar saldo pendiente ({fmtMoney(balance)})
            </button>
          )}
        </section>

        {/* ── Notas ───────────────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
            Notas <span className="font-normal normal-case text-on-surface-variant/50">(opcional)</span>
          </p>
          <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={2}
            placeholder="Referencia, número de transacción…"
            className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-3 text-sm text-on-surface outline-none focus:border-primary resize-none" />
        </section>

      </div>

      {/* ── FAB ─────────────────────────────────────────── */}
      <div className="fixed bottom-16 left-4 right-4 z-30">
        <button onClick={goConfirm} disabled={amount <= 0}
          className="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary py-4 rounded-2xl text-base font-bold shadow-lg active:scale-[0.98] transition-all disabled:opacity-40">
          <span className="material-symbols-outlined"
            style={{ fontVariationSettings: "'FILL' 1" }}>
            {dest === 'caja' ? 'point_of_sale' : 'account_balance'}
          </span>
          Cobrar {fmtMoney(amount)} · {dest === 'caja' ? 'Caja' : 'Banco'}
        </button>
      </div>

    </div>
  );
}
