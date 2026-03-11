import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { UserPlus } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { CentroSearch } from '@/components/domain/CentroSearch';
import { authApi } from '@/api/auth';
import type { CentroSearchResult } from '@/types';
import { NIVELES_EDUCATIVOS, DEPENDENCIAS } from '@/utils/constants';

export function DocenteRegisterForm() {
  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [selectedCentro, setSelectedCentro] = useState<CentroSearchResult | null>(null);
  const [newCentro, setNewCentro] = useState(false);
  const [centroNombre, setCentroNombre] = useState('');
  const [centroCodigo, setCentroCodigo] = useState('');
  const [centroProvincia, setCentroProvincia] = useState('');
  const [centroCanton, setCentroCanton] = useState('');
  const [centroNivel, setCentroNivel] = useState('');
  const [centroDependencia, setCentroDependencia] = useState('');

  const mutation = useMutation({
    mutationFn: () =>
      authApi.registerDocente({
        nombre,
        email,
        password,
        ...(selectedCentro
          ? { centroId: selectedCentro.id }
          : {
              centroNombre,
              centroCodigoMep: centroCodigo,
              centroProvincia,
              centroCanton,
              centroNivelEducativo: centroNivel,
              centroDependencia,
            }),
      }),
    onSuccess: (data) => {
      if (data.redirectUrl) window.location.href = data.redirectUrl;
    },
  });

  return (
    <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)', textAlign: 'center' }}>Registro Docente</h2>

      {mutation.isSuccess && (
        <Alert variant="success" title="Registro exitoso">
          Tu cuenta ha sido creada. Un administrador debe aprobarla antes de que puedas acceder al panel.
        </Alert>
      )}

      {mutation.error && (
        <Alert variant="error">{(mutation.error as Error).message}</Alert>
      )}

      <Input label="Nombre completo" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
      <Input label="Correo electrónico" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Contraseña" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />

      <h3 style={{ margin: 'var(--gnf-space-6) 0 var(--gnf-space-4)', fontSize: '1rem' }}>Centro Educativo</h3>

      {!newCentro ? (
        <div>
          <CentroSearch onSelect={(c) => setSelectedCentro(c)} />
          {selectedCentro && (
            <p style={{ fontSize: '0.875rem', color: 'var(--gnf-forest)', marginTop: 'var(--gnf-space-2)' }}>
              Seleccionado: {selectedCentro.nombre} ({selectedCentro.codigoMep})
            </p>
          )}
          <Button variant="ghost" size="sm" type="button" onClick={() => setNewCentro(true)} style={{ marginTop: 'var(--gnf-space-3)' }}>
            Registrar un nuevo centro
          </Button>
        </div>
      ) : (
        <div>
          <Input label="Nombre del centro" value={centroNombre} onChange={(e) => setCentroNombre(e.target.value)} required />
          <Input label="Código MEP" value={centroCodigo} onChange={(e) => setCentroCodigo(e.target.value)} />
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--gnf-space-4)' }}>
            <Input label="Provincia" value={centroProvincia} onChange={(e) => setCentroProvincia(e.target.value)} />
            <Input label="Cantón" value={centroCanton} onChange={(e) => setCentroCanton(e.target.value)} />
          </div>
          <Select
            label="Nivel educativo"
            value={centroNivel}
            onChange={(e) => setCentroNivel(e.target.value)}
            options={NIVELES_EDUCATIVOS.map((n) => ({ value: n, label: n }))}
            placeholder="Seleccionar..."
          />
          <Select
            label="Dependencia"
            value={centroDependencia}
            onChange={(e) => setCentroDependencia(e.target.value)}
            options={DEPENDENCIAS.map((d) => ({ value: d, label: d }))}
            placeholder="Seleccionar..."
          />
          <Button variant="ghost" size="sm" type="button" onClick={() => { setNewCentro(false); setSelectedCentro(null); }}>
            Buscar centro existente
          </Button>
        </div>
      )}

      <Button
        type="submit"
        loading={mutation.isPending}
        icon={<UserPlus size={16} />}
        style={{ width: '100%', marginTop: 'var(--gnf-space-6)' }}
      >
        Registrarse como Docente
      </Button>
    </form>
  );
}
