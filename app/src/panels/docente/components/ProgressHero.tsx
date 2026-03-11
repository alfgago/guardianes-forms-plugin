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
  metaEstrellas,
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
        background: 'linear-gradient(135deg, var(--gnf-forest) 0%, var(--gnf-forest-dark) 100%)',
        borderRadius: 'var(--gnf-radius-lg)',
        padding: 'var(--gnf-space-8)',
        color: 'var(--gnf-white)',
        marginBottom: 'var(--gnf-space-6)',
      }}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 'var(--gnf-space-4)' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-2)' }}>
            <h3 style={{ color: 'var(--gnf-white)', margin: 0 }}>Meta: {metaEstrellas} Estrellas</h3>
            <StarRating rating={estrellaFinal} size={18} />
          </div>
          <p style={{ opacity: 0.85, marginBottom: 0, fontSize: '0.9375rem' }}>
            Año {anio} | {retosCount} retos matriculados | {puntajeTotal} pts
          </p>
        </div>

        <div style={{ display: 'flex', gap: 'var(--gnf-space-6)', textAlign: 'center' }}>
          <div>
            <strong style={{ fontSize: '1.5rem', display: 'block' }}>{aprobados}</strong>
            <small style={{ opacity: 0.8 }}>Aprobados</small>
          </div>
          <div>
            <strong style={{ fontSize: '1.5rem', display: 'block' }}>{enviados}</strong>
            <small style={{ opacity: 0.8 }}>En revisión</small>
          </div>
          <div>
            <strong style={{ fontSize: '1.5rem', display: 'block' }}>{enProgreso}</strong>
            <small style={{ opacity: 0.8 }}>Pendientes</small>
          </div>
          {correccion > 0 && (
            <div style={{ color: 'var(--gnf-sun)' }}>
              <strong style={{ fontSize: '1.5rem', display: 'block' }}>{correccion}</strong>
              <small style={{ opacity: 0.8 }}>Corrección</small>
            </div>
          )}
        </div>
      </div>

      <div style={{ marginTop: 'var(--gnf-space-5)' }}>
        <ProgressBar
          value={aprobados}
          max={retosCount}
          color="var(--gnf-leaf)"
          height={8}
        />
        <small style={{ opacity: 0.7, marginTop: 'var(--gnf-space-1)', display: 'block' }}>
          {percentage}% completado
        </small>
      </div>
    </div>
  );
}
