import type { CentroWithStats } from '@/types';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Eye } from 'lucide-react';

interface CentroTableProps {
  centros: CentroWithStats[];
  onViewDetail: (centroId: number) => void;
  emptyMessage?: string;
}

export function CentroTable({ centros, onViewDetail, emptyMessage = 'No hay centros con matrícula activa.' }: CentroTableProps) {
  const columns: Column<CentroWithStats>[] = [
    {
      key: 'nombre',
      header: 'Centro',
      sortable: true,
      sortValue: (c) => c.nombre,
      render: (c) => (
        <button
          type="button"
          onClick={(event) => { event.stopPropagation(); onViewDetail(c.id); }}
          style={{ border: 'none', background: 'none', padding: 0, font: 'inherit', fontWeight: 700, color: 'var(--gnf-ocean-dark)', cursor: 'pointer', textAlign: 'left' }}
        >
          {c.nombre}
        </button>
      ),
    },
    {
      key: 'codigo',
      header: 'Código MEP',
      render: (c) => <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{c.codigoMep || '—'}</span>,
    },
    {
      key: 'ubicacion',
      header: 'Ubicación',
      render: (c) => (
        <div style={{ display: 'grid', gap: 2, fontSize: '0.8125rem' }}>
          <span>{c.regionName || '—'}</span>
          <span style={{ color: 'var(--gnf-muted)' }}>{c.circuito ? `Circuito ${c.circuito}` : ''}</span>
        </div>
      ),
    },
    {
      key: 'puntaje',
      header: 'Puntaje',
      sortable: true,
      sortValue: (c) => c.annual.puntajeTotal,
      render: (c) => <span>{c.annual.puntajeTotal} pts</span>,
    },
    {
      key: 'evidencias',
      header: 'Evidencias',
      sortable: true,
      sortValue: (c) => c.evPendientes ?? 0,
      render: (c) => {
        const pending = c.evPendientes ?? 0;
        const approved = c.evAprobadas ?? 0;
        const rejected = c.evRechazadas ?? 0;
        const total = c.evTotal ?? 0;
        if (total === 0) {
          return <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>Sin evidencias</span>;
        }
        return (
          <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: '0.8125rem' }}>
            {pending > 0 && <Badge color="#b45309" bg="rgba(245, 158, 11, 0.12)">{pending} pendientes</Badge>}
            {rejected > 0 && <Badge color="#dc2626" bg="rgba(239, 107, 74, 0.12)">{rejected} rechazadas</Badge>}
            {pending === 0 && rejected === 0 && approved === total && (
              <Badge color="#166534" bg="rgba(34, 197, 94, 0.12)">Todo aprobado</Badge>
            )}
            {pending === 0 && rejected === 0 && approved < total && (
              <span style={{ color: 'var(--gnf-muted)' }}>{approved}/{total}</span>
            )}
          </div>
        );
      },
    },
    {
      key: 'retos',
      header: 'Retos',
      render: (c) => (
        <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{c.retosCount}</span>
      ),
    },
    {
      key: 'actions',
      header: '',
      width: '100px',
      render: (c) => (
        <Button variant="ghost" size="sm" icon={<Eye size={14} />} onClick={() => onViewDetail(c.id)}>
          Ver
        </Button>
      ),
    },
  ];

  return (
    <DataTable
      data={centros}
      columns={columns}
      keyExtractor={(c) => c.id}
      onRowClick={(c) => onViewDetail(c.id)}
      rowHighlight={(c) => ((c.evPendientes ?? 0) > 0 ? 'rgba(30, 95, 138, 0.05)' : undefined)}
      emptyMessage={emptyMessage}
    />
  );
}
