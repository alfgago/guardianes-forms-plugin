import { useQuery } from '@tanstack/react-query';
import { comiteApi } from '@/api/comite';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { CentroCard } from '@/components/domain/CentroCard';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { StarRating } from '@/components/ui/StarRating';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import { ArrowLeft } from 'lucide-react';
import type { Evidencia } from '@/types';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  const { data, isLoading, error } = useQuery({
    queryKey: ['comite-centro', centroId, year],
    queryFn: () => comiteApi.getCentroDetail(centroId, year),
  });

  if (isLoading) return <Spinner />;
  if (error || !data) return <Alert variant="error">Error al cargar centro.</Alert>;

  const { centro, entries } = data;

  return (
    <div>
      <Button variant="ghost" size="sm" icon={<ArrowLeft size={16} />} onClick={onBack} style={{ marginBottom: 'var(--gnf-space-4)' }}>
        Volver
      </Button>

      <CentroCard centro={centro} />

      <div style={{ display: 'flex', gap: 'var(--gnf-space-6)', margin: 'var(--gnf-space-6) 0' }}>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Meta:</span>
          <StarRating rating={centro.annual.metaEstrellas} size={16} />
        </div>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>Actual:</span>
          <StarRating rating={centro.annual.estrellaFinal} size={16} />
        </div>
        <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>{centro.annual.puntajeTotal} pts</span>
      </div>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Retos ({entries.length})</h3>
      {entries.map((entry) => (
        <Card key={entry.id} style={{ marginBottom: 'var(--gnf-space-4)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-3)' }}>
            <h4 style={{ margin: 0, color: entry.retoColor }}>{entry.retoTitulo}</h4>
            <StatusBadge estado={entry.estado} />
          </div>
          <ProgressBar value={entry.puntaje} max={entry.puntajeMaximo} height={6} />
          <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>{entry.puntaje} / {entry.puntajeMaximo} pts</span>
          {entry.evidencias && (entry.evidencias as Evidencia[]).length > 0 && (
            <div style={{ marginTop: 'var(--gnf-space-3)' }}>
              <EvidenceViewer evidencias={entry.evidencias as Evidencia[]} />
            </div>
          )}
        </Card>
      ))}
    </div>
  );
}
