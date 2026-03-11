import type { RetoWithEntry } from '@/types';
import { RetoCard } from '@/components/domain/RetoCard';

interface RetoGridProps {
  retos: RetoWithEntry[];
  onFillForm: (retoId: number) => void;
  onReopen: (retoId: number) => void;
  onViewFeedback: (notes: string) => void;
}

export function RetoGrid({ retos, onFillForm, onReopen, onViewFeedback }: RetoGridProps) {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(340px, 1fr))',
        gap: 'var(--gnf-space-5)',
      }}
    >
      {retos.map((reto) => (
        <RetoCard
          key={reto.id}
          reto={reto}
          onFillForm={onFillForm}
          onReopen={onReopen}
          onViewFeedback={onViewFeedback}
        />
      ))}
    </div>
  );
}
