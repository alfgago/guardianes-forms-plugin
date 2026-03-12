import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { get } from '@/api/client';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { CentroSearch } from '@/components/domain/CentroSearch';
import { useToast } from '@/components/ui/Toast';
import { PendingUsersSection } from '../components/PendingUsersSection';
import type { CentroSearchResult, PendingUser, Region } from '@/types';

export function UsuariosPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('all');
  const [status, setStatus] = useState('all');
  const [editingUser, setEditingUser] = useState<PendingUser | null>(null);
  const [formState, setFormState] = useState({
    name: '',
    email: '',
    status: 'activo',
    telefono: '',
    cargo: '',
    identificacion: '',
    regionId: '',
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
    setFormState({
      name: editingUser.name ?? '',
      email: editingUser.email ?? '',
      status: editingUser.status ?? 'activo',
      telefono: editingUser.telefono ?? '',
      cargo: editingUser.cargo ?? '',
      identificacion: editingUser.identificacion ?? '',
      regionId: editingUser.regionId ? String(editingUser.regionId) : '',
    });
    setSelectedCentro(
      editingUser.centroId
        ? {
            id: editingUser.centroId,
            nombre: editingUser.centroName ?? '',
            codigoMep: '',
            regionId: editingUser.regionId,
            regionName: editingUser.regionName,
          }
        : null,
    );
  }, [editingUser]);

  const updateUser = useMutation({
    mutationFn: () => {
      if (!editingUser) {
        throw new Error('No hay usuario seleccionado.');
      }

      return adminApi.updateUser(editingUser.id, {
        ...formState,
        status: formState.status as 'activo' | 'pendiente',
        regionId: formState.regionId ? Number(formState.regionId) : undefined,
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
      const haystack = [user.name, user.email, user.centroName, user.regionName].join(' ').toLowerCase();
      const matchesSearch = !search.trim() || haystack.includes(search.trim().toLowerCase());
      const matchesRole = role === 'all' || user.role === role;
      const matchesStatus = status === 'all' || (user.status ?? 'activo') === status;

      return matchesSearch && matchesRole && matchesStatus;
    });
  }, [role, search, status, users]);

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-4)' }}>Usuarios</h2>

      <Card style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', alignItems: 'flex-end' }}>
          <div style={{ flex: '1 1 280px' }}>
            <Input
              label="Buscar"
              placeholder="Nombre, correo, centro o region..."
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              style={{ marginBottom: 0 }}
            />
          </div>

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

      <p style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)', marginBottom: 'var(--gnf-space-4)' }}>
        {filteredUsers.length} usuario{filteredUsers.length === 1 ? '' : 's'} visibles.
      </p>

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
                  options={(regions ?? []).map((region) => ({ value: String(region.id), label: region.name }))}
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

            {(editingUser.role === 'supervisor' || editingUser.role === 'comite_bae') && (
              <Select
                label="Region"
                value={formState.regionId}
                onChange={(event) => setFormState((current) => ({ ...current, regionId: event.target.value }))}
                options={(regions ?? []).map((region) => ({ value: String(region.id), label: region.name }))}
                placeholder="Seleccionar region..."
              />
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
