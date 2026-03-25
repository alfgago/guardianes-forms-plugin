import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
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
import { Card } from '@/components/ui/Card';
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

type RegistrationFilter = 'registered' | 'all' | 'unregistered';

const ESTADO_OPTIONS = [
  { value: 'activo', label: 'Activos' },
  { value: 'pendiente_de_revision_admin', label: 'Pendientes' },
  { value: 'rechazado', label: 'Rechazados' },
];

const REGISTRATION_OPTIONS = [
  { value: 'registered', label: 'Registrados' },
  { value: 'all', label: 'Todos' },
  { value: 'unregistered', label: 'Sin registrar' },
];

export function CentrosPage({ onViewCentro }: CentrosPageProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const year = useYearStore((state) => state.selectedYear);
  const [search, setSearch] = useState('');
  const [region, setRegion] = useState('');
  const [estado, setEstado] = useState('');
  const [registration, setRegistration] = useState<RegistrationFilter>('registered');
  const [editingCentroId, setEditingCentroId] = useState<number | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [formState, setFormState] = useState<Partial<Centro>>({});
  const debouncedSearch = useDebounce(search, 300);
  const isCreating = isCreateModalOpen && !editingCentroId;

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const { data: centros, isLoading } = useQuery({
    queryKey: ['admin-centros', year, region, estado, registration, debouncedSearch],
    queryFn: () =>
      adminApi.getCentros({
        year,
        region: region || undefined,
        estado: estado || undefined,
        registration,
        search: debouncedSearch || undefined,
      }),
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

  const closeCentroModal = () => {
    setEditingCentroId(null);
    setIsCreateModalOpen(false);
    setFormState({});
  };

  const openCreateModal = () => {
    setEditingCentroId(null);
    setIsCreateModalOpen(true);
    setFormState({
      regionId: region ? Number(region) : 0,
    });
  };

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
      closeCentroModal();
    },
    onError: (error: Error) => {
      toast('error', error.message || 'No se pudo actualizar el centro.');
    },
  });

  const createCentro = useMutation({
    mutationFn: () => adminApi.createCentro(formState),
    onSuccess: (createdCentro) => {
      setRegistration('all');
      setRegion(createdCentro.regionId ? String(createdCentro.regionId) : '');
      setSearch(createdCentro.nombre || '');
      queryClient.invalidateQueries({ queryKey: ['admin-centros'] });
      queryClient.invalidateQueries({ queryKey: ['centro-detail', createdCentro.id] });
      toast('success', 'Centro educativo creado. Se muestran todos los centros para ubicarlo.');
      closeCentroModal();
    },
    onError: (error: Error) => {
      toast('error', error.message || 'No se pudo crear el centro.');
    },
  });

  const currentError = isCreating ? createCentro.error : updateCentro.error;

  const centrosByRegion = useMemo(() => {
    const counter = new Map<string, number>();

    (centros ?? []).forEach((centro) => {
      const key = centro.regionName || 'Sin región';
      counter.set(key, (counter.get(key) ?? 0) + 1);
    });

    return Array.from(counter.entries())
      .map(([name, total]) => ({ name, total }))
      .sort((a, b) => b.total - a.total || a.name.localeCompare(b.name, 'es'));
  }, [centros]);

  const columns: Column<CentroWithStats>[] = [
    { key: 'nombre', header: 'Centro', sortable: true, sortValue: (centro) => centro.nombre, render: (centro) => <strong>{centro.nombre}</strong> },
    { key: 'codigo', header: 'Codigo', render: (centro) => centro.codigoMep || 'Sin codigo' },
    { key: 'region', header: 'Region', render: (centro) => centro.regionName ?? '-' },
    { key: 'puntaje', header: 'Puntaje', sortable: true, sortValue: (centro) => centro.annual.puntajeTotal, render: (centro) => `${centro.annual.puntajeTotal} pts` },
    { key: 'estrella', header: 'Galardon', render: (centro) => <StarRating rating={centro.annual.estrellaFinal} size={14} /> },
    { key: 'estado', header: 'Matricula', render: (centro) => <StatusBadge estado={centro.annual.matriculaEstado as Estado} /> },
    {
      key: 'acciones',
      header: '',
      render: (centro) => (
        <Button
          variant="ghost"
          size="sm"
          onClick={(event) => {
            event.stopPropagation();
            setIsCreateModalOpen(false);
            setEditingCentroId(centro.id);
          }}
        >
          Editar
        </Button>
      ),
    },
  ];

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-4)' }}>
        <div>
          <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>Centros Educativos</h2>
          <p style={{ color: 'var(--gnf-muted)', marginBottom: 0 }}>Por defecto se muestran los centros ya registrados por alguna cuenta docente.</p>
        </div>
        <Button icon={<Plus size={16} />} onClick={openCreateModal}>
          Nuevo centro
        </Button>
      </div>

      <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', marginBottom: 'var(--gnf-space-4)', flexWrap: 'wrap', alignItems: 'flex-start' }}>
        <div style={{ flex: 1, minWidth: 220 }}>
          <Input placeholder="Buscar centro..." value={search} onChange={(event) => setSearch(event.target.value)} style={{ marginBottom: 0 }} />
        </div>
        <RegionFilter regions={regions ?? []} value={region} onChange={setRegion} />
        <Select
          aria-label="Filtrar por estado del centro"
          value={estado}
          onChange={(event) => setEstado(event.target.value)}
          options={ESTADO_OPTIONS}
          placeholder="Todos los estados"
          style={{ marginBottom: 0, minWidth: 180 }}
        />
        <Select
          aria-label="Filtrar por registro"
          value={registration}
          onChange={(event) => setRegistration(event.target.value as RegistrationFilter)}
          options={REGISTRATION_OPTIONS}
          style={{ marginBottom: 0, minWidth: 180 }}
        />
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
          gap: 'var(--gnf-space-4)',
          marginBottom: 'var(--gnf-space-4)',
        }}
      >
        <Card padding="var(--gnf-space-5)">
          <strong style={{ display: 'block', fontSize: '1.5rem', color: 'var(--gnf-ocean-dark)' }}>{centros?.length ?? 0}</strong>
          <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>Centros visibles</span>
        </Card>

        <Card padding="var(--gnf-space-5)" style={{ gridColumn: '1 / -1' }}>
          <div style={{ marginBottom: 'var(--gnf-space-3)' }}>
            <strong>Resumen por DRE</strong>
            <p style={{ margin: '4px 0 0', color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
              Se actualiza con los filtros actuales del listado.
            </p>
          </div>

          {centrosByRegion.length > 0 ? (
            <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
              {centrosByRegion.map((item) => (
                <div
                  key={item.name}
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    gap: 'var(--gnf-space-3)',
                    padding: 'var(--gnf-space-3)',
                    borderRadius: 'var(--gnf-radius-sm)',
                    background: 'rgba(30, 95, 138, 0.05)',
                  }}
                >
                  <span>{item.name}</span>
                  <strong>{item.total} centro{item.total === 1 ? '' : 's'}</strong>
                </div>
              ))}
            </div>
          ) : (
            <p style={{ margin: 0, color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
              No hay centros para resumir con los filtros actuales.
            </p>
          )}
        </Card>
      </div>

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          data={centros ?? []}
          columns={columns}
          keyExtractor={(centro) => centro.id}
          onRowClick={(centro) => onViewCentro(centro.id)}
        />
      )}

      <Modal open={isCreateModalOpen || !!editingCentroId} onClose={closeCentroModal} title={isCreating ? 'Nuevo centro educativo' : 'Editar centro educativo'} width="760px">
        {!isCreating && loadingCentro && <Spinner />}
        {(isCreating || (!loadingCentro && editingCentro)) && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              if (isCreating) {
                createCentro.mutate();
                return;
              }

              updateCentro.mutate();
            }}
          >
            {currentError && <Alert variant="error">{(currentError as Error).message}</Alert>}

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
              <Input
                label="Correo institucional"
                value={formState.correoInstitucional ?? ''}
                onChange={(event) => setFormState((current) => ({ ...current, correoInstitucional: event.target.value }))}
              />
              <Input
                label="Nivel educativo"
                value={formState.nivelEducativo ?? ''}
                onChange={(event) => setFormState((current) => ({ ...current, nivelEducativo: event.target.value }))}
              />
              <Input label="Dependencia" value={formState.dependencia ?? ''} onChange={(event) => setFormState((current) => ({ ...current, dependencia: event.target.value }))} />
              <Input label="Jornada" value={formState.jornada ?? ''} onChange={(event) => setFormState((current) => ({ ...current, jornada: event.target.value }))} />
              <Input
                label="Tipo de Centro Educativo"
                value={formState.tipoCentroEducativo ?? ''}
                onChange={(event) => setFormState((current) => ({ ...current, tipoCentroEducativo: event.target.value }))}
              />
            </div>

            <Input label="Direccion" value={formState.direccion ?? ''} onChange={(event) => setFormState((current) => ({ ...current, direccion: event.target.value }))} />

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--gnf-space-3)' }}>
              <Button type="button" variant="ghost" onClick={closeCentroModal}>
                Cancelar
              </Button>
              <Button type="submit" loading={createCentro.isPending || updateCentro.isPending}>
                {isCreating ? 'Crear centro' : 'Guardar cambios'}
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
