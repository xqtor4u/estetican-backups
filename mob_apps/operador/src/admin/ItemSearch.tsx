import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { ScreenHeader } from '../ScreenHeader';

interface ApiItem {
  id: number;
  name: string;
  department: string | null;
  brand: string | null;
  presentation: string | null;
  price: string | null;
  photo: string | null;
  stock_quantity: number | null;
}

interface ItemsResponse {
  items: ApiItem[];
  departments: string[];
  brands: string[];
}

type StockFilter = 'all' | 'in_stock' | 'out_of_stock';

export function ItemSearch() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  const [department, setDepartment] = useState('');
  const [brand, setBrand] = useState('');
  const [stockFilter, setStockFilter] = useState<StockFilter>('all');
  const [items, setItems] = useState<ApiItem[]>([]);
  const [departments, setDepartments] = useState<string[]>([]);
  const [brands, setBrands] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchItems = useCallback(async (search: string, dept: string, brnd: string, stock: StockFilter) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (search) params.set('search', search);
      if (dept) params.set('department', dept);
      if (brnd) params.set('brand', brnd);
      if (stock !== 'all') params.set('has_stock', stock === 'in_stock' ? '1' : '0');

      const qs = params.toString();
      const res = await fetch(`/api/items${qs ? `?${qs}` : ''}`);
      const data: ItemsResponse = await res.json();
      setItems(data.items ?? []);
      setDepartments(data.departments ?? []);
      setBrands(data.brands ?? []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => fetchItems(query, department, brand, stockFilter), query ? 300 : 0);
    return () => clearTimeout(t);
  }, [query, department, brand, stockFilter, fetchItems]);

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">
      <ScreenHeader title="Artículos" screenTag="MobArtSrch" onBack={() => navigate(-1)} noCrumbs />

      <div className="flex flex-col gap-3 px-4 pt-4">
        <div className="flex items-center gap-2 bg-surface-container rounded-2xl px-4 py-2 border border-outline-variant">
          <span className="material-symbols-outlined text-on-surface-variant">search</span>
          <input
            type="text"
            placeholder="Buscar por nombre, marca o departamento…"
            value={query}
            onChange={e => setQuery(e.target.value)}
            className="flex-1 bg-transparent text-on-surface placeholder:text-on-surface-variant outline-none text-sm"
          />
          {query.length > 0 && (
            <button onClick={() => setQuery('')}>
              <span className="material-symbols-outlined text-on-surface-variant text-xl">close</span>
            </button>
          )}
        </div>

        <div className="grid grid-cols-2 gap-2">
          <select
            value={department}
            onChange={e => setDepartment(e.target.value)}
            className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface"
          >
            <option value="">Todos los departamentos</option>
            {departments.map(d => <option key={d} value={d}>{d}</option>)}
          </select>
          <select
            value={brand}
            onChange={e => setBrand(e.target.value)}
            className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface"
          >
            <option value="">Todas las marcas</option>
            {brands.map(b => <option key={b} value={b}>{b}</option>)}
          </select>
        </div>

        <div className="flex rounded-xl overflow-hidden border border-outline-variant">
          {(['all', 'in_stock', 'out_of_stock'] as StockFilter[]).map(f => (
            <button
              key={f}
              onClick={() => setStockFilter(f)}
              className={`flex-1 px-3 py-1.5 text-xs font-medium transition-colors ${
                stockFilter === f ? 'bg-primary text-on-primary' : 'bg-surface text-on-surface-variant'
              }`}
            >
              {f === 'all' ? 'Todos' : f === 'in_stock' ? 'Con stock' : 'Sin stock'}
            </button>
          ))}
        </div>

        <span className="text-xs text-on-surface-variant">
          {items.length} resultado{items.length !== 1 ? 's' : ''}
        </span>

        <div className="flex flex-col gap-2">
          {items.map(item => (
            <div key={item.id} className="bg-surface border border-outline-variant rounded-2xl p-3 flex items-center gap-3">
              <div className="w-12 h-12 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                {item.photo
                  ? <img src={item.photo} alt={item.name} className="w-full h-full object-cover" />
                  : <span className="material-symbols-outlined text-2xl text-primary">inventory_2</span>
                }
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-on-surface text-sm truncate">{item.name}</p>
                <p className="text-xs text-on-surface-variant truncate">
                  {[item.department, item.brand].filter(Boolean).join(' · ') || '—'}
                </p>
              </div>
              <div className="text-right shrink-0">
                {item.price && <p className="text-sm font-semibold text-on-surface">${item.price}</p>}
                <p className="text-[10px] text-on-surface-variant">Stock: {item.stock_quantity ?? 0}</p>
              </div>
            </div>
          ))}
        </div>

        {loading && (
          <div className="flex justify-center py-16">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && items.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-on-surface-variant gap-2">
            <span className="material-symbols-outlined text-5xl">inventory_2</span>
            <p className="text-sm">Sin artículos para estos filtros</p>
          </div>
        )}
      </div>
    </div>
  );
}
