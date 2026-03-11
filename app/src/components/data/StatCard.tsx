import type { ReactNode } from 'react';

interface StatCardProps {
  label: string;
  value: number | string;
  icon?: ReactNode;
  color?: string;
  bg?: string;
}

export function StatCard({ label, value, icon, color = 'var(--gnf-gray-800)', bg = 'var(--gnf-gray-50)' }: StatCardProps) {
  return (
    <div
      style={{
        padding: 'var(--gnf-space-5)',
        background: bg,
        borderRadius: 'var(--gnf-radius)',
        textAlign: 'center',
      }}
    >
      {icon && <div style={{ color, marginBottom: 'var(--gnf-space-2)' }}>{icon}</div>}
      <strong style={{ fontSize: '1.75rem', color, display: 'block', lineHeight: 1.2 }}>{value}</strong>
      <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{label}</span>
    </div>
  );
}
