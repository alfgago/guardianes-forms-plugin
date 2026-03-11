import { useQuery } from '@tanstack/react-query';
import { comiteApi } from '@/api/comite';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { EmptyState } from '@/components/ui/EmptyState';
import { Timeline, type TimelineItem } from '@/components/data/Timeline';
import { History, CheckCircle2, AlertCircle, FileText } from 'lucide-react';

export function HistorialPage() {
  const year = useYearStore((s) => s.selectedYear);

  const { data: historial, isLoading } = useQuery({
    queryKey: ['comite-historial', year],
    queryFn: () => comiteApi.getHistorial(year),
  });

  if (isLoading) return <Spinner />;
  if (!historial?.length) return <EmptyState icon={<History size={48} />} title="Sin historial" description="No hay acciones registradas para este año." />;

  const items: TimelineItem[] = historial.map((h) => ({
    id: h.id,
    title: `${h.centroNombre} - ${h.action}`,
    description: `${h.details} (por ${h.userName})`,
    timestamp: h.createdAt,
    icon: h.action.includes('aprobado') ? <CheckCircle2 size={12} /> :
          h.action.includes('correccion') ? <AlertCircle size={12} /> :
          <FileText size={12} />,
    color: h.action.includes('aprobado') ? '#22c55e' :
           h.action.includes('correccion') ? 'var(--gnf-coral)' :
           'var(--gnf-ocean)',
  }));

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Historial de Validaciones</h2>
      <Timeline items={items} />
    </div>
  );
}
