import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ScreenHeader } from '../ScreenHeader';
import { getNavCrumbs, setNavCrumbs } from '../navState';

interface Operator {
  id: number;
  name: string;
  role: string | null;
  role_acronym: string | null;
  photo_url: string | null;
}

export function GroomerPicker() {
  const navigate = useNavigate();
  const [operators, setOperators] = useState<Operator[]>([]);
  const [loading, setLoading] = useState(true);
  const [parentCrumbs] = useState(() => getNavCrumbs());

  useEffect(() => {
    fetch('/api/operators')
      .then(r => r.json())
      .then(data => { setOperators(data); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  const selectGroomer = (op: Operator) => {
    setNavCrumbs([{ label: 'Operador', to: '/groomer' }]);
    navigate(`/groomer/${op.id}`);
  };

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      <ScreenHeader
        title="Operador"
        screenTag="MobOpPkr"
        crumbs={parentCrumbs}
        onBack={parentCrumbs.length > 0 ? () => navigate(-1) : undefined}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to); }}
      />

      <div className="flex flex-col gap-3 px-4 pt-4">

        {loading && (
          <div className="flex justify-center py-16">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && operators.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-2">
            <span className="material-symbols-outlined text-5xl">person_off</span>
            <p className="text-sm">No hay operadores activos</p>
          </div>
        )}

        {!loading && operators.length > 0 && (
          <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
            <div className="px-4 py-2 bg-surface-container-low border-b border-outline-variant"
                 style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: 0 }}>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Operador</span>
              <span className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Rol</span>
              <span />
            </div>
            {operators.map((op, i) => (
              <button
                key={op.id}
                onClick={() => selectGroomer(op)}
                className={`w-full items-center px-4 py-3 active:bg-surface-container transition-colors text-left ${
                  i < operators.length - 1 ? 'border-b border-outline-variant' : ''
                }`}
                style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', alignItems: 'center' }}
              >
                <div className="flex items-center gap-2 min-w-0">
                  <div className="w-8 h-8 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                    {op.photo_url
                      ? <img src={op.photo_url} alt={op.name} className="w-full h-full object-cover" />
                      : <span className="material-symbols-outlined text-base text-primary">person</span>
                    }
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-on-surface truncate">{op.name}</p>
                  </div>
                </div>
                <div className="min-w-0 pr-2">
                  {op.role_acronym
                    ? <span className="inline-block text-[10px] font-bold font-mono bg-primary/10 text-primary px-1.5 py-0.5 rounded">{op.role_acronym}</span>
                    : <p className="text-xs text-on-surface-variant truncate">{op.role ?? '—'}</p>
                  }
                </div>
                <span className="material-symbols-outlined text-on-surface-variant text-base shrink-0">chevron_right</span>
              </button>
            ))}
          </div>
        )}

      </div>
    </div>
  );
}
