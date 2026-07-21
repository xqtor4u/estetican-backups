import React from 'react';

interface GridBooking {
  id: number;
  date: string;
  time: string;
  status: string;
  pet: { name: string };
}

const STATUS_DOT: Record<string, string> = {
  scheduled: 'bg-primary',
  work_order: 'bg-secondary',
  completed: 'bg-tertiary',
  no_show: 'bg-error',
};

function toDateStr(d: Date) {
  return d.toISOString().slice(0, 10);
}

interface WeekGridProps {
  days: Date[];
  bookingsByDate: Map<string, GridBooking[]>;
  today: Date;
  onSelectBooking: (id: number) => void;
  onSelectDay: (date: Date) => void;
  blockedDates?: Set<string>;
}

/** Grid semanal estilo Google Calendar: 7 columnas (lun-dom) con scroll horizontal, cada una con sus citas del día. */
export function WeekGrid({ days, bookingsByDate, today, onSelectBooking, onSelectDay, blockedDates }: WeekGridProps) {
  const todayStr = toDateStr(today);
  return (
    <div className="flex gap-2 overflow-x-auto pb-1 snap-x snap-mandatory hide-scrollbar">
      {days.map(d => {
        const ds = toDateStr(d);
        const items = bookingsByDate.get(ds) ?? [];
        const isToday = ds === todayStr;
        const isBlocked = blockedDates?.has(ds) ?? false;
        return (
          <div
            key={ds}
            className={`shrink-0 w-[124px] snap-start rounded-2xl border flex flex-col ${
              isToday ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface'
            }`}
          >
            <button
              onClick={() => onSelectDay(d)}
              className={`px-2 py-1.5 flex items-center justify-between border-b shrink-0 ${
                isToday ? 'border-primary/30' : 'border-outline-variant'
              }`}
            >
              <span className="flex items-center gap-1">
                <span className="text-[10px] font-semibold uppercase text-on-surface-variant">
                  {d.toLocaleDateString('es-MX', { weekday: 'short' })}
                </span>
                {isBlocked && (
                  <span className="material-symbols-outlined text-[11px] text-on-surface-variant" title="Operador no disponible">event_busy</span>
                )}
              </span>
              <span
                className={`text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full ${
                  isToday ? 'bg-primary text-on-primary' : 'text-on-surface'
                }`}
              >
                {d.getDate()}
              </span>
            </button>
            <div className="flex flex-col gap-1 p-1.5 min-h-[72px]">
              {items.length === 0 ? (
                <button onClick={() => onSelectDay(d)} className="flex-1 min-h-[48px]" />
              ) : (
                items.map(b => (
                  <button
                    key={b.id}
                    onClick={() => onSelectBooking(b.id)}
                    className="text-left rounded-lg bg-surface-container px-1.5 py-1 active:scale-[0.97] transition-transform"
                  >
                    <span className="flex items-center gap-1">
                      <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${STATUS_DOT[b.status] ?? 'bg-outline'}`} />
                      <span className="text-[10px] font-mono text-on-surface-variant">{b.time}</span>
                    </span>
                    <span className="block text-[11px] font-semibold text-on-surface truncate">{b.pet.name}</span>
                  </button>
                ))
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

const DOW_SHORT = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

interface MonthGridProps {
  days: { date: Date; outside: boolean }[];
  bookingsByDate: Map<string, GridBooking[]>;
  today: Date;
  onSelectDay: (date: Date) => void;
  blockedDates?: Set<string>;
}

/** Grid mensual estilo Google Calendar: 7x5/6 celdas con puntos de color por cita; tocar un día abre su vista Día. */
export function MonthGrid({ days, bookingsByDate, today, onSelectDay, blockedDates }: MonthGridProps) {
  const todayStr = toDateStr(today);
  return (
    <div>
      <div className="grid grid-cols-7 gap-1 mb-1">
        {DOW_SHORT.map(dow => (
          <span key={dow} className="text-[10px] font-bold text-on-surface-variant text-center uppercase">
            {dow}
          </span>
        ))}
      </div>
      <div className="grid grid-cols-7 gap-1">
        {days.map(({ date, outside }) => {
          const ds = toDateStr(date);
          const items = bookingsByDate.get(ds) ?? [];
          const isToday = ds === todayStr;
          const isBlocked = blockedDates?.has(ds) ?? false;
          return (
            <button
              key={ds}
              onClick={() => onSelectDay(date)}
              className={`aspect-square rounded-xl border flex flex-col items-center pt-1 gap-0.5 ${
                outside ? 'border-transparent opacity-35' : 'border-outline-variant'
              } ${isToday ? 'bg-primary/10 border-primary' : 'bg-surface'}`}
            >
              <span className="flex items-center gap-0.5">
                <span
                  className={`text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full ${
                    isToday ? 'bg-primary text-on-primary' : 'text-on-surface'
                  }`}
                >
                  {date.getDate()}
                </span>
                {isBlocked && (
                  <span className="w-1.5 h-1.5 rounded-full bg-on-surface-variant" title="Operador no disponible" />
                )}
              </span>
              {items.length > 0 && (
                <span className="flex items-center gap-0.5">
                  {items.slice(0, 3).map(b => (
                    <span key={b.id} className={`w-1.5 h-1.5 rounded-full ${STATUS_DOT[b.status] ?? 'bg-outline'}`} />
                  ))}
                  {items.length > 3 && <span className="text-[8px] text-on-surface-variant">+{items.length - 3}</span>}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
