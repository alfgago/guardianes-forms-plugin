import { CalendarDays } from 'lucide-react';
import { useYearStore } from '@/stores/useYearStore';

export function YearSelector() {
  const { selectedYear, availableYears, setSelectedYear } = useYearStore();
  const hasMultipleYears = availableYears.length > 1;

  function handleYearChange(year: number) {
    setSelectedYear(year);

    const params = new URLSearchParams(window.location.search);
    params.set('year', String(year));
    const qs = params.toString();
    const nextUrl = `${window.location.pathname}${qs ? `?${qs}` : ''}`;
    window.history.replaceState({}, '', nextUrl);
  }

  return (
    <div className="gnf-year-selector">
      <div className="gnf-year-selector__header">
        <span className="gnf-year-selector__icon">
          <CalendarDays size={16} />
        </span>
        <div>
          <div className="gnf-year-selector__eyebrow">Ano activo</div>
          <div className="gnf-year-selector__value">{selectedYear}</div>
        </div>
      </div>

      {hasMultipleYears && (
        <select
          className="gnf-year-selector__select"
          value={selectedYear}
          onChange={(e) => handleYearChange(Number(e.target.value))}
          aria-label="Seleccionar ano"
        >
          {availableYears.map((year) => (
            <option key={year} value={year}>
              {year}
            </option>
          ))}
        </select>
      )}
    </div>
  );
}
