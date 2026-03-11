import { useYearStore } from '@/stores/useYearStore';

export function YearSelector() {
  const { selectedYear, availableYears, setSelectedYear } = useYearStore();

  if (availableYears.length <= 1) return null;

  return (
    <select
      value={selectedYear}
      onChange={(e) => setSelectedYear(Number(e.target.value))}
      aria-label="Seleccionar año"
      style={{
        padding: '6px 12px',
        border: '1px solid var(--gnf-border)',
        borderRadius: 'var(--gnf-radius-sm)',
        fontSize: '0.875rem',
        fontFamily: 'var(--gnf-font-body)',
        background: 'var(--gnf-white)',
        color: 'var(--gnf-text)',
        cursor: 'pointer',
      }}
    >
      {availableYears.map((y) => (
        <option key={y} value={y}>
          {y}
        </option>
      ))}
    </select>
  );
}
