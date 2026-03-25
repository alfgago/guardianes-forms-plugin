import { useState, useRef, useEffect, useMemo } from 'react';
import { Search } from 'lucide-react';
import type { CentroSearchResult } from '@/types';

interface CentroSearchProps {
  onSelect: (centro: CentroSearchResult) => void;
  placeholder?: string;
  regionId?: number;
  disabled?: boolean;
  includeClaimed?: boolean;
}

function normalize(str: string) {
  return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function getPreloadedCentros(): CentroSearchResult[] {
  const panels = ['AUTH', 'DOCENTE', 'SUPERVISOR', 'ADMIN', 'COMITE'] as const;
  for (const panel of panels) {
    const data = (window as unknown as Record<string, { centros?: CentroSearchResult[] }>)[`__GNF_${panel}__`];
    if (data?.centros) return data.centros;
  }
  return [];
}

const ALL_CENTROS = getPreloadedCentros();

export function CentroSearch({
  onSelect,
  placeholder = 'Buscar centro educativo...',
  regionId,
  disabled = false,
  includeClaimed = false,
}: CentroSearchProps) {
  const [query, setQuery] = useState('');
  const [showDropdown, setShowDropdown] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setQuery('');
    setShowDropdown(false);
  }, [regionId]);

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const regionCentros = useMemo(() => {
    if (!regionId) return [];
    const centros = ALL_CENTROS.filter((c) => c.regionId === regionId);
    return includeClaimed ? centros : centros.filter((c) => !c.claimed);
  }, [regionId, includeClaimed]);

  const filtered = useMemo(() => {
    if (!query.trim()) return regionCentros;
    const norm = normalize(query);
    return regionCentros.filter(
      (c) => normalize(c.nombre).includes(norm) || normalize(c.codigoMep || '').includes(norm),
    );
  }, [regionCentros, query]);

  const isDisabled = disabled || !regionId;
  const hint = regionCentros.length > 0
    ? `${filtered.length} de ${regionCentros.length} centros`
    : regionId
      ? 'No hay centros para esta región'
      : '';

  return (
    <div ref={containerRef} style={{ position: 'relative' }}>
      <div style={{ position: 'relative' }}>
        <Search
          size={18}
          style={{
            position: 'absolute',
            left: 14,
            top: '50%',
            transform: 'translateY(-50%)',
            color: 'var(--gnf-gray-400)',
            pointerEvents: 'none',
          }}
        />
        <input
          type="text"
          value={query}
          onChange={(e) => { setQuery(e.target.value); setShowDropdown(true); }}
          onFocus={() => { if (regionId) setShowDropdown(true); }}
          placeholder={isDisabled ? 'Primero selecciona una región...' : placeholder}
          disabled={isDisabled}
          style={{
            width: '100%',
            padding: '10px 14px 10px 42px',
            border: '1.5px solid var(--gnf-field-border)',
            borderRadius: 'var(--gnf-radius)',
            fontSize: '0.9375rem',
            fontFamily: 'var(--gnf-font-body)',
            outline: 'none',
            background: isDisabled ? 'var(--gnf-gray-50)' : 'var(--gnf-white)',
            cursor: isDisabled ? 'not-allowed' : 'text',
          }}
        />
      </div>

      {hint && !showDropdown && (
        <p style={{ margin: '4px 0 0', fontSize: '0.8rem', color: 'var(--gnf-muted)' }}>{hint}</p>
      )}

      {showDropdown && (
        <ul
          style={{
            position: 'absolute',
            top: '100%',
            left: 0,
            right: 0,
            background: 'var(--gnf-white)',
            border: '1px solid var(--gnf-border)',
            borderRadius: 'var(--gnf-radius)',
            boxShadow: 'var(--gnf-shadow-md)',
            zIndex: 50,
            listStyle: 'none',
            maxHeight: 280,
            overflowY: 'auto',
            marginTop: 4,
            padding: 0,
          }}
        >
          {filtered.length === 0 ? (
            <li style={{ padding: 'var(--gnf-space-3) var(--gnf-space-4)', color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
              No se encontraron centros
            </li>
          ) : (
            filtered.map((centro) => (
              <li
                key={centro.id}
                onClick={() => {
                  onSelect(centro);
                  setQuery(centro.nombre);
                  setShowDropdown(false);
                }}
                style={{
                  padding: 'var(--gnf-space-3) var(--gnf-space-4)',
                  cursor: 'pointer',
                  fontSize: '0.9375rem',
                  borderBottom: '1px solid var(--gnf-gray-100)',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--gnf-gray-50)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = ''; }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                  <strong>{centro.nombre}</strong>
                  {centro.codigoMep && (
                    <span style={{ color: 'var(--gnf-muted)', fontSize: '0.8125rem', fontFamily: 'monospace' }}>
                      {centro.codigoMep}
                    </span>
                  )}
                  {centro.claimed && (
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--gnf-danger)' }}>
                      Ya en uso
                    </span>
                  )}
                </div>
                {centro.claimed && centro.correoInstitucional && (
                  <div style={{ marginTop: 4, color: 'var(--gnf-muted)', fontSize: '0.8125rem' }}>
                    Correo: {centro.correoInstitucional}
                  </div>
                )}
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}
