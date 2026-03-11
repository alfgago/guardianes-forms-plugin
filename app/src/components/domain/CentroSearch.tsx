import { useState, useRef, useEffect } from 'react';
import { Search } from 'lucide-react';
import { useDebounce } from '@/hooks/useDebounce';
import { centrosApi } from '@/api/centros';
import type { CentroSearchResult } from '@/types';

interface CentroSearchProps {
  onSelect: (centro: CentroSearchResult) => void;
  placeholder?: string;
}

export function CentroSearch({ onSelect, placeholder = 'Buscar centro educativo...' }: CentroSearchProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<CentroSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const debouncedQuery = useDebounce(query, 300);
  const containerRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController>();

  useEffect(() => {
    if (debouncedQuery.length < 2) {
      setResults([]);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    centrosApi
      .search(debouncedQuery, controller.signal)
      .then((data) => {
        setResults(data);
        setShowDropdown(true);
      })
      .catch((err: unknown) => {
        if (err instanceof DOMException && err.name === 'AbortError') return;
      })
      .finally(() => setLoading(false));
  }, [debouncedQuery]);

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

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
          }}
        />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setShowDropdown(true)}
          placeholder={placeholder}
          style={{
            width: '100%',
            padding: '10px 14px 10px 42px',
            border: '1px solid var(--gnf-border)',
            borderRadius: 'var(--gnf-radius)',
            fontSize: '0.9375rem',
            fontFamily: 'var(--gnf-font-body)',
            outline: 'none',
          }}
        />
        {loading && (
          <span
            style={{
              position: 'absolute',
              right: 14,
              top: '50%',
              transform: 'translateY(-50%)',
              fontSize: '0.75rem',
              color: 'var(--gnf-muted)',
            }}
          >
            Buscando...
          </span>
        )}
      </div>
      {showDropdown && results.length > 0 && (
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
            maxHeight: 240,
            overflowY: 'auto',
            marginTop: 4,
          }}
        >
          {results.map((centro) => (
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
                transition: 'background var(--gnf-transition-fast)',
              }}
              onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--gnf-gray-50)'; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = ''; }}
            >
              <strong>{centro.nombre}</strong>
              <span style={{ color: 'var(--gnf-muted)', marginLeft: 8, fontSize: '0.8125rem' }}>
                {centro.codigoMep}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
