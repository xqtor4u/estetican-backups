import React from 'react';
import { Link } from 'react-router-dom';

export function ClientDashboard() {
  return (
    <div className="bg-background text-on-surface min-h-screen pb-24">
      {/* TopAppBar */}
      <header className="bg-surface/80 backdrop-blur-md shadow-[0px_4px_20px_rgba(74,144,226,0.05)] docked full-width top-0 z-50 flex justify-between items-center w-full px-margin-mobile h-16 sticky">
        <div className="flex items-center gap-3">
          <Link to="/" className="material-symbols-outlined text-primary cursor-pointer active:scale-95 transition-transform">arrow_back</Link>
          <div className="w-10 h-10 rounded-full overflow-hidden border-2 border-primary-fixed shadow-sm">
            <img alt="Profile" src="https://lh3.googleusercontent.com/aida-public/AB6AXuABk_r7gz5KEUyJ8bLaATNwgiTq6SgU64lpV8dQtc-r7CqI_5UHzEY1xcLRWag3mGMl0TJqKLktrruxrd4b0QASZamZYs8hjklrvt8s1OOZWlqpwTSDHSLTZLrSmMZmx-6gwt0z-RHVD5LjYn_LftpCmWnvkJX9Vna63kloEu3rFq_DjnMMc0QDW-b6XJt_Er2f1NTXQNM2biQHy-dgFTZkmrgEcN1KdHga-GsONbgTgd6HBwKpjeAG0czTe0Me5XY682UoqN28Bm8" />
          </div>
          <h1 className="font-headline-md text-headline-md font-bold text-primary tracking-tight">EstetiCAN</h1>
        </div>
        <button className="active:scale-95 transition-transform duration-200 hover:opacity-80 transition-opacity">
          <span className="material-symbols-outlined text-primary text-2xl">notifications</span>
        </button>
      </header>
      
      <main className="px-margin-mobile pt-lg max-w-screen-xl mx-auto space-y-xl">
        {/* Welcome Section */}
        <section className="flex flex-col gap-xs">
          <p className="font-label-md text-label-md text-on-surface-variant">¡Hola, Roberto!</p>
          <h2 className="font-headline-lg text-headline-lg text-on-surface">Tus peludos están en buenas manos</h2>
        </section>
        
        {/* Active Status Card: Pet in SPA */}
        <section className="bg-secondary-container p-lg rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] relative overflow-hidden">
          <div className="flex justify-between items-start mb-md relative z-10">
            <div className="flex items-center gap-md">
              <div className="w-14 h-14 rounded-full border-4 border-white overflow-hidden">
                <img alt="Bruno" src="https://lh3.googleusercontent.com/aida-public/AB6AXuABiOcWwHQecsTVsk0bux8tifiYN4fssLbVW7QKGZ_r8CklmePNTwqM-DsrPbwtlJqImlxLTWbXKLDs0iuFhFnpOqd5ci-EgIF998a0M_-pL3OUgdVSCEIHaEzmyPaNkWStVebYWCELaHafR1843a5Ub33P0j3WH9m2Uw4Tx1KgxMVR1L38wqtsOBsoqgQRJb_DEOhngcafLKlXUS1Qv-SrmBt2sPOpmul4mi_Yar54NGhBW-9eosHUkDAKUUaoJDZEGEg-ydE-zKY" />
              </div>
              <div>
                <h3 className="font-headline-sm text-headline-sm text-on-secondary-container">Bruno está en el SPA</h3>
                <p className="font-label-md text-label-md text-on-secondary-container/80">Servicio de Baño & Corte</p>
              </div>
            </div>
            <span className="bg-secondary text-on-secondary px-3 py-1 rounded-full text-label-sm font-label-sm">En progreso</span>
          </div>
          <div className="relative z-10 space-y-xs">
            <div className="flex justify-between text-label-sm text-on-secondary-container">
              <span>Etapa: Secado</span>
              <span>75%</span>
            </div>
            <div className="w-full bg-white/40 h-3 rounded-full overflow-hidden">
              <div className="bg-secondary h-full rounded-full w-[75%] shadow-sm"></div>
            </div>
          </div>
          {/* Abstract background shape */}
          <div className="absolute -right-12 -top-12 w-48 h-48 bg-secondary/5 rounded-full blur-3xl"></div>
        </section>

        {/* Upcoming Appointments */}
        <section>
          <div className="flex justify-between items-center mb-md">
            <h3 className="font-headline-md text-headline-md text-on-surface">Próximas Citas</h3>
            <button className="text-primary font-label-md hover:opacity-80">Ver todas</button>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-md">
            {/* Appointment Card 1 */}
            <div className="bg-surface-container-lowest p-md rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex gap-md items-center border border-outline-variant/20">
              <div className="bg-primary-fixed w-16 h-16 rounded-xl flex items-center justify-center">
                <span className="material-symbols-outlined text-primary text-3xl">content_cut</span>
              </div>
              <div className="flex-1">
                <h4 className="font-headline-sm text-headline-sm text-on-surface">SPA Premium</h4>
                <p className="font-body-md text-body-md text-on-surface-variant">Luna • Hoy, 16:30</p>
              </div>
              <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
            </div>
            {/* Appointment Card 2 */}
            <div className="bg-surface-container-lowest p-md rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex gap-md items-center border border-outline-variant/20">
              <div className="bg-tertiary-fixed w-16 h-16 rounded-xl flex items-center justify-center">
                <span className="material-symbols-outlined text-tertiary text-3xl">hotel</span>
              </div>
              <div className="flex-1">
                <h4 className="font-headline-sm text-headline-sm text-on-surface">Hotel Canino</h4>
                <p className="font-body-md text-body-md text-on-surface-variant">Toby • Mañana, 09:00</p>
              </div>
              <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
            </div>
          </div>
        </section>

        {/* Quick Access Bento Grid */}
        <section>
          <h3 className="font-headline-md text-headline-md text-on-surface mb-md">Accesos Directos</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-md">
            <div className="bg-white p-lg rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex flex-col items-center justify-center text-center gap-sm active:scale-95 transition-transform group cursor-pointer border border-transparent hover:border-primary-fixed">
              <div className="w-14 h-14 bg-primary-fixed rounded-full flex items-center justify-center mb-xs group-hover:scale-110 transition-transform">
                <span className="material-symbols-outlined text-primary text-2xl">style</span>
              </div>
              <p className="font-label-md text-label-md text-on-surface">Corte de Pelo</p>
            </div>
            <div className="bg-white p-lg rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex flex-col items-center justify-center text-center gap-sm active:scale-95 transition-transform group cursor-pointer border border-transparent hover:border-secondary-fixed">
              <div className="w-14 h-14 bg-secondary-fixed rounded-full flex items-center justify-center mb-xs group-hover:scale-110 transition-transform">
                <span className="material-symbols-outlined text-secondary text-2xl">content_cut</span>
              </div>
              <p className="font-label-md text-label-md text-on-surface">Corte de Uñas</p>
            </div>
            <div className="bg-white p-lg rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex flex-col items-center justify-center text-center gap-sm active:scale-95 transition-transform group cursor-pointer border border-transparent hover:border-tertiary-fixed">
              <div className="w-14 h-14 bg-tertiary-fixed rounded-full flex items-center justify-center mb-xs group-hover:scale-110 transition-transform">
                <span className="material-symbols-outlined text-tertiary text-2xl">emoji_events</span>
              </div>
              <p className="font-label-md text-label-md text-on-surface">Concursos</p>
            </div>
            <div className="bg-white p-lg rounded-[24px] shadow-[0px_10px_25px_rgba(74,144,226,0.08)] flex flex-col items-center justify-center text-center gap-sm active:scale-95 transition-transform group cursor-pointer border border-transparent hover:border-primary-fixed-dim">
              <div className="w-14 h-14 bg-primary-fixed-dim rounded-full flex items-center justify-center mb-xs group-hover:scale-110 transition-transform">
                <span className="material-symbols-outlined text-on-primary-fixed-variant text-2xl">house_siding</span>
              </div>
              <p className="font-label-md text-label-md text-on-surface">Hotelería</p>
            </div>
          </div>
        </section>

        {/* CTA Feature */}
        <section className="bg-tertiary-container text-on-tertiary-container rounded-[24px] p-lg flex items-center justify-between shadow-[0px_10px_25px_rgba(74,144,226,0.08)]">
          <div className="max-w-[60%]">
            <h3 className="font-headline-sm text-headline-sm mb-xs">¿Necesitas una cita urgente?</h3>
            <p className="font-body-md text-body-md opacity-90 mb-md">Reserva un espacio hoy mismo con nuestros mejores especialistas.</p>
            <Link to="/client/booking">
              <button className="bg-white text-tertiary font-label-md px-lg py-sm rounded-full active:scale-90 transition-transform">Agendar Ahora</button>
            </Link>
          </div>
          <div className="w-32 h-32 hidden md:block">
            <img alt="Promo Dog" className="w-full h-full object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAWxlPas0JH-LxSt4SJVGdZMz5tPnyda-m3bODRqmrK-sjqIyQfsb5xLE9rWzvdrJ_ACA4LPFti3M8Yx03HuADp7MLkOXWcZMfufCANkbfRJZIB8AdbWKuLBYemPe9qr4FdpIOcdgKYaD9hfEaWEjBiyRboEK1SZ3PWXt45vpJ6cOHvD8RGjOKMLsiy7oeaAL8jxZBbbhh2yQL2stR4Xe2I1fDXSpRtJ3JV35USf88NNlFG5SfM9qE9kpfy63zaOA_JK6TPgyMZDCc" />
          </div>
        </section>
      </main>

      {/* BottomNavBar */}
      <nav className="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-xs pb-safe h-20 bg-surface-container-lowest dark:bg-inverse-surface shadow-[0px_-10px_25px_rgba(74,144,226,0.08)] rounded-t-xl">
        <button className="flex flex-col items-center justify-center bg-secondary-container dark:bg-secondary text-on-secondary-container dark:text-on-secondary rounded-full px-4 py-1 mt-1 active:scale-90 transition-transform duration-200">
          <span className="material-symbols-outlined text-2xl">grid_view</span>
          <span className="font-label-sm text-label-sm">Inicio</span>
        </button>
        <button className="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline-variant py-2 hover:bg-surface-container-high dark:hover:bg-surface-variant transition-colors active:scale-90 transition-transform duration-200">
          <span className="material-symbols-outlined text-2xl">content_cut</span>
          <span className="font-label-sm text-label-sm">Servicios</span>
        </button>
        <Link to="/client/booking" className="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline-variant py-2 hover:bg-surface-container-high dark:hover:bg-surface-variant transition-colors active:scale-90 transition-transform duration-200">
          <span className="material-symbols-outlined text-2xl">calendar_month</span>
          <span className="font-label-sm text-label-sm">Reservas</span>
        </Link>
        <button className="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline-variant py-2 hover:bg-surface-container-high dark:hover:bg-surface-variant transition-colors active:scale-90 transition-transform duration-200">
          <span className="material-symbols-outlined text-2xl">pets</span>
          <span className="font-label-sm text-label-sm">Mascotas</span>
        </button>
      </nav>
    </div>
  );
}
