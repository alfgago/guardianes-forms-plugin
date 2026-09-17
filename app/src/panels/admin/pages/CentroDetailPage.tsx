import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Download, Award, Undo2 } from 'lucide-react';
import { adminApi } from '@/api/admin';
import { Modal } from '@/components/ui/Modal';
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
  const queryClient = useQueryClient();
  const [awardAction, setAwardAction] = useState<'assign' | 'revoke' | null>(null);
  const assignment = useMutation({
    mutationFn: (action: 'assign' | 'revoke') => adminApi.assignAward(centroId, year, action),
    onSuccess: () => {
      setAwardAction(null);
      queryClient.invalidateQueries({ queryKey: ['admin-centro-supervisor', centroId, year] });
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
    },
  });

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
      {centro.annual.award && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', marginBottom: 24 }}>
          <Button icon={<Award size={18} />} disabled={year !== 2026 || !centro.annual.award.validated?.stars} onClick={() => { assignment.reset(); setAwardAction('assign'); }}>
            {centro.annual.award.assigned ? 'Actualizar galardón asignado' : 'Asignar galardón'}
          </Button>
          {centro.annual.award.assigned && (
            <Button variant="outline" icon={<Undo2 size={18} />} onClick={() => { assignment.reset(); setAwardAction('revoke'); }}>Retirar asignación</Button>
          )}
          <span>{centro.annual.award.assigned ? 'Resultado asignado' : 'Pendiente de asignación'}</span>
        </div>
      )}
      <Modal open={awardAction !== null} onClose={() => { if (!assignment.isPending) setAwardAction(null); }} title={awardAction === 'revoke' ? 'Retirar asignación' : 'Asignar galardón'}>
        <p>{awardAction === 'revoke' ? 'Las estrellas dejarán de mostrarse en el panel docente.' : `Se asignarán ${centro.annual.award?.validated?.stars ?? 0} estrellas con el resultado validado de ${year}. El centro podrá verlas cuando la funcionalidad esté habilitada para él.`}</p>
        {assignment.isError && <Alert variant="error">{assignment.error.message}</Alert>}
        <Button loading={assignment.isPending} onClick={() => { if (awardAction) assignment.mutate(awardAction); }}>Confirmar</Button>
      </Modal>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Revision de retos ({entries.length})</h3>
      {entries.map((entry) => (
        <EntryReviewCard key={entry.id || entry.retoId} entry={entry} />
      ))}
    </div>
  );
}
