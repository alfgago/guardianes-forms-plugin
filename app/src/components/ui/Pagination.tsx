import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationProps {
  page: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}

export function Pagination({ page, totalPages, onPageChange }: PaginationProps) {
  if (totalPages <= 1) return null;

  const pages: (number | '...')[] = [];
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== '...') {
      pages.push('...');
    }
  }

  const btnBase: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 36,
    height: 36,
    border: '1px solid var(--gnf-border)',
    borderRadius: 'var(--gnf-radius-sm)',
    background: 'var(--gnf-white)',
    cursor: 'pointer',
    fontSize: '0.875rem',
    fontWeight: 500,
    fontFamily: 'var(--gnf-font-body)',
    color: 'var(--gnf-gray-700)',
    transition: 'all var(--gnf-transition-fast)',
  };

  return (
    <nav
      aria-label="Paginación"
      style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-1)', justifyContent: 'center' }}
    >
      <button
        onClick={() => onPageChange(page - 1)}
        disabled={page <= 1}
        aria-label="Anterior"
        style={{ ...btnBase, opacity: page <= 1 ? 0.4 : 1 }}
      >
        <ChevronLeft size={16} />
      </button>
      {pages.map((p, i) =>
        p === '...' ? (
          <span key={`ellipsis-${i}`} style={{ padding: '0 4px', color: 'var(--gnf-muted)' }}>...</span>
        ) : (
          <button
            key={p}
            onClick={() => onPageChange(p)}
            aria-current={p === page ? 'page' : undefined}
            style={{
              ...btnBase,
              ...(p === page
                ? { background: 'var(--gnf-forest)', color: 'var(--gnf-white)', borderColor: 'var(--gnf-forest)' }
                : {}),
            }}
          >
            {p}
          </button>
        ),
      )}
      <button
        onClick={() => onPageChange(page + 1)}
        disabled={page >= totalPages}
        aria-label="Siguiente"
        style={{ ...btnBase, opacity: page >= totalPages ? 0.4 : 1 }}
      >
        <ChevronRight size={16} />
      </button>
    </nav>
  );
}
