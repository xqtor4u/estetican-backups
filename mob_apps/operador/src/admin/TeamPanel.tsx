import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ScreenHeader } from '../ScreenHeader';

interface CurrentJob {
  booking_id: number;
  pet_name: string | null;
  service_name: string | null;
  scheduled_at: string;
}

interface TeamMember {
  id: number;
  name: string;
  role: string | null;
  photo_url: string | null;
  checked_in: boolean;
  checked_in_at: string | null;
  branch: { id: number; name: string } | null;
  pending_today: number;
  completed_today: number;
  current_job: CurrentJob | null;
}

function fmtTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

function statusInfo(m: TeamMember): { label: string; dot: string; badge: string } {
  if (m.current_job) {
    return { label: 'En Servicio', dot: 'bg-tertiary-fixed-dim animate-pulse', badge: 'text-on-tertiary-container bg-tertiary-fixed' };
  }
  if (m.checked_in) {
    return { label: 'Disponible', dot: 'bg-secondary', badge: 'text-secondary bg-secondary-container' };
  }
  return { label: 'Fuera de turno', dot: 'bg-outline-variant', badge: 'text-on-surface-variant bg-surface-container-high' };
}

export function TeamPanel() {
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);

  const load = () => {
    fetch('/api/team')
      .then(r => r.json())
      .then(data => { setTeam(data); setLoading(false); })
      .catch(() => setLoading(false));
  };

  useEffect(() => {
    load();
    const interval = setInterval(load, 30000);
    return () => clearInterval(interval);
  }, []);

  const sorted = [...team].sort((a, b) => {
    const rank = (m: TeamMember) => (m.current_job ? 0 : m.checked_in ? 1 : 2);
    return rank(a) - rank(b);
  });

  const completedToday = team.reduce((sum, m) => sum + m.completed_today, 0);
  const enServicio = team.filter(m => m.current_job).length;
  const checkedInCount = team.filter(m => m.checked_in).length;
  const teamActivePct = team.length > 0 ? Math.round((checkedInCount / team.length) * 100) : 0;

  return (
    <div className="bg-background text-on-background min-h-screen pb-20 md:pb-0">
      <ScreenHeader title="Equipo" screenTag="MobTeam" noCrumbs />

      <main className="container mx-auto px-gutter py-section-padding md:grid md:grid-cols-12 gap-container-margin">
        <div className="md:col-span-8 flex flex-col gap-operational-gap">
          <div className="flex justify-between items-end mb-4">
            <div>
              <h1 className="font-display-sm text-display-sm text-on-surface">Panel de Equipo</h1>
              <p className="font-body-sm text-body-sm text-on-surface-variant mt-1">Gestión operativa y disponibilidad en tiempo real.</p>
            </div>
          </div>

          {loading && (
            <div className="flex justify-center py-16">
              <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
            </div>
          )}

          {!loading && sorted.length === 0 && (
            <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-2">
              <span className="material-symbols-outlined text-5xl">group_off</span>
              <p className="text-sm">No hay operadores activos</p>
            </div>
          )}

          {sorted.map(m => {
            const status = statusInfo(m);
            return (
              <div
                key={m.id}
                className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center relative overflow-hidden"
              >
                {m.current_job && <div className="absolute left-0 top-0 bottom-0 w-1 bg-tertiary-fixed-dim"></div>}
                <div className="relative">
                  <div className="w-16 h-16 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center border-2 border-surface shrink-0">
                    {m.photo_url
                      ? <img alt={m.name} className="w-full h-full object-cover" src={m.photo_url} />
                      : <span className="material-symbols-outlined text-2xl text-primary">person</span>
                    }
                  </div>
                  <div className={`absolute bottom-0 right-0 w-4 h-4 border-2 border-surface rounded-full ${status.dot}`}></div>
                </div>
                <div className="flex-1 w-full">
                  <div className="flex justify-between items-start">
                    <div>
                      <h2 className="font-headline-sm text-headline-sm text-on-surface">{m.name}</h2>
                      <span className={`font-label-sm text-label-sm uppercase px-2 py-0.5 rounded-sm inline-block mt-1 ${status.badge}`}>
                        {status.label}
                      </span>
                    </div>
                    <span className={`font-mono-data text-mono-data px-2 py-1 rounded ${m.pending_today > 0 ? 'text-error bg-error-container font-bold' : 'text-on-surface-variant bg-surface-container'}`}>
                      {m.pending_today} pend.
                    </span>
                  </div>

                  {m.current_job ? (
                    <div className="mt-2 bg-surface-container-low border border-outline-variant rounded-lg p-2 flex items-center gap-2">
                      <span className="material-symbols-outlined text-on-surface-variant text-[18px]">cut</span>
                      <div className="flex-1 font-body-sm text-body-sm text-on-surface">
                        <span className="font-bold">{m.current_job.pet_name ?? 'Mascota'}</span>
                        {m.current_job.service_name ? ` - ${m.current_job.service_name}` : ''}
                      </div>
                      <span className="font-mono-data text-mono-data text-on-surface-variant">{fmtTime(m.current_job.scheduled_at)}</span>
                    </div>
                  ) : (
                    <div className="mt-3 flex items-center justify-between">
                      <div className="text-on-surface-variant font-body-sm text-body-sm flex items-center gap-1">
                        <span className="material-symbols-outlined text-[16px]">schedule</span>
                        {m.checked_in && m.checked_in_at
                          ? `Desde ${fmtTime(m.checked_in_at)}${m.branch ? ' · ' + m.branch.name : ''}`
                          : 'Sin check-in hoy'}
                      </div>
                      <Link to={`/groomer/${m.id}`}>
                        <button className="bg-primary text-on-primary font-label-md text-label-md px-4 py-2 rounded-lg hover:opacity-90 transition-opacity">
                          Ver Agenda Individual
                        </button>
                      </Link>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        <div className="md:col-span-4 mt-8 md:mt-0">
          <div className="bg-surface-container-lowest border border-outline-variant rounded-xl p-section-padding sticky top-20">
            <h3 className="font-headline-sm text-headline-sm text-on-surface mb-4 flex items-center gap-2">
              <span className="material-symbols-outlined">analytics</span>
              Resumen Operativo
            </h3>
            <div className="flex flex-col gap-tight-stack">
              <div className="bg-surface-container-low p-3 rounded-lg flex justify-between items-center border border-surface-variant">
                <span className="font-body-sm text-body-sm text-on-surface-variant">Servicios Completados Hoy</span>
                <span className="font-display-sm text-display-sm text-primary">{completedToday}</span>
              </div>
              <div className="bg-surface-container-low p-3 rounded-lg flex justify-between items-center border border-surface-variant">
                <span className="font-body-sm text-body-sm text-on-surface-variant">En Curso</span>
                <span className="font-display-sm text-display-sm text-tertiary-fixed-dim">{enServicio}</span>
              </div>
            </div>
            <div className="mt-6">
              <h4 className="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider mb-2">
                Equipo con check-in ({checkedInCount}/{team.length})
              </h4>
              <div className="w-full bg-surface-container-high rounded-full h-2.5 overflow-hidden">
                <div className="bg-secondary h-2.5" style={{ width: `${teamActivePct}%` }}></div>
              </div>
            </div>
          </div>
        </div>
      </main>

    </div>
  );
}
