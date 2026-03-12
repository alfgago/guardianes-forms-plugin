import type { ReactNode } from 'react';

interface StatCardProps {
  label: string;
  value: number | string;
  icon?: ReactNode;
  color?: string;
  bg?: string;
}

export function StatCard({ label, value, icon, color = 'var(--gnf-gray-800)', bg = 'rgba(30, 95, 138, 0.08)' }: StatCardProps) {
  return (
    <div
      style={{
        padding: 'var(--gnf-space-5)',
        background: 'var(--gnf-white)',
        borderRadius: 'var(--gnf-radius)',
        textAlign: 'center',
        border: '1px solid var(--gnf-border)',
        boxShadow: 'var(--gnf-shadow-sm)',
      }}
    >
      {icon && (
        <div
          style={{
            width: 52,
            height: 52,
            margin: '0 auto var(--gnf-space-3)',
            display: 'grid',
            placeItems: 'center',
            borderRadius: '16px',
            background: bg,
            color,
          }}
        >
          {icon}
        </div>
      )}
      <strong style={{ fontSize: '1.75rem', color: 'var(--gnf-gray-900)', display: 'block', lineHeight: 1.2 }}>{value}</strong>
      <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{label}</span>
    </div>
  );
}
