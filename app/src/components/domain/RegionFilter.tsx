import type { Region } from '@/types';

interface RegionFilterProps {
  regions: Region[];
  value: string;
  onChange: (regionId: string) => void;
}

export function RegionFilter({ regions, value, onChange }: RegionFilterProps) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      aria-label="Filtrar por región"
      style={{
        padding: '8px 14px',
        border: '1px solid var(--gnf-border)',
        borderRadius: 'var(--gnf-radius)',
        fontSize: '0.9375rem',
        fontFamily: 'var(--gnf-font-body)',
        background: 'var(--gnf-white)',
        color: 'var(--gnf-text)',
        cursor: 'pointer',
      }}
    >
      <option value="">Todas las regiones</option>
      {regions.map((r) => (
        <option key={r.id} value={String(r.id)}>
          {r.name}
        </option>
      ))}
    </select>
  );
}
