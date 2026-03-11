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
  const [regionId, setRegionId] = useState('');
  const [cargo, setCargo] = useState('');
  const [telefono, setTelefono] = useState('');

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
        cargo,
        telefono,
      }),
    onSuccess: (data) => {
      if (data.redirectUrl) window.location.href = data.redirectUrl;
    },
  });

  return (
    <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)', textAlign: 'center' }}>Registro Supervisor</h2>

      {mutation.isSuccess && (
        <Alert variant="success" title="Registro exitoso">
          Tu cuenta ha sido creada y está pendiente de aprobación por un administrador.
        </Alert>
      )}

      {mutation.error && (
        <Alert variant="error">{(mutation.error as Error).message}</Alert>
      )}

      <Input label="Nombre completo" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
      <Input label="Correo electrónico" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Contraseña" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />

      <Select
        label="Dirección Regional de Educación"
        value={regionId}
        onChange={(e) => setRegionId(e.target.value)}
        options={(regions ?? []).map((r) => ({ value: String(r.id), label: r.name }))}
        placeholder="Seleccionar región..."
        required
      />

      <Input label="Cargo" value={cargo} onChange={(e) => setCargo(e.target.value)} />
      <Input label="Teléfono" type="tel" value={telefono} onChange={(e) => setTelefono(e.target.value)} />

      <Button
        type="submit"
        loading={mutation.isPending}
        icon={<UserPlus size={16} />}
        style={{ width: '100%', marginTop: 'var(--gnf-space-4)' }}
      >
        Registrarse como Supervisor
      </Button>
    </form>
  );
}
