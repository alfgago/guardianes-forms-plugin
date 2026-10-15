import { useQuery } from '@tanstack/react-query';
import { supervisorApi } from '@/api/supervisor';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { CentroCard } from '@/components/domain/CentroCard';
import { ChallengeReview } from '../components/ChallengeReview';
import { ArrowLeft } from 'lucide-react';
import { AwardSummary } from '@/components/domain/AwardSummary';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  const { data, isLoading, error } = useQuery({
    queryKey: ['supervisor-centro', centroId, year],
    queryFn: () => supervisorApi.getCentroDetail(centroId, year),
    staleTime: 0,
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
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>
            {centro.annual.puntajeTotal} pts
          </span>
        </div>
      </div>

      <AwardSummary award={centro.annual.award} year={year} collapsible />
      <ChallengeReview key={`${centroId}:${year}`} entries={entries} centroId={centroId} year={year} />
    </div>
  );
}
