import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { UserPlus } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { CentroSearch } from '@/components/domain/CentroSearch';
import { authApi } from '@/api/auth';
import { get } from '@/api/client';
import type { CentroSearchResult, Region } from '@/types';
import { NIVELES_EDUCATIVOS, DEPENDENCIAS } from '@/utils/constants';

export function DocenteRegisterForm() {
  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [regionId, setRegionId] = useState('');
  const [selectedCentro, setSelectedCentro] = useState<CentroSearchResult | null>(null);
  const [newCentro, setNewCentro] = useState(false);
  const [centroNombre, setCentroNombre] = useState('');
  const [centroCodigo, setCentroCodigo] = useState('');
  const [centroDireccion, setCentroDireccion] = useState('');
  const [centroRegionId, setCentroRegionId] = useState('');
  const [centroProvincia, setCentroProvincia] = useState('');
  const [centroCanton, setCentroCanton] = useState('');
  const [centroNivel, setCentroNivel] = useState('');
  const [centroDependencia, setCentroDependencia] = useState('');

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const mutation = useMutation({
    mutationFn: () => {
      if (!newCentro && !regionId) {
        throw new Error('Selecciona primero la direccion regional del centro.');
      }

      if (!newCentro && !selectedCentro) {
        throw new Error('Selecciona un centro existente o registra uno nuevo.');
      }

      if (!newCentro && selectedCentro?.claimed) {
        const correo = selectedCentro.correoInstitucional?.trim();
        throw new Error(
          correo
            ? `Este centro educativo ya esta siendo utilizado con el correo ${correo}.`
            : 'Este centro educativo ya esta siendo utilizado por otra cuenta.',
        );
      }

      if (newCentro && !centroRegionId) {
        throw new Error('Selecciona la direccion regional del nuevo centro.');
      }

      return authApi.registerDocente({
        nombre,
        email,
        password,
        ...(!newCentro && selectedCentro
          ? { centroId: selectedCentro.id }
          : {
              centroNombre,
              centroCodigoMep: centroCodigo,
              centroDireccion,
              centroRegionId: centroRegionId ? Number(centroRegionId) : undefined,
              centroProvincia,
              centroCanton,
              centroNivelEducativo: centroNivel,
              centroDependencia,
            }),
      });
    },
    onSuccess: (data) => {
      if (data.redirectUrl) window.location.href = data.redirectUrl;
    },
  });

  return (
    <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }}>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)', textAlign: 'center' }}>Registrar centro educativo</h2>

      {mutation.isSuccess && (
        <Alert variant="success" title="Registro exitoso">
          La cuenta quedo creada y ya puedes continuar con la matricula del centro educativo.
        </Alert>
      )}

      {mutation.error && (
        <Alert variant="error">{(mutation.error as Error).message}</Alert>
      )}

      <Input label="Nombre completo" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
      <Input label="Correo electronico institucional" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <Input label="Contrasena" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />

      <h3 style={{ margin: 'var(--gnf-space-6) 0 var(--gnf-space-4)', fontSize: '1rem' }}>Centro educativo</h3>

      {!newCentro ? (
        <div>
          <Select
            label="Direccion regional de educacion"
            value={regionId}
            onChange={(e) => {
              setRegionId(e.target.value);
              setSelectedCentro(null);
            }}
            options={(regions ?? []).map((region) => ({ value: String(region.id), label: region.name }))}
            placeholder="Seleccionar region..."
            required
          />
          <CentroSearch
            regionId={regionId ? Number(regionId) : undefined}
            includeClaimed
            onSelect={(c) => setSelectedCentro(c)}
          />
          <p style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', marginTop: 'var(--gnf-space-2)' }}>
            Solo puede existir una cuenta por centro educativo. Si un centro ya esta en uso, se mostrara con su correo institucional.
          </p>
          {selectedCentro?.claimed && (
            <Alert variant="error" title="Centro educativo ya en uso">
              {selectedCentro.correoInstitucional
                ? `Este centro educativo ya esta siendo utilizado con el correo ${selectedCentro.correoInstitucional}.`
                : 'Este centro educativo ya esta siendo utilizado por otra cuenta.'}
            </Alert>
          )}
          {selectedCentro && (
            <p style={{ fontSize: '0.875rem', color: 'var(--gnf-forest)', marginTop: 'var(--gnf-space-2)' }}>
              Seleccionado: {selectedCentro.nombre} ({selectedCentro.codigoMep || 'Sin código'})
              {selectedCentro.regionName ? ` - ${selectedCentro.regionName}` : ''}
            </p>
          )}
        </div>
      ) : (
        <div>
          <Input label="Nombre del centro" value={centroNombre} onChange={(e) => setCentroNombre(e.target.value)} required />
          <Input label="Direccion" value={centroDireccion} onChange={(e) => setCentroDireccion(e.target.value)} />
          <Input label="Codigo MEP" value={centroCodigo} onChange={(e) => setCentroCodigo(e.target.value)} />
          <Select
            label="Direccion regional de educacion"
            value={centroRegionId}
            onChange={(e) => setCentroRegionId(e.target.value)}
            options={(regions ?? []).map((region) => ({ value: String(region.id), label: region.name }))}
            placeholder="Seleccionar region..."
            required
          />
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--gnf-space-4)' }}>
            <Input label="Provincia" value={centroProvincia} onChange={(e) => setCentroProvincia(e.target.value)} />
            <Input label="Canton" value={centroCanton} onChange={(e) => setCentroCanton(e.target.value)} />
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
          <Button
            variant="ghost"
            size="sm"
            type="button"
            onClick={() => {
              setNewCentro(false);
              setSelectedCentro(null);
              setCentroNombre('');
              setCentroCodigo('');
              setCentroDireccion('');
              setCentroRegionId('');
              setCentroProvincia('');
              setCentroCanton('');
              setCentroNivel('');
              setCentroDependencia('');
            }}
          >
            Buscar centro existente
          </Button>
        </div>
      )}

      <Button
        type="submit"
        loading={mutation.isPending}
        disabled={!newCentro && Boolean(selectedCentro?.claimed)}
        icon={<UserPlus size={16} />}
        style={{ width: '100%', marginTop: 'var(--gnf-space-6)' }}
      >
        Registrar centro educativo
      </Button>
    </form>
  );
}
