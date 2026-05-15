import React from 'react';
import { Link } from 'react-router-dom';

export function AssignService() {
  return (
    <div className="bg-background text-on-background font-body-md min-h-screen flex flex-col pb-[72px] md:pb-0">
      <header className="flex items-center px-gutter w-full h-14 bg-surface text-on-surface border-b border-outline-variant font-headline-md text-headline-md sticky top-0 z-50">
        <Link to="/admin/directory" className="mr-4 text-on-surface-variant flex items-center justify-center">
          <span className="material-symbols-outlined">arrow_back</span>
        </Link>
        <span className="font-headline-sm text-headline-sm font-bold text-primary truncate">Nueva Cita</span>
      </header>

      <main className="flex-1 w-full max-w-4xl mx-auto px-gutter py-section-padding md:py-container-margin">
        <form className="space-y-container-margin">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-operational-gap">
            <div className="col-span-1 md:col-span-12 bg-surface-container-lowest p-gutter rounded-xl border border-outline-variant flex flex-col gap-operational-gap">
              <h2 className="font-headline-sm text-headline-sm text-on-surface border-b border-outline-variant pb-2">Cliente y Mascota</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-gutter">
                <div className="flex flex-col gap-tight-stack relative">
                  <label className="font-label-sm text-label-sm text-on-surface-variant">Cliente Registrado</label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">person</span>
                    <input className="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md text-body-md text-on-surface" placeholder="Buscar..." type="text" />
                  </div>
                </div>
                <div className="flex flex-col gap-tight-stack relative">
                  <label className="font-label-sm text-label-sm text-on-surface-variant">Mascota</label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">pets</span>
                    <select className="w-full pl-10 pr-8 py-2 bg-surface border border-outline-variant rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md text-body-md text-on-surface appearance-none defaultValue=''">
                      <option disabled value="">Seleccione la mascota</option>
                      <option value="1">Lola (Golden Retriever)</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="col-span-1 md:col-span-7 bg-surface-container-lowest p-gutter rounded-xl border border-outline-variant flex flex-col gap-operational-gap">
              <h2 className="font-headline-sm text-headline-sm text-on-surface border-b border-outline-variant pb-2">Detalles del Servicio</h2>
              <div className="flex flex-col gap-tight-stack relative">
                <label className="font-label-sm text-label-sm text-on-surface-variant">Tipo de Servicio</label>
                <div className="relative">
                  <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">content_cut</span>
                  <select className="w-full pl-10 pr-8 py-2 bg-surface border border-outline-variant rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md text-body-md text-on-surface appearance-none defaultValue=''">
                    <option disabled value="">Seleccionar servicio</option>
                    <option value="bano">Baño Básico</option>
                  </select>
                </div>
              </div>
              <div className="flex flex-col gap-tight-stack relative mt-2">
                <label className="font-label-sm text-label-sm text-on-surface-variant flex items-center justify-between">
                  <span>Operador Asignado</span>
                  <span className="bg-secondary-container text-on-secondary-container px-2 py-0.5 rounded text-[10px] uppercase tracking-wider font-bold">Crítico</span>
                </label>
                <div className="relative">
                  <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">badge</span>
                  <select className="w-full pl-10 pr-8 py-2 bg-surface border-2 border-primary rounded focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md text-body-md text-on-surface appearance-none font-medium defaultValue=''">
                    <option disabled value="">Seleccione personal disponible</option>
                    <option value="op1">Ana García</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="col-span-1 md:col-span-5 bg-surface-container-lowest p-gutter rounded-xl border border-outline-variant flex flex-col gap-operational-gap">
              <h2 className="font-headline-sm text-headline-sm text-on-surface border-b border-outline-variant pb-2">Programación</h2>
              <div className="flex flex-col gap-operational-gap">
                <div className="flex flex-col gap-tight-stack relative">
                  <label className="font-label-sm text-label-sm text-on-surface-variant">Fecha</label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">calendar_today</span>
                    <input className="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded font-mono-data" type="date" />
                  </div>
                </div>
                <div className="flex flex-col gap-tight-stack relative">
                  <label className="font-label-sm text-label-sm text-on-surface-variant">Hora de Inicio</label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">schedule</span>
                    <input className="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded font-mono-data" type="time" />
                  </div>
                </div>
              </div>
            </div>
            
            <div className="col-span-1 md:col-span-12 bg-surface-container-lowest p-gutter rounded-xl border border-outline-variant flex flex-col gap-operational-gap">
              <h2 className="font-headline-sm text-headline-sm text-on-surface border-b border-outline-variant pb-2">Notas Operativas</h2>
              <div className="flex flex-col gap-tight-stack">
                <label className="font-label-sm text-label-sm text-on-surface-variant">Instrucciones Especiales / Alertas Médicas</label>
                <textarea className="w-full p-3 bg-surface border border-outline-variant rounded resize-none" placeholder="Ej: Perro nervioso..." rows={3}></textarea>
              </div>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row justify-end gap-gutter mt-8 pt-4 border-t border-outline-variant">
            <Link to="/admin/directory">
              <button className="px-6 py-3 border w-full border-outline-variant text-on-surface rounded font-label-md text-label-md hover:bg-surface-container-high text-center flex items-center justify-center gap-2" type="button">
                Cancelar
              </button>
            </Link>
            <Link to="/agenda-global">
              <button className="px-6 py-3 w-full bg-primary text-on-primary rounded font-label-md text-label-md font-bold hover:bg-surface-tint flex items-center justify-center gap-2" type="button">
                <span className="material-symbols-outlined">check_circle</span>
                Confirmar y Asignar
              </button>
            </Link>
          </div>
        </form>
      </main>
    </div>
  );
}
