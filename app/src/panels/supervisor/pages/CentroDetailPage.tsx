import { useQuery } from '@tanstack/react-query';
import { supervisorApi } from '@/api/supervisor';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { CentroCard } from '@/components/domain/CentroCard';
import { StarRating } from '@/components/ui/StarRating';
import { EntryReviewCard } from '../components/EntryReviewCard';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { ArrowLeft } from 'lucide-react';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  const { data, isLoading, error } = useQuery({
    queryKey: ['supervisor-centro', centroId, year],
    queryFn: () => supervisorApi.getCentroDetail(centroId, year),
  });

  if (isLoading) return <Spinner />;
  if (error || !data) return <Alert variant="error">Error al cargar centro.</Alert>;

  const { centro, entries } = data;

  return (
    <div>
      <Button variant="ghost" size="sm" icon={<ArrowLeft size={16} />} onClick={onBack} style={{ marginBottom: 'var(--gnf-space-4)' }}>
        Volver al listado
      </Button>

      <CentroCard centro={centro} />

      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)', margin: 'var(--gnf-space-6) 0 var(--gnf-space-4)' }}>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Galardón actual:</span>
          <StarRating rating={centro.annual.estrellaFinal} size={16} />
        </div>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
            {centro.annual.puntajeTotal} pts
          </span>
        </div>
      </div>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Retos ({entries.length})</h3>
      {entries.map((entry) => {
        const hasContent = entry.puntaje > 0 || !!entry.supervisorNotes || (entry.responses ?? []).some((response) => response.hasValue);
        return (
          <details key={entry.retoId} open={hasContent} style={{ marginBottom: 'var(--gnf-space-4)' }}>
            <summary style={{
              display: 'flex',
              alignItems: 'center',
              gap: 'var(--gnf-space-3)',
              padding: 'var(--gnf-space-4)',
              background: 'var(--gnf-white)',
              border: '1px solid var(--gnf-border)',
              borderRadius: 'var(--gnf-radius)',
              cursor: 'pointer',
              listStyle: 'none',
            }}>
              {entry.retoIconUrl && (
                <img src={entry.retoIconUrl} alt="" style={{ width: 36, height: 36, borderRadius: 'var(--gnf-radius-sm)', objectFit: 'cover' }} />
              )}
              <span style={{ flex: 1, fontWeight: 600, color: entry.retoColor }}>{entry.retoTitulo}</span>
              <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
                {entry.puntaje} / {entry.puntajeMaximo} pts
              </span>
              <StatusBadge estado={entry.estado} />
            </summary>
            <div style={{ borderLeft: '1px solid var(--gnf-border)', borderRight: '1px solid var(--gnf-border)', borderBottom: '1px solid var(--gnf-border)', borderRadius: '0 0 var(--gnf-radius) var(--gnf-radius)', padding: 'var(--gnf-space-4)', background: 'var(--gnf-white)' }}>
              <EntryReviewCard entry={entry} />
            </div>
          </details>
        );
      })}
    </div>
  );
}
