import React from 'react';
import { Link } from 'react-router-dom';

export function GlobalAgenda() {
  return (
    <div className="bg-background text-on-background font-body-md min-h-screen flex flex-col pb-20 md:pb-0">
      <header className="bg-surface border-b border-outline-variant flex items-center justify-between px-gutter w-full h-14 sticky top-0 z-40">
        <div className="flex items-center gap-4">
          <Link to="/" className="hover:bg-surface-container-high transition-colors p-2 rounded-full flex items-center justify-center">
            <span className="material-symbols-outlined text-primary">arrow_back</span>
          </Link>
          <h1 className="font-headline-sm text-headline-sm font-bold text-primary">SPA Canino Admin</h1>
        </div>
      </header>

      <main className="flex-1 overflow-y-auto hidden-scrollbar flex flex-col items-center">
        <div className="w-full max-w-[1200px] px-gutter py-section-padding flex flex-col gap-operational-gap">
          <section className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-surface-container-lowest p-4 rounded-xl border border-outline-variant shadow-sm">
            <div className="flex items-center gap-2 overflow-x-auto pb-2 md:pb-0 hide-scrollbar">
              <button className="flex items-center justify-center bg-secondary-container text-on-secondary-container font-label-md text-label-md px-4 py-2 rounded-full shrink-0">Hoy, 24 Oct</button>
              <button className="flex items-center justify-center bg-surface-container text-on-surface-variant font-label-md text-label-md px-4 py-2 rounded-full shrink-0 border border-outline-variant">Mañana</button>
              <button className="flex items-center justify-center p-2 text-on-surface-variant">
                <span className="material-symbols-outlined">calendar_month</span>
              </button>
            </div>
            <div className="flex items-center gap-2 overflow-x-auto pb-2 md:pb-0 hide-scrollbar">
              <span className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider shrink-0 mr-2">Operador:</span>
              <button className="flex items-center justify-center bg-surface-tint text-white font-label-md text-label-md px-3 py-1.5 rounded-full shrink-0">Todos</button>
              <button className="flex items-center justify-center bg-surface-container text-on-surface-variant font-label-md text-label-md px-3 py-1.5 rounded-full shrink-0 border border-outline-variant">
                <div className="w-4 h-4 rounded-full bg-primary-fixed-dim mr-2 overflow-hidden flex items-center justify-center">
                  <span className="material-symbols-outlined text-[10px] text-primary">person</span>
                </div>
                Roberto S.
              </button>
            </div>
          </section>

          <div className="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden mt-2 relative">
            <div className="grid grid-cols-[60px_1fr] md:grid-cols-[80px_1fr_1fr_1fr] bg-surface-container-low border-b border-outline-variant sticky top-0 z-10">
              <div className="p-3 flex items-center justify-center text-on-surface-variant font-label-sm text-label-sm border-r border-outline-variant">Hora</div>
              <div className="hidden md:flex p-3 items-center justify-center font-label-md text-label-md text-on-surface border-r border-outline-variant gap-2 bg-surface-container-low">
                <div className="w-6 h-6 rounded-full bg-primary-fixed-dim overflow-hidden flex items-center justify-center">
                  <span className="material-symbols-outlined text-[14px] text-primary">person</span>
                </div>
                Roberto S.
              </div>
              <div className="hidden md:flex p-3 items-center justify-center font-label-md text-label-md text-on-surface border-r border-outline-variant gap-2 bg-surface-container-low">
                <div className="w-6 h-6 rounded-full bg-secondary-fixed-dim overflow-hidden flex items-center justify-center">
                  <span className="material-symbols-outlined text-[14px] text-on-secondary-fixed">person</span>
                </div>
                Maria L.
              </div>
              <div className="hidden md:flex p-3 items-center justify-center font-label-md text-label-md text-on-surface gap-2 bg-surface-container-low">
                <div className="w-6 h-6 rounded-full bg-tertiary-fixed-dim overflow-hidden flex items-center justify-center">
                  <span className="material-symbols-outlined text-[14px] text-tertiary-container">person</span>
                </div>
                Juan P.
              </div>
              <div className="md:hidden p-3 flex items-center justify-center font-label-md text-label-md text-on-surface bg-surface-container-low">
                Agenda Global
              </div>
            </div>

            <div className="relative">
              <div className="grid grid-cols-[60px_1fr] md:grid-cols-[80px_1fr_1fr_1fr] min-h-[100px] border-b border-outline-variant">
                <div className="p-2 md:p-4 flex flex-col items-center justify-start border-r border-outline-variant bg-surface-bright">
                  <span className="font-mono-data text-mono-data text-on-surface-variant">09:00</span>
                  <span className="font-label-sm text-label-sm text-on-surface-variant opacity-60">AM</span>
                </div>
                <div className="col-span-1 md:col-span-3 grid grid-cols-1 md:grid-cols-3">
                  <div className="p-2 border-b md:border-b-0 md:border-r border-outline-variant relative group">
                    <div className="bg-surface border border-outline-variant rounded-lg p-3 h-full flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group cursor-pointer">
                      <div className="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                      <div className="flex justify-between items-start mb-2">
                        <div className="flex flex-col">
                          <span className="font-headline-sm text-headline-sm text-on-surface font-bold">Max</span>
                          <span className="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Golden Retriever</span>
                        </div>
                        <div className="bg-secondary-container text-on-secondary-container px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase border border-secondary-fixed">
                          En Curso
                        </div>
                      </div>
                      <div className="flex items-center gap-2 mb-2">
                        <span className="material-symbols-outlined text-[16px] text-primary">content_cut</span>
                        <span className="font-body-sm text-body-sm text-on-surface">Baño y Corte Full</span>
                      </div>
                      <div className="flex items-center justify-between mt-auto pt-2 border-t border-surface-container-high">
                        <span className="font-mono-data text-mono-data text-on-surface-variant bg-surface-container-low px-1.5 py-0.5 rounded">09:00 - 10:30</span>
                      </div>
                    </div>
                  </div>

                  <div className="p-2 border-b md:border-b-0 md:border-r border-outline-variant relative group">
                    <div className="bg-error-container/20 border border-error-container rounded-lg p-3 h-full flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group cursor-pointer">
                      <div className="absolute top-0 left-0 w-1 h-full bg-error"></div>
                      <div className="flex justify-between items-start mb-2">
                        <div className="flex flex-col">
                          <span className="font-headline-sm text-headline-sm text-on-surface font-bold">Luna</span>
                          <span className="font-label-sm text-label-sm text-error uppercase tracking-wider flex items-center gap-1">
                            Alerta Médica
                          </span>
                        </div>
                        <div className="bg-surface-container text-on-surface-variant px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase border border-outline-variant">
                          Esperando
                        </div>
                      </div>
                      <div className="flex items-center justify-between mt-auto pt-2 border-t border-surface-container-high">
                        <span className="font-mono-data text-mono-data text-on-surface-variant bg-surface-container-low px-1.5 py-0.5 rounded">09:00 - 10:00</span>
                      </div>
                    </div>
                  </div>

                  <div className="p-2 relative group flex items-center justify-center bg-surface-container-lowest hover:bg-surface-container-low transition-colors">
                    <Link to="/admin/assign" className="w-full h-full min-h-[80px]">
                      <button className="border border-dashed border-outline-variant rounded-lg w-full h-full flex flex-col items-center justify-center gap-1 text-on-surface-variant hover:text-primary hover:border-primary transition-colors group-hover:bg-surface-bright">
                        <span className="material-symbols-outlined">add_circle</span>
                        <span className="font-label-md text-label-md">Asignar a Juan</span>
                      </button>
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <nav className="md:hidden fixed bottom-0 left-0 w-full flex justify-around items-center py-2 bg-surface-container-lowest z-50 h-16 border-t border-outline-variant">
        <Link to="/agenda-global" className="flex flex-col items-center justify-center bg-secondary-container text-on-secondary-container rounded-full px-4 py-1">
          <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>calendar_month</span>
        </Link>
        <Link to="/admin/team" className="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1">
          <span className="material-symbols-outlined">groups</span>
        </Link>
      </nav>
    </div>
  );
}
