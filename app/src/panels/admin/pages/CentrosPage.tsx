import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { centrosApi } from '@/api/centros';
import { get } from '@/api/client';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { useToast } from '@/components/ui/Toast';
import { RegionFilter } from '@/components/domain/RegionFilter';
import { DataTable, type Column } from '@/components/data/DataTable';
import { StarRating } from '@/components/ui/StarRating';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { useDebounce } from '@/hooks/useDebounce';
import type { Centro, CentroWithStats, Estado, Region } from '@/types';

interface CentrosPageProps {
  onViewCentro: (centroId: number) => void;
}

export function CentrosPage({ onViewCentro }: CentrosPageProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const year = useYearStore((s) => s.selectedYear);
  const [search, setSearch] = useState('');
  const [region, setRegion] = useState('');
  const [editingCentroId, setEditingCentroId] = useState<number | null>(null);
  const [formState, setFormState] = useState<Partial<Centro>>({});
  const debouncedSearch = useDebounce(search, 300);

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const { data: centros, isLoading } = useQuery({
    queryKey: ['admin-centros', year, region, debouncedSearch],
    queryFn: () => adminApi.getCentros(year, region || undefined, debouncedSearch || undefined),
  });

  const { data: editingCentro, isLoading: loadingCentro } = useQuery({
    queryKey: ['centro-detail', editingCentroId],
    queryFn: () => centrosApi.getById(editingCentroId!),
    enabled: !!editingCentroId,
  });

  useEffect(() => {
    if (!editingCentro) return;
    setFormState(editingCentro);
  }, [editingCentro]);

  const updateCentro = useMutation({
    mutationFn: () => {
      if (!editingCentroId) {
        throw new Error('No hay centro seleccionado.');
      }
      return centrosApi.update(editingCentroId, formState);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-centros'] });
      queryClient.invalidateQueries({ queryKey: ['centro-detail', editingCentroId] });
      toast('success', 'Centro educativo actualizado.');
      setEditingCentroId(null);
    },
  });

  const columns: Column<CentroWithStats>[] = [
    { key: 'nombre', header: 'Centro', sortable: true, sortValue: (c) => c.nombre, render: (c) => <strong>{c.nombre}</strong> },
    { key: 'codigo', header: 'Codigo', render: (c) => c.codigoMep || 'Sin código' },
    { key: 'region', header: 'Region', render: (c) => c.regionName ?? '-' },
    { key: 'puntaje', header: 'Puntaje', sortable: true, sortValue: (c) => c.annual.puntajeTotal, render: (c) => `${c.annual.puntajeTotal} pts` },
    { key: 'estrella', header: 'Galardón', render: (c) => <StarRating rating={c.annual.estrellaFinal} size={14} /> },
    { key: 'estado', header: 'Matricula', render: (c) => <StatusBadge estado={c.annual.matriculaEstado as Estado} /> },
    {
      key: 'acciones',
      header: '',
      render: (c) => (
        <Button
          variant="ghost"
          size="sm"
          onClick={(event) => {
            event.stopPropagation();
            setEditingCentroId(c.id);
          }}
        >
          Editar
        </Button>
      ),
    },
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

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          data={centros ?? []}
          columns={columns}
          keyExtractor={(c) => c.id}
          onRowClick={(centro) => onViewCentro(centro.id)}
        />
      )}

      <Modal open={!!editingCentroId} onClose={() => setEditingCentroId(null)} title="Editar centro educativo" width="760px">
        {loadingCentro && <Spinner />}
        {!loadingCentro && editingCentro && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              updateCentro.mutate();
            }}
          >
            {updateCentro.error && <Alert variant="error">{(updateCentro.error as Error).message}</Alert>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--gnf-space-4)' }}>
              <Input label="Nombre" value={formState.nombre ?? ''} onChange={(event) => setFormState((current) => ({ ...current, nombre: event.target.value }))} />
              <Input label="Codigo MEP" value={formState.codigoMep ?? ''} onChange={(event) => setFormState((current) => ({ ...current, codigoMep: event.target.value }))} />
              <Select
                label="Region"
                value={formState.regionId ? String(formState.regionId) : ''}
                onChange={(event) => setFormState((current) => ({ ...current, regionId: Number(event.target.value) }))}
                options={(regions ?? []).map((item) => ({ value: String(item.id), label: item.name }))}
                placeholder="Seleccionar region..."
              />
              <Input label="Circuito" value={formState.circuito ?? ''} onChange={(event) => setFormState((current) => ({ ...current, circuito: event.target.value }))} />
              <Input label="Provincia" value={formState.provincia ?? ''} onChange={(event) => setFormState((current) => ({ ...current, provincia: event.target.value }))} />
              <Input label="Canton" value={formState.canton ?? ''} onChange={(event) => setFormState((current) => ({ ...current, canton: event.target.value }))} />
              <Input label="Telefono" value={formState.telefono ?? ''} onChange={(event) => setFormState((current) => ({ ...current, telefono: event.target.value }))} />
              <Input label="Correo institucional" value={formState.correoInstitucional ?? ''} onChange={(event) => setFormState((current) => ({ ...current, correoInstitucional: event.target.value }))} />
              <Input label="Nivel educativo" value={formState.nivelEducativo ?? ''} onChange={(event) => setFormState((current) => ({ ...current, nivelEducativo: event.target.value }))} />
              <Input label="Dependencia" value={formState.dependencia ?? ''} onChange={(event) => setFormState((current) => ({ ...current, dependencia: event.target.value }))} />
              <Input label="Jornada" value={formState.jornada ?? ''} onChange={(event) => setFormState((current) => ({ ...current, jornada: event.target.value }))} />
              <Input label="Tipologia" value={formState.tipologia ?? ''} onChange={(event) => setFormState((current) => ({ ...current, tipologia: event.target.value }))} />
            </div>

            <Input label="Direccion" value={formState.direccion ?? ''} onChange={(event) => setFormState((current) => ({ ...current, direccion: event.target.value }))} />

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--gnf-space-3)' }}>
              <Button type="button" variant="ghost" onClick={() => setEditingCentroId(null)}>
                Cancelar
              </Button>
              <Button type="submit" loading={updateCentro.isPending}>
                Guardar cambios
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
