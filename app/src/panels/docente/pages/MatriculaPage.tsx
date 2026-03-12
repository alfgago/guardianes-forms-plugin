import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { matriculaApi } from '@/api/matricula';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { useToast } from '@/components/ui/Toast';
import type { MatriculaFormValues, MatriculaReto } from '@/types';
import { Save } from 'lucide-react';

function toOptions(record: Record<string, string>) {
  return Object.entries(record).map(([value, label]) => ({ value, label }));
}

function getRequiredSelectedIds(retos: MatriculaReto[], selectedIds: number[]) {
  const ids = new Set(selectedIds);
  retos.forEach((reto) => {
    if (reto.obligatorio) {
      ids.add(reto.id);
    }
  });
  return Array.from(ids);
}

export function MatriculaPage() {
  const year = useYearStore((s) => s.selectedYear);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data, isLoading } = useQuery({
    queryKey: ['matricula-prefill', year],
    queryFn: () => matriculaApi.getPrefill(year),
  });

  const [form, setForm] = useState<MatriculaFormValues | null>(null);
  const [selectedRetos, setSelectedRetos] = useState<number[]>([]);

  useEffect(() => {
    if (!data) return;
    setForm(data.prefill);
    setSelectedRetos(data.retosSeleccionados);
  }, [data]);

  const saveMatricula = useMutation({
    mutationFn: (payload: MatriculaFormValues & { retosSeleccionados: number[] }) => matriculaApi.save(payload, year),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['matricula-prefill', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      toast('success', 'MatrÃ­cula guardada correctamente.');
    },
    onError: (error: Error) => {
      toast('error', error.message || 'No se pudo guardar la matrÃ­cula.');
    },
  });

  const regionOptions = useMemo(
    () => (data?.regiones ?? []).map((region) => ({ value: String(region.id), label: region.name })),
    [data?.regiones],
  );

  if (isLoading) return <Spinner />;
  if (!data || !form) return <Alert variant="error">Error al cargar los datos de matrÃ­cula.</Alert>;

  const selectedSet = new Set(selectedRetos);
  const cantones = data.cantonesPorProvincia[form.centroProvincia] ?? [];
  const totalSelected = getRequiredSelectedIds(data.retosDisponibles, selectedRetos).length;

  const handleField = <K extends keyof MatriculaFormValues>(key: K, value: MatriculaFormValues[K]) => {
    setForm((current) => current ? { ...current, [key]: value } : current);
  };

  const toggleReto = (reto: MatriculaReto) => {
    if (reto.obligatorio) return;

    setSelectedRetos((current) => (
      current.includes(reto.id)
        ? current.filter((id) => id !== reto.id)
        : [...current, reto.id]
    ));
  };

  const handleSave = () => {
    saveMatricula.mutate({
      ...form,
      retosSeleccionados: getRequiredSelectedIds(data.retosDisponibles, selectedRetos),
    });
  };

  return (
    <div>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          gap: 'var(--gnf-space-4)',
          flexWrap: 'wrap',
          marginBottom: 'var(--gnf-space-6)',
        }}
      >
        <div>
          <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>MatrÃ­cula del Centro Educativo {year}</h2>
          <p style={{ color: 'var(--gnf-muted)', marginBottom: 0 }}>
            AquÃ­ puedes editar los datos del centro y definir todos los eco retos matriculados del aÃ±o.
          </p>
        </div>

        <Button variant="primary" icon={<Save size={16} />} loading={saveMatricula.isPending} onClick={handleSave}>
          Guardar matrÃ­cula
        </Button>
      </div>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
            gap: 'var(--gnf-space-4)',
          }}
        >
          <div>
            <strong>{form.centroNombre || data.centro?.nombre || 'Centro educativo'}</strong>
            <p style={{ margin: '6px 0 0', color: 'var(--gnf-muted)' }}>
              CÃ³digo MEP: {form.centroCodigoMep || 'Sin cÃ³digo'}
            </p>
          </div>
          <div>
            <strong>{totalSelected}</strong>
            <p style={{ margin: '6px 0 0', color: 'var(--gnf-muted)' }}>eco retos matriculados</p>
          </div>
        </div>
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Datos del centro educativo</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Input label="Nombre del centro educativo" value={form.centroNombre} onChange={(e) => handleField('centroNombre', e.target.value)} />
          <Input label="CÃ³digo MEP" value={form.centroCodigoMep} onChange={(e) => handleField('centroCodigoMep', e.target.value)} />
          <Input label="Correo institucional" type="email" value={form.centroCorreoInstitucional} onChange={(e) => handleField('centroCorreoInstitucional', e.target.value)} />
          <Input label="TelÃ©fono institucional" value={form.centroTelefono} onChange={(e) => handleField('centroTelefono', e.target.value)} />
          <Select label="DirecciÃ³n regional" value={String(form.centroRegion || '')} onChange={(e) => handleField('centroRegion', Number(e.target.value) || 0)} options={regionOptions} placeholder="Selecciona una regiÃ³n" />
          <Input label="Circuito" value={form.centroCircuito} onChange={(e) => handleField('centroCircuito', e.target.value)} />
          <Select label="Provincia" value={form.centroProvincia} onChange={(e) => { handleField('centroProvincia', e.target.value); handleField('centroCanton', ''); }} options={data.provincias.map((provincia) => ({ value: provincia, label: provincia }))} placeholder="Selecciona una provincia" />
          <Select label="CantÃ³n" value={form.centroCanton} onChange={(e) => handleField('centroCanton', e.target.value)} options={cantones.map((canton) => ({ value: canton, label: canton }))} placeholder="Selecciona un cantÃ³n" />
          <Input label="CÃ³digo presupuestario" value={form.centroCodigoPresupuestario} onChange={(e) => handleField('centroCodigoPresupuestario', e.target.value)} />
        </div>
        <Textarea label="DirecciÃ³n exacta" value={form.centroDireccion} onChange={(e) => handleField('centroDireccion', e.target.value)} />
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Perfil institucional</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Select label="Nivel educativo" value={form.centroNivelEducativo} onChange={(e) => handleField('centroNivelEducativo', e.target.value)} options={toOptions(data.choiceSets.nivel_educativo)} placeholder="Selecciona una opciÃ³n" />
          <Select label="Dependencia" value={form.centroDependencia} onChange={(e) => handleField('centroDependencia', e.target.value)} options={toOptions(data.choiceSets.dependencia)} placeholder="Selecciona una opciÃ³n" />
          <Select label="Jornada" value={form.centroJornada} onChange={(e) => handleField('centroJornada', e.target.value)} options={toOptions(data.choiceSets.jornada)} placeholder="Selecciona una opciÃ³n" />
          <Select label="TipologÃ­a" value={form.centroTipologia} onChange={(e) => handleField('centroTipologia', e.target.value)} options={toOptions(data.choiceSets.tipologia)} placeholder="Selecciona una opciÃ³n" />
          <Input label="Total de estudiantes" type="number" min={0} value={form.centroTotalEstudiantes} onChange={(e) => handleField('centroTotalEstudiantes', Number(e.target.value) || 0)} />
          <Input label="Estudiantes hombres" type="number" min={0} value={form.centroEstudiantesHombres} onChange={(e) => handleField('centroEstudiantesHombres', Number(e.target.value) || 0)} />
          <Input label="Estudiantes mujeres" type="number" min={0} value={form.centroEstudiantesMujeres} onChange={(e) => handleField('centroEstudiantesMujeres', Number(e.target.value) || 0)} />
          <Input label="Estudiantes migrantes" type="number" min={0} value={form.centroEstudiantesMigrantes} onChange={(e) => handleField('centroEstudiantesMigrantes', Number(e.target.value) || 0)} />
        </div>
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>ParticipaciÃ³n y coordinaciÃ³n</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Select label="Ãšltimo galardÃ³n obtenido" value={form.centroUltimoGalardonEstrellas} onChange={(e) => handleField('centroUltimoGalardonEstrellas', e.target.value)} options={toOptions(data.choiceSets.ultimo_galardon_estrellas)} placeholder="Selecciona una opciÃ³n" />
          <Select label="Ãšltimo aÃ±o de participaciÃ³n" value={form.centroUltimoAnioParticipacion} onChange={(e) => handleField('centroUltimoAnioParticipacion', e.target.value)} options={toOptions(data.choiceSets.ultimo_anio_participacion)} placeholder="Selecciona una opciÃ³n" />
          {form.centroUltimoAnioParticipacion === 'otro' && (
            <Input label="Otro aÃ±o de participaciÃ³n" value={form.centroUltimoAnioParticipacionOtro} onChange={(e) => handleField('centroUltimoAnioParticipacionOtro', e.target.value)} />
          )}
          <Select label="Cargo de coordinaciÃ³n PBAE" value={form.coordinadorCargo} onChange={(e) => handleField('coordinadorCargo', e.target.value)} options={toOptions(data.choiceSets.coordinador_cargo)} placeholder="Selecciona una opciÃ³n" />
          <Input label="Nombre de coordinaciÃ³n PBAE" value={form.coordinadorNombre} onChange={(e) => handleField('coordinadorNombre', e.target.value)} />
          <Input label="Celular de coordinaciÃ³n" value={form.coordinadorCelular} onChange={(e) => handleField('coordinadorCelular', e.target.value)} />
          <Input label="Cantidad de estudiantes en comitÃ©" type="number" min={0} value={form.comiteEstudiantes} onChange={(e) => handleField('comiteEstudiantes', Number(e.target.value) || 0)} />
          <Select label="InscripciÃ³n anterior" value={form.inscripcionAnterior} onChange={(e) => handleField('inscripcionAnterior', e.target.value)} options={[{ value: 'SÃ­', label: 'SÃ­' }, { value: 'No', label: 'No' }]} />

        </div>
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Persona responsable del centro educativo</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Input label="Nombre completo" value={form.representanteNombre} onChange={(e) => handleField('representanteNombre', e.target.value)} />
          <Input label="Cargo" value={form.representanteCargo} onChange={(e) => handleField('representanteCargo', e.target.value)} />
          <Input label="TelÃ©fono" value={form.representanteTelefono} onChange={(e) => handleField('representanteTelefono', e.target.value)} />
          <Input label="Correo electrÃ³nico" type="email" value={form.representanteEmail} onChange={(e) => handleField('representanteEmail', e.target.value)} />
          <Input label="Confirmar correo electrÃ³nico" type="email" value={form.representanteEmailConfirm} onChange={(e) => handleField('representanteEmailConfirm', e.target.value)} />
        </div>
      </Card>

      <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-2)' }}>SelecciÃ³n de eco retos</h3>
        <p style={{ color: 'var(--gnf-muted)', marginBottom: 0 }}>
          Los retos marcados como <strong>REQUISITO</strong> siempre quedan seleccionados.
        </p>
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
          gap: 'var(--gnf-space-4)',
        }}
      >
        {data.retosDisponibles.map((reto) => {
          const isSelected = selectedSet.has(reto.id) || reto.obligatorio;

          return (
            <Card
              key={reto.id}
              hoverable
              onClick={() => toggleReto(reto)}
              style={{
                cursor: reto.obligatorio ? 'default' : 'pointer',
                border: isSelected ? '1px solid rgba(22, 163, 74, 0.45)' : '1px solid var(--gnf-border)',
                boxShadow: isSelected ? '0 14px 28px rgba(22, 163, 74, 0.08)' : 'var(--gnf-shadow-sm)',
                padding: 'var(--gnf-space-5)',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 'var(--gnf-space-4)' }}>
                {reto.iconUrl && (
                  <img
                    src={reto.iconUrl}
                    alt=""
                    style={{ width: 54, height: 54, borderRadius: 'var(--gnf-radius-sm)', objectFit: 'cover', flexShrink: 0 }}
                  />
                )}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--gnf-space-2)', alignItems: 'center', marginBottom: 'var(--gnf-space-2)' }}>
                    <strong style={{ lineHeight: 1.3 }}>{reto.titulo}</strong>
                    {reto.obligatorio && (
                      <span style={{ fontSize: '0.7rem', fontWeight: 700, color: 'var(--gnf-ocean)' }}>REQUISITO</span>
                    )}
                  </div>
                  <p style={{ color: 'var(--gnf-muted)', fontSize: '0.9rem', margin: 0 }}>
                    {reto.descripcion}
                  </p>
                  <p style={{ color: 'var(--gnf-ocean)', fontSize: '0.82rem', margin: 'var(--gnf-space-3) 0 0', fontWeight: 600 }}>
                    Hasta {reto.puntajeMaximo} eco puntos
                  </p>
                </div>
              </div>
              <div style={{ marginTop: 'var(--gnf-space-4)', display: 'flex', justifyContent: 'flex-end' }}>
                <Button
                  type="button"
                  variant={isSelected ? 'primary' : 'outline'}
                  size="sm"
                  disabled={reto.obligatorio}
                  onClick={(event) => {
                    event.stopPropagation();
                    toggleReto(reto);
                  }}
                >
                  {reto.obligatorio ? 'Requisito' : isSelected ? 'Matriculado' : 'Agregar'}
                </Button>
              </div>
            </Card>
          );
        })}
      </div>

      <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 'var(--gnf-space-6)' }}>
        <Button variant="primary" icon={<Save size={16} />} loading={saveMatricula.isPending} onClick={handleSave}>
          Guardar matrÃ­cula
        </Button>
      </div>
    </div>
  );
}

