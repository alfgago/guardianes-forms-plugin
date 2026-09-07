import { Award, CheckCircle2, CircleAlert, Star } from 'lucide-react';
import type { AwardBundle, AwardResult } from '@/types';

interface AwardSummaryProps {
  award?: AwardBundle;
  year: number;
}

const requiredLabels: Record<string, string> = {
  agua: 'Agua',
  electricidad: 'Energía',
  residuos: 'Residuos',
};

function getNextStar(result: AwardResult) {
  return Object.entries(result.thresholds)
    .map(([stars, minimum]) => ({ stars: Number(stars), minimum: Number(minimum) }))
    .sort((a, b) => a.stars - b.stars)
    .find((level) => result.score < level.minimum);
}

function ResultColumn({ title, result, description }: { title: string; result: AwardResult; description: string }) {
  return (
    <div style={{ minWidth: 0 }}>
      <span style={{ display: 'block', color: 'var(--gnf-muted)', fontSize: '0.8125rem', fontWeight: 600 }}>{title}</span>
      <strong style={{ display: 'flex', alignItems: 'baseline', flexWrap: 'wrap', gap: 10, marginTop: 4 }}>
        <span style={{ fontSize: '1.5rem', fontVariantNumeric: 'tabular-nums' }}>{result.score} pts</span>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, color: 'var(--gnf-forest)' }}>
          {result.stars} <Star size={17} fill="var(--gnf-sun)" color="var(--gnf-sun)" aria-hidden="true" />
        </span>
      </strong>
      <span style={{ display: 'block', color: 'var(--gnf-muted)', fontSize: '0.75rem', marginTop: 4 }}>{description}</span>
    </div>
  );
}

export function AwardSummary({ award, year }: AwardSummaryProps) {
  const projected = award?.projected;
  const validated = award?.validated;
  if (!projected?.rubricLabel || !validated?.rubricLabel) return null;

  const achieved = Object.values(validated.awards ?? {}).filter((item) => item.achieved);
  const pendingAwards = Object.values(validated.awards ?? {}).filter((item) => !item.achieved && item.missing.length > 0);
  const missing = (validated.missingRequired ?? []).map((key) => requiredLabels[key] ?? key);
  const nextStar = getNextStar(projected);

  return (
    <section
      aria-label={`Galardón ${year}`}
      style={{ borderTop: '1px solid var(--gnf-border)', borderBottom: '1px solid var(--gnf-border)', padding: 'var(--gnf-space-5) 0', margin: 'var(--gnf-space-5) 0' }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginBottom: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <Award size={20} color="var(--gnf-forest)" aria-hidden="true" />
        <h3 style={{ margin: 0 }}>Estado del galardón {year}</h3>
        {award?.rollout?.mode !== 'all' && award?.rollout?.label && (
          <span style={{ border: '1px solid #d6b56b', background: '#fff8e7', color: '#7c5709', borderRadius: 4, padding: '3px 7px', fontSize: '0.75rem', fontWeight: 600 }}>
            {award.rollout.label}
          </span>
        )}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))', gap: 'var(--gnf-space-5)' }}>
        <ResultColumn title="Progreso estimado" result={projected} description="Incluye evidencia activa pendiente de revisión." />
        <ResultColumn title="Resultado validado" result={validated} description="Incluye únicamente evidencia aprobada." />
        <div>
          <span style={{ display: 'block', color: 'var(--gnf-muted)', fontSize: '0.8125rem' }}>Rúbrica aplicada</span>
          <strong style={{ display: 'block', marginTop: 4 }}>{validated.rubricLabel}</strong>
          {nextStar ? (
            <span style={{ display: 'block', color: 'var(--gnf-muted)', fontSize: '0.75rem', marginTop: 4 }}>
              Siguiente estrella: faltan {Math.max(0, nextStar.minimum - projected.score)} pts para {nextStar.stars}.
            </span>
          ) : (
            <span style={{ display: 'block', color: 'var(--gnf-muted)', fontSize: '0.75rem', marginTop: 4 }}>Nivel máximo por puntaje.</span>
          )}
        </div>
      </div>

      {missing.length > 0 && (
        <p style={{ display: 'flex', alignItems: 'flex-start', gap: 8, color: '#9a3412', margin: 'var(--gnf-space-4) 0 0' }}>
          <CircleAlert size={17} style={{ flex: '0 0 auto', marginTop: 2 }} aria-hidden="true" />
          <span><strong>Requisitos base pendientes de validación:</strong> {missing.join(', ')}.</span>
        </p>
      )}

      {achieved.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 'var(--gnf-space-4)' }} aria-label="Reconocimientos validados">
          {achieved.map((item) => (
            <span key={item.key} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 8px', border: '1px solid #b8d8ca', borderRadius: 4, color: '#166534', background: '#f0f9f4', fontSize: '0.8125rem' }}>
              <CheckCircle2 size={14} aria-hidden="true" /> {item.label}
            </span>
          ))}
        </div>
      )}

      {pendingAwards.length > 0 && (
        <details style={{ marginTop: 'var(--gnf-space-4)' }}>
          <summary style={{ cursor: 'pointer', color: 'var(--gnf-forest)', fontWeight: 600 }}>Ver requisitos de reconocimientos</summary>
          <div style={{ display: 'grid', gap: 8, marginTop: 10 }}>
            {pendingAwards.map((item) => (
              <div key={item.key} style={{ fontSize: '0.8125rem', overflowWrap: 'anywhere' }}>
                <strong>{item.label}:</strong> {item.missing.join(', ')}.
              </div>
            ))}
          </div>
        </details>
      )}
    </section>
  );
}
