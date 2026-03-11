import type { HTMLAttributes, ReactNode } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
  padding?: string;
  hoverable?: boolean;
}

export function Card({ children, padding = 'var(--gnf-space-6)', hoverable, style, ...props }: CardProps) {
  return (
    <div
      style={{
        background: 'var(--gnf-white)',
        borderRadius: 'var(--gnf-radius)',
        boxShadow: 'var(--gnf-shadow-sm)',
        border: '1px solid var(--gnf-border)',
        padding,
        transition: hoverable ? 'box-shadow var(--gnf-transition-fast), transform var(--gnf-transition-fast)' : undefined,
        ...style,
      }}
      onMouseEnter={hoverable ? (e) => {
        e.currentTarget.style.boxShadow = 'var(--gnf-shadow-md)';
        e.currentTarget.style.transform = 'translateY(-2px)';
      } : undefined}
      onMouseLeave={hoverable ? (e) => {
        e.currentTarget.style.boxShadow = 'var(--gnf-shadow-sm)';
        e.currentTarget.style.transform = 'translateY(0)';
      } : undefined}
      {...props}
    >
      {children}
    </div>
  );
}
