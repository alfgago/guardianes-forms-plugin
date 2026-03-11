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
      render: (c) => <strong>{c.nombre}</strong>,
    },
    {
      key: 'codigo',
      header: 'Código MEP',
      render: (c) => <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{c.codigoMep}</span>,
    },
    {
      key: 'meta',
      header: 'Meta',
      render: (c) => <StarRating rating={c.annual.metaEstrellas} size={14} />,
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
      header: 'Estrella',
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
          return <Badge color="#92400e" bg="var(--gnf-sun-light)">{c.enviados} por revisar</Badge>;
        }
        if (c.aprobados === c.retosCount && c.retosCount > 0) {
          return <Badge color="#fff" bg="#22c55e">Completo</Badge>;
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
      rowHighlight={(c) => (c.enviados > 0 ? 'var(--gnf-sun-light)' : undefined)}
      emptyMessage="No hay centros con matrícula activa."
    />
  );
}
