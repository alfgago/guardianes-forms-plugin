import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { get } from '@/api/client';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Checkbox } from '@/components/ui/Checkbox';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { CentroSearch } from '@/components/domain/CentroSearch';
import { RegionFilter } from '@/components/domain/RegionFilter';
import { useToast } from '@/components/ui/Toast';
import { PendingUsersSection } from '../components/PendingUsersSection';
import type { CentroSearchResult, PendingUser, Region } from '@/types';

function getUserRegionLabel(user: PendingUser) {
  if (user.regionNames?.length) {
    return user.regionNames.join(', ');
  }

  return user.regionName ?? '';
}

export function UsuariosPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('docente');
  const [status, setStatus] = useState('all');
  const [region, setRegion] = useState('');
  const [editingUser, setEditingUser] = useState<PendingUser | null>(null);
  const [formState, setFormState] = useState({
    name: '',
    email: '',
    status: 'activo',
    telefono: '',
    cargo: '',
    identificacion: '',
    regionId: '',
    regionIds: [] as string[],
  });
  const [selectedCentro, setSelectedCentro] = useState<CentroSearchResult | null>(null);

  const { data: users, isLoading } = useQuery({
    queryKey: ['admin-users'],
    queryFn: () => adminApi.getUsers(),
  });

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  useEffect(() => {
    if (!editingUser) return;

    const regionIds = editingUser.regionIds?.length
      ? editingUser.regionIds.map(String)
      : editingUser.regionId
        ? [String(editingUser.regionId)]
        : [];

    setFormState({
      name: editingUser.name ?? '',
      email: editingUser.email ?? '',
      status: editingUser.status ?? 'activo',
      telefono: editingUser.telefono ?? '',
      cargo: editingUser.cargo ?? '',
      identificacion: editingUser.identificacion ?? '',
      regionId: editingUser.regionId ? String(editingUser.regionId) : '',
      regionIds,
    });

    setSelectedCentro(
      editingUser.centroId
        ? {
            id: editingUser.centroId,
            nombre: editingUser.centroName ?? '',
            codigoMep: '',
            regionId: editingUser.regionId,
            regionName: editingUser.regionNames?.[0] ?? editingUser.regionName,
          }
        : null,
    );
  }, [editingUser]);

  const updateUser = useMutation({
    mutationFn: () => {
      if (!editingUser) {
        throw new Error('No hay usuario seleccionado.');
      }

      const regionIds = formState.regionIds.map(Number).filter(Boolean);
      if (editingUser.role === 'comite_bae' && regionIds.length === 0) {
        throw new Error('Debes asignar al menos una región al comité.');
      }

      return adminApi.updateUser(editingUser.id, {
        ...formState,
        status: formState.status as 'activo' | 'pendiente',
        regionId: formState.regionId ? Number(formState.regionId) : (regionIds[0] ?? undefined),
        regionIds: editingUser.role === 'comite_bae' ? regionIds : undefined,
        centroId: editingUser.role === 'docente' ? selectedCentro?.id : undefined,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-pending-users'] });
      toast('success', 'Usuario actualizado.');
      setEditingUser(null);
    },
  });

  const filteredUsers = useMemo(() => {
    return (users ?? []).filter((user) => {
      const regionLabel = getUserRegionLabel(user);
      const haystack = [user.name, user.email, user.centroName, regionLabel].join(' ').toLowerCase();
      const matchesSearch = !search.trim() || haystack.includes(search.trim().toLowerCase());
      const matchesRole = role === 'all' || user.role === role;
      const matchesStatus = status === 'all' || (user.status ?? 'activo') === status;
      const matchesRegion = !region
        || user.regionIds?.includes(Number(region))
        || String(user.regionId ?? '') === region;

      return matchesSearch && matchesRole && matchesStatus && matchesRegion;
    });
  }, [region, role, search, status, users]);

  const docenteCenterSummary = useMemo(() => {
    const distinctCenters = new Map<number | string, PendingUser>();
    const pendingCenters = new Set<number | string>();
    const visibleSupervisors = filteredUsers.filter((user) => user.role === 'supervisor').length;

    filteredUsers.forEach((user) => {
      if (user.role !== 'docente') return;

      const key = user.centroId ?? `user-${user.id}`;
      if (!distinctCenters.has(key)) {
        distinctCenters.set(key, user);
      }

      if ((user.status ?? 'activo') === 'pendiente') {
        pendingCenters.add(key);
      }
    });

    const regionCounter = new Map<string, number>();
    distinctCenters.forEach((user) => {
      const regionName = getUserRegionLabel(user) || 'Sin región';
      regionCounter.set(regionName, (regionCounter.get(regionName) ?? 0) + 1);
    });

    const byRegion = Array.from(regionCounter.entries())
      .map(([name, total]) => ({ name, total }))
      .sort((a, b) => b.total - a.total || a.name.localeCompare(b.name, 'es'));

    return {
      visibleCenters: distinctCenters.size,
      pendingCenters: pendingCenters.size,
      visibleSupervisors,
      byRegion,
    };
  }, [filteredUsers]);

  const toggleComiteRegion = (regionId: string, checked: boolean) => {
    setFormState((current) => ({
      ...current,
      regionIds: checked
        ? Array.from(new Set([...current.regionIds, regionId]))
        : current.regionIds.filter((item) => item !== regionId),
    }));
  };

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-4)' }}>Usuarios</h2>

      <Card style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', alignItems: 'flex-end' }}>
          <div style={{ flex: '1 1 280px' }}>
            <Input
              label="Buscar"
              placeholder="Nombre, correo, centro o región..."
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              style={{ marginBottom: 0 }}
            />
          </div>

          <RegionFilter regions={regions ?? []} value={region} onChange={setRegion} />

          <label style={{ display: 'grid', gap: 6, fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
            Rol
            <select
              value={role}
              onChange={(event) => setRole(event.target.value)}
              style={{
                minWidth: 180,
                padding: '10px 12px',
                borderRadius: 'var(--gnf-radius)',
                border: '1px solid var(--gnf-border)',
                background: 'var(--gnf-white)',
              }}
            >
              <option value="all">Todos</option>
              <option value="docente">Centros educativos</option>
              <option value="supervisor">Supervisores</option>
              <option value="comite_bae">Comité</option>
              <option value="administrator">Administradores</option>
            </select>
          </label>

          <label style={{ display: 'grid', gap: 6, fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
            Estado
            <select
              value={status}
              onChange={(event) => setStatus(event.target.value)}
              style={{
                minWidth: 160,
                padding: '10px 12px',
                borderRadius: 'var(--gnf-radius)',
                border: '1px solid var(--gnf-border)',
                background: 'var(--gnf-white)',
              }}
            >
              <option value="all">Todos</option>
              <option value="activo">Activos</option>
              <option value="pendiente">Pendientes</option>
            </select>
          </label>
        </div>
      </Card>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
          gap: 'var(--gnf-space-4)',
          marginBottom: 'var(--gnf-space-4)',
        }}
      >
        <Card padding="var(--gnf-space-5)">
          <strong style={{ display: 'block', fontSize: '1.5rem', color: 'var(--gnf-ocean-dark)' }}>{docenteCenterSummary.visibleCenters}</strong>
          <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>Centros educativos visibles</span>
        </Card>
        <Card padding="var(--gnf-space-5)">
          <strong style={{ display: 'block', fontSize: '1.5rem', color: '#b45309' }}>{docenteCenterSummary.pendingCenters}</strong>
          <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>Centros pendientes</span>
        </Card>
        <Card padding="var(--gnf-space-5)">
          <strong style={{ display: 'block', fontSize: '1.5rem', color: 'var(--gnf-forest)' }}>{docenteCenterSummary.visibleSupervisors}</strong>
          <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>Supervisores visibles</span>
        </Card>
      </div>

      <Card style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-3)' }}>
          <div>
            <strong>Resumen por DRE</strong>
            <p style={{ margin: '4px 0 0', fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
              Conteo distinto por centro educativo para no inflar matrículas.
            </p>
          </div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
            {filteredUsers.length} usuario{filteredUsers.length === 1 ? '' : 's'} visible{filteredUsers.length === 1 ? '' : 's'}
          </span>
        </div>

        {docenteCenterSummary.byRegion.length > 0 ? (
          <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
            {docenteCenterSummary.byRegion.map((item) => (
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
            No hay centros visibles con los filtros actuales.
          </p>
        )}
      </Card>

      {isLoading ? <Spinner /> : <PendingUsersSection users={filteredUsers} onEdit={setEditingUser} />}

      <Modal open={!!editingUser} onClose={() => setEditingUser(null)} title="Editar usuario" width="680px">
        {editingUser && (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              updateUser.mutate();
            }}
          >
            {updateUser.error && <Alert variant="error">{(updateUser.error as Error).message}</Alert>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--gnf-space-4)' }}>
              <Input
                label="Nombre"
                value={formState.name}
                onChange={(event) => setFormState((current) => ({ ...current, name: event.target.value }))}
                required
              />
              <Input
                label="Correo"
                type="email"
                value={formState.email}
                onChange={(event) => setFormState((current) => ({ ...current, email: event.target.value }))}
                required
              />
              <Select
                label="Estado"
                value={formState.status}
                onChange={(event) => setFormState((current) => ({ ...current, status: event.target.value }))}
                options={[
                  { value: 'activo', label: 'Activo' },
                  { value: 'pendiente', label: 'Pendiente' },
                ]}
              />
              <Input
                label="Telefono"
                value={formState.telefono}
                onChange={(event) => setFormState((current) => ({ ...current, telefono: event.target.value }))}
              />
              <Input
                label="Cargo"
                value={formState.cargo}
                onChange={(event) => setFormState((current) => ({ ...current, cargo: event.target.value }))}
              />
              <Input
                label="Identificacion"
                value={formState.identificacion}
                onChange={(event) => setFormState((current) => ({ ...current, identificacion: event.target.value }))}
              />
            </div>

            {editingUser.role === 'docente' && (
              <>
                <Select
                  label="Region"
                  value={formState.regionId}
                  onChange={(event) => {
                    setFormState((current) => ({ ...current, regionId: event.target.value }));
                    setSelectedCentro(null);
                  }}
                  options={(regions ?? []).map((item) => ({ value: String(item.id), label: item.name }))}
                  placeholder="Seleccionar region..."
                />
                <CentroSearch
                  regionId={formState.regionId ? Number(formState.regionId) : undefined}
                  onSelect={setSelectedCentro}
                />
                {selectedCentro && (
                  <p style={{ fontSize: '0.875rem', color: 'var(--gnf-forest)', margin: 'var(--gnf-space-2) 0 var(--gnf-space-4)' }}>
                    Centro asignado: {selectedCentro.nombre}
                  </p>
                )}
              </>
            )}

            {editingUser.role === 'supervisor' && (
              <Select
                label="Region"
                value={formState.regionId}
                onChange={(event) => setFormState((current) => ({ ...current, regionId: event.target.value }))}
                options={(regions ?? []).map((item) => ({ value: String(item.id), label: item.name }))}
                placeholder="Seleccionar region..."
              />
            )}

            {editingUser.role === 'comite_bae' && (
              <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
                <div style={{ marginBottom: 'var(--gnf-space-2)', fontWeight: 500, fontSize: '0.875rem', color: 'var(--gnf-gray-700)' }}>
                  Regiones asignadas
                </div>
                <div
                  style={{
                    display: 'grid',
                    gap: 'var(--gnf-space-3)',
                    maxHeight: 240,
                    overflowY: 'auto',
                    padding: 'var(--gnf-space-4)',
                    borderRadius: 'var(--gnf-radius)',
                    border: '1.5px solid var(--gnf-field-border)',
                    background: 'rgba(248, 250, 252, 0.7)',
                  }}
                >
                  {(regions ?? []).map((item) => (
                    <Checkbox
                      key={item.id}
                      label={item.name}
                      checked={formState.regionIds.includes(String(item.id))}
                      onChange={(event) => toggleComiteRegion(String(item.id), event.target.checked)}
                    />
                  ))}
                </div>
                <p style={{ margin: 'var(--gnf-space-2) 0 0', fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
                  El comité puede revisar varias DRE. Marca una o más regiones.
                </p>
              </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--gnf-space-3)' }}>
              <Button type="button" variant="ghost" onClick={() => setEditingUser(null)}>
                Cancelar
              </Button>
              <Button type="submit" loading={updateUser.isPending}>
                Guardar cambios
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
