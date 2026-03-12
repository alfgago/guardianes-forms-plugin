interface CircuitoFilterProps {
  circuitos: string[];
  value: string;
  onChange: (circuito: string) => void;
}

export function CircuitoFilter({ circuitos, value, onChange }: CircuitoFilterProps) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      aria-label="Filtrar por circuito"
      style={{
        padding: '8px 14px',
        border: '1.5px solid var(--gnf-field-border)',
        borderRadius: 'var(--gnf-radius)',
        fontSize: '0.9375rem',
        fontFamily: 'var(--gnf-font-body)',
        background: 'var(--gnf-white)',
        color: 'var(--gnf-text)',
        cursor: 'pointer',
      }}
    >
      <option value="">Todos los circuitos</option>
      {circuitos.map((c) => (
        <option key={c} value={c}>
          {c}
        </option>
      ))}
    </select>
  );
}
