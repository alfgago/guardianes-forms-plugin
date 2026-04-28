import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { matriculaApi } from '@/api/matricula';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import { useYearStore } from '@/stores/useYearStore';
import type { MatriculaFormValues, MatriculaPrefill, MatriculaReto } from '@/types';

type MatriculaSavePayload = MatriculaFormValues & { retosSeleccionados: number[] };
type SaveMode = 'auto' | 'manual';
type SaveState = 'idle' | 'saving' | 'saved' | 'error';
type FormFieldKey = keyof typeof FIELD_NAMES;

const FIELD_NAMES = {
  centroNombre: 'centro_nombre',
  centroCodigoMep: 'centro_codigo_mep',
  centroCorreoInstitucional: 'centro_correo_institucional',
  centroTelefono: 'centro_telefono',
  centroNivelEducativo: 'centro_nivel_educativo',
  centroDependencia: 'centro_dependencia',
  centroJornada: 'centro_jornada',
  centroTipologia: 'centro_tipologia',
  centroTipoCentroEducativo: 'centro_tipo_centro_educativo',
  centroRegion: 'centro_region',
  centroCircuito: 'centro_circuito',
  centroProvincia: 'centro_provincia',
  centroCanton: 'centro_canton',
  centroCodigoPresupuestario: 'centro_codigo_presupuestario',
  centroDireccion: 'centro_direccion',
  centroTotalEstudiantes: 'centro_total_estudiantes',
  centroEstudiantesHombres: 'centro_estudiantes_hombres',
  centroEstudiantesMujeres: 'centro_estudiantes_mujeres',
  centroEstudiantesMigrantes: 'centro_estudiantes_migrantes',
  centroUltimoGalardonEstrellas: 'centro_ultimo_galardon_estrellas',
  centroUltimoAnioParticipacion: 'centro_ultimo_anio_participacion',
  centroUltimoAnioParticipacionOtro: 'centro_ultimo_anio_participacion_otro',
  coordinadorCargo: 'coordinador_cargo',
  coordinadorNombre: 'coordinador_nombre',
  coordinadorCelular: 'coordinador_celular',
  representanteNombre: 'docente_nombre',
  representanteCargo: 'docente_cargo',
  representanteTelefono: 'docente_telefono',
  representanteEmail: 'docente_email',
  representanteEmailConfirm: 'docente_email_confirm',
  docenteConfirmaciones: 'docente_confirmaciones',
  comiteEstudiantes: 'bae_comite_estudiantes',
  inscripcionAnterior: 'bae_inscripcion_anterior',
  metaEstrellas: 'bae_meta_estrellas',
} as const;

const INSCRIPCION_ANTERIOR_FALLBACK = {
  'Sí': 'Sí',
  No: 'No',
};

const META_ESTRELLAS_FALLBACK = {
  '1 estrella': '1 estrella',
  '2 estrellas': '2 estrellas',
  '3 estrellas': '3 estrellas',
  '4 estrellas': '4 estrellas',
  '5 estrellas': '5 estrellas',
};

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

function formatSavedStamp(savedAt?: string | null) {
  if (!savedAt) return '';

  const date = new Date(savedAt);
  if (Number.isNaN(date.getTime())) return '';

  return date.toLocaleString('es-CR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
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
  const [saveState, setSaveState] = useState<SaveState>('idle');
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
  const initializedRef = useRef(false);
  const mountedRef = useRef(true);
  const saveInFlightRef = useRef(false);
  const lastSavedKeyRef = useRef('');
  const queuedSaveRef = useRef<{ payload: MatriculaSavePayload; mode: SaveMode; key: string } | null>(null);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    initializedRef.current = false;
    setForm(null);
    setSelectedRetos([]);
    setSaveState('idle');
    setLastSavedAt(null);
    lastSavedKeyRef.current = '';
    queuedSaveRef.current = null;
  }, [year]);

  useEffect(() => {
    if (!data || initializedRef.current) return;

    const initialPayload = {
      ...data.prefill,
      retosSeleccionados: getRequiredSelectedIds(data.retosDisponibles, data.retosSeleccionados),
    };

    setForm({
      ...data.prefill,
      docenteConfirmaciones: data.prefill.docenteConfirmaciones ?? [],
    });
    setSelectedRetos(data.retosSeleccionados);
    setLastSavedAt(new Date().toISOString());
    lastSavedKeyRef.current = JSON.stringify(initialPayload);
    initializedRef.current = true;
  }, [data]);

  const saveMatricula = useMutation({
    mutationFn: (payload: MatriculaSavePayload) => matriculaApi.save(payload, year),
  });

  const regionOptions = useMemo(
    () => (data?.regiones ?? []).map((region) => ({ value: String(region.id), label: region.name })),
    [data?.regiones],
  );

  const savePayload = useMemo<MatriculaSavePayload | null>(() => {
    if (!data || !form) return null;

    return {
      ...form,
      docenteConfirmaciones: form.docenteConfirmaciones ?? [],
      retosSeleccionados: getRequiredSelectedIds(data.retosDisponibles, selectedRetos),
    };
  }, [data, form, selectedRetos]);

  const debouncedSavePayload = useDebounce(savePayload, 900);

  const persistMatricula = useCallback(async (payload: MatriculaSavePayload, mode: SaveMode) => {
    const payloadKey = JSON.stringify(payload);

    if (mode === 'auto' && payloadKey === lastSavedKeyRef.current) {
      return;
    }

    if (saveInFlightRef.current) {
      queuedSaveRef.current = { payload, mode, key: payloadKey };
      return;
    }

    saveInFlightRef.current = true;
    if (mountedRef.current) {
      setSaveState('saving');
    }

    try {
      await saveMatricula.mutateAsync(payload);

      lastSavedKeyRef.current = payloadKey;
      queryClient.setQueryData<MatriculaPrefill>(['matricula-prefill', year], (current) => (
        current
          ? {
              ...current,
              prefill: payload,
              retosSeleccionados: payload.retosSeleccionados,
            }
          : current
      ));
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });

      if (mountedRef.current) {
        setLastSavedAt(new Date().toISOString());
        setSaveState('saved');
      }

      if (mode === 'manual') {
        toast('success', 'Matricula guardada correctamente.');
      }
    } catch (error) {
      if (mountedRef.current) {
        setSaveState('error');
      }

      if (mode === 'manual') {
        const message = error instanceof Error ? error.message : 'No se pudo guardar la matricula.';
        toast('error', message || 'No se pudo guardar la matricula.');
      }
    } finally {
      saveInFlightRef.current = false;

      const nextSave = queuedSaveRef.current;
      queuedSaveRef.current = null;

      if (nextSave && nextSave.key !== lastSavedKeyRef.current) {
        void persistMatricula(nextSave.payload, nextSave.mode);
      }
    }
  }, [queryClient, saveMatricula, toast, year]);

  useEffect(() => {
    if (!initializedRef.current || !debouncedSavePayload) return;
    void persistMatricula(debouncedSavePayload, 'auto');
  }, [debouncedSavePayload, persistMatricula]);

  if (isLoading) return <Spinner />;
  if (!data || !form) return <Alert variant="error">Error al cargar los datos de matricula.</Alert>;

  const getFieldLabel = (key: FormFieldKey, fallback: string) => (
    data.fieldDefs[FIELD_NAMES[key]]?.label || fallback
  );

  const getFieldChoices = (key: FormFieldKey, fallback: Record<string, string>) => {
    const choices = data.fieldDefs[FIELD_NAMES[key]]?.choices;
    return toOptions(choices && Object.keys(choices).length > 0 ? choices : fallback);
  };

  const getFieldInstructions = (key: FormFieldKey) => {
    const instructions = data.fieldDefs[FIELD_NAMES[key]]?.instructions?.trim();
    return instructions ? instructions : undefined;
  };

  const cantones = data.cantonesPorProvincia[form.centroProvincia] ?? [];
  const selectedSet = new Set(selectedRetos);
  const totalSelected = savePayload?.retosSeleccionados.length ?? 0;
  const saveStatusLabel = (() => {
    if (saveState === 'saving') return 'Guardando cambios...';
    if (saveState === 'saved' && lastSavedAt) return `Guardado automatico: ${formatSavedStamp(lastSavedAt)}`;
    if (saveState === 'error') return 'No se pudo guardar automaticamente. Revisa la conexion y vuelve a intentarlo.';
    return 'Guardado automatico activo. Los cambios en la matricula y en la seleccion de eco retos se guardan al editar.';
  })();

  const handleField = <K extends keyof MatriculaFormValues>(key: K, value: MatriculaFormValues[K]) => {
    setForm((current) => (current ? { ...current, [key]: value } : current));
  };

  const toggleReto = (reto: MatriculaReto) => {
    if (reto.obligatorio) return;

    setSelectedRetos((current) => (
      current.includes(reto.id)
        ? current.filter((id) => id !== reto.id)
        : [...current, reto.id]
    ));
  };

  const toggleConfirmation = (value: string, checked: boolean) => {
    setForm((current) => {
      if (!current) return current;

      const nextValues = checked
        ? Array.from(new Set([...(current.docenteConfirmaciones ?? []), value]))
        : (current.docenteConfirmaciones ?? []).filter((item) => item !== value);

      return {
        ...current,
        docenteConfirmaciones: nextValues,
      };
    });
  };

  const handleSave = () => {
    if (!savePayload) return;
    void persistMatricula(savePayload, 'manual');
  };

  const confirmacionOptions = getFieldChoices('docenteConfirmaciones', {});
  const metaEstrellasOptions = getFieldChoices('metaEstrellas', META_ESTRELLAS_FALLBACK);
  const inscripcionAnteriorOptions = getFieldChoices('inscripcionAnterior', INSCRIPCION_ANTERIOR_FALLBACK);

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
          <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>Matricula del Centro Educativo {year}</h2>
          <p style={{ color: 'var(--gnf-muted)', marginBottom: 0 }}>
            Aqui puedes editar los datos del centro y definir todos los eco retos matriculados del ano.
          </p>
        </div>

        <Button variant="primary" icon={<Save size={16} />} loading={saveState === 'saving'} onClick={handleSave}>
          Guardar ahora
        </Button>
      </div>

      <div style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <Alert variant={saveState === 'error' ? 'error' : saveState === 'saved' ? 'success' : 'info'}>
          {saveStatusLabel}
        </Alert>
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
              Codigo MEP: {form.centroCodigoMep || 'Sin codigo'}
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
          <Input
            label={getFieldLabel('centroNombre', 'Nombre del centro educativo')}
            value={form.centroNombre}
            onChange={(e) => handleField('centroNombre', e.target.value)}
          />
          <Input
            label={getFieldLabel('centroCodigoMep', 'Codigo MEP')}
            value={form.centroCodigoMep}
            onChange={(e) => handleField('centroCodigoMep', e.target.value)}
          />
          <Input
            label={getFieldLabel('centroCorreoInstitucional', 'Correo institucional')}
            type="email"
            value={form.centroCorreoInstitucional}
            readOnly
          />
          <Input
            label={getFieldLabel('centroTelefono', 'Telefono institucional')}
            value={form.centroTelefono}
            onChange={(e) => handleField('centroTelefono', e.target.value)}
          />
          <Select
            label={getFieldLabel('centroRegion', 'Direccion regional')}
            value={String(form.centroRegion || '')}
            onChange={(e) => handleField('centroRegion', Number(e.target.value) || 0)}
            options={regionOptions}
            placeholder="Selecciona una region"
          />
          <Input
            label={getFieldLabel('centroCircuito', 'Circuito')}
            value={form.centroCircuito}
            onChange={(e) => handleField('centroCircuito', e.target.value)}
          />
          <Select
            label={getFieldLabel('centroProvincia', 'Provincia')}
            value={form.centroProvincia}
            onChange={(e) => {
              handleField('centroProvincia', e.target.value);
              handleField('centroCanton', '');
            }}
            options={data.provincias.map((provincia) => ({ value: provincia, label: provincia }))}
            placeholder="Selecciona una provincia"
          />
          <Select
            label={getFieldLabel('centroCanton', 'Canton')}
            value={form.centroCanton}
            onChange={(e) => handleField('centroCanton', e.target.value)}
            options={cantones.map((canton) => ({ value: canton, label: canton }))}
            placeholder="Selecciona un canton"
          />
          <Input
            label={getFieldLabel('centroCodigoPresupuestario', 'Codigo presupuestario')}
            value={form.centroCodigoPresupuestario}
            onChange={(e) => handleField('centroCodigoPresupuestario', e.target.value)}
            hint={getFieldInstructions('centroCodigoPresupuestario')}
          />
        </div>
        <Textarea
          label={getFieldLabel('centroDireccion', 'Direccion exacta')}
          value={form.centroDireccion}
          onChange={(e) => handleField('centroDireccion', e.target.value)}
        />
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Perfil institucional</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Select
            label={getFieldLabel('centroNivelEducativo', 'Nivel educativo')}
            value={form.centroNivelEducativo}
            onChange={(e) => handleField('centroNivelEducativo', e.target.value)}
            options={getFieldChoices('centroNivelEducativo', data.choiceSets.nivel_educativo)}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('centroDependencia', 'Dependencia')}
            value={form.centroDependencia}
            onChange={(e) => handleField('centroDependencia', e.target.value)}
            options={getFieldChoices('centroDependencia', data.choiceSets.dependencia)}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('centroJornada', 'Jornada')}
            value={form.centroJornada}
            onChange={(e) => handleField('centroJornada', e.target.value)}
            options={getFieldChoices('centroJornada', data.choiceSets.jornada)}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('centroTipologia', 'Tipologia segun matricula')}
            value={form.centroTipologia}
            onChange={(e) => handleField('centroTipologia', e.target.value)}
            options={getFieldChoices('centroTipologia', data.choiceSets.tipologia)}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('centroTipoCentroEducativo', 'Tipo de Centro Educativo')}
            value={form.centroTipoCentroEducativo}
            onChange={(e) => handleField('centroTipoCentroEducativo', e.target.value)}
            options={getFieldChoices('centroTipoCentroEducativo', data.choiceSets.tipo_centro_educativo)}
            placeholder="Selecciona una opcion"
          />
          <Input
            label={getFieldLabel('centroTotalEstudiantes', 'Total de estudiantes')}
            type="number"
            min={0}
            value={form.centroTotalEstudiantes}
            onChange={(e) => handleField('centroTotalEstudiantes', Number(e.target.value) || 0)}
          />
          <Input
            label={getFieldLabel('centroEstudiantesHombres', 'Estudiantes hombres')}
            type="number"
            min={0}
            value={form.centroEstudiantesHombres}
            onChange={(e) => handleField('centroEstudiantesHombres', Number(e.target.value) || 0)}
          />
          <Input
            label={getFieldLabel('centroEstudiantesMujeres', 'Estudiantes mujeres')}
            type="number"
            min={0}
            value={form.centroEstudiantesMujeres}
            onChange={(e) => handleField('centroEstudiantesMujeres', Number(e.target.value) || 0)}
          />
          <Input
            label={getFieldLabel('centroEstudiantesMigrantes', 'Estudiantes migrantes')}
            type="number"
            min={0}
            value={form.centroEstudiantesMigrantes}
            onChange={(e) => handleField('centroEstudiantesMigrantes', Number(e.target.value) || 0)}
          />
        </div>
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Participacion y coordinacion</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Select
            label={getFieldLabel('centroUltimoGalardonEstrellas', 'Ultimo galardon obtenido')}
            value={form.centroUltimoGalardonEstrellas}
            onChange={(e) => handleField('centroUltimoGalardonEstrellas', e.target.value)}
            options={getFieldChoices('centroUltimoGalardonEstrellas', data.choiceSets.ultimo_galardon_estrellas)}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('centroUltimoAnioParticipacion', 'Ultimo ano de participacion')}
            value={form.centroUltimoAnioParticipacion}
            onChange={(e) => handleField('centroUltimoAnioParticipacion', e.target.value)}
            options={getFieldChoices('centroUltimoAnioParticipacion', data.choiceSets.ultimo_anio_participacion)}
            placeholder="Selecciona una opcion"
          />
          {form.centroUltimoAnioParticipacion === 'otro' && (
            <Input
              label={getFieldLabel('centroUltimoAnioParticipacionOtro', 'Otro ano de participacion')}
              type="number"
              value={form.centroUltimoAnioParticipacionOtro}
              onChange={(e) => handleField('centroUltimoAnioParticipacionOtro', e.target.value)}
            />
          )}
          <Select
            label={getFieldLabel('coordinadorCargo', 'Cargo de coordinacion PBAE')}
            value={form.coordinadorCargo}
            onChange={(e) => handleField('coordinadorCargo', e.target.value)}
            options={getFieldChoices('coordinadorCargo', data.choiceSets.coordinador_cargo)}
            placeholder="Selecciona una opcion"
          />
          <Input
            label={getFieldLabel('coordinadorNombre', 'Nombre de coordinacion PBAE')}
            value={form.coordinadorNombre}
            onChange={(e) => handleField('coordinadorNombre', e.target.value)}
          />
          <Input
            label={getFieldLabel('coordinadorCelular', 'Celular de coordinacion')}
            value={form.coordinadorCelular}
            onChange={(e) => handleField('coordinadorCelular', e.target.value)}
          />
          <Input
            label={getFieldLabel('comiteEstudiantes', 'Cantidad de estudiantes en comite')}
            type="number"
            min={0}
            value={form.comiteEstudiantes}
            onChange={(e) => handleField('comiteEstudiantes', Number(e.target.value) || 0)}
          />
          <Select
            label={getFieldLabel('inscripcionAnterior', 'Inscripcion anterior')}
            value={form.inscripcionAnterior}
            onChange={(e) => handleField('inscripcionAnterior', e.target.value)}
            options={inscripcionAnteriorOptions}
            placeholder="Selecciona una opcion"
          />
          <Select
            label={getFieldLabel('metaEstrellas', 'Meta de estrellas')}
            value={form.metaEstrellas}
            onChange={(e) => handleField('metaEstrellas', e.target.value)}
            options={metaEstrellasOptions}
            placeholder="Selecciona una opcion"
          />
        </div>
      </Card>

      <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Persona responsable del centro educativo</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--gnf-space-4)' }}>
          <Input
            label={getFieldLabel('representanteNombre', 'Nombre completo')}
            value={form.representanteNombre}
            onChange={(e) => handleField('representanteNombre', e.target.value)}
          />
          <Input
            label={getFieldLabel('representanteCargo', 'Cargo')}
            value={form.representanteCargo}
            onChange={(e) => handleField('representanteCargo', e.target.value)}
          />
          <Input
            label={getFieldLabel('representanteTelefono', 'Telefono')}
            value={form.representanteTelefono}
            onChange={(e) => handleField('representanteTelefono', e.target.value)}
          />
          <Input
            label={getFieldLabel('representanteEmail', 'Correo electronico')}
            type="email"
            value={form.representanteEmail}
            onChange={(e) => handleField('representanteEmail', e.target.value)}
          />
          <Input
            label={getFieldLabel('representanteEmailConfirm', 'Confirmar correo electronico')}
            type="email"
            value={form.representanteEmailConfirm}
            onChange={(e) => handleField('representanteEmailConfirm', e.target.value)}
          />
        </div>
      </Card>

      {confirmacionOptions.length > 0 && (
        <Card style={{ marginBottom: 'var(--gnf-space-6)' }}>
          <h3 style={{ marginBottom: 'var(--gnf-space-2)' }}>
            {getFieldLabel('docenteConfirmaciones', 'Confirmaciones')}
          </h3>
          {getFieldInstructions('docenteConfirmaciones') && (
            <p style={{ margin: '0 0 var(--gnf-space-4)', color: 'var(--gnf-muted)' }}>
              {getFieldInstructions('docenteConfirmaciones')}
            </p>
          )}
          <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
            {confirmacionOptions.map((option) => (
              <Checkbox
                key={option.value}
                label={option.label}
                checked={(form.docenteConfirmaciones ?? []).includes(option.value)}
                onChange={(event) => toggleConfirmation(option.value, event.target.checked)}
              />
            ))}
          </div>
        </Card>
      )}

      <div style={{ marginBottom: 'var(--gnf-space-4)' }}>
        <h3 style={{ marginBottom: 'var(--gnf-space-2)' }}>Seleccion de eco retos</h3>
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
        <Button variant="primary" icon={<Save size={16} />} loading={saveState === 'saving'} onClick={handleSave}>
          Guardar ahora
        </Button>
      </div>
    </div>
  );
}
