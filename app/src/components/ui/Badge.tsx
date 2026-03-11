import type { ReactNode } from 'react';

interface BadgeProps {
  children: ReactNode;
  color?: string;
  bg?: string;
  size?: 'sm' | 'md';
}

export function Badge({ children, color = 'var(--gnf-gray-700)', bg = 'var(--gnf-gray-100)', size = 'sm' }: BadgeProps) {
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: '4px',
        padding: size === 'sm' ? '2px 8px' : '4px 12px',
        borderRadius: 'var(--gnf-radius-full)',
        fontSize: size === 'sm' ? '0.75rem' : '0.8125rem',
        fontWeight: 600,
        color,
        backgroundColor: bg,
        whiteSpace: 'nowrap',
      }}
    >
      {children}
    </span>
  );
}
