import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { UserPlus } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { authApi } from '@/api/auth';
import { get } from '@/api/client';
import type { Region } from '@/types';

export function SupervisorRegisterForm() {
  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rolSolicitado, setRolSolicitado] = useState<'supervisor' | 'comite_bae'>('supervisor');
  const [regionId, setRegionId] = useState('');

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const mutation = useMutation({
    mutationFn: () =>
      authApi.registerSupervisor({
        nombre,
        email,
        password,
        regionId: Number(regionId),
        rolSolicitado,
      }),
    onSuccess: (data) => {
      if (data.redirectUrl) window.location.href = data.redirectUrl;
    },
  });

  return (
    <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)', textAlign: 'center' }}>Registro DRE / Supervisores</h2>

      <Alert variant="info" title="Autorizacion manual requerida">
        La cuenta de {rolSolicitado === 'comite_bae' ? 'comite BAE' : 'supervisor'} quedara pendiente hasta que un
        administrador la autorice manualmente. Cuando eso ocurra, podras ingresar a revisar centros y retos.
      </Alert>

      {mutation.isSuccess && (
        <Alert variant="success" title="Registro exitoso">
          Tu cuenta ha sido creada. Quedara en espera de autorizacion administrativa antes de habilitar el acceso de revision.
        </Alert>
      )}

      {mutation.error && (
        <Alert variant="error">{(mutation.error as Error).message}</Alert>
      )}

      <Input label="Nombre completo" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
      <Input label="Correo electronico" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Contrasena" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />

      <Select
        label="Tipo de cuenta"
        value={rolSolicitado}
        onChange={(e) => setRolSolicitado(e.target.value as 'supervisor' | 'comite_bae')}
        options={[
          { value: 'supervisor', label: 'Supervisor' },
          { value: 'comite_bae', label: 'Comité BAE-DRE' },
        ]}
        required
      />

      <Select
        label="Dirección Regional de Educación"
        value={regionId}
        onChange={(e) => setRegionId(e.target.value)}
        options={(regions ?? []).map((region) => ({ value: String(region.id), label: region.name }))}
        placeholder="Seleccionar region..."
        required
      />

      <Button
        type="submit"
        loading={mutation.isPending}
        icon={<UserPlus size={16} />}
        style={{ width: '100%', marginTop: 'var(--gnf-space-4)' }}
      >
        {rolSolicitado === 'comite_bae' ? 'Registrarse como Comité BAE-DRE' : 'Registrarse como Supervisor'}
      </Button>
    </form>
  );
}
