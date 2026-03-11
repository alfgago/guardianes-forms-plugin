import { useState, useMemo, type ReactNode } from 'react';
import { ChevronUp, ChevronDown } from 'lucide-react';
import { Pagination } from '@/components/ui/Pagination';
import { EmptyState } from '@/components/ui/EmptyState';

export interface Column<T> {
  key: string;
  header: string;
  render: (item: T) => ReactNode;
  sortable?: boolean;
  sortValue?: (item: T) => string | number;
  width?: string;
}

interface DataTableProps<T> {
  data: T[];
  columns: Column<T>[];
  keyExtractor: (item: T) => string | number;
  perPage?: number;
  emptyMessage?: string;
  onRowClick?: (item: T) => void;
  rowHighlight?: (item: T) => string | undefined;
}

export function DataTable<T>({
  data,
  columns,
  keyExtractor,
  perPage = 10,
  emptyMessage = 'No hay datos.',
  onRowClick,
  rowHighlight,
}: DataTableProps<T>) {
  const [sortKey, setSortKey] = useState<string | null>(null);
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const [page, setPage] = useState(1);

  const sorted = useMemo(() => {
    if (!sortKey) return data;
    const col = columns.find((c) => c.key === sortKey);
    if (!col?.sortValue) return data;
    const getter = col.sortValue;
    return [...data].sort((a, b) => {
      const va = getter(a);
      const vb = getter(b);
      const cmp = typeof va === 'number' && typeof vb === 'number' ? va - vb : String(va).localeCompare(String(vb));
      return sortDir === 'asc' ? cmp : -cmp;
    });
  }, [data, sortKey, sortDir, columns]);

  const totalPages = Math.ceil(sorted.length / perPage);
  const paginated = sorted.slice((page - 1) * perPage, page * perPage);

  function handleSort(key: string) {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('asc');
    }
    setPage(1);
  }

  if (data.length === 0) {
    return <EmptyState title={emptyMessage} />;
  }

  return (
    <div>
      <div style={{ overflowX: 'auto' }}>
        <table
          style={{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: '0.9375rem',
          }}
        >
          <thead>
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={col.sortable ? () => handleSort(col.key) : undefined}
                  style={{
                    padding: 'var(--gnf-space-3) var(--gnf-space-4)',
                    textAlign: 'left',
                    fontWeight: 600,
                    fontSize: '0.8125rem',
                    color: 'var(--gnf-gray-500)',
                    borderBottom: '2px solid var(--gnf-border)',
                    cursor: col.sortable ? 'pointer' : 'default',
                    userSelect: col.sortable ? 'none' : undefined,
                    whiteSpace: 'nowrap',
                    width: col.width,
                    position: 'sticky',
                    top: 0,
                    background: 'var(--gnf-white)',
                    zIndex: 1,
                  }}
                >
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                    {col.header}
                    {col.sortable && sortKey === col.key && (
                      sortDir === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />
                    )}
                  </span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {paginated.map((item) => (
              <tr
                key={keyExtractor(item)}
                onClick={onRowClick ? () => onRowClick(item) : undefined}
                style={{
                  cursor: onRowClick ? 'pointer' : 'default',
                  background: rowHighlight?.(item),
                  transition: 'background var(--gnf-transition-fast)',
                }}
                onMouseEnter={(e) => {
                  if (!rowHighlight?.(item)) {
                    e.currentTarget.style.background = 'var(--gnf-gray-50)';
                  }
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.background = rowHighlight?.(item) ?? '';
                }}
              >
                {columns.map((col) => (
                  <td
                    key={col.key}
                    style={{
                      padding: 'var(--gnf-space-3) var(--gnf-space-4)',
                      borderBottom: '1px solid var(--gnf-gray-100)',
                    }}
                  >
                    {col.render(item)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {totalPages > 1 && (
        <div style={{ marginTop: 'var(--gnf-space-4)' }}>
          <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </div>
      )}
    </div>
  );
}
