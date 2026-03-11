import { useYearStore } from '@/stores/useYearStore';
import { WizardView } from '../components/WizardView';

interface FormulariosPageProps {
  retoId?: number;
}

export function FormulariosPage({ retoId }: FormulariosPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Formularios</h2>
      <WizardView year={year} initialRetoId={retoId} />
    </div>
  );
}
