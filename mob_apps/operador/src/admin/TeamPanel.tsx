import React from 'react';
import { Link } from 'react-router-dom';

export function TeamPanel() {
  return (
    <div className="bg-background text-on-background min-h-screen pb-20 md:pb-0 pt-14">
      <header className="bg-surface dark:bg-on-primary-container text-on-surface dark:text-surface font-headline-md text-headline-md docked full-width top-0 border-b border-outline-variant flex items-center justify-between px-gutter w-full h-14 fixed z-50">
        <div className="flex items-center gap-operational-gap">
          <Link to="/" className="text-on-surface hover:bg-surface-container-high transition-colors rounded-full p-2 flex items-center justify-center">
            <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 0" }}>arrow_back</span>
          </Link>
          <span className="font-headline-sm text-headline-sm font-bold text-primary dark:text-secondary-fixed-dim">SPA Canino Admin</span>
        </div>
      </header>

      <main className="container mx-auto px-gutter py-section-padding md:grid md:grid-cols-12 gap-container-margin">
        <div className="md:col-span-8 flex flex-col gap-operational-gap">
          <div className="flex justify-between items-end mb-4">
            <div>
              <h1 className="font-display-sm text-display-sm text-on-surface">Panel de Equipo</h1>
              <p className="font-body-sm text-body-sm text-on-surface-variant mt-1">Gestión operativa y disponibilidad en tiempo real.</p>
            </div>
          </div>

          <div className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div className="relative">
              <img alt="Team member profile" className="w-16 h-16 rounded-full object-cover border-2 border-surface" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCYQnbh-zdZeEXnDdCTdDZl70RzWlQ_To1afvkGeR5E-7equIDlWF5p2br5vPszoLz5f6kvSP9mY35ORW0C4pFjjNHkVm1wtMrfhOqdjyevGzwMBAGwbQ-HzyVFjUMOmMyY8xUugr7F9L2vZ5disQJwEnvVJH-E7eO58PgIM6R2-axLbLjkCYZH-YYClZOmecv6VvlkcS_nW1CPL3xyzZDigqWqCA8xiCN1arQ6MUT_5Tk34lLadHBxIlpzuYBe2oB368bhPGkgyGo" />
              <div className="absolute bottom-0 right-0 w-4 h-4 bg-secondary border-2 border-surface rounded-full"></div>
            </div>
            <div className="flex-1 w-full">
              <div className="flex justify-between items-start">
                <div>
                  <h2 className="font-headline-sm text-headline-sm text-on-surface">María G.</h2>
                  <span className="font-label-sm text-label-sm text-secondary uppercase bg-secondary-container px-2 py-0.5 rounded-sm inline-block mt-1">Disponible</span>
                </div>
                <span className="font-mono-data text-mono-data text-on-surface-variant bg-surface-container px-2 py-1 rounded">0 pend.</span>
              </div>
              <div className="mt-3 flex items-center justify-between">
                <div className="text-on-surface-variant font-body-sm text-body-sm flex items-center gap-1">
                  <span className="material-symbols-outlined text-[16px]">schedule</span>
                  Turno: 08:00 - 16:00
                </div>
                <Link to="/agenda-global">
                  <button className="bg-primary text-on-primary font-label-md text-label-md px-4 py-2 rounded-lg hover:opacity-90 transition-opacity">
                    Ver Agenda Individual
                  </button>
                </Link>
              </div>
            </div>
          </div>
          
          <div className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center relative overflow-hidden">
            <div className="absolute left-0 top-0 bottom-0 w-1 bg-tertiary-fixed-dim"></div>
            <div className="relative">
              <img alt="Team member profile" className="w-16 h-16 rounded-full object-cover border-2 border-surface" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBvqtVnhJV1cB5gbm6abd-eYJa95W9ER-ZgAolplI-Yg2fE22YbMtu1flNRNUtvvnDXN25qWSdxE0FdOfMkHjBtozEcK89MqcsT-OdJfqvhWpd7pu8hKy_oM7fEhffUmfgDQLl8wXRUMM9lhbTn1e4VAjmt9h2l8cYeTmp4KcScBsrylJIPBXgoXSzKkXtj09FvooY3dDfWHOWUOSojcYduAiVIFmtnPIMhyuztpO_WPTTHAnkowr8ekTCDPH4HY7agwG5cfNSH5Z8" />
              <div className="absolute bottom-0 right-0 w-4 h-4 bg-tertiary-fixed-dim border-2 border-surface rounded-full animate-pulse"></div>
            </div>
            <div className="flex-1 w-full">
              <div className="flex justify-between items-start">
                <div>
                  <h2 className="font-headline-sm text-headline-sm text-on-surface">Carlos M.</h2>
                  <span className="font-label-sm text-label-sm text-on-tertiary-container uppercase bg-tertiary-fixed px-2 py-0.5 rounded-sm inline-block mt-1">En Servicio</span>
                </div>
                <span className="font-mono-data text-mono-data text-error bg-error-container px-2 py-1 rounded font-bold">3 pend.</span>
              </div>
              <div className="mt-2 bg-surface-container-low border border-outline-variant rounded-lg p-2 flex items-center gap-2">
                <span className="material-symbols-outlined text-on-surface-variant text-[18px]">cut</span>
                <div className="flex-1 font-body-sm text-body-sm text-on-surface">
                  <span className="font-bold">Max (Golden)</span> - Baño y Corte
                </div>
                <span className="font-mono-data text-mono-data text-on-surface-variant">15m rest.</span>
              </div>
            </div>
          </div>
        </div>

        <div className="md:col-span-4 mt-8 md:mt-0">
          <div className="bg-surface-container-lowest border border-outline-variant rounded-xl p-section-padding sticky top-20">
            <h3 className="font-headline-sm text-headline-sm text-on-surface mb-4 flex items-center gap-2">
              <span className="material-symbols-outlined">analytics</span>
              Resumen Operativo
            </h3>
            <div className="flex flex-col gap-tight-stack">
              <div className="bg-surface-container-low p-3 rounded-lg flex justify-between items-center border border-surface-variant">
                <span className="font-body-sm text-body-sm text-on-surface-variant">Servicios Completados</span>
                <span className="font-display-sm text-display-sm text-primary">24</span>
              </div>
              <div className="bg-surface-container-low p-3 rounded-lg flex justify-between items-center border border-surface-variant">
                <span className="font-body-sm text-body-sm text-on-surface-variant">En Curso</span>
                <span className="font-display-sm text-display-sm text-tertiary-fixed-dim">3</span>
              </div>
            </div>
            <div className="mt-6">
              <h4 className="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider mb-2">Estado General</h4>
              <div className="w-full bg-surface-container-high rounded-full h-2.5 overflow-hidden">
                <div className="bg-secondary h-2.5" style={{ width: "65%" }}></div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <nav className="md:hidden fixed bottom-0 left-0 w-full flex justify-around items-center py-2 bg-surface-container-lowest dark:bg-primary-container border-t border-outline-variant dark:border-on-primary-fixed-variant z-50 h-16">
        <Link to="/agenda-global" className="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1">
          <span className="material-symbols-outlined">calendar_month</span>
        </Link>
        <Link to="/admin/team" className="flex flex-col items-center justify-center bg-secondary-container text-on-secondary-container rounded-full px-4 py-1">
          <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>groups</span>
        </Link>
      </nav>
    </div>
  );
}
