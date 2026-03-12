import type { CentroWithStats } from '@/types';
import { DataTable, type Column } from '@/components/data/DataTable';
import { StarRating } from '@/components/ui/StarRating';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Eye } from 'lucide-react';

interface CentroTableProps {
  centros: CentroWithStats[];
  onViewDetail: (centroId: number) => void;
}

export function CentroTable({ centros, onViewDetail }: CentroTableProps) {
  const columns: Column<CentroWithStats>[] = [
    {
      key: 'nombre',
      header: 'Centro',
      sortable: true,
      sortValue: (c) => c.nombre,
      render: (c) => (
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation();
            onViewDetail(c.id);
          }}
          style={{
            border: 'none',
            background: 'none',
            padding: 0,
            font: 'inherit',
            fontWeight: 700,
            color: 'var(--gnf-ocean-dark)',
            cursor: 'pointer',
            textAlign: 'left',
          }}
        >
          {c.nombre}
        </button>
      ),
    },
    {
      key: 'codigo',
      header: 'Codigo MEP',
      render: (c) => <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{c.codigoMep || 'Sin código'}</span>,
    },
    {
      key: 'puntaje',
      header: 'Puntaje',
      sortable: true,
      sortValue: (c) => c.annual.puntajeTotal,
      render: (c) => <span>{c.annual.puntajeTotal} pts</span>,
    },
    {
      key: 'estrella',
      header: 'Galardón',
      render: (c) => <StarRating rating={c.annual.estrellaFinal} size={14} />,
    },
    {
      key: 'retos',
      header: 'Retos',
      render: (c) => (
        <div style={{ display: 'flex', gap: 4, fontSize: '0.8125rem' }}>
          <span style={{ color: '#16a34a' }}>{c.aprobados}A</span>
          <span style={{ color: '#d97706' }}>{c.enviados}E</span>
          {c.correccion > 0 && <span style={{ color: '#dc2626' }}>{c.correccion}C</span>}
          <span style={{ color: 'var(--gnf-muted)' }}>/{c.retosCount}</span>
        </div>
      ),
    },
    {
      key: 'estado',
      header: 'Estado',
      render: (c) => {
        if (c.enviados > 0) {
          return <Badge color="#b45309" bg="rgba(245, 158, 11, 0.12)">{c.enviados} por revisar</Badge>;
        }
        if (c.aprobados === c.retosCount && c.retosCount > 0) {
          return <Badge color="#166534" bg="rgba(34, 197, 94, 0.12)">Completo</Badge>;
        }
        return <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>En progreso</span>;
      },
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
      rowHighlight={(c) => (c.enviados > 0 ? 'rgba(30, 95, 138, 0.05)' : undefined)}
      emptyMessage="No hay centros con matricula activa."
    />
  );
}
