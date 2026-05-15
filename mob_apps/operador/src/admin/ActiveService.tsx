import React from 'react';
import { Link } from 'react-router-dom';

export function ActiveService() {
  return (
    <div className="bg-background text-on-background min-h-screen pb-24">
      <header className="fixed top-0 w-full z-50 flex items-center justify-between px-6 h-16 bg-surface-bright shadow-sm">
        <div className="flex items-center gap-4">
          <Link to="/agenda-global" className="p-2 hover:bg-surface-container-high transition-colors active:scale-95 duration-150 rounded-full">
            <span className="material-symbols-outlined text-primary">arrow_back</span>
          </Link>
          <span className="font-headline-sm text-headline-sm font-bold text-primary">BarkStyle Pro</span>
        </div>
      </header>
      
      <main className="pt-20 px-4 md:px-container-margin max-w-4xl mx-auto space-y-6">
        <section className="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl shadow-sm">
          <div className="flex flex-col items-center text-center">
            <span className="font-label-sm text-label-sm text-on-surface-variant mb-1 uppercase tracking-widest">TIEMPO TRANSCURRIDO</span>
            <div className="font-mono-data text-[48px] leading-none font-bold text-primary tracking-tighter mb-4">
              00:42:15
            </div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2 px-3 py-1 bg-secondary-container text-on-secondary-container rounded-full">
                <div className="w-2 h-2 bg-secondary rounded-full animate-pulse"></div>
                <span className="font-label-md text-label-md">EN PROCESO</span>
              </div>
              <button className="flex items-center gap-2 px-4 py-1 border border-outline text-on-surface-variant hover:bg-surface-container-low transition-colors rounded-full">
                <span className="material-symbols-outlined text-[18px]">pause</span>
                <span className="font-label-md text-label-md">PAUSAR</span>
              </button>
            </div>
          </div>
        </section>

        <section className="grid grid-cols-1 md:grid-cols-3 gap-operational-gap">
          <div className="md:col-span-2 bg-surface-container-lowest border border-outline-variant p-4 rounded-lg flex items-center gap-4">
            <img alt="Golden Retriever" className="w-20 h-20 rounded-lg object-cover border border-outline-variant" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCdJvXDqc6t5oowA8xbONO3_zhhN7_nwM_AWJnJ3SUMjg6eX-KgMmcaSPHSM1d_2k0Xs22M2Ukv0vQPsP6UJfgmFkhIe_9hbbOGDuF0SoMFWaUP26SSMl03Q5S8t4KdaFW1LYk6OpiHaW8FY59V7-vqdyU40JM3hZTDNhwKb-skD7eEv8SId1JEbx4zUcbkd8kWvCxHqv8wdB98B17EWmL23p3SWGPjXCitXSBElfJko6XRk1f-ELDOCyfjxaCFroq9KyEschD3Fp4" />
            <div>
              <h2 className="font-headline-md text-headline-md text-primary">Max • Golden Retriever</h2>
              <p className="font-body-sm text-body-sm text-on-surface-variant">Corte Ejecutivo + Tratamiento Keratina</p>
              <div className="mt-2 flex gap-2">
                <span className="px-2 py-0.5 border-2 border-error text-error font-label-sm text-label-sm rounded uppercase flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">warning</span> Alerta Médica: Piel Sensible
                </span>
              </div>
            </div>
          </div>
          <div className="bg-surface-container-lowest border border-outline-variant p-4 rounded-lg flex flex-col justify-center">
            <span className="font-label-sm text-label-sm text-on-surface-variant">CLIENTE</span>
            <span className="font-body-md text-body-md font-bold">Ricardo M.</span>
            <button className="mt-2 text-secondary font-label-md text-label-md flex items-center gap-1 hover:underline">
              <span className="material-symbols-outlined text-[16px]">chat</span> CONTACTAR
            </button>
          </div>
        </section>

        <section className="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
          <div className="p-4 border-b border-outline-variant bg-surface-container-low">
            <h3 className="font-headline-sm text-headline-sm text-primary">Etapas del Servicio</h3>
          </div>
          <div className="divide-y divide-outline-variant">
            <div className="flex items-center justify-between p-4 bg-secondary-container/10">
              <div className="flex items-center gap-4">
                <div className="w-8 h-8 flex items-center justify-center bg-secondary text-on-secondary rounded-full">
                  <span className="material-symbols-outlined text-[20px]">check</span>
                </div>
                <div>
                  <p className="font-body-md text-body-md font-bold text-on-surface">1. Baño</p>
                  <p className="font-label-sm text-label-sm text-secondary">Completado a las 14:15</p>
                </div>
              </div>
              <span className="material-symbols-outlined text-secondary">task_alt</span>
            </div>
            <div className="flex items-center justify-between p-4 bg-surface-container-highest/20">
              <div className="flex items-center gap-4">
                <div className="w-8 h-8 flex items-center justify-center bg-primary text-on-primary rounded-full">
                  <span className="font-label-md text-label-md">2</span>
                </div>
                <div>
                  <p className="font-body-md text-body-md font-bold text-primary">2. Secado</p>
                  <p className="font-label-sm text-label-sm text-on-surface-variant italic">En progreso...</p>
                </div>
              </div>
              <button className="px-4 py-1.5 bg-primary text-on-primary font-label-md text-label-md rounded-lg active:scale-95 transition-transform">
                MARCAR COMPLETADO
              </button>
            </div>
            <div className="flex items-center justify-between p-4 opacity-50">
              <div className="flex items-center gap-4">
                <div className="w-8 h-8 flex items-center justify-center border border-outline text-outline rounded-full">
                  <span className="font-label-md text-label-md">3</span>
                </div>
                <p className="font-body-md text-body-md font-bold text-on-surface-variant">3. Corte</p>
              </div>
              <span className="material-symbols-outlined text-outline">circle</span>
            </div>
          </div>
        </section>

        <section className="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-headline-sm text-headline-sm text-primary">Evidencia de Resultado</h3>
            <span className="font-label-sm text-label-sm text-on-surface-variant">Mínimo 1 foto</span>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <button className="aspect-square flex flex-col items-center justify-center border-2 border-dashed border-outline-variant rounded-xl hover:bg-surface-container-low transition-colors group">
              <span className="material-symbols-outlined text-outline group-hover:text-primary mb-2 text-[32px]">add_a_photo</span>
              <span className="font-label-sm text-label-sm text-on-surface-variant">SUBIR FOTO</span>
            </button>
            <div className="aspect-square rounded-xl overflow-hidden border border-outline-variant relative group">
              <img alt="Grooming progress" className="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCFbZ6X2HbD5Vo-SCy-FATSfCWe9eLDIdDYw0OYmFLwJLfSeHqUD1fBX-auqPZCyxd0ZIVV5pYb-SCrUFVKQQk9kMnyGTFvo5iqYjbVFxr_AuGN2Rgn9i5rmoWHEguy8qrrKIyyRYCrld5Le5ve7LZR02fViUy0VuuQS4q12ECTTYj9JRzq-oX0akrN7dlOmlrdmRjQz3RN_9s62AoQhK10cXeZlWcC1RFGuGB7GLgmo83e5z2T9AGomlvu6AkfAIGHhXSH_lNEzZY" />
            </div>
          </div>
        </section>

        <div className="pt-4">
          <Link to="/admin/directory">
            <button className="w-full h-14 bg-primary text-on-primary font-headline-sm text-headline-sm rounded-xl flex items-center justify-center gap-3 shadow-lg hover:brightness-110 active:scale-[0.98] transition-all">
              <span className="material-symbols-outlined">send</span>
              Finalizar y Notificar Dueño
            </button>
          </Link>
        </div>
      </main>

      <nav className="fixed bottom-0 w-full z-50 flex justify-around items-center h-20 px-4 pb-safe bg-surface-container-lowest shadow-lg rounded-t-xl md:hidden">
        <Link to="/agenda-global" className="flex flex-col items-center justify-center text-on-surface-variant px-5 py-1">
          <span className="material-symbols-outlined">calendar_today</span>
        </Link>
        <Link to="/groomer/active" className="flex flex-col items-center justify-center bg-secondary-container text-on-secondary-container rounded-full px-5 py-1">
          <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>inventory_2</span>
        </Link>
      </nav>
    </div>
  );
}
