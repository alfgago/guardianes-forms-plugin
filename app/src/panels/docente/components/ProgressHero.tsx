import { ProgressBar } from '@/components/ui/ProgressBar';
import { StarRating } from '@/components/ui/StarRating';
import { formatPercentage } from '@/utils/formatters';

interface ProgressHeroProps {
  metaEstrellas: number;
  estrellaFinal: number;
  anio: number;
  retosCount: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
  puntajeTotal: number;
}

export function ProgressHero({
  estrellaFinal,
  anio,
  retosCount,
  aprobados,
  enviados,
  correccion,
  enProgreso,
  puntajeTotal,
}: ProgressHeroProps) {
  const percentage = formatPercentage(aprobados, retosCount);

  return (
    <div
      style={{
        background: 'linear-gradient(135deg, var(--gnf-forest) 0%, var(--gnf-ocean-dark) 100%)',
        borderRadius: 'var(--gnf-radius-lg)',
        padding: 'var(--gnf-space-8)',
        color: 'var(--gnf-white)',
        marginBottom: 'var(--gnf-space-6)',
        boxShadow: 'var(--gnf-shadow-md)',
      }}
    >
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          flexWrap: 'wrap',
          gap: 'var(--gnf-space-4)',
        }}
      >
        <div>
          <p
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 'var(--gnf-space-2)',
              padding: '6px 12px',
              borderRadius: 'var(--gnf-radius-full)',
              background: 'rgba(255,255,255,0.14)',
              fontSize: '0.8125rem',
              marginBottom: 'var(--gnf-space-3)',
            }}
          >
            Participacion {anio}
          </p>
          <h3 style={{ color: 'var(--gnf-white)', margin: 0 }}>Eco puntos acumulados: {puntajeTotal}</h3>
          <p style={{ opacity: 0.88, margin: 'var(--gnf-space-2) 0 0', fontSize: '0.9375rem' }}>
            {retosCount} retos matriculados para este centro educativo.
          </p>
        </div>

        <div
          style={{
            minWidth: 210,
            padding: 'var(--gnf-space-4)',
            borderRadius: 'var(--gnf-radius)',
            background: 'rgba(255,255,255,0.12)',
            backdropFilter: 'blur(10px)',
          }}
        >
          <small style={{ opacity: 0.8, display: 'block', marginBottom: 'var(--gnf-space-2)' }}>
            Reconocimiento actual
          </small>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
            <strong style={{ fontSize: '1.25rem' }}>{estrellaFinal} estrellas</strong>
            <StarRating rating={estrellaFinal} size={18} />
          </div>
        </div>
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
          gap: 'var(--gnf-space-4)',
          marginTop: 'var(--gnf-space-6)',
        }}
      >
        <div>
          <strong style={{ fontSize: '1.5rem', display: 'block' }}>{aprobados}</strong>
          <small style={{ opacity: 0.8 }}>Aprobados</small>
        </div>
        <div>
          <strong style={{ fontSize: '1.5rem', display: 'block' }}>{enviados}</strong>
          <small style={{ opacity: 0.8 }}>En revision</small>
        </div>
        <div>
          <strong style={{ fontSize: '1.5rem', display: 'block' }}>{enProgreso}</strong>
          <small style={{ opacity: 0.8 }}>En progreso</small>
        </div>
        {correccion > 0 && (
          <div style={{ color: 'var(--gnf-sun)' }}>
            <strong style={{ fontSize: '1.5rem', display: 'block' }}>{correccion}</strong>
            <small style={{ opacity: 0.9 }}>Con observaciones</small>
          </div>
        )}
      </div>

      <div style={{ marginTop: 'var(--gnf-space-5)' }}>
        <ProgressBar value={aprobados} max={retosCount} color="var(--gnf-leaf)" height={8} />
        <small style={{ opacity: 0.75, marginTop: 'var(--gnf-space-1)', display: 'block' }}>
          {percentage}% de los retos ya fueron aprobados
        </small>
      </div>
    </div>
  );
}
