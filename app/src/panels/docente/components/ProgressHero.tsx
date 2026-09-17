import { CheckCircle2, Clock3, Download, Star, CircleAlert } from 'lucide-react';
import { ProgressBar } from '@/components/ui/ProgressBar';
import type { AssignedAward } from '@/types';

interface ProgressHeroProps {
  anio: number;
  retosCount: number;
  puntajeTotal: number;
  evidenceCounts: { pending: number; approved: number; rejected: number; total: number };
  assignedAward?: AssignedAward | null;
  reportPdfUrl?: string;
  reportPdfStatus?: 'draft' | 'final';
  onViewRejected?: () => void;
}

export function ProgressHero({ anio, retosCount, puntajeTotal, evidenceCounts, assignedAward, reportPdfUrl, reportPdfStatus, onViewRejected }: ProgressHeroProps) {
  const stars = assignedAward?.result.stars ?? 0;
  const percentage = evidenceCounts.total > 0 ? Math.round(evidenceCounts.approved / evidenceCounts.total * 100) : 0;
  const reportAvailable = Boolean(reportPdfUrl && reportPdfStatus === 'final');

  return (
    <section className="gnf-docente-summary" aria-label={`Resumen de participación ${anio}`}>
      <div className="gnf-docente-summary__header">
        <div>
          <p className="gnf-docente-summary__year">Participación {anio}</p>
          <h3>Eco puntos acumulados: {puntajeTotal}</h3>
          <p>{retosCount} retos matriculados para este centro educativo.</p>
        </div>
        <div className="gnf-docente-summary__actions">
          <div>
            <div className="gnf-docente-summary__award">
              <strong>Galardón logrado</strong>
              <span className="gnf-docente-summary__stars" role="img" aria-label={assignedAward ? `${stars} de 5 estrellas asignadas` : 'Galardón pendiente de asignación'}>
                {Array.from({ length: 5 }, (_, index) => (
                  <Star key={index} size={25} aria-hidden="true" fill={index < stars ? 'currentColor' : 'none'} className={index < stars ? 'is-awarded' : ''} />
                ))}
              </span>
            </div>
            <small>{assignedAward ? 'Galardón asignado' : 'Pendiente de asignación'}</small>
          </div>
          <button type="button" className="gnf-docente-summary__report" disabled={!reportAvailable}
            title={reportAvailable ? 'Descargar reporte final PDF' : 'Disponible al finalizar la revisión'}
            onClick={() => { if (reportAvailable && reportPdfUrl) window.location.href = reportPdfUrl; }}>
            <Download size={18} aria-hidden="true" /> Reporte final
          </button>
        </div>
      </div>
      <div className="gnf-docente-summary__counts">
        <div><Clock3 aria-hidden="true" /><strong>{evidenceCounts.pending}</strong><span>Evidencias pendientes de revisión</span></div>
        <div><CheckCircle2 aria-hidden="true" /><strong>{evidenceCounts.approved}</strong><span>Evidencias aprobadas</span></div>
        <button type="button" onClick={onViewRejected} disabled={!onViewRejected || evidenceCounts.rejected === 0} className={evidenceCounts.rejected > 0 ? 'has-rejections' : ''}>
          <CircleAlert aria-hidden="true" /><strong>{evidenceCounts.rejected}</strong><span>Evidencias rechazadas</span>
        </button>
      </div>
      <ProgressBar value={evidenceCounts.approved} max={Math.max(1, evidenceCounts.total)} color="var(--gnf-leaf)" height={8} />
      <small className="gnf-docente-summary__progress">{evidenceCounts.total > 0 ? `${percentage}% de las evidencias aprobadas` : 'Aún no hay evidencias registradas'}</small>
    </section>
  );
}
