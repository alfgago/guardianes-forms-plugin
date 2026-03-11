import type { ReactNode } from 'react';

interface StatsGridProps {
  children: ReactNode;
  columns?: number;
}

export function StatsGrid({ children, columns = 4 }: StatsGridProps) {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: `repeat(auto-fit, minmax(${Math.floor(600 / columns)}px, 1fr))`,
        gap: 'var(--gnf-space-4)',
        marginBottom: 'var(--gnf-space-6)',
      }}
    >
      {children}
    </div>
  );
}
