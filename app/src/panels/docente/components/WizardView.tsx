import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { retosApi } from '@/api/retos';
import { WpFormsEmbed } from '@/components/domain/WpFormsEmbed';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { trackClientEvent } from '@/utils/analytics';
import { CheckCircle2, ChevronLeft, ChevronRight, Send, RotateCcw } from 'lucide-react';
import type { Estado } from '@/types';

interface WizardViewProps {
  year: number;
  initialRetoId?: number;
}

const SUBMIT_STEP = -1;

export function WizardView({ year, initialRetoId }: WizardViewProps) {
  const queryClient = useQueryClient();

  const { data: steps, isLoading } = useQuery({
    queryKey: ['wizard-steps', year],
    queryFn: () => retosApi.getWizardSteps(year),
  });

  const [currentIndex, setCurrentIndex] = useState(0);

  useEffect(() => {
    if (!steps || steps.length === 0) return;

    if (initialRetoId) {
      const index = steps.findIndex((step) => step.retoId === initialRetoId);
      setCurrentIndex(index >= 0 ? index : 0);
      return;
    }

    setCurrentIndex((previous) => (previous === SUBMIT_STEP ? SUBMIT_STEP : Math.min(previous, steps.length - 1)));
  }, [initialRetoId, steps]);

  const finalizeMut = useMutation({
    mutationFn: (retoId: number) => retosApi.finalizeReto(retoId, year),
    onSuccess: (_data, retoId) => {
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      trackClientEvent('reto_finalize_click', {
        panel: 'docente',
        page: 'formularios',
        year,
        retoId,
      });
    },
  });

  const reopenMut = useMutation({
    mutationFn: (retoId: number) => retosApi.reopenReto(retoId, year),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
    },
  });

  const submitAllMut = useMutation({
    mutationFn: () => retosApi.submitAll(year),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      trackClientEvent('annual_submit_click', {
        panel: 'docente',
        page: 'formularios',
        year,
      });
    },
  });

  if (isLoading || !steps) return <Spinner label="Cargando eco retos..." />;
  if (steps.length === 0) return <p>No hay retos disponibles para este centro educativo.</p>;

  const safeSteps = steps;
  const isOnSubmitStep = currentIndex === SUBMIT_STEP;
  const step = isOnSubmitStep ? null : safeSteps[currentIndex];
  const allComplete = safeSteps.every((item) => ['completo', 'enviado', 'aprobado'].includes(item.estado));
  const allSent = safeSteps.every((item) => ['enviado', 'aprobado'].includes(item.estado));

  function renderStepIndicators() {
    return (
      <div
        style={{
          display: 'flex',
          gap: 'var(--gnf-space-2)',
          marginBottom: 'var(--gnf-space-6)',
          overflowX: 'auto',
          padding: 'var(--gnf-space-2) var(--gnf-space-1) var(--gnf-space-3)',
          WebkitOverflowScrolling: 'touch',
          scrollbarWidth: 'thin',
          minWidth: 0,
          maxWidth: '100%',
        }}
      >
        {safeSteps.map((item, index) => (
          <button
            key={item.retoId}
            onClick={() => setCurrentIndex(index)}
            style={{
              padding: 'var(--gnf-space-2) var(--gnf-space-4)',
              borderRadius: 'var(--gnf-radius-full)',
              border: !isOnSubmitStep && index === currentIndex ? '2px solid var(--gnf-forest)' : '1px solid var(--gnf-border)',
              background: !isOnSubmitStep && index === currentIndex ? 'var(--gnf-forest)' : 'var(--gnf-white)',
              color: !isOnSubmitStep && index === currentIndex ? 'var(--gnf-white)' : 'var(--gnf-gray-600)',
              fontSize: '0.8125rem',
              fontWeight: 600,
              fontFamily: 'var(--gnf-font-body)',
              cursor: 'pointer',
              whiteSpace: 'nowrap',
              flex: '0 0 auto',
            }}
          >
            {index + 1}. {item.retoTitulo}
          </button>
        ))}

        <button
          onClick={() => setCurrentIndex(SUBMIT_STEP)}
          style={{
            padding: 'var(--gnf-space-2) var(--gnf-space-4)',
            borderRadius: 'var(--gnf-radius-full)',
            border: isOnSubmitStep ? '2px solid var(--gnf-forest)' : '1px solid var(--gnf-border)',
            background: isOnSubmitStep ? 'var(--gnf-forest)' : 'var(--gnf-white)',
            color: isOnSubmitStep ? 'var(--gnf-white)' : 'var(--gnf-gray-600)',
            fontSize: '0.8125rem',
            fontWeight: 600,
            fontFamily: 'var(--gnf-font-body)',
            cursor: 'pointer',
            whiteSpace: 'nowrap',
            flex: '0 0 auto',
          }}
        >
          Enviar
        </button>
      </div>
    );
  }

  function renderStatusBanner(item: NonNullable<typeof step>) {
    const estado = item.estado as Estado;

    if (['en_progreso', 'no_iniciado'].includes(estado)) {
      return (
        <div
          style={{
            background: 'var(--gnf-white)',
            borderRadius: 'var(--gnf-radius)',
            padding: 'var(--gnf-space-5)',
            marginTop: 'var(--gnf-space-6)',
            border: '1px solid var(--gnf-border)',
            borderLeft: '4px solid var(--gnf-ocean)',
            boxShadow: 'var(--gnf-shadow-sm)',
            display: 'none'
          }}
        >
          <p style={{ margin: '0 0 12px', color: 'var(--gnf-gray-800)' }}>
            <strong>Cuando este reto ya esté listo, marcalo como completo.</strong>
            <br />
            El guardado es automático. Finalizar solo indica que el centro educativo terminó este reto y puede pasar a revisión final. De lo contrario, será revisado igualmente al finalizar el ciclo lectivo
          </p>
          <Button
            variant="primary"
            icon={<CheckCircle2 size={16} />}
            loading={finalizeMut.isPending}
            onClick={() => finalizeMut.mutate(item.retoId)}
          >
            Marcar reto como completo
          </Button>
          {finalizeMut.isError && (
            <p style={{ color: 'var(--gnf-coral)', marginTop: 8, fontSize: '0.875rem' }}>
              No se pudo finalizar este reto. Intenta de nuevo.
            </p>
          )}
        </div>
      );
    }

    if (estado === 'completo') {
      return (
        <Alert variant="success" title="Reto listo para envio final">
          Este reto ya quedó completo. Puedes continuar con los demas y luego enviar toda la participación para revision.
        </Alert>
      );
    }

    if (estado === 'enviado') {
      return (
        <Alert variant="info" title="Reto enviado para revision">
          Este reto ya fue incluido en el envío final y ahora está pendiente de revision por supervision y comite.
        </Alert>
      );
    }

    if (estado === 'aprobado') {
      return (
        <Alert variant="success" title="Reto aprobado">
          Este reto ya fue aprobado y acumuló {item.puntaje} eco puntos.
        </Alert>
      );
    }

    if (estado === 'correccion') {
      return (
        <div
          style={{
            background: 'var(--gnf-white)',
            borderRadius: 'var(--gnf-radius)',
            padding: 'var(--gnf-space-5)',
            marginTop: 'var(--gnf-space-6)',
            border: '1px solid var(--gnf-border)',
            borderLeft: '4px solid var(--gnf-coral)',
            boxShadow: 'var(--gnf-shadow-sm)',
          }}
        >
          <p style={{ margin: '0 0 12px', color: 'var(--gnf-gray-800)' }}>
            <strong>Se solicitaron ajustes.</strong>
            <br />
            Revisa las observaciones y vuelve a abrir este reto para corregirlo.
          </p>
          <Button
            variant="outline"
            icon={<RotateCcw size={16} />}
            loading={reopenMut.isPending}
            onClick={() => reopenMut.mutate(item.retoId)}
          >
            Reabrir reto
          </Button>
          {reopenMut.isError && (
            <p style={{ color: 'var(--gnf-coral)', marginTop: 8, fontSize: '0.875rem' }}>
              No se pudo reabrir este reto. Intenta de nuevo.
            </p>
          )}
        </div>
      );
    }

    return null;
  }

  function renderSubmitPanel() {
    return (
      <div>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>
          {allComplete ? 'El centro educativo ya puede enviar su participación' : 'Aun faltan retos por completar'}
        </h3>

        {!allComplete && (
          <Alert variant="warning">
            Completa todos los eco retos matriculados antes de enviar la participación anual.
          </Alert>
        )}

        {allSent && (
          <Alert variant="info">
            La participación ya fue enviada. A partir de aqui solo queda esperar la revision regional.
          </Alert>
        )}

        <div
          style={{
            display: 'grid',
            gap: 'var(--gnf-space-3)',
            marginTop: 'var(--gnf-space-5)',
          }}
        >
          {safeSteps.map((item) => {
            const isComplete = ['completo', 'enviado', 'aprobado'].includes(item.estado);
            return (
              <div
                key={item.retoId}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 'var(--gnf-space-3)',
                  padding: 'var(--gnf-space-3) var(--gnf-space-4)',
                  borderRadius: 'var(--gnf-radius)',
                  background: 'var(--gnf-white)',
                  border: `1px solid ${isComplete ? 'rgba(34, 197, 94, 0.28)' : 'var(--gnf-border)'}`,
                  boxShadow: isComplete ? 'var(--gnf-shadow-sm)' : 'none',
                }}
              >
                {item.retoIconUrl ? (
                  <img
                    src={item.retoIconUrl}
                    alt=""
                    style={{ width: 36, height: 36, objectFit: 'contain', borderRadius: 6 }}
                  />
                ) : (
                  <div
                    style={{
                      width: 36,
                      height: 36,
                      borderRadius: 6,
                      background: item.retoColor || 'var(--gnf-gray-200)',
                    }}
                  />
                )}
                <div style={{ flex: 1 }}>
                  <strong style={{ fontSize: '0.875rem' }}>{item.retoTitulo}</strong>
                  <div style={{ fontSize: '0.8125rem', opacity: 0.8 }}>
                    <StatusBadge estado={item.estado as Estado} size="sm" />
                  </div>
                </div>
                {isComplete && <CheckCircle2 size={20} style={{ color: '#22c55e', flexShrink: 0 }} />}
              </div>
            );
          })}
        </div>

        {allComplete && !allSent && (
          <div style={{ marginTop: 'var(--gnf-space-6)', textAlign: 'center' }}>
            <Button
              size="lg"
              icon={<Send size={18} />}
              loading={submitAllMut.isPending}
              onClick={() => {
                if (
                  window.confirm(
                    'Al enviar, la plataforma notificara a supervision y comite regional. Quieres continuar?',
                  )
                ) {
                  submitAllMut.mutate();
                }
              }}
            >
              Enviar participación anual
            </Button>
            {submitAllMut.isError && (
              <p style={{ color: 'var(--gnf-coral)', marginTop: 8, fontSize: '0.875rem' }}>
                No se pudo enviar la participación. Intenta de nuevo.
              </p>
            )}
          </div>
        )}

        {!allComplete && (
          <p
            style={{
              marginTop: 'var(--gnf-space-5)',
              color: 'var(--gnf-muted)',
              fontSize: '0.875rem',
            }}
          >
            Regresa a los pasos anteriores para completar los retos pendientes.
          </p>
        )}
      </div>
    );
  }

  return (
    <div>
      {renderStepIndicators()}

      {isOnSubmitStep ? (
        renderSubmitPanel()
      ) : step ? (
        <>
          <WpFormsEmbed key={`${year}-${step.retoId}`} retoId={step.retoId} year={year} />

          <div style={{ marginTop: 'var(--gnf-space-4)' }}>
            {renderStatusBanner(step)}
          </div>
        </>
      ) : null}

      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          marginTop: 'var(--gnf-space-6)',
        }}
      >
        <Button
          variant="ghost"
          icon={<ChevronLeft size={16} />}
          onClick={() =>
            setCurrentIndex((index) => {
              if (index === SUBMIT_STEP) return safeSteps.length - 1;
              return Math.max(0, index - 1);
            })
          }
          disabled={currentIndex === 0}
        >
          Anterior
        </Button>
        <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)', alignSelf: 'center' }}>
          {isOnSubmitStep ? 'Enviar participación' : `${currentIndex + 1} de ${safeSteps.length}`}
        </span>
        <Button
          variant="ghost"
          onClick={() =>
            setCurrentIndex((index) => {
              if (index === safeSteps.length - 1) return SUBMIT_STEP;
              return Math.min(safeSteps.length - 1, index + 1);
            })
          }
          disabled={isOnSubmitStep}
        >
          Siguiente
          <ChevronRight size={16} />
        </Button>
      </div>
    </div>
  );
}
