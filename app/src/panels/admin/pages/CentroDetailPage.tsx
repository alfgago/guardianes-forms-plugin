import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Download } from 'lucide-react';
import { supervisorApi } from '@/api/supervisor';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { CentroCard } from '@/components/domain/CentroCard';
import { EntryReviewCard } from '@/panels/supervisor/components/EntryReviewCard';
import { AwardSummary } from '@/components/domain/AwardSummary';

interface CentroDetailPageProps {
  centroId: number;
  onBack: () => void;
}

export function CentroDetailPage({ centroId, onBack }: CentroDetailPageProps) {
  const year = useYearStore((state) => state.selectedYear);

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-centro-supervisor', centroId, year],
    queryFn: () => supervisorApi.getCentroDetail(centroId, year),
  });

  if (isLoading) return <Spinner />;
  if (error || !data) return <Alert variant="error">Error al cargar el detalle del centro.</Alert>;

  const { centro, entries } = data;

  return (
    <div>
      <Button variant="ghost" size="sm" icon={<ArrowLeft size={16} />} onClick={onBack} style={{ marginBottom: 'var(--gnf-space-4)' }}>
        Volver al listado
      </Button>

      <CentroCard centro={centro} />

      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)', margin: 'var(--gnf-space-6) 0 var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <div>
          <span style={{ fontSize: '0.875rem', color: 'var(--gnf-muted)' }}>{centro.annual.puntajeTotal} pts</span>
        </div>
        {centro.annual.reportPdfUrl && (
          <Button
            variant="outline"
            size="sm"
            icon={<Download size={16} />}
            onClick={() => { window.location.href = centro.annual.reportPdfUrl ?? ''; }}
          >
            {centro.annual.reportPdfStatus === 'final' ? 'Descargar reporte final PDF' : 'Descargar borrador PDF'}
          </Button>
        )}
      </div>

      <AwardSummary award={centro.annual.award} year={year} />

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Revision de retos ({entries.length})</h3>
      {entries.map((entry) => (
        <EntryReviewCard key={entry.id || entry.retoId} entry={entry} />
      ))}
    </div>
  );
}
