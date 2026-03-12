import { useYearStore } from '@/stores/useYearStore';
import { WizardView } from '../components/WizardView';

interface FormulariosPageProps {
  retoId?: number;
}

export function FormulariosPage({ retoId }: FormulariosPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>Eco retos y evidencias</h2>
      <p style={{ color: 'var(--gnf-muted)', marginBottom: 'var(--gnf-space-6)' }}>
        Completa cada reto al ritmo de tu centro educativo. El progreso se guarda automaticamente.
      </p>
      <WizardView year={year} initialRetoId={retoId} />
    </div>
  );
}
