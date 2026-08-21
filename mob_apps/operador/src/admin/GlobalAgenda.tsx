import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { clearNavCrumbs, setCitaPresetDate, setCitaPresetOperator, setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import {
  CalView, toDateStr, addDays, fmtDate, shiftAnchor, rangeLabel, weekDays, monthGridDays, groupByDateMap, expandDateRange,
  agendaAlertKind, AGENDA_ALERT_LABEL,
} from './agendaViews';
import { WeekGrid, MonthGrid } from './AgendaCalendarGrid';
import { WhatsAppMessageSheet } from '../WhatsAppMessageSheet';

interface Operator { id: number; name: string; role: string | null; photo_url: string | null }
interface Branch   { id: number; name: string }
interface Booking  {
  id: number;
  date: string;
  time: string;
  end_time: string | null;
  duration_minutes: number | null;
  status: string;
  notes: string | null;
  total: number | null;
  pet: { id: number; name: string; species: string | null; breed: string | null; photo: string | null };
  client: { id: number; name: string; phone: string | null } | null;
  services: { id: number | null; name: string; type: string | null }[];
  operators: { id: number; name: string; photo_url: string | null }[];
}
interface Vencida {
  id: number;
  time: string;
  date_label: string;
  status: string;
  reason: 'stale_day' | 'not_started' | 'overdue' | 'future' | 'pending_balance';
  balance: number;
  pet: { id: number; name: string; photo: string | null };
  client: { id: number; name: string } | null;
  services: { name: string }[];
}
const VENCIDA_REASON_LABEL: Record<Vencida['reason'], string> = {
  stale_day:       'Sin resolver desde un día anterior',
  not_started:     'Programada — nunca se inició',
  overdue:         'En proceso, ya pasó su hora estimada de cierre',
  future:          'En proceso con fecha programada a futuro',
  pending_balance: 'Completada — saldo pendiente de cobro',
};
interface Unavailability {
  operator_id: number;
  operator_name: string | null;
  starts_at: string;
  ends_at: string;
  reason: string | null;
}

const STATUS_LABEL: Record<string, string> = {
  scheduled:     'Programada',
  work_order:    'En proceso',
  completed:     'Completada',
  no_show:       'No se presentó',
  unfulfillable: 'No realizada',
};
const STATUS_COLOR: Record<string, string> = {
  scheduled:     'bg-primary-container text-on-primary-container border-primary-fixed-dim',
  work_order:    'bg-secondary-container text-on-secondary-container border-secondary-fixed',
  completed:     'bg-tertiary-container text-on-tertiary-container border-tertiary-fixed-dim',
  no_show:       'bg-error-container text-on-error-container border-error',
  // Ámbar sólido a propósito, no un token del tema: mismo color que ya usamos
  // en los puntos de semana/mes y en la alerta parpadeante — "no realizada por
  // otro motivo" no es culpa del cliente ni del operador, pero tampoco es un
  // "en proceso" normal (verde/secondary), así que no debe compartir su color.
  unfulfillable: 'bg-amber-500 text-white border-amber-500',
};

export function GlobalAgenda() {
  const navigate = useNavigate();
  const { user } = useAuth();

  const [today]        = useState(() => new Date());
  const [searchParams] = useSearchParams();
  const dateInputRef = useRef<HTMLInputElement>(null);
  // Si llegamos con ?date=YYYY-MM-DD (ej. al volver de crear una cita), arrancar en ese
  // día en vez de HOY — para que el operador pueda comprobar de un vistazo que la cita
  // quedó agendada donde correspondía, sin tener que navegar manualmente hasta ahí.
  const [selectedDate, setSelectedDate] = useState<Date>(() => {
    const dateParam = searchParams.get('date');
    if (dateParam) {
      const [y, m, d] = dateParam.split('-').map(Number);
      if (y && m && d) return new Date(y, m - 1, d);
    }
    return new Date();
  });
  const [calView,      setCalView]      = useState<CalView>('day');
  const [operators,    setOperators]    = useState<Operator[]>([]);
  const [branches,     setBranches]     = useState<Branch[]>([]);
  const [bookings,     setBookings]     = useState<Booking[]>([]);
  const [blocked,      setBlocked]      = useState<Unavailability[]>([]);
  const [loadingAg,    setLoadingAg]    = useState(true);
  const [filterOp,     setFilterOp]     = useState<number | null>(null);
  const [vencidas,          setVencidas]          = useState<Vencida[]>([]);
  const [showVencidasModal, setShowVencidasModal]  = useState(false);
  const [showAddMenu,       setShowAddMenu]        = useState(false);
  const [graceMinutes,      setGraceMinutes]        = useState(15);
  const [startingCitaId,    setStartingCitaId]      = useState<number | null>(null);
  const [waSheet,           setWaSheet]              = useState<{ clientId: number; phone: string; petId: number } | null>(null);

  // Carga inicial: operadores, sucursales, citas vencidas y minutos de gracia (para saber
  // si el botón rápido "Iniciar" de la tarjeta aplica, o si hay que ir al detalle porque
  // está fuera de la ventana y hace falta el diálogo de confirmación completo).
  useEffect(() => {
    fetch('/api/operators').then(r => r.json()).then(setOperators).catch(() => {});
    fetch('/api/branches').then(r => r.json()).then(setBranches).catch(() => {});
    fetch('/api/agenda/vencidas').then(r => r.ok ? r.json() : []).then(setVencidas).catch(() => {});
    fetch('/api/settings/booking')
      .then(r => r.json())
      .then((d: { grace_minutes?: number }) => { if (d.grace_minutes != null) setGraceMinutes(d.grace_minutes); })
      .catch(() => {});
  }, []);

  // Carga de agenda al cambiar fecha o vista (día/semana/mes)
  const loadAgenda = useCallback((date: Date, view: CalView) => {
    setLoadingAg(true);
    fetch(`/api/agenda?date=${toDateStr(date)}&view=${view}`)
      .then(r => r.json())
      .then(setBookings)
      .catch(() => setBookings([]))
      .finally(() => setLoadingAg(false));
    fetch(`/api/agenda/unavailabilities?date=${toDateStr(date)}&view=${view}`)
      .then(r => r.ok ? r.json() : [])
      .then(setBlocked)
      .catch(() => setBlocked([]));
  }, []);

  useEffect(() => { loadAgenda(selectedDate, calView); }, [selectedDate, calView, loadAgenda]);

  // Filtro de operador
  const visibleBookings = filterOp == null
    ? bookings
    : bookings.filter(b => b.operators.some(o => o.id === filterOp));
  const visibleBlocked = filterOp == null
    ? blocked
    : blocked.filter(w => w.operator_id === filterOp);
  const blockedDates: Set<string> = new Set(visibleBlocked.flatMap((w): string[] => expandDateRange(w.starts_at, w.ends_at)));
  const blockedForSelectedDay = visibleBlocked.filter(w => expandDateRange(w.starts_at, w.ends_at).includes(toDateStr(selectedDate)));

  const isSameDay = (a: Date, b: Date) => toDateStr(a) === toDateStr(b);
  const isToday    = isSameDay(selectedDate, today);
  const isTomorrow = isSameDay(selectedDate, addDays(today, 1));


  const renderBookingCard = (b: Booking) => {
    const alert = agendaAlertKind(b, new Date());
    return (
    <div
      key={b.id}
      className="bg-surface border border-outline-variant rounded-2xl overflow-hidden shadow-sm active:scale-[0.99] transition-transform cursor-pointer"
      onClick={() => { setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate(`/citas/${b.id}`); }}
    >
      {/* Franja de estado */}
      <div className={`h-1 w-full ${
        alert                        ? 'bg-amber-500 animate-pulse' :
        b.status === 'work_order'    ? 'bg-secondary' :
        b.status === 'completed'     ? 'bg-tertiary'  :
        b.status === 'no_show'       ? 'bg-error'     :
        b.status === 'unfulfillable' ? 'bg-amber-500' : 'bg-primary'
      }`} />

      <div className="p-3 flex gap-3">
        {/* Foto mascota */}
        <div className="w-12 h-12 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
          {b.pet.photo ? (
            <img src={b.pet.photo} className="w-full h-full object-cover" alt={b.pet.name} />
          ) : (
            <span className="material-symbols-outlined text-primary text-2xl" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
          )}
        </div>

        {/* Info principal */}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <p className="font-bold text-on-surface text-sm truncate">{b.pet.name}</p>
              {b.pet.breed && (
                <p className="text-xs text-on-surface-variant truncate">{b.pet.breed}</p>
              )}
            </div>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded border shrink-0 ${
              alert ? 'bg-amber-500 text-white border-amber-500 animate-pulse' : (STATUS_COLOR[b.status] ?? 'bg-surface-container text-on-surface-variant border-outline-variant')
            }`}>
              {alert ? AGENDA_ALERT_LABEL[alert] : (STATUS_LABEL[b.status] ?? b.status)}
            </span>
          </div>

          {/* Servicios */}
          {b.services.length > 0 && (
            <p className="text-xs text-on-surface mt-1 truncate">
              {b.services.map(s => s.name).join(' · ')}
            </p>
          )}

          {/* Footer: hora, cliente, operadores */}
          <div className="flex items-center gap-3 mt-2 flex-wrap">
            <span className="flex items-center gap-1 text-xs text-on-surface-variant font-mono">
              <span className="material-symbols-outlined text-sm">schedule</span>
              {b.time}{b.end_time ? ` → ${b.end_time}` : ''}
            </span>
            {b.client && (
              <span className="flex items-center gap-1 text-xs text-on-surface-variant truncate">
                <span className="material-symbols-outlined text-sm">person</span>
                {b.client.name}
              </span>
            )}
            {b.operators.length > 0 && (
              <span className="flex items-center gap-1 text-xs text-on-surface-variant">
                <span className="material-symbols-outlined text-sm">content_cut</span>
                {b.operators.map(o => o.name.split(' ')[0]).join(', ')}
              </span>
            )}
          </div>

          {/* Acciones directas — evitan entrar al detalle para lo más común del día a día */}
          {(() => {
            const scheduledAt = new Date(`${b.date}T${b.time}:00`);
            const diffMin = Math.abs((Date.now() - scheduledAt.getTime()) / 60000);
            const withinGrace = diffMin <= graceMinutes;
            const showIniciar = b.status === 'scheduled' && withinGrace;
            const showCobrar = b.status === 'work_order';
            const clientForWa = b.client && b.client.phone ? { clientId: b.client.id, phone: b.client.phone, petId: b.pet.id } : null;
            if (!showIniciar && !showCobrar && !clientForWa) return null;
            return (
              <div className="flex items-center gap-2 mt-2">
                {showIniciar && (
                  <button
                    disabled={startingCitaId === b.id}
                    onClick={e => { e.stopPropagation(); startCita(b.id); }}
                    className="min-h-11 flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/30 px-3 rounded-full text-xs font-semibold active:scale-95 transition-transform disabled:opacity-50"
                  >
                    <span className="material-symbols-outlined text-base">
                      {startingCitaId === b.id ? 'progress_activity' : 'play_arrow'}
                    </span>
                    Iniciar
                  </button>
                )}
                {showCobrar && (
                  <button
                    onClick={e => { e.stopPropagation(); setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate(`/citas/${b.id}/cobro`); }}
                    className="min-h-11 flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/30 px-3 rounded-full text-xs font-semibold active:scale-95 transition-transform"
                  >
                    <span className="material-symbols-outlined text-base">point_of_sale</span>
                    Cobrar
                  </button>
                )}
                {clientForWa && (
                  <button
                    onClick={e => { e.stopPropagation(); setWaSheet(clientForWa); }}
                    className="min-h-11 min-w-11 flex items-center justify-center bg-green-100 text-green-700 rounded-full active:scale-95 transition-transform"
                    aria-label="WhatsApp"
                  >
                    <span className="material-symbols-outlined text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>chat</span>
                  </button>
                )}
              </div>
            );
          })()}
        </div>
      </div>
    </div>
  );
  };

  // Acción rápida "Iniciar" desde la tarjeta — mismo PATCH que ya hace MobCitaDet para
  // scheduled → work_order, pero solo se ofrece acá cuando la cita está dentro de la
  // ventana de gracia (igual que el detalle): fuera de esa ventana, el detalle completo
  // tiene el diálogo de confirmación ("¿seguro? faltan/pasaron X minutos") que no
  // conviene duplicar en el espacio chico de una tarjeta.
  const startCita = async (bookingId: number) => {
    setStartingCitaId(bookingId);
    try {
      const res = await fetch(`/api/bookings/${bookingId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: 'work_order' }),
      });
      if (res.ok) loadAgenda(selectedDate, calView);
    } finally {
      setStartingCitaId(null);
    }
  };

  const startNewCita = () => {
    setShowAddMenu(false);
    setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]);
    setCitaPresetDate(toDateStr(selectedDate));
    // Si Agenda ya tiene un operador filtrado (no "Todos"), preseleccionarlo en el
    // formulario — el sistema ya sabe la respuesta, no hace falta repreguntarla.
    setCitaPresetOperator(filterOp);
    navigate('/mascotas');
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20 md:pb-0">
      <ScreenHeader
        title="EstetiCAN"
        screenTag="MobAgGbl"
        subtitle={branches.length === 1 ? branches[0].name : undefined}
        noCrumbs
        rightAction={
          <div className="flex items-center gap-1">
            <button onClick={() => { clearNavCrumbs(); navigate('/mascotas'); }} className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
              <span className="material-symbols-outlined text-on-surface-variant text-xl">pets</span>
            </button>
            <button onClick={() => { clearNavCrumbs(); navigate('/clientes/seleccionar'); }} className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
              <span className="material-symbols-outlined text-on-surface-variant text-xl">person_search</span>
            </button>
            <button
              onClick={() => {
                // La hoja inferior solo tiene sentido cuando hay más de una opción real
                // ("Bloquear mi horario" es exclusivo de operadores con perfil) — para
                // el resto de los usuarios, mostrar un menú de un solo botón es un tap
                // de más sin ninguna decisión real que tomar.
                if (user?.operator_role) {
                  setShowAddMenu(true);
                } else {
                  startNewCita();
                }
              }}
              className="flex items-center gap-1 bg-primary text-on-primary px-3 py-1.5 rounded-full text-xs font-semibold active:scale-95 transition-transform ml-1"
            >
              <span className="material-symbols-outlined text-base">add</span>
              Cita
            </button>
          </div>
        }
      />

      {showAddMenu && (
        <>
          <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={() => setShowAddMenu(false)} />
          <div className="fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl">
            <div className="flex justify-center pt-3 pb-1">
              <div className="w-10 h-1 rounded-full bg-outline-variant" />
            </div>
            <div className="px-4 pb-8 pt-2 flex flex-col gap-2">
              <button
                onClick={startNewCita}
                className="flex items-center gap-3 px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform"
              >
                <span className="material-symbols-outlined text-2xl text-primary">event</span>
                <div className="flex-1">
                  <p className="text-sm font-medium text-on-surface">Nueva cita</p>
                  <p className="text-xs text-on-surface-variant">Agendar servicio para una mascota</p>
                </div>
                <span className="material-symbols-outlined text-base text-on-surface-variant">chevron_right</span>
              </button>

              {user?.operator_role && (
                <button
                  onClick={() => { setShowAddMenu(false); navigate('/configuracion/disponibilidad'); }}
                  className="flex items-center gap-3 px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform"
                >
                  <span className="material-symbols-outlined text-2xl text-error">event_busy</span>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-on-surface">Bloquear mi horario</p>
                    <p className="text-xs text-on-surface-variant">Vacaciones o permiso — no se te podrá agendar</p>
                  </div>
                  <span className="material-symbols-outlined text-base text-on-surface-variant">chevron_right</span>
                </button>
              )}
            </div>
          </div>
        </>
      )}

      <main className="flex-1 flex flex-col">

        {/* Toggle Día/Semana/Mes + alerta de citas vencidas */}
        <div className="bg-surface px-4 pt-2 flex items-center justify-between gap-1.5">
          <div className="flex items-center gap-1.5">
            {(['day', 'week', 'month'] as CalView[]).map(v => (
              <button
                key={v}
                onClick={() => setCalView(v)}
                className={`min-h-11 inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold transition-colors ${
                  calView === v ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant border border-outline-variant'
                }`}
              >
                {v === 'day' ? 'Día' : v === 'week' ? 'Semana' : 'Mes'}
              </button>
            ))}
          </div>

          {vencidas.length > 0 && (
            <button
              onClick={() => setShowVencidasModal(true)}
              className="min-h-11 flex items-center gap-1 bg-error/10 text-error border border-error/30 px-3 py-1 rounded-full text-xs font-semibold active:scale-95 transition-transform shrink-0"
            >
              <span className="material-symbols-outlined text-base" style={{ fontVariationSettings: "'FILL' 1" }}>warning</span>
              {vencidas.length}
            </button>
          )}
        </div>

        {/* Selector de fecha (vista Día) */}
        {calView === 'day' && (
          <div className="bg-surface border-b border-outline-variant px-4 py-2 flex items-center gap-2 overflow-x-auto hide-scrollbar">
            <button
              onClick={() => setSelectedDate(addDays(selectedDate, -1))}
              className="min-w-11 min-h-11 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors shrink-0"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_left</span>
            </button>

            {[-1, 0, 1, 2].map(offset => {
              const d = addDays(today, offset);
              const sel = isSameDay(d, selectedDate);
              return (
                <button
                  key={offset}
                  onClick={() => setSelectedDate(d)}
                  className={`flex flex-col items-center px-3 py-1.5 rounded-xl shrink-0 transition-colors ${
                    sel ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface border border-outline-variant'
                  }`}
                >
                  <span className="text-[10px] uppercase tracking-wide opacity-70">
                    {offset === 0 ? 'Hoy' : offset === 1 ? 'Mañana' : fmtDate(d).split(' ')[0]}
                  </span>
                  <span className="text-sm font-bold leading-tight">
                    {d.getDate()}
                  </span>
                </button>
              );
            })}

            <input
              ref={dateInputRef}
              type="date"
              className="sr-only"
              id="date-picker"
              value={toDateStr(selectedDate)}
              onChange={e => e.target.value && setSelectedDate(new Date(e.target.value + 'T12:00:00'))}
            />
            <button
              type="button"
              onClick={() => {
                // En Chrome de escritorio, enfocar el input (ej. vía label) no abre el
                // calendario nativo — solo showPicker() lo hace explícitamente, mismo
                // patrón ya usado en MobCitaNueva.tsx para este problema.
                const el = dateInputRef.current;
                if (el && typeof el.showPicker === 'function') el.showPicker();
                else el?.focus();
              }}
              className="min-w-11 min-h-11 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors shrink-0"
              aria-label="Elegir otra fecha del calendario"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">calendar_month</span>
            </button>

            <button
              onClick={() => setSelectedDate(addDays(selectedDate, 1))}
              className="min-w-11 min-h-11 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors shrink-0"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_right</span>
            </button>
          </div>
        )}

        {/* Navegación de rango (vistas Semana/Mes) */}
        {calView !== 'day' && (
          <div className="bg-surface border-b border-outline-variant px-4 py-2 flex items-center justify-between gap-2">
            <button
              onClick={() => setSelectedDate(shiftAnchor(calView, selectedDate, -1))}
              className="min-w-11 min-h-11 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors shrink-0"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_left</span>
            </button>
            <button
              onClick={() => setSelectedDate(new Date())}
              className="min-h-11 flex items-center justify-center text-xs font-semibold text-on-surface text-center truncate px-2"
            >
              {rangeLabel(calView, selectedDate)}
            </button>
            <button
              onClick={() => setSelectedDate(shiftAnchor(calView, selectedDate, 1))}
              className="min-w-11 min-h-11 flex items-center justify-center rounded-full hover:bg-surface-container-high transition-colors shrink-0"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_right</span>
            </button>
          </div>
        )}

        {/* Filtro de operadores */}
        {operators.length > 0 && (
          <div className="px-4 py-2 flex items-center gap-2 overflow-x-auto hide-scrollbar border-b border-outline-variant bg-surface">
            <span className="text-xs text-on-surface-variant shrink-0">Operador:</span>
            <button
              onClick={() => setFilterOp(null)}
              className={`min-h-11 inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold shrink-0 transition-colors ${
                filterOp == null
                  ? 'bg-primary text-on-primary'
                  : 'bg-surface-container text-on-surface border border-outline-variant'
              }`}
            >
              Todos
            </button>
            {operators.map(op => (
              <button
                key={op.id}
                onClick={() => setFilterOp(filterOp === op.id ? null : op.id)}
                className={`min-h-11 flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shrink-0 transition-colors ${
                  filterOp === op.id
                    ? 'bg-primary text-on-primary'
                    : 'bg-surface-container text-on-surface border border-outline-variant'
                }`}
              >
                {op.photo_url ? (
                  <img src={op.photo_url} className="w-4 h-4 rounded-full object-cover" alt="" />
                ) : (
                  <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                )}
                {op.name.split(' ')[0]}
              </button>
            ))}
          </div>
        )}

        {/* Encabezado de fecha + contador */}
        <div className="px-4 pt-3 pb-1 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-on-surface truncate">
            {calView === 'day' ? (
              <>
                {isToday ? 'Hoy' : isTomorrow ? 'Mañana' : fmtDate(selectedDate)}
                {' — '}
                <span className="text-on-surface-variant font-normal">
                  {selectedDate.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })}
                </span>
              </>
            ) : (
              rangeLabel(calView, selectedDate)
            )}
          </h2>
          {!loadingAg && (
            <span className="text-xs text-on-surface-variant bg-surface-container px-2 py-0.5 rounded-full border border-outline-variant">
              {visibleBookings.length} {visibleBookings.length === 1 ? 'cita' : 'citas'}
            </span>
          )}
        </div>

        {/* Aviso de operadores no disponibles (bloqueos de vacaciones/permiso) */}
        {calView === 'day' && blockedForSelectedDay.length > 0 && (
          <div className="mx-4 mb-2 px-3 py-2 rounded-xl bg-surface-container border border-outline-variant flex flex-col gap-1">
            <p className="text-xs font-semibold text-on-surface-variant">Operadores no disponibles hoy</p>
            {blockedForSelectedDay.map((w, i) => (
              <p key={i} className="text-xs text-on-surface-variant flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">event_busy</span>
                {w.operator_name ?? `Operador #${w.operator_id}`} — {w.starts_at.slice(11, 16)}–{w.ends_at.slice(11, 16)}
                {w.reason ? ` (${w.reason})` : ''}
              </p>
            ))}
          </div>
        )}

        {/* Lista de citas (Día) / Grid tipo calendario (Semana/Mes) */}
        <div className="flex-1 px-4 pb-4 flex flex-col gap-3">

          {loadingAg ? (
            <div className="flex items-center justify-center py-16">
              <span className="material-symbols-outlined text-4xl text-on-surface-variant/40 animate-spin">progress_activity</span>
            </div>
          ) : calView === 'day' && visibleBookings.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-16 gap-3 text-center">
              <span className="material-symbols-outlined text-5xl text-on-surface-variant/30" style={{ fontVariationSettings: "'FILL' 1" }}>event_busy</span>
              <p className="text-sm text-on-surface-variant">Sin citas para este día</p>
              <button
                onClick={startNewCita}
                className="flex items-center gap-2 bg-primary text-on-primary px-4 py-2 rounded-full text-sm font-semibold active:scale-95 transition-transform"
              >
                <span className="material-symbols-outlined text-base">add</span>
                Nueva cita
              </button>
            </div>
          ) : calView === 'day' ? (
            visibleBookings.map(renderBookingCard)
          ) : calView === 'week' ? (
            <WeekGrid
              days={weekDays(selectedDate)}
              bookingsByDate={groupByDateMap(visibleBookings)}
              today={today}
              onSelectBooking={id => { setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate(`/citas/${id}`); }}
              onSelectDay={date => { setSelectedDate(date); setCalView('day'); }}
              blockedDates={blockedDates}
            />
          ) : (
            <MonthGrid
              days={monthGridDays(selectedDate)}
              bookingsByDate={groupByDateMap(visibleBookings)}
              today={today}
              onSelectDay={date => { setSelectedDate(date); setCalView('day'); }}
              blockedDates={blockedDates}
            />
          )}
        </div>

      </main>

      {/* Ventana de citas vencidas sin resolver, abierta desde el botón de alerta */}
      {showVencidasModal && (
        <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={() => setShowVencidasModal(false)} />
      )}
      <div
        className={`fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl transition-transform duration-300 ease-out ${
          showVencidasModal ? '[transform:translateY(0)]' : '[transform:translateY(100%)]'
        }`}
        style={{ maxHeight: '85vh', overflowY: 'auto' }}
      >
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>
        <div className="flex items-center gap-2 px-4 py-2.5 border-b border-error/20">
          <span className="material-symbols-outlined text-error text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>warning</span>
          <p className="flex-1 text-sm font-semibold text-error">
            {vencidas.length} {vencidas.length === 1 ? 'cita pendiente sin resolver' : 'citas pendientes sin resolver'}
          </p>
          <button onClick={() => setShowVencidasModal(false)} className="p-1 rounded-full hover:bg-error/10">
            <span className="material-symbols-outlined text-error/60 text-base">close</span>
          </button>
        </div>
        <div className="flex flex-col divide-y divide-error/10 pb-4">
          {vencidas.map(v => (
            <button
              key={v.id}
              onClick={() => {
                setShowVencidasModal(false);
                setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]);
                navigate(`/citas/${v.id}`);
              }}
              className="flex items-center gap-3 px-4 py-3 text-left active:bg-error/10 transition-colors w-full"
            >
              <div className="w-8 h-8 rounded-lg bg-error/10 overflow-hidden flex items-center justify-center shrink-0">
                {v.pet.photo
                  ? <img src={v.pet.photo} className="w-full h-full object-cover" alt={v.pet.name} />
                  : <span className="material-symbols-outlined text-error text-base" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-on-surface truncate">{v.pet.name}</p>
                <p className="text-xs text-error/80">
                  {v.date_label} {v.time}
                  {v.services.length > 0 && ` · ${v.services.map(s => s.name).join(', ')}`}
                </p>
                <p className="text-[11px] font-semibold text-amber-600 mt-0.5">
                  {VENCIDA_REASON_LABEL[v.reason]}
                  {v.reason === 'pending_balance' && ` · $${v.balance.toFixed(2)}`}
                </p>
              </div>
              <span className="material-symbols-outlined text-error/50 text-base shrink-0">chevron_right</span>
            </button>
          ))}
        </div>
      </div>

      {waSheet && (
        <WhatsAppMessageSheet clientId={waSheet.clientId} phone={waSheet.phone} petId={waSheet.petId} onClose={() => setWaSheet(null)} />
      )}
    </div>
  );
}
