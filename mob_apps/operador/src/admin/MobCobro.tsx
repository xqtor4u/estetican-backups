import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';

/* ── Tipos ────────────────────────────────────────────────── */
interface BookingSummary {
  id: number;
  time: string;
  end_time: string | null;
  total: number;
  notes: string | null;
  pet: { id: number; name: string; species: string | null; photo: string | null };
  client: { id: number; name: string } | null;
  services: { name: string; price: number }[];
  operator: { name: string } | null;
  status: string;
}
interface ProcessNote { id: number; note: string; author: string | null; created_at: string; updated_at: string }
interface ExistingPayment {
  id: number;
  amount: number;
  payment_method: string;
  category: string;
  destination: 'caja' | 'banco';
  notes: string | null;
  created_at: string;
}
interface PaymentMethodOption {
  code: string;
  name: string;
  type: string;
  requires_reference: boolean;
  icon: string;
  dest: 'caja' | 'banco';
}

/* ── Estados de la pantalla ──────────────────────────────── */
type Screen = 'form' | 'confirm' | 'saving' | 'done';
interface DoneSummary {
  amount: number;
  methodName: string;
  dest: 'caja' | 'banco';
  bookingId: number;
  petId: number;
  petName: string;
}

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
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();

  /* Datos remotos */
  const [booking,     setBooking]     = useState<BookingSummary | null>(null);
  const [existing,    setExisting]    = useState<ExistingPayment[]>([]);
  const [apiMethods,  setApiMethods]  = useState<PaymentMethodOption[]>([]);
  const [loading,     setLoading]     = useState(true);
  const [loadErr,     setLoadErr]     = useState<string | null>(null);

  /* Notas de proceso (tomadas en MobCitaDet mientras estaba "En proceso") */
  const [processNotes,  setProcessNotes]  = useState<ProcessNote[]>([]);
  const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
  const [editingText,   setEditingText]   = useState('');
  const [savingNoteId,  setSavingNoteId]  = useState<number | null>(null);

  /* Edición rápida de "Notas de la cita" (la puesta al agendar) */
  const [editingBookingNote, setEditingBookingNote] = useState(false);
  const [bookingNoteText,    setBookingNoteText]    = useState('');
  const [savingBookingNote,  setSavingBookingNote]  = useState(false);

  /* Formulario */
  const [selectedCode, setSelectedCode] = useState<string>('');
  const [amountStr,    setAmountStr]    = useState('');
  const [reference,    setReference]    = useState('');
  const [notes,        setNotes]        = useState('');

  /* Flujo de pantallas */
  const [screen,      setScreen]      = useState<Screen>('form');
  const [saveErr,     setSaveErr]     = useState<string | null>(null);
  const [attempt,     setAttempt]     = useState(0);
  const [doneSummary, setDoneSummary] = useState<DoneSummary | null>(null);

  /* ── Carga inicial ────────────────────────────────────── */
  useEffect(() => {
    if (!id) return;
    const load = async () => {
      try {
        const [bRes, pRes, mRes, nRes] = await Promise.all([
          fetch(`/api/bookings/${id}`),
          fetch(`/api/bookings/${id}/payments`),
          fetch('/api/payment-methods'),
          fetch(`/api/bookings/${id}/process-notes`),
        ]);
        if (!bRes.ok) { setLoadErr('No se pudo cargar la cita.'); return; }

        const [b, p, m, n]: [
          BookingSummary,
          { payments: ExistingPayment[]; paid: number },
          PaymentMethodOption[],
          ProcessNote[]
        ] = await Promise.all([bRes.json(), pRes.json(), mRes.json(), nRes.ok ? nRes.json() : Promise.resolve([])]);

        setBooking(b);
        setExisting(p.payments);
        setApiMethods(m);
        setProcessNotes(n);
        setSelectedCode(m[0]?.code ?? '');
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

  /* ── Método seleccionado ──────────────────────────────── */
  const selectedMethod = useMemo(
    () => apiMethods.find(m => m.code === selectedCode) ?? null,
    [apiMethods, selectedCode]
  );

  /* ── Cálculos ─────────────────────────────────────────── */
  const totalPaid = useMemo(() => existing.reduce((s, p) => s + p.amount, 0), [existing]);
  const balance   = useMemo(() => Math.max(0, (booking?.total ?? 0) - totalPaid), [booking, totalPaid]);
  const amount    = parseFloat(amountStr.replace(',', '.')) || 0;

  /* ── Editar "Notas de la cita" (la puesta al agendar) ─── */
  const startEditBookingNote = () => { setBookingNoteText(booking?.notes ?? ''); setEditingBookingNote(true); };
  const cancelEditBookingNote = () => { setEditingBookingNote(false); setBookingNoteText(''); };
  const saveBookingNote = async () => {
    if (!booking) return;
    setSavingBookingNote(true);
    try {
      const res = await fetch(`/api/bookings/${booking.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notes: bookingNoteText.trim() || null }),
      });
      const data = await res.json();
      if (res.ok) {
        setBooking(prev => prev ? { ...prev, notes: data.notes } : prev);
        setEditingBookingNote(false);
      }
    } catch { /* silencioso: no bloquea el flujo de cobro */ }
    setSavingBookingNote(false);
  };

  /* ── Editar/completar una nota de proceso ─────────────── */
  const startEditNote = (n: ProcessNote) => { setEditingNoteId(n.id); setEditingText(n.note); };
  const cancelEditNote = () => { setEditingNoteId(null); setEditingText(''); };
  const saveEditNote = async () => {
    if (!booking || editingNoteId == null || !editingText.trim()) return;
    setSavingNoteId(editingNoteId);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/process-notes/${editingNoteId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note: editingText.trim() }),
      });
      const data = await res.json();
      if (res.ok) {
        setProcessNotes(prev => prev.map(n => n.id === editingNoteId ? data : n));
        setEditingNoteId(null);
        setEditingText('');
      }
    } catch { /* silencioso: no bloquea el flujo de cobro */ }
    setSavingNoteId(null);
  };

  /* ── Paso 1: ir a confirmación ────────────────────────── */
  const goConfirm = () => {
    if (amount <= 0 || !booking || !selectedMethod) return;
    setSaveErr(null);
    setScreen('confirm');
  };

  /* ── Paso 2: el pago falló — volver a form ────────────── */
  const paymentFailed = () => {
    setAttempt(a => a + 1);
    setSaveErr(null);
    setScreen('form');
    setSelectedCode(apiMethods[0]?.code ?? '');
    setReference('');
  };

  /* ── Paso 2: pago exitoso → registrar en BD ──────────── */
  const confirm = async () => {
    if (!booking || !selectedMethod) return;
    setScreen('saving');
    setSaveErr(null);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/payments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount:               amount,
          payment_method_code:  selectedMethod.code,
          reference:            reference.trim() || null,
          notes:                notes.trim() || null,
          mark_completed:       true,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        setSaveErr(data.message ?? `Error ${res.status}`);
        setScreen('confirm');
        return;
      }
      setDoneSummary({
        amount,
        methodName: selectedMethod.name,
        dest:       selectedMethod.dest,
        bookingId:  booking.id,
        petId:      booking.pet.id,
        petName:    booking.pet.name,
      });
      setScreen('done');
    } catch {
      setSaveErr('No se pudo conectar con el servidor.');
      setScreen('confirm');
    }
  };

  /* ══════════════════════════════════════════════════════ */
  /* ── Pantalla de éxito ────────────────────────────────── */
  if (screen === 'done' && doneSummary) {
    const ds = doneSummary;
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-8 px-6 text-center">
        <div className="w-24 h-24 rounded-full bg-tertiary-container flex items-center justify-center">
          <span className="material-symbols-outlined text-on-tertiary-container"
            style={{ fontSize: 56, fontVariationSettings: "'FILL' 1" }}>check_circle</span>
        </div>

        <div className="flex flex-col items-center gap-1">
          <p className="text-3xl font-bold text-on-surface">{fmtMoney(ds.amount)}</p>
          <p className="text-base text-on-surface-variant">
            {ds.methodName} · {ds.dest === 'caja' ? 'Caja' : 'Banco'}
          </p>
          <p className="text-sm text-on-surface-variant/60 mt-1">
            Cita #{ds.bookingId} · {ds.petName} · completada
          </p>
        </div>

        <div className="flex flex-col gap-3 w-full">
          <button
            onClick={() => navigate(`/mascotas/${ds.petId}`, { replace: true })}
            className="w-full py-4 rounded-2xl bg-primary text-on-primary font-semibold text-base">
            Ver ficha de {ds.petName}
          </button>
          <button
            onClick={() => navigate('/agenda', { replace: true })}
            className="w-full py-4 rounded-2xl bg-surface-container border border-outline-variant text-on-surface font-semibold text-base">
            Ir a la agenda
          </button>
        </div>
      </div>
    );
  }

  /* ── Pantalla de confirmación ─────────────────────────── */
  if ((screen === 'confirm' || screen === 'saving') && booking && selectedMethod) {
    const isSaving   = screen === 'saving';
    const methodIcon = selectedMethod.icon;
    return (
      <div className="min-h-screen bg-background flex flex-col pb-20">
        <ScreenHeader
          title="¿Se cobró exitosamente?"
          noCrumbs
          onBack={() => !isSaving && setScreen('form')}
        />

        <div className="flex flex-col items-center gap-6 px-6 pt-12">

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
            <p className="text-base text-on-surface-variant mt-1">{selectedMethod.name}</p>
            <p className="text-sm text-on-surface-variant">
              {selectedMethod.dest === 'caja' ? 'Se registrará en Caja' : 'Se registrará en Banco'}
            </p>
            {reference && (
              <p className="text-xs text-on-surface-variant/70 mt-1">Ref: {reference}</p>
            )}
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
              <button onClick={confirm}
                className="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary py-4 rounded-2xl font-bold text-base active:scale-[0.98] transition-transform">
                <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>check_circle</span>
                Sí, el cobro fue exitoso
              </button>
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

      <ScreenHeader
        title="Cobro de servicio"
        screenTag="MobCobro"
        subtitle={`Cita #${booking.id} · ${booking.pet.name}`}
        onBack={() => navigate(-1)}
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

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

        {/* ── Notas de la cita (tomadas al agendar, editable aquí también) ── */}
        <div className="bg-surface-container-low border border-outline-variant rounded-2xl px-4 py-3">
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1">Notas de la cita</p>
          {editingBookingNote ? (
            <div className="flex flex-col gap-2">
              <textarea
                value={bookingNoteText}
                onChange={e => setBookingNoteText(e.target.value)}
                rows={3}
                autoFocus
                placeholder="Instrucciones especiales…"
                className="w-full bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-primary"
              />
              <div className="flex gap-2">
                <button onClick={cancelEditBookingNote}
                  className="flex-1 py-2 rounded-xl text-xs border border-outline-variant text-on-surface-variant">
                  Cancelar
                </button>
                <button onClick={saveBookingNote} disabled={savingBookingNote}
                  className="flex-1 py-2 rounded-xl text-xs font-semibold bg-primary text-on-primary disabled:opacity-50">
                  {savingBookingNote ? 'Guardando…' : 'Guardar'}
                </button>
              </div>
            </div>
          ) : (
            <button onClick={startEditBookingNote} className="w-full text-left">
              {booking.notes ? (
                <p className="text-sm text-on-surface leading-relaxed">{booking.notes}</p>
              ) : (
                <p className="text-sm text-on-surface-variant/60 italic">Sin notas — toca para agregar</p>
              )}
              <span className="text-[11px] text-primary mt-1 inline-flex items-center gap-1">
                <span className="material-symbols-outlined text-xs">edit</span>Editar
              </span>
            </button>
          )}
        </div>

        {/* ── Notas del proceso (tomadas durante el servicio) ── */}
        {processNotes.length > 0 && (
          <section>
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
              Notas del proceso <span className="font-normal normal-case text-on-surface-variant/50">(puedes completarlas antes de cobrar)</span>
            </p>
            <div className="flex flex-col gap-2">
              {processNotes.map(n => (
                <div key={n.id} className="bg-tertiary-container/20 border border-tertiary-fixed-dim/40 rounded-2xl px-4 py-3">
                  {editingNoteId === n.id ? (
                    <div className="flex flex-col gap-2">
                      <textarea
                        value={editingText}
                        onChange={e => setEditingText(e.target.value)}
                        rows={3}
                        autoFocus
                        className="w-full bg-background border border-tertiary/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-tertiary"
                      />
                      <div className="flex gap-2">
                        <button onClick={cancelEditNote}
                          className="flex-1 py-2 rounded-xl text-xs border border-outline-variant text-on-surface-variant">
                          Cancelar
                        </button>
                        <button onClick={saveEditNote} disabled={savingNoteId === n.id || !editingText.trim()}
                          className="flex-1 py-2 rounded-xl text-xs font-semibold bg-tertiary text-on-tertiary disabled:opacity-50">
                          {savingNoteId === n.id ? 'Guardando…' : 'Guardar'}
                        </button>
                      </div>
                    </div>
                  ) : (
                    <button onClick={() => startEditNote(n)} className="w-full text-left">
                      <p className="text-sm text-on-surface leading-relaxed">{n.note}</p>
                      <p className="text-[11px] text-on-surface-variant mt-1.5 flex items-center gap-1">
                        {n.author ? `${n.author} · ` : ''}
                        {new Date(n.created_at.replace(' ', 'T')).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                        <span className="material-symbols-outlined text-xs ml-auto">edit</span>
                      </p>
                    </button>
                  )}
                </div>
              ))}
            </div>
          </section>
        )}

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
          {apiMethods.length === 0 ? (
            <p className="text-sm text-on-surface-variant/60 italic">
              No hay métodos de pago configurados.
            </p>
          ) : (
            <div className="grid grid-cols-2 gap-2">
              {apiMethods.map(m => {
                const sel = selectedCode === m.code;
                return (
                  <button key={m.code} onClick={() => { setSelectedCode(m.code); setReference(''); }}
                    className={`flex items-center gap-3 px-4 py-3.5 rounded-2xl border transition-colors text-left ${
                      sel
                        ? 'bg-primary/10 border-primary/40 text-primary'
                        : 'bg-surface-container border-outline-variant text-on-surface'
                    }`}>
                    <span className="material-symbols-outlined text-xl"
                      style={{ fontVariationSettings: `'FILL' ${sel ? 1 : 0}` }}>{m.icon}</span>
                    <span className="text-sm font-medium leading-tight">{m.name}</span>
                  </button>
                );
              })}
            </div>
          )}
        </section>

        {/* ── Destino (indicativo, no editable) ──────── */}
        {selectedMethod && (
          <div className="flex items-center gap-2 px-3 py-2 bg-surface-container/60 rounded-xl">
            <span className="material-symbols-outlined text-base text-on-surface-variant"
              style={{ fontVariationSettings: "'FILL' 1" }}>
              {selectedMethod.dest === 'caja' ? 'point_of_sale' : 'account_balance'}
            </span>
            <p className="text-xs text-on-surface-variant">
              Se registrará en <span className="font-semibold">
                {selectedMethod.dest === 'caja' ? 'Caja' : 'Banco'}
              </span>
            </p>
          </div>
        )}

        {/* ── Referencia (cuando el método la requiere) ─ */}
        {selectedMethod?.requires_reference && (
          <section>
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
              Referencia <span className="font-normal normal-case text-on-surface-variant/50">(número de transacción, aprobación…)</span>
            </p>
            <input
              type="text"
              value={reference}
              onChange={e => setReference(e.target.value)}
              placeholder="Ej. TXN-0012345"
              className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-3 text-sm text-on-surface outline-none focus:border-primary"
            />
          </section>
        )}

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

        {/* ── Notas del cobro ───────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
            Notas del cobro <span className="font-normal normal-case text-on-surface-variant/50">(opcional)</span>
          </p>
          <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={2}
            placeholder="Observaciones adicionales…"
            className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-3 text-sm text-on-surface outline-none focus:border-primary resize-none" />
        </section>

      </div>

      {/* ── FAB ─────────────────────────────────────────── */}
      <div className="fixed bottom-16 left-4 right-4 z-30">
        <button onClick={goConfirm} disabled={amount <= 0 || !selectedMethod}
          className="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary py-4 rounded-2xl text-base font-bold shadow-lg active:scale-[0.98] transition-all disabled:opacity-40">
          <span className="material-symbols-outlined"
            style={{ fontVariationSettings: "'FILL' 1" }}>
            {selectedMethod?.dest === 'caja' ? 'point_of_sale' : 'account_balance'}
          </span>
          Cobrar {fmtMoney(amount)}{selectedMethod ? ` · ${selectedMethod.dest === 'caja' ? 'Caja' : 'Banco'}` : ''}
        </button>
      </div>

    </div>
  );
}
