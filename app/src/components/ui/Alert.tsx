import type { ReactNode } from 'react';
import { AlertCircle, CheckCircle2, Info, AlertTriangle } from 'lucide-react';

type AlertVariant = 'info' | 'success' | 'warning' | 'error';

interface AlertProps {
  variant?: AlertVariant;
  children: ReactNode;
  title?: string;
}

const config: Record<AlertVariant, { border: string; color: string; Icon: typeof Info }> = {
  info: { border: '#0ea5e9', color: '#0369a1', Icon: Info },
  success: { border: '#22c55e', color: '#16a34a', Icon: CheckCircle2 },
  warning: { border: 'var(--gnf-sun)', color: '#b45309', Icon: AlertTriangle },
  error: { border: 'var(--gnf-coral)', color: '#dc2626', Icon: AlertCircle },
};

export function Alert({ variant = 'info', children, title }: AlertProps) {
  const { border, color, Icon } = config[variant];

  return (
    <div
      role="alert"
      style={{
        display: 'flex',
        gap: 'var(--gnf-space-3)',
        padding: 'var(--gnf-space-4)',
        borderRadius: 'var(--gnf-radius)',
        backgroundColor: 'var(--gnf-white)',
        border: '1px solid var(--gnf-border)',
        borderLeft: `4px solid ${border}`,
        boxShadow: 'var(--gnf-shadow-sm)',
        color: 'var(--gnf-gray-800)',
      }}
    >
      <Icon size={20} style={{ flexShrink: 0, marginTop: 2, color }} />
      <div>
        {title && <strong style={{ display: 'block', marginBottom: 4, color: 'var(--gnf-gray-900)' }}>{title}</strong>}
        <div style={{ fontSize: '0.875rem' }}>{children}</div>
      </div>
    </div>
  );
}
