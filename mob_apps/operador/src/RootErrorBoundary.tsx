// @ts-nocheck
/*
 * Error boundary de la app. Sin esto, cualquier excepción en render desmonta el árbol
 * entero y deja la PANTALLA EN NEGRO sin ninguna pista. Con esto se ve un mensaje + botones.
 *
 * `@ts-nocheck`: este proyecto no tiene instaladas las definiciones de tipos de React
 * (`@types/react`), así que `React.Component` se resuelve a `any` y TS no reconoce
 * `this.props` / `this.state` / `this.setState`. Es el único componente de clase del código.
 */
import React from 'react';

export class RootErrorBoundary extends React.Component {
  state = { error: null };

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error('RootErrorBoundary', error, info);
  }

  render() {
    const { error } = this.state;
    if (!error) return this.props.children;
    return (
      <div className="theme-admin min-h-screen bg-background flex flex-col items-center justify-center gap-4 px-8 text-center">
        <span className="material-symbols-outlined text-5xl text-error/70" style={{ fontVariationSettings: "'FILL' 1" }}>
          error
        </span>
        <div>
          <p className="text-base font-bold text-on-surface">Algo salió mal en esta pantalla</p>
          <p className="text-xs text-on-surface-variant mt-1 max-w-xs break-words">{String(error?.message ?? error)}</p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => window.history.back()}
            className="px-4 py-2 rounded-2xl text-sm border border-outline-variant text-on-surface-variant"
          >
            Volver
          </button>
          <button
            onClick={() => window.location.reload()}
            className="px-4 py-2 rounded-2xl text-sm font-semibold bg-primary text-on-primary"
          >
            Recargar
          </button>
        </div>
      </div>
    );
  }
}
