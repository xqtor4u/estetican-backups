import React from 'react';
import { Link } from 'react-router-dom';

export function GroomerDashboard() {
  return (
    <div className="bg-background text-on-surface">
      <header className="fixed top-0 w-full z-50 flex items-center justify-between px-6 h-16 bg-surface-bright shadow-sm">
        <div className="flex items-center gap-4">
          <Link to="/" className="material-symbols-outlined text-primary cursor-pointer active:scale-95 transition-transform">arrow_back</Link>
          <h1 className="font-headline-sm text-headline-sm font-bold text-primary">BarkStyle Pro</h1>
        </div>
        <div className="flex items-center gap-4">
          <span className="material-symbols-outlined text-primary cursor-pointer active:scale-95 transition-transform">qr_code_scanner</span>
        </div>
      </header>

      <main className="pt-20 pb-24 px-4 max-w-5xl mx-auto space-y-4">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-surface-container-lowest p-4 rounded-xl border border-outline-variant">
          <div className="flex items-center gap-4">
            <div className="relative">
              <img alt="Max the Golden Retriever" className="w-16 h-16 rounded-lg object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDZc8oT_uIEcoiLf7gsIfGJSPSlIzvybXpIeQKnI5wfYw3Eu95Pi_HvhftelC8z88OwnhUHmIHRwj7k8_emqdCLbm9O6LkA4ntANHzQo7KLsvY1YuljizEOtvzKK0VOAqjdj2-CbGLIpvQai6mRTkQ4D-x45WaTWPwGWrm0B4ElzcVPsnMnsI7TwzIVTbt-_5t3wcU_2L0a5Gxkh6uCVDNP_lqojqpjB46jx9wMHtO_7iLiVXs2xM62YmCEATg6-ymcc4LbvXaI5Es" />
              <div className="absolute -bottom-1 -right-1 bg-secondary-container text-on-secondary-container px-2 py-0.5 rounded text-[10px] font-bold border border-white">ESTÁNDAR</div>
            </div>
            <div>
              <h2 className="font-display-sm text-display-sm text-primary">Max</h2>
              <p className="font-label-md text-label-md text-on-surface-variant flex items-center gap-1">
                <span className="material-symbols-outlined text-[14px]">pets</span>
                Golden Retriever • 4 años • Macho
              </p>
            </div>
          </div>
          <Link to="/groomer/active" className="w-full md:w-auto bg-primary text-on-primary font-headline-sm text-headline-sm py-3 px-8 rounded-xl shadow-lg active:scale-95 transition-all flex items-center justify-center gap-2">
            <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>play_arrow</span>
            Iniciar Servicio
          </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-operational-gap">
          <div className="bg-error-container border-2 border-error p-3 rounded-lg flex gap-3">
            <span className="material-symbols-outlined text-error animate-pulse" style={{ fontVariationSettings: "'FILL' 1" }}>medical_services</span>
            <div className="space-y-1">
              <p className="font-label-sm text-label-sm text-on-error-container uppercase">Alerta Médica</p>
              <p className="font-headline-sm text-headline-sm text-on-error-container font-bold">Dermatitis atópica</p>
              <p className="font-body-sm text-body-sm text-on-error-container">Usar solo shampoo hipoalergénico medicado.</p>
            </div>
          </div>

          <div className="bg-surface-container-highest border border-outline-variant p-3 rounded-lg flex gap-3">
            <span className="material-symbols-outlined text-on-surface" style={{ fontVariationSettings: "'FILL' 1" }}>warning</span>
            <div className="space-y-1">
              <p className="font-label-sm text-label-sm text-on-surface-variant uppercase">Comportamiento</p>
              <p className="font-headline-sm text-headline-sm text-primary font-bold">Muerde patas</p>
              <p className="font-body-sm text-body-sm text-on-surface-variant">Reacciona agresivamente al tocar patas traseras.</p>
            </div>
          </div>

          <div className="bg-surface-container-lowest border border-outline-variant p-3 rounded-lg flex items-center justify-between">
            <div className="space-y-1">
              <p className="font-label-sm text-label-sm text-on-surface-variant uppercase">Último Peso</p>
              <p className="font-display-sm text-display-sm text-primary font-mono-data">32.4 <span className="text-label-md">kg</span></p>
              <p className="font-label-sm text-label-sm text-secondary font-bold flex items-center">
                <span className="material-symbols-outlined text-[14px]">trending_down</span>
                -0.5kg vs mes ant.
              </p>
            </div>
            <span className="material-symbols-outlined text-outline-variant text-4xl">scale</span>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-12 gap-operational-gap">
          <div className="md:col-span-4 space-y-4">
            <div className="bg-white border border-outline-variant p-4 rounded-xl">
              <p className="font-label-sm text-label-sm text-on-surface-variant mb-3 uppercase">Propietario</p>
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center">
                  <span className="material-symbols-outlined text-on-primary-fixed">person</span>
                </div>
                <div>
                  <p className="font-headline-sm text-headline-sm text-primary">Claudia Méndez</p>
                  <p className="font-body-sm text-body-sm text-on-surface-variant">+52 55 1234 5678</p>
                </div>
              </div>
              <div className="flex gap-2">
                <button className="flex-1 border border-outline text-primary py-2 rounded-lg flex items-center justify-center gap-2 hover:bg-surface-container transition-colors">
                  <span className="material-symbols-outlined text-[18px]">call</span>
                  Llamar
                </button>
              </div>
            </div>

            <div className="bg-surface-container-low p-4 rounded-xl border border-outline-variant">
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center p-2">
                  <p className="font-label-sm text-label-sm text-on-surface-variant uppercase">Visitas</p>
                  <p className="font-headline-md text-headline-md font-mono-data">24</p>
                </div>
                <div className="text-center p-2 border-l border-outline-variant">
                  <p className="font-label-sm text-label-sm text-on-surface-variant uppercase">No-Show</p>
                  <p className="font-headline-md text-headline-md font-mono-data">0</p>
                </div>
              </div>
            </div>
          </div>

          <div className="md:col-span-8">
            <div className="bg-white border border-outline-variant rounded-xl flex flex-col h-full">
              <div className="p-4 border-b border-outline-variant flex justify-between items-center">
                <h3 className="font-headline-md text-headline-md text-primary flex items-center gap-2">
                  <span className="material-symbols-outlined">history</span>
                  Historial Técnico
                </h3>
              </div>
              <div className="divide-y divide-outline-variant overflow-y-auto max-h-[500px]">
                <div className="p-4 hover:bg-surface-container-lowest transition-colors">
                  <div className="flex justify-between items-start mb-2">
                    <div>
                      <p className="font-headline-sm text-headline-sm text-primary">Corte de Raza Full</p>
                      <p className="font-label-sm text-label-sm text-on-surface-variant">15 Oct 2023 • Groomer: Roberto S.</p>
                    </div>
                    <span className="bg-secondary-container text-on-secondary-container text-[11px] font-bold px-2 py-0.5 rounded-full">COMPLETADO</span>
                  </div>
                  <div className="bg-surface-container-low p-3 rounded-lg border-l-4 border-primary">
                    <p className="font-body-md text-body-md text-primary italic leading-relaxed">
                      "Se utilizó cuchilla #4 en cuerpo y tijera en patas por sensibilidad. El cliente prefiere las orejas redondeadas."
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <nav className="fixed bottom-0 w-full z-50 flex justify-around items-center h-20 px-4 pb-safe bg-surface-container-lowest shadow-lg rounded-t-xl border-t border-outline-variant md:hidden">
        <Link to="/agenda-global" className="flex flex-col items-center justify-center text-on-surface-variant px-5 py-1">
          <span className="material-symbols-outlined">calendar_today</span>
          <span className="font-label-md text-label-md">Agenda</span>
        </Link>
        <Link to="/admin/directory" className="flex flex-col items-center justify-center bg-secondary-container text-on-secondary-container rounded-full px-5 py-1">
          <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
          <span className="font-label-md text-label-md">Clientes</span>
        </Link>
      </nav>
    </div>
  );
}
