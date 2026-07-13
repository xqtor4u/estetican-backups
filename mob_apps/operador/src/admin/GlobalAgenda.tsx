import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { clearNavCrumbs, setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import {
  CalView, toDateStr, addDays, fmtDate, shiftAnchor, rangeLabel, weekDays, monthGridDays, groupByDateMap,
} from './agendaViews';
import { WeekGrid, MonthGrid } from './AgendaCalendarGrid';

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
  client: { id: number; name: string } | null;
  services: { id: number | null; name: string; type: string | null }[];
  operators: { id: number; name: string; photo_url: string | null }[];
}
interface Vencida {
  id: number;
  time: string;
  date_label: string;
  status: string;
  pet: { id: number; name: string; photo: string | null };
  client: { id: number; name: string } | null;
  services: { name: string }[];
}

const STATUS_LABEL: Record<string, string> = {
  scheduled: 'Programada',
  work_order: 'En proceso',
  completed:  'Completada',
  no_show:    'No se presentó',
};
const STATUS_COLOR: Record<string, string> = {
  scheduled:  'bg-primary/10 text-primary border-primary/30',
  work_order: 'bg-secondary-container text-on-secondary-container border-secondary-fixed',
  completed:  'bg-tertiary-container/40 text-on-tertiary-container border-tertiary-fixed-dim',
  no_show:    'bg-error/10 text-error border-error/30',
};

export function GlobalAgenda() {
  const navigate = useNavigate();

  const [today]        = useState(() => new Date());
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [calView,      setCalView]      = useState<CalView>('day');
  const [operators,    setOperators]    = useState<Operator[]>([]);
  const [branches,     setBranches]     = useState<Branch[]>([]);
  const [bookings,     setBookings]     = useState<Booking[]>([]);
  const [loadingAg,    setLoadingAg]    = useState(true);
  const [filterOp,     setFilterOp]     = useState<number | null>(null);
  const [vencidas,          setVencidas]          = useState<Vencida[]>([]);
  const [showVencidasModal, setShowVencidasModal]  = useState(false);

  // Carga inicial: operadores, sucursales y citas vencidas
  useEffect(() => {
    fetch('/api/operators').then(r => r.json()).then(setOperators).catch(() => {});
    fetch('/api/branches').then(r => r.json()).then(setBranches).catch(() => {});
    fetch('/api/agenda/vencidas').then(r => r.ok ? r.json() : []).then(setVencidas).catch(() => {});
  }, []);

  // Carga de agenda al cambiar fecha o vista (día/semana/mes)
  const loadAgenda = useCallback((date: Date, view: CalView) => {
    setLoadingAg(true);
    fetch(`/api/agenda?date=${toDateStr(date)}&view=${view}`)
      .then(r => r.json())
      .then(setBookings)
      .catch(() => setBookings([]))
      .finally(() => setLoadingAg(false));
  }, []);

  useEffect(() => { loadAgenda(selectedDate, calView); }, [selectedDate, calView, loadAgenda]);

  // Filtro de operador
  const visibleBookings = filterOp == null
    ? bookings
    : bookings.filter(b => b.operators.some(o => o.id === filterOp));

  const isSameDay = (a: Date, b: Date) => toDateStr(a) === toDateStr(b);
  const isToday    = isSameDay(selectedDate, today);
  const isTomorrow = isSameDay(selectedDate, addDays(today, 1));


  const renderBookingCard = (b: Booking) => (
    <div
      key={b.id}
      className="bg-surface border border-outline-variant rounded-2xl overflow-hidden shadow-sm active:scale-[0.99] transition-transform cursor-pointer"
      onClick={() => { setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate(`/citas/${b.id}`); }}
    >
      {/* Franja de estado */}
      <div className={`h-1 w-full ${
        b.status === 'work_order' ? 'bg-secondary' :
        b.status === 'completed'  ? 'bg-tertiary'  :
        b.status === 'no_show'    ? 'bg-error'     : 'bg-primary'
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
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded border shrink-0 ${STATUS_COLOR[b.status] ?? 'bg-surface-container text-on-surface-variant border-outline-variant'}`}>
              {STATUS_LABEL[b.status] ?? b.status}
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
        </div>
      </div>
    </div>
  );

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
              onClick={() => { setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate('/mascotas'); }}
              className="flex items-center gap-1 bg-primary text-on-primary px-3 py-1.5 rounded-full text-xs font-semibold active:scale-95 transition-transform ml-1"
            >
              <span className="material-symbols-outlined text-base">add</span>
              Cita
            </button>
          </div>
        }
      />

      <main className="flex-1 flex flex-col">

        {/* Toggle Día/Semana/Mes + alerta de citas vencidas */}
        <div className="bg-surface px-4 pt-2 flex items-center justify-between gap-1.5">
          <div className="flex items-center gap-1.5">
            {(['day', 'week', 'month'] as CalView[]).map(v => (
              <button
                key={v}
                onClick={() => setCalView(v)}
                className={`px-3 py-1 rounded-full text-xs font-semibold transition-colors ${
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
              className="flex items-center gap-1 bg-error/10 text-error border border-error/30 px-3 py-1 rounded-full text-xs font-semibold active:scale-95 transition-transform shrink-0"
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
              className="p-1.5 rounded-full hover:bg-surface-container-high transition-colors shrink-0"
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
              type="date"
              className="sr-only"
              id="date-picker"
              value={toDateStr(selectedDate)}
              onChange={e => e.target.value && setSelectedDate(new Date(e.target.value + 'T12:00:00'))}
            />
            <label
              htmlFor="date-picker"
              className="p-1.5 rounded-full hover:bg-surface-container-high transition-colors shrink-0 cursor-pointer"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">calendar_month</span>
            </label>

            <button
              onClick={() => setSelectedDate(addDays(selectedDate, 1))}
              className="p-1.5 rounded-full hover:bg-surface-container-high transition-colors shrink-0"
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
              className="p-1.5 rounded-full hover:bg-surface-container-high transition-colors shrink-0"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-lg">chevron_left</span>
            </button>
            <button
              onClick={() => setSelectedDate(new Date())}
              className="text-xs font-semibold text-on-surface text-center truncate px-2"
            >
              {rangeLabel(calView, selectedDate)}
            </button>
            <button
              onClick={() => setSelectedDate(shiftAnchor(calView, selectedDate, 1))}
              className="p-1.5 rounded-full hover:bg-surface-container-high transition-colors shrink-0"
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
              className={`px-3 py-1 rounded-full text-xs font-semibold shrink-0 transition-colors ${
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
                className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shrink-0 transition-colors ${
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
                onClick={() => { setNavCrumbs([{ label: 'Agenda', to: '/agenda' }]); navigate('/mascotas'); }}
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
            />
          ) : (
            <MonthGrid
              days={monthGridDays(selectedDate)}
              bookingsByDate={groupByDateMap(visibleBookings)}
              today={today}
              onSelectDay={date => { setSelectedDate(date); setCalView('day'); }}
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
          showVencidasModal ? 'translate-y-0' : 'translate-y-full'
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
              </div>
              <span className="material-symbols-outlined text-error/50 text-base shrink-0">chevron_right</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
