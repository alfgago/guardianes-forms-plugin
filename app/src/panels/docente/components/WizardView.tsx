import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { retosApi } from '@/api/retos';
import { WpFormsEmbed } from '@/components/domain/WpFormsEmbed';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Estado } from '@/types';

interface WizardViewProps {
  year: number;
  initialRetoId?: number;
}

export function WizardView({ year, initialRetoId }: WizardViewProps) {
  const { data: steps, isLoading } = useQuery({
    queryKey: ['wizard-steps', year],
    queryFn: () => retosApi.getWizardSteps(year),
  });

  const [currentIndex, setCurrentIndex] = useState(() => {
    if (!initialRetoId || !steps) return 0;
    const idx = steps.findIndex((s) => s.retoId === initialRetoId);
    return idx >= 0 ? idx : 0;
  });

  if (isLoading || !steps) return <Spinner label="Cargando wizard..." />;
  if (steps.length === 0) return <p>No hay retos disponibles.</p>;

  const step = steps[currentIndex]!;

  return (
    <div>
      {/* Step indicators */}
      <div style={{
        display: 'flex',
        gap: 'var(--gnf-space-2)',
        marginBottom: 'var(--gnf-space-6)',
        overflowX: 'auto',
        padding: 'var(--gnf-space-2) 0',
      }}>
        {steps.map((s, i) => (
          <button
            key={s.retoId}
            onClick={() => setCurrentIndex(i)}
            style={{
              padding: 'var(--gnf-space-2) var(--gnf-space-4)',
              borderRadius: 'var(--gnf-radius-full)',
              border: i === currentIndex ? '2px solid var(--gnf-forest)' : '1px solid var(--gnf-border)',
              background: i === currentIndex ? 'var(--gnf-forest)' : 'var(--gnf-white)',
              color: i === currentIndex ? 'var(--gnf-white)' : 'var(--gnf-gray-600)',
              fontSize: '0.8125rem',
              fontWeight: 600,
              fontFamily: 'var(--gnf-font-body)',
              cursor: 'pointer',
              whiteSpace: 'nowrap',
            }}
          >
            {i + 1}. {s.retoTitulo}
          </button>
        ))}
      </div>

      {/* Current step header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-4)' }}>
        {step.retoIconUrl && (
          <img src={step.retoIconUrl} alt="" style={{ width: 40, height: 40, borderRadius: 'var(--gnf-radius-sm)' }} />
        )}
        <div>
          <h3 style={{ margin: 0, color: step.retoColor }}>{step.retoTitulo}</h3>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginTop: 2 }}>
            <StatusBadge estado={step.estado as Estado} />
            <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
              {step.puntaje} / {step.puntajeMaximo} pts
            </span>
          </div>
        </div>
      </div>

      {/* WPForms embed */}
      <WpFormsEmbed retoId={step.retoId} year={year} />

      {/* Navigation */}
      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 'var(--gnf-space-6)' }}>
        <Button
          variant="ghost"
          icon={<ChevronLeft size={16} />}
          onClick={() => setCurrentIndex((i) => Math.max(0, i - 1))}
          disabled={currentIndex === 0}
        >
          Anterior
        </Button>
        <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)', alignSelf: 'center' }}>
          {currentIndex + 1} de {steps.length}
        </span>
        <Button
          variant="ghost"
          onClick={() => setCurrentIndex((i) => Math.min(steps.length - 1, i + 1))}
          disabled={currentIndex === steps.length - 1}
        >
          Siguiente
          <ChevronRight size={16} />
        </Button>
      </div>
    </div>
  );
}
