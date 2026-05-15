import React from 'react';
import { Link } from 'react-router-dom';

export function ClientBooking() {
  return (
    <div className="bg-background text-on-background font-body-md min-h-screen pb-32">
      <header className="bg-surface/80 backdrop-blur-md shadow-[0px_4px_20px_rgba(74,144,226,0.05)] docked full-width top-0 z-50 fixed flex justify-between items-center w-full px-margin-mobile h-16">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full overflow-hidden border-2 border-primary/10">
            <img alt="User profile" className="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBFVcrd2PVlgLIoJprOac35d0uPpc4EDWyqLXSmzOJBc6fg1CWGKkCoaFLbpu-FdGQcwMl4OwYYt7qe1b83jQ7HQJGIxK881XWj-J9pjUw2TPs9bmxTqPcHXQ5Un97fEbaCsK3ugJOc6OUhWt0Pv59klptEZeuU3KL5C9pgGcy711OKK1A_cZljtNY1MfZk84V8-yfvZRmPqXlgPKs5Sb5GajYHiuPbc4519rx3c-AyDeXUsYV7pnfgRSCQXFMciuis1wtqMf6uvng" />
          </div>
          <h1 className="font-headline-md text-headline-md font-bold text-primary tracking-tight">Agendar Servicio</h1>
        </div>
      </header>

      <main className="pt-24 px-margin-mobile max-w-screen-xl mx-auto space-y-xl">
        <section>
          <div className="flex justify-between items-end mb-md">
            <h2 className="font-headline-sm text-headline-sm text-on-background">Selecciona un Servicio</h2>
            <span className="text-label-md font-label-md text-primary">Paso 1 de 4</span>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-md">
            <div className="bg-surface-container-lowest rounded-[24px] overflow-hidden shadow-[0px_10px_25px_rgba(74,144,226,0.08)] border-2 border-secondary-container transition-all hover:scale-[1.02] cursor-pointer">
              <div className="h-32 w-full bg-cover bg-center" style={{ backgroundImage: "url('https://lh3.googleusercontent.com/aida-public/AB6AXuCDI78gjxw0SngIWGwq5eDxvEX8VDhf82ncXdsfnRysY-Ee7PHX1d3M5zrvK5J1hct8n0_DZoHqTQVbBy_2WIIztQX1TqhwRuztN28n5FpJyfzaHteFoHxtngQSsYbFzEzbtOrGGeXwNyw6cOBq9zrW_fSK4eP7Mc3NrSwf-__7fu_legc5BTjAs22fyf7YUGlnVk0r5IGNgdMw9B0_C37FQb8RQ8nz67LzsquSw24XHTo4pK8oRBEBN0vPm9Ip1kjGVNWzqqV58lg')" }}></div>
              <div className="p-lg">
                <h3 className="font-headline-md text-headline-md mb-xs">Corte de Pelo</h3>
                <p className="text-on-surface-variant font-body-md mb-md">Estilo y frescura para tu mejor amigo.</p>
                <div className="flex justify-between items-center">
                  <span className="text-secondary font-headline-sm">$35.00</span>
                  <span className="bg-secondary-container text-on-secondary-container px-3 py-1 rounded-full font-label-sm text-label-sm">Popular</span>
                </div>
              </div>
            </div>
            <div className="bg-surface-container-lowest rounded-[24px] overflow-hidden shadow-[0px_10px_25px_rgba(74,144,226,0.08)] border-2 border-transparent hover:border-outline-variant/30 transition-all hover:scale-[1.02] cursor-pointer">
              <div className="h-32 w-full bg-cover bg-center" style={{ backgroundImage: "url('https://lh3.googleusercontent.com/aida-public/AB6AXuB_D51QW5sPdHDZIADoR_pHr6EQ3kWJdW6jp4dQ5jBIo9rDQflu-BpRR5IGW6PKFBuhFAWVXtA4-Wi7xEXO0oUEOdLxRl7V5tbs8BM6154c1cx73C-czFDvCKdZJlmBBXgqWF2aXrIde1Uz6goH9hOVz238eLLVtl-Yyu4T4HXocruoK2FdKb2BuaHRDf8Ir6aO9uNt1ua-sXOFsuSZr_HimTCAeWKoXgFoBx8Jh7bB_Tku2OrjuIwvRLGoULM_jIWNKde--kbZR-k')" }}></div>
              <div className="p-lg">
                <h3 className="font-headline-md text-headline-md mb-xs">Corte de Uñas</h3>
                <p className="text-on-surface-variant font-body-md mb-md">Cuidado esencial para pasos seguros.</p>
                <div className="flex justify-between items-center">
                  <span className="text-secondary font-headline-sm">$15.00</span>
                  <span className="material-symbols-outlined text-outline">check_circle</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section>
          <div className="flex justify-between items-end mb-md">
            <h2 className="font-headline-sm text-headline-sm text-on-background">¿Para quién es la cita?</h2>
            <span className="text-label-md font-label-md text-primary">Paso 2 de 4</span>
          </div>
          <div className="flex gap-md overflow-x-auto hide-scrollbar pb-xs">
            <div className="flex-shrink-0 flex flex-col items-center gap-sm">
              <div className="w-20 h-20 rounded-full border-4 border-primary p-1 bg-surface ring-4 ring-primary-fixed">
                <img alt="Max" className="w-full h-full object-cover rounded-full" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAca1Uh-Hr8xbA1zgpgiFQltYr7e6Zgld474GjnQZbxSIVJG8RMR0SKvdZRuyN6uWKp8Ce3DX5uzgJ_4FLIPjYTqDZTzRAeQ0PA6_fKb3Zg0MbhB641jl0pXRVYdqNXwlJjRYuITGkfdjZfTfJv-mBfxPNpbCXD33ECHrlhYpNGD6lbEjMRIhu-2H65cdiygP-WsN6GGcp9LseAB8DZM2GiHqeyhRGEtdJA85xVvyj2qkIb_8_2mclnVSo-b_sT294kX_ONlRzEfl4" />
              </div>
              <span className="font-label-md text-primary">Max</span>
            </div>
            <div className="flex-shrink-0 flex flex-col items-center gap-sm">
              <div className="w-20 h-20 rounded-full border-2 border-outline-variant p-1 bg-surface">
                <img alt="Bella" className="w-full h-full object-cover rounded-full opacity-60" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD6AcRsXfVkSqe89HviaaMTsU4qxqPJD6m-xFnmlRoDPfxaQU0bK_es92udmmNvaUZVkbxMvMXDK6ly_4art3qjhyBRNSYcFP4J90gJN8wwnUeRZ-pxjUx756O404hXG_swA8kEr-mrCz9yVuO3KYwuDQV9MevQ_ndQvrPRywfcx2pMFmH2D5gMCDsULVNQd60PzDVAIFSC_-ye7iR8WhXBx4cuKOUee1S3SvapCi4S86Lsiv92fye8dsdOyBeMJChQb1Kd_c-AF5g" />
              </div>
              <span className="font-label-md text-on-surface-variant">Bella</span>
            </div>
          </div>
        </section>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-lg">
          <section className="bg-surface-container-low p-lg rounded-[32px] shadow-[0px_10px_25px_rgba(74,144,226,0.05)]">
            <div className="flex justify-between items-center mb-lg">
              <h2 className="font-headline-sm text-headline-sm">Elige la Fecha</h2>
            </div>
            <div className="grid grid-cols-7 gap-xs text-center">
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Lun</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Mar</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Mie</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Jue</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Vie</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Sab</span>
              <span className="text-label-sm font-label-md text-on-surface-variant py-2">Dom</span>
              <div className="aspect-square flex items-center justify-center text-label-md font-body-md opacity-20">26</div>
              <div className="aspect-square flex items-center justify-center text-label-md font-body-md opacity-20">27</div>
              <div className="aspect-square flex items-center justify-center text-label-md font-body-md">1</div>
              <div className="aspect-square flex items-center justify-center text-label-md font-body-md bg-primary-fixed-dim text-on-primary-fixed rounded-full font-bold">9</div>
              <div className="aspect-square flex items-center justify-center text-label-md font-body-md">10</div>
            </div>
          </section>

          <section className="bg-surface-container-low p-lg rounded-[32px] shadow-[0px_10px_25px_rgba(74,144,226,0.05)]">
            <h2 className="font-headline-sm text-headline-sm mb-lg">Horarios Disponibles</h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-md">
              <button className="py-3 px-4 rounded-xl border border-outline-variant text-on-surface-variant font-label-md hover:border-primary hover:text-primary transition-all">09:00 AM</button>
              <button className="py-3 px-4 rounded-xl bg-secondary-container text-on-secondary-container font-label-md shadow-sm">11:30 AM</button>
            </div>
            <div className="mt-xl">
              <button className="w-full bg-tertiary-container hover:opacity-90 text-white font-headline-sm py-4 rounded-2xl shadow-lg shadow-tertiary/20 active:scale-95 transition-all">
                Confirmar Cita
              </button>
            </div>
          </section>
        </div>
      </main>

      <nav className="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-xs pb-safe h-20 bg-surface-container-lowest border-t border-outline-variant/30 rounded-t-xl shadow-[0px_-10px_25px_rgba(74,144,226,0.08)]">
        <Link to="/client/dashboard" className="flex flex-col items-center justify-center text-on-surface-variant py-2 hover:bg-surface-container-high transition-colors active:scale-90 duration-200">
          <span className="material-symbols-outlined">grid_view</span>
          <span className="font-label-sm text-label-sm mt-1">Inicio</span>
        </Link>
        <button className="flex flex-col items-center justify-center bg-secondary-container text-on-secondary-container rounded-full px-4 py-1 mt-1 active:scale-90 transition-transform duration-200">
          <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>calendar_month</span>
          <span className="font-label-sm text-label-sm">Reservas</span>
        </button>
      </nav>
    </div>
  );
}
