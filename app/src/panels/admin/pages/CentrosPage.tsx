import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { get } from '@/api/client';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Input } from '@/components/ui/Input';
import { RegionFilter } from '@/components/domain/RegionFilter';
import { DataTable, type Column } from '@/components/data/DataTable';
import { StarRating } from '@/components/ui/StarRating';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { useDebounce } from '@/hooks/useDebounce';
import type { CentroWithStats, Region, Estado } from '@/types';

export function CentrosPage() {
  const year = useYearStore((s) => s.selectedYear);
  const [search, setSearch] = useState('');
  const [region, setRegion] = useState('');
  const debouncedSearch = useDebounce(search, 300);

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const { data: centros, isLoading } = useQuery({
    queryKey: ['admin-centros', year, region, debouncedSearch],
    queryFn: () => adminApi.getCentros(year, region || undefined, debouncedSearch || undefined),
  });

  const columns: Column<CentroWithStats>[] = [
    { key: 'nombre', header: 'Centro', sortable: true, sortValue: (c) => c.nombre, render: (c) => <strong>{c.nombre}</strong> },
    { key: 'codigo', header: 'Código', render: (c) => c.codigoMep },
    { key: 'region', header: 'Región', render: (c) => c.regionName ?? '-' },
    { key: 'meta', header: 'Meta', render: (c) => <StarRating rating={c.annual.metaEstrellas} size={14} /> },
    { key: 'puntaje', header: 'Puntaje', sortable: true, sortValue: (c) => c.annual.puntajeTotal, render: (c) => `${c.annual.puntajeTotal} pts` },
    { key: 'estrella', header: 'Estrella', render: (c) => <StarRating rating={c.annual.estrellaFinal} size={14} /> },
    { key: 'estado', header: 'Matrícula', render: (c) => <StatusBadge estado={c.annual.matriculaEstado as Estado} /> },
  ];

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-4)' }}>Centros Educativos</h2>

      <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', marginBottom: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 200 }}>
          <Input placeholder="Buscar centro..." value={search} onChange={(e) => setSearch(e.target.value)} style={{ marginBottom: 0 }} />
        </div>
        <RegionFilter regions={regions ?? []} value={region} onChange={setRegion} />
      </div>

      {isLoading ? <Spinner /> : <DataTable data={centros ?? []} columns={columns} keyExtractor={(c) => c.id} />}
    </div>
  );
}
