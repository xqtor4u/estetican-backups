import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ScreenHeader } from '../ScreenHeader';
import { setNavCrumbs } from '../navState';

export function Directory() {
  const navigate = useNavigate();
  return (
    <div className="bg-background text-on-surface min-h-screen pb-20">
      <ScreenHeader title="Directorio" screenTag="MobDir" noCrumbs />

      <main className="pb-24 px-4 md:px-container-margin max-w-7xl mx-auto">
        <section className="mb-gutter">
          <div className="bg-surface-container-lowest border border-outline-variant p-operational-gap flex flex-col md:flex-row gap-operational-gap shadow-sm rounded-lg">
            <div className="flex-grow flex items-center bg-surface-container-low px-3 py-2 rounded transition-all">
              <span className="material-symbols-outlined text-on-surface-variant mr-2">search</span>
              <input className="bg-transparent border-none focus:ring-0 w-full text-body-md font-body-md" placeholder="Buscar dueño o mascota..." type="text" />
            </div>
            <div className="flex gap-operational-gap overflow-x-auto pb-1 md:pb-0">
              <button className="whitespace-nowrap px-4 py-2 bg-primary text-on-primary font-label-md text-label-md rounded-full shadow-sm hover:opacity-90 active:scale-95 transition-all">Todos</button>
              <button className="whitespace-nowrap px-4 py-2 border border-outline-variant text-on-surface-variant font-label-md text-label-md rounded-full hover:bg-surface-container transition-all">Pendientes</button>
            </div>
          </div>
        </section>

        <div className="mb-gutter bg-error-container border-l-4 border-error p-3 flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-3">
            <span className="material-symbols-outlined text-error">Notifications_active</span>
            <p className="text-on-error-container font-label-md text-label-md">3 mascotas pendientes de entrega hace más de 15 min.</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-operational-gap">
          <div className="bg-surface-container-lowest border border-outline-variant p-3 flex flex-col gap-3 hover:shadow-md transition-shadow">
            <div className="flex justify-between items-start">
              <div className="flex gap-3">
                <img alt="Pet Profile" className="w-12 h-12 object-cover rounded" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDszkcKlj_CcEqEj2Tf58fYDmCY6M5m6H6-jtAkVUNjx1oNG7ZHLHj5oQ7nvhwaOdvW_TumjwZ5I2P_eVFOYNAFHFpn8roCniPvYS-jsLjwtcCaIkWfU6CTGxu9ZEMGL4apVigTWpQOdEVZZgsdnRCK-cdKiHzMgdGRGFXLwN5U2BBvln5MKulBoh8Ipkr1oIY9XI0y8VtfzbIZRtIHNvziuxmCswpEr8Wke2pRnswpLHODpwKQoohegUL40-edE8cSPPm_-YTiKDA" />
                <div>
                  <h3 className="font-headline-sm text-headline-sm text-on-surface">Max</h3>
                  <p className="font-body-sm text-body-sm text-on-surface-variant">Golden Retriever • 5 años</p>
                </div>
              </div>
              <div className="flex flex-col items-end">
                <span className="px-2 py-0.5 bg-secondary-container text-on-secondary-container font-label-sm text-label-sm rounded uppercase tracking-wider">Listo</span>
              </div>
            </div>
            <div className="bg-surface-container-low p-2 rounded flex flex-col gap-1">
              <div className="flex justify-between items-center">
                <span className="font-label-sm text-label-sm text-on-surface-variant/80">Propietario</span>
                <span className="font-body-md text-body-md font-semibold text-on-surface">Carlos Mendoza</span>
              </div>
            </div>
            <div className="grid grid-cols-3 gap-operational-gap">
              <button className="flex items-center justify-center gap-1 py-2 border border-outline-variant hover:bg-surface-container transition-all active:scale-95">
                <span className="material-symbols-outlined text-on-surface-variant">call</span>
              </button>
              <button
                onClick={() => { setNavCrumbs([{ label: 'Directorio', to: '/directorio' }]); navigate('/groomer'); }}
                className="flex items-center justify-center gap-1 py-2 bg-primary text-on-primary hover:opacity-90 transition-all active:scale-95"
              >
                <span className="material-symbols-outlined">person_search</span>
                <span className="font-label-sm text-label-sm">Perfil</span>
              </button>
            </div>
          </div>
        </div>

        <Link to="/admin/assign">
          <button className="fixed bottom-24 right-container-margin w-14 h-14 bg-primary text-on-primary rounded-full shadow-lg flex items-center justify-center active:scale-90 transition-transform duration-200 z-40">
            <span className="material-symbols-outlined">add</span>
          </button>
        </Link>
      </main>

    </div>
  );
}
