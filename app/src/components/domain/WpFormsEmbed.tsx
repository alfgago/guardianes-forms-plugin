import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { retosApi, type AutosaveFieldPayload } from '@/api/retos';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { trackClientEvent } from '@/utils/analytics';
import type { Evidencia, RetoEntry } from '@/types';
import { CheckCircle2, ExternalLink, Save } from 'lucide-react';

interface WpFormsEmbedProps {
  retoId: number;
  year: number;
}

function getFieldId(fieldEl: Element): string | null {
  const id = fieldEl.getAttribute('id') ?? '';
  const match = id.match(/field_(\d+)/);
  return match?.[1] ?? null;
}

function getFieldInputs(fieldEl: Element, fieldId: string): Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement> {
  return Array.from(
    fieldEl.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(
      `[name^="wpforms[fields][${fieldId}]"]`,
    ),
  );
}

function getFieldLabel(fieldEl: Element, fieldId: string): string {
  const label = fieldEl.querySelector('.wpforms-field-label')?.textContent?.trim();
  return label || `Campo ${fieldId}`;
}

function getFieldType(fieldEl: Element, inputs: Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>): string {
  const className = fieldEl.className;

  if (className.includes('wpforms-field-file-upload')) return 'file-upload';
  if (className.includes('wpforms-field-checkbox')) return 'checkbox';
  if (className.includes('wpforms-field-radio')) return 'radio';
  if (className.includes('wpforms-field-textarea')) return 'textarea';
  if (className.includes('wpforms-field-select')) return 'select';
  if (className.includes('wpforms-field-email')) return 'email';

  const firstInput = inputs[0];
  if (!firstInput) return 'text';
  if (firstInput instanceof HTMLTextAreaElement) return 'textarea';
  if (firstInput instanceof HTMLSelectElement) return 'select';
  return firstInput.type || 'text';
}

function getFieldValue(fieldEl: HTMLElement, type: string, inputs: Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>): string | string[] {
  const isVisible = fieldEl.offsetParent !== null || window.getComputedStyle(fieldEl).display !== 'none';
  if (!isVisible) {
    return type === 'checkbox' ? [] : '';
  }

  if (type === 'file-upload') {
    const hiddenUploads = inputs
      .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
      .filter((input) => input.type === 'hidden' && input.value);

    if (hiddenUploads.length > 1) {
      return hiddenUploads.map((input) => input.value);
    }

    return hiddenUploads[0]?.value ?? '';
  }

  if (type === 'checkbox') {
    return inputs
      .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
      .filter((input) => input.checked)
      .map((input) => normalizeBinaryValue(input.value));
  }

  if (type === 'radio') {
    const checked = inputs
      .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
      .find((input) => input.checked);
    return normalizeBinaryValue(checked?.value ?? '');
  }

  const select = inputs[0];
  if (select instanceof HTMLSelectElement && select.multiple) {
    return Array.from(select.selectedOptions).map((option) => option.value);
  }

  return select ? normalizeBinaryValue(select.value) : '';
}

function buildFieldSnapshot(form: HTMLFormElement): Record<string, AutosaveFieldPayload> {
  const fields = Array.from(form.querySelectorAll<HTMLElement>('.wpforms-field'));
  const payload: Record<string, AutosaveFieldPayload> = {};

  fields.forEach((fieldEl) => {
    const fieldId = getFieldId(fieldEl);
    if (!fieldId) return;

    const inputs = getFieldInputs(fieldEl, fieldId);
    if (inputs.length === 0) return;

    const type = getFieldType(fieldEl, inputs);
    payload[fieldId] = {
      type,
      name: getFieldLabel(fieldEl, fieldId),
      value: getFieldValue(fieldEl, type, inputs),
    };
  });

  return payload;
}

function hydrateSavedValues(form: HTMLFormElement, savedValues: Record<string, string | string[]>) {
  const fieldIds = Object.keys(savedValues).sort((a, b) => Number(a) - Number(b));

  fieldIds.forEach((fieldId) => {
    const fieldEl = form.querySelector<HTMLElement>(`[id*="field_${fieldId}"]`);
    if (!fieldEl) return;

    const inputs = getFieldInputs(fieldEl, fieldId);
    if (inputs.length === 0) return;

    const rawValue = savedValues[fieldId];
    const values = (Array.isArray(rawValue) ? rawValue : [rawValue]).map((value) => normalizeBinaryValue(String(value)));
    const type = getFieldType(fieldEl, inputs);

    if (type === 'checkbox') {
      inputs
        .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
        .forEach((input) => {
          input.checked = values.includes(normalizeBinaryValue(input.value));
        });
    } else if (type === 'radio') {
      inputs
        .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
        .forEach((input) => {
          input.checked = values[0] === normalizeBinaryValue(input.value);
        });
    } else if (type === 'select') {
      const select = inputs[0];
      if (select instanceof HTMLSelectElement) {
        Array.from(select.options).forEach((option) => {
          option.selected = values.includes(normalizeBinaryValue(option.value));
        });
      }
    } else if (type !== 'file-upload') {
      const input = inputs[0];
      if (input) {
        input.value = values[0] ?? '';
      }
    }

    inputs.forEach((input) => {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
}

function translateCommonWpformsCopy(container: HTMLElement) {
  const replacements = new Map<string, string>([
    ['Drag and drop files here or click to upload.', 'Arrastra los archivos aqui o haz clic para subirlos.'],
    ['Drop files here or click to upload.', 'Arrastra los archivos aqui o haz clic para subirlos.'],
    ['Click or drag a file to this area to upload.', 'Haz clic o arrastra un archivo a esta area para subirlo.'],
    ['Choose File', 'Seleccionar archivo'],
    ['No file chosen', 'Ningun archivo seleccionado'],
  ]);

  container.querySelectorAll<HTMLElement>('*').forEach((element) => {
    if (element.childNodes.length !== 1 || element.childNodes[0]?.nodeType !== Node.TEXT_NODE) {
      return;
    }

    const current = element.textContent?.trim() ?? '';
    const replacement = replacements.get(current);
    if (replacement) {
      element.textContent = replacement;
    }
  });
}

function normalizeBinaryValue(value: string): string {
  const normalized = value
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

  if (normalized === 'si') return 'si';
  if (normalized === 'no') return 'no';
  return value;
}

function formatBinaryLabel(value: string): string {
  const normalized = normalizeBinaryValue(value);
  if (normalized === 'si') return 'Sí';
  if (normalized === 'no') return 'No';
  return value;
}

function normalizeWpformsBinaryChoices(container: HTMLElement) {
  container.querySelectorAll<HTMLLabelElement>('.wpforms-field-radio li label, .wpforms-field-checkbox li label').forEach((label) => {
    const input = label.parentElement?.querySelector<HTMLInputElement>('input');
    const rawLabel = label.textContent?.trim() ?? '';
    const normalizedValue = normalizeBinaryValue(input?.value || rawLabel);

    if (normalizedValue === 'si' || normalizedValue === 'no') {
      label.textContent = formatBinaryLabel(rawLabel || normalizedValue);
    }
  });

  container.querySelectorAll<HTMLOptionElement>('select option').forEach((option) => {
    const normalizedValue = normalizeBinaryValue(option.value || option.textContent || '');
    if (normalizedValue === 'si' || normalizedValue === 'no') {
      option.textContent = formatBinaryLabel(option.textContent || normalizedValue);
    }
  });

  container.querySelectorAll<HTMLElement>('.wpforms-field-label, .wpforms-field-description').forEach((element) => {
    const text = element.textContent ?? '';
    element.textContent = text.replace(/\(si\/no\)/gi, '(Sí/No)');
  });
}

function extractFieldValues(snapshot: Record<string, AutosaveFieldPayload>) {
  return Object.fromEntries(Object.entries(snapshot).map(([fieldId, field]) => [fieldId, field.value]));
}

function hasCompletedValue(value: string | string[] | undefined, tipo: string): boolean {
  if (Array.isArray(value)) {
    return value.some((item) => String(item).trim() !== '');
  }

  const normalized = String(value ?? '').trim();
  if (normalized === '') {
    return false;
  }

  if (tipo === 'file-upload') {
    return normalized !== '' && normalized !== '[]';
  }

  return true;
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

export function WpFormsEmbed({ retoId, year }: WpFormsEmbedProps) {
  const queryClient = useQueryClient();
  const containerRef = useRef<HTMLDivElement>(null);
  const formRef = useRef<HTMLFormElement | null>(null);
  const saveTimerRef = useRef<number | null>(null);
  const hydratingRef = useRef(false);
  const queuedSaveRef = useRef(false);
  const lastSnapshotRef = useRef('');
  const mountedRef = useRef(false);
  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
  const [currentEntry, setCurrentEntry] = useState<RetoEntry | null>(null);
  const [fieldValues, setFieldValues] = useState<Record<string, string | string[]>>({});

  const { data, isLoading, error } = useQuery({
    queryKey: ['wpforms-html', retoId, year],
    queryFn: () => retosApi.getFormHtml(retoId, year),
    staleTime: 0,
  });

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    setLastSavedAt(data?.savedAt ?? null);
    setCurrentEntry(data?.entry ?? null);
    setFieldValues(data?.savedValues ?? {});
    setSaveState('idle');
  }, [data?.entry, data?.savedAt, data?.savedValues]);

  const syncFieldValues = useCallback((form: HTMLFormElement) => {
    const snapshot = buildFieldSnapshot(form);
    setFieldValues(extractFieldValues(snapshot));
    return snapshot;
  }, []);

  const autosaveMutation = useMutation({
    mutationFn: (fields: Record<string, AutosaveFieldPayload>) => {
      if (!data) {
        throw new Error('No hay formulario cargado.');
      }
      return retosApi.autosaveReto(retoId, year, data.formId, fields);
    },
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });

      if (!mountedRef.current) return;

      setLastSavedAt(response.savedAt ?? new Date().toISOString());
      setCurrentEntry(response.entry);
      setSaveState('saved');
      trackClientEvent('form_autosave', {
        panel: 'docente',
        page: 'formularios',
        year,
        retoId,
        formId: data?.formId ?? 0,
        status: response.entry?.estado ?? '',
      });
    },
    onError: () => {
      if (mountedRef.current) {
        setSaveState('error');
      }
    },
  });

  const triggerAutosave = useCallback(async (mode: 'auto' | 'manual') => {
    const form = formRef.current;
    if (!form) return;

    const fields = syncFieldValues(form);
    const serialized = JSON.stringify(fields);

    if (mode === 'auto' && serialized === lastSnapshotRef.current) {
      return;
    }

    if (autosaveMutation.isPending) {
      queuedSaveRef.current = true;
      return;
    }

    if (mountedRef.current) {
      setSaveState('saving');
    }

    try {
      await autosaveMutation.mutateAsync(fields);
      lastSnapshotRef.current = serialized;
    } finally {
      if (queuedSaveRef.current) {
        queuedSaveRef.current = false;
        void triggerAutosave('auto');
      }
    }
  }, [autosaveMutation, data, retoId, syncFieldValues, year]);

  const scheduleAutosave = useCallback((delay = 700) => {
    if (hydratingRef.current) return;

    if (saveTimerRef.current) {
      window.clearTimeout(saveTimerRef.current);
    }

    saveTimerRef.current = window.setTimeout(() => {
      void triggerAutosave('auto');
    }, delay);
  }, [triggerAutosave]);

  useEffect(() => {
    if (!data?.html || !containerRef.current) return;

    const container = containerRef.current;
    container.innerHTML = data.html;

    const runWpformsInit = () => {
      const wpforms = (window as unknown as Record<string, unknown>).wpforms as { init?: () => void } | undefined;
      if (wpforms?.init) {
        wpforms.init();
      }

      normalizeWpformsBinaryChoices(container);
      translateCommonWpformsCopy(container);
    };

    const scripts = container.querySelectorAll('script');
    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');
      if (oldScript.src) {
        newScript.src = oldScript.src;
        newScript.addEventListener('load', () => {
          window.setTimeout(runWpformsInit, 0);
        });
      } else {
        newScript.textContent = oldScript.textContent;
      }
      oldScript.parentNode?.replaceChild(newScript, oldScript);
    });

    runWpformsInit();
    window.setTimeout(runWpformsInit, 120);
    window.setTimeout(runWpformsInit, 400);

    const form = container.querySelector('form');
    if (!(form instanceof HTMLFormElement)) {
      formRef.current = null;
      return;
    }

    formRef.current = form;
    hydratingRef.current = true;

    const submitContainer = form.querySelector<HTMLElement>('.wpforms-submit-container');
    if (submitContainer) {
      submitContainer.style.display = 'none';
    }

    if (data.savedValues && Object.keys(data.savedValues).length > 0) {
      hydrateSavedValues(form, data.savedValues);
    }

    lastSnapshotRef.current = JSON.stringify(syncFieldValues(form));
    window.setTimeout(() => {
      hydratingRef.current = false;
    }, 250);

    const handleInput = () => {
      syncFieldValues(form);
      scheduleAutosave(650);
    };
    const handleChange = (event: Event) => {
      const target = event.target;
      if (target instanceof HTMLInputElement && target.type === 'file') {
        syncFieldValues(form);
        scheduleAutosave(1200);
        window.setTimeout(() => {
          syncFieldValues(form);
          scheduleAutosave(0);
        }, 2200);
        return;
      }
      syncFieldValues(form);
      scheduleAutosave(650);
    };
    const handleSubmit = (event: Event) => {
      event.preventDefault();
      event.stopPropagation();
      void triggerAutosave('manual');
    };

    form.addEventListener('input', handleInput);
    form.addEventListener('change', handleChange);
    form.addEventListener('submit', handleSubmit, true);

    const observer = new MutationObserver(() => {
      if (!hydratingRef.current) {
        syncFieldValues(form);
        scheduleAutosave(850);
      }
    });
    observer.observe(form, { childList: true, subtree: true, attributes: true, attributeFilter: ['value', 'style', 'class'] });

    return () => {
      form.removeEventListener('input', handleInput);
      form.removeEventListener('change', handleChange);
      form.removeEventListener('submit', handleSubmit, true);
      observer.disconnect();

      if (saveTimerRef.current) {
        window.clearTimeout(saveTimerRef.current);
        saveTimerRef.current = null;
        void triggerAutosave('manual');
      }
    };
  }, [data?.html, data?.savedValues, scheduleAutosave, syncFieldValues, triggerAutosave]);

  const saveStatusLabel = useMemo(() => {
    if (saveState === 'saving') return 'Guardando cambios...';
    if (saveState === 'saved' && lastSavedAt) return `Guardado automatico: ${formatSavedStamp(lastSavedAt)}`;
    if (saveState === 'error') return 'No se pudo guardar. Revisa la conexion y vuelve a intentarlo.';
    return 'El progreso se guarda automaticamente, incluso cuando subes evidencias.';
  }, [lastSavedAt, saveState]);

  const evidenceList = (currentEntry?.evidencias ?? []) as Evidencia[];
  const completedFieldIds = useMemo(() => {
    const ids = new Set<string>();

    data?.fieldPoints.forEach((fieldPoint) => {
      if (hasCompletedValue(fieldValues[String(fieldPoint.fieldId)], fieldPoint.tipo)) {
        ids.add(String(fieldPoint.fieldId));
      }
    });

    evidenceList.forEach((item) => {
      const fieldId = String(item.field_id ?? '');
      if (fieldId) {
        ids.add(fieldId);
      }
    });

    return ids;
  }, [data?.fieldPoints, evidenceList, fieldValues]);

  if (isLoading) return <Spinner label="Cargando formulario..." />;
  if (error || !data) return <Alert variant="error">Error al cargar el formulario.</Alert>;

  return (
    <div>
      <style>{`
        .gnf-wpforms-shell .wpforms-container-full,
        .gnf-wpforms-shell .wpforms-form {
          margin: 0;
        }

        .gnf-wpforms-shell .wpforms-field {
          border: 1px solid var(--gnf-border);
          border-radius: var(--gnf-radius);
          background: #fff;
          padding: var(--gnf-space-4);
          margin-bottom: var(--gnf-space-4);
          box-shadow: var(--gnf-shadow-sm);
        }

        .gnf-wpforms-shell .wpforms-field-label {
          font-weight: 700;
          color: var(--gnf-gray-900);
          margin-bottom: var(--gnf-space-3);
        }

        .gnf-wpforms-shell input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
        .gnf-wpforms-shell textarea,
        .gnf-wpforms-shell select {
          width: 100%;
          border-radius: 12px;
          border: 1.5px solid var(--gnf-field-border);
          padding: 12px 14px;
          background: #fff;
          transition: border-color var(--gnf-transition-fast), box-shadow var(--gnf-transition-fast);
        }

        .gnf-wpforms-shell input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):focus,
        .gnf-wpforms-shell textarea:focus,
        .gnf-wpforms-shell select:focus {
          outline: none;
          border-color: var(--gnf-ocean);
          box-shadow: 0 0 0 3px rgba(30, 95, 138, 0.12);
        }

        .gnf-wpforms-shell textarea {
          min-height: 140px;
        }

        .gnf-wpforms-shell .wpforms-field-description {
          color: var(--gnf-muted);
          margin-top: var(--gnf-space-2);
        }

        .gnf-wpforms-shell .wpforms-field-file-upload .wpforms-uploader {
          border-radius: var(--gnf-radius);
          border: 1px dashed var(--gnf-ocean);
          background: linear-gradient(135deg, #f3fbff 0%, #eefbf5 100%);
        }
      `}</style>

      <Card padding="0" style={{ overflow: 'hidden', boxShadow: 'var(--gnf-shadow-md)' }}>
        <div
          style={{
            padding: 'var(--gnf-space-6)',
            background: 'linear-gradient(135deg, rgba(45,138,95,0.11) 0%, rgba(30,95,138,0.12) 100%)',
            borderBottom: '1px solid var(--gnf-border)',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', alignItems: 'center' }}>
              {data.reto.iconUrl && (
                <img
                  src={data.reto.iconUrl}
                  alt=""
                  style={{
                    width: 72,
                    height: 72,
                    objectFit: 'contain',
                    borderRadius: 'var(--gnf-radius)',
                    background: '#fff',
                    padding: 'var(--gnf-space-2)',
                    boxShadow: 'var(--gnf-shadow-sm)',
                  }}
                />
              )}
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-2)' }}>
                  {currentEntry && <StatusBadge estado={currentEntry.estado} />}
                  <span
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '6px',
                      padding: '6px 12px',
                      borderRadius: 'var(--gnf-radius-full)',
                      background: '#fff',
                      fontSize: '0.8125rem',
                      color: 'var(--gnf-ocean-dark)',
                    }}
                  >
                    <CheckCircle2 size={14} />
                    Hasta {data.reto.puntajeMaximo} eco puntos
                  </span>
                </div>
                <h4 style={{ margin: 0, color: data.reto.color || 'var(--gnf-forest)' }}>{data.reto.titulo}</h4>
                <p style={{ margin: 'var(--gnf-space-2) 0 0', color: 'var(--gnf-gray-600)', maxWidth: 720 }}>
                  Completa las actividades poco a poco. Todo lo que subas o respondas queda guardado y el envio final del wizard solo notifica a supervision y comite.
                </p>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', flexWrap: 'wrap' }}>
              {data.reto.pdfUrl && (
                <a href={data.reto.pdfUrl} target="_blank" rel="noopener noreferrer" style={{ textDecoration: 'none' }}>
                  <Button variant="ghost" size="sm" icon={<ExternalLink size={14} />}>
                    Ver guia
                  </Button>
                </a>
              )}
              <Button
                variant="outline"
                size="sm"
                icon={<Save size={14} />}
                loading={autosaveMutation.isPending}
                onClick={() => void triggerAutosave('manual')}
              >
                Guardar ahora
              </Button>
            </div>
          </div>

          <div style={{ marginTop: 'var(--gnf-space-4)' }}>
            <Alert variant={saveState === 'error' ? 'error' : saveState === 'saved' ? 'success' : 'info'}>
              {saveStatusLabel}
            </Alert>
          </div>
        </div>

        <div style={{ padding: 'var(--gnf-space-6)' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--gnf-space-6)', alignItems: 'flex-start' }}>
            <div style={{ flex: '999 1 620px', minWidth: 0 }}>
              <div className="gnf-wpforms-shell" ref={containerRef} />
            </div>

            <div style={{ flex: '320 1 300px', display: 'grid', gap: 'var(--gnf-space-4)' }}>
              <Card style={{ background: '#fbfffd' }}>
                <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Resumen del reto</h5>
                <div style={{ display: 'grid', gap: 'var(--gnf-space-2)', fontSize: '0.875rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                    <span style={{ color: 'var(--gnf-muted)' }}>Eco puntos actuales</span>
                    <strong>{currentEntry?.puntaje ?? 0}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                    <span style={{ color: 'var(--gnf-muted)' }}>Maximo del reto</span>
                    <strong>{data.reto.puntajeMaximo}</strong>
                  </div>
                  {lastSavedAt && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                      <span style={{ color: 'var(--gnf-muted)' }}>Ultimo guardado</span>
                      <strong style={{ textAlign: 'right' }}>{formatSavedStamp(lastSavedAt)}</strong>
                    </div>
                  )}
                </div>
              </Card>

              {data.fieldPoints.length > 0 && (
                <Card>
                  <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Puntos por evidencia</h5>
                  <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
                    {data.fieldPoints.map((fieldPoint) => (
                      <div
                        key={fieldPoint.fieldId}
                        style={{
                          display: 'flex',
                          justifyContent: 'space-between',
                          gap: 'var(--gnf-space-3)',
                          padding: 'var(--gnf-space-3)',
                          borderRadius: '12px',
                          border: `1px solid ${completedFieldIds.has(String(fieldPoint.fieldId)) ? 'rgba(34, 197, 94, 0.35)' : 'var(--gnf-gray-100)'}`,
                          background: completedFieldIds.has(String(fieldPoint.fieldId)) ? 'rgba(34, 197, 94, 0.08)' : 'var(--gnf-white)',
                          fontSize: '0.8125rem',
                        }}
                      >
                        <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', alignItems: 'flex-start' }}>
                          <CheckCircle2
                            size={16}
                            style={{
                              color: completedFieldIds.has(String(fieldPoint.fieldId)) ? '#22c55e' : 'var(--gnf-gray-300)',
                              flexShrink: 0,
                              marginTop: 1,
                            }}
                          />
                          <span style={{ color: 'var(--gnf-gray-700)' }}>{fieldPoint.label || `Campo ${fieldPoint.fieldId}`}</span>
                        </div>
                        <strong style={{ whiteSpace: 'nowrap', color: 'var(--gnf-forest)' }}>{fieldPoint.puntos} pts</strong>
                      </div>
                    ))}
                  </div>
                </Card>
              )}

              {evidenceList.length > 0 && (
                <Card>
                  <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Evidencias guardadas</h5>
                  <EvidenceViewer evidencias={evidenceList} />
                </Card>
              )}
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
}

