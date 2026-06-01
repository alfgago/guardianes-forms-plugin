import { CalendarDays } from 'lucide-react';
import { useYearStore } from '@/stores/useYearStore';

export function YearSelector() {
  const { selectedYear } = useYearStore();

  return (
    <div className="gnf-year-selector">
      <div className="gnf-year-selector__header">
        <span className="gnf-year-selector__icon">
          <CalendarDays size={16} />
        </span>
        <div>
          <div className="gnf-year-selector__eyebrow">Año activo</div>
          <div className="gnf-year-selector__value">{selectedYear}</div>
        </div>
      </div>
    </div>
  );
}
