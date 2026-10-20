import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { retosApi } from '@/api/retos';
import { WpFormsEmbed } from '@/components/domain/WpFormsEmbed';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react';

interface WizardViewProps {
  year: number;
  initialRetoId?: number;
}

export function WizardView({ year, initialRetoId }: WizardViewProps) {
  const queryClient = useQueryClient();
  const { data: steps, isLoading } = useQuery({
    queryKey: ['wizard-steps', year],
    queryFn: () => retosApi.getWizardSteps(year),
  });
  const [currentIndex, setCurrentIndex] = useState(0);

  useEffect(() => {
    if (!steps?.length) return;
    if (initialRetoId) {
      const index = steps.findIndex((step) => step.retoId === initialRetoId);
      setCurrentIndex(index >= 0 ? index : 0);
      return;
    }
    setCurrentIndex((previous) => Math.min(previous, steps.length - 1));
  }, [initialRetoId, steps]);

  const reopenMut = useMutation({
    mutationFn: (retoId: number) => retosApi.reopenReto(retoId, year),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
    },
  });

  if (isLoading || !steps) return <Spinner label="Cargando eco retos..." />;
  if (steps.length === 0) return <p>No hay retos disponibles para este centro educativo.</p>;

  const activeIndex = Math.min(currentIndex, steps.length - 1);
  const step = steps[activeIndex];
  if (!step) return <p>No hay retos disponibles para este centro educativo.</p>;

  return (
    <div>
      <div
        role="navigation"
        aria-label="Eco retos"
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
        {steps.map((item, index) => (
          <button
            type="button"
            key={item.retoId}
            aria-current={index === activeIndex ? 'step' : undefined}
            onClick={() => setCurrentIndex(index)}
            style={{
              padding: 'var(--gnf-space-2) var(--gnf-space-4)',
              borderRadius: 'var(--gnf-radius-full)',
              border: index === activeIndex ? '2px solid var(--gnf-forest)' : '1px solid var(--gnf-border)',
              background: index === activeIndex ? 'var(--gnf-forest)' : 'var(--gnf-white)',
              color: index === activeIndex ? 'var(--gnf-white)' : 'var(--gnf-gray-600)',
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
      </div>

      <WpFormsEmbed key={`${year}-${step.retoId}`} retoId={step.retoId} year={year} />

      {step.estado === 'aprobado' && (
        <Alert variant="success" title="Reto aprobado">
          Este reto ya fue aprobado y acumuló {step.puntaje} eco puntos.
        </Alert>
      )}
      {step.estado === 'correccion' && (
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
            onClick={() => reopenMut.mutate(step.retoId)}
          >
            Reabrir reto
          </Button>
          {reopenMut.isError && (
            <p style={{ color: 'var(--gnf-coral)', marginTop: 8, fontSize: '0.875rem' }}>
              No se pudo reabrir este reto. Intenta de nuevo.
            </p>
          )}
        </div>
      )}

      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 'var(--gnf-space-6)' }}>
        <Button
          variant="ghost"
          icon={<ChevronLeft size={16} />}
          onClick={() => setCurrentIndex(Math.max(0, activeIndex - 1))}
          disabled={activeIndex === 0}
        >
          Anterior
        </Button>
        <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)', alignSelf: 'center' }}>
          {activeIndex + 1} de {steps.length}
        </span>
        <Button
          variant="ghost"
          onClick={() => setCurrentIndex(Math.min(steps.length - 1, activeIndex + 1))}
          disabled={activeIndex === steps.length - 1}
        >
          Siguiente
          <ChevronRight size={16} />
        </Button>
      </div>
    </div>
  );
}
