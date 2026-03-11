import { useQuery } from '@tanstack/react-query';
import { supervisorApi } from '@/api/supervisor';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { CentroCard } from '@/components/domain/CentroCard';
import { StarRating } from '@/components/ui/StarRating';
import { EntryReviewCard } from '../components/EntryReviewCard';
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
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Meta:</span>
          <StarRating rating={centro.annual.metaEstrellas} size={16} />
        </div>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Actual:</span>
          <StarRating rating={centro.annual.estrellaFinal} size={16} />
        </div>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
            {centro.annual.puntajeTotal} pts
          </span>
        </div>
      </div>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Retos ({entries.length})</h3>
      {entries.map((entry) => (
        <EntryReviewCard key={entry.id} entry={entry} year={year} />
      ))}
    </div>
  );
}
