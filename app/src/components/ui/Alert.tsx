import type { ReactNode } from 'react';
import { AlertCircle, CheckCircle2, Info, AlertTriangle } from 'lucide-react';

type AlertVariant = 'info' | 'success' | 'warning' | 'error';

interface AlertProps {
  variant?: AlertVariant;
  children: ReactNode;
  title?: string;
}

const config: Record<AlertVariant, { bg: string; border: string; color: string; Icon: typeof Info }> = {
  info: { bg: '#e0f2fe', border: '#0ea5e9', color: '#0369a1', Icon: Info },
  success: { bg: '#dcfce7', border: '#22c55e', color: '#16a34a', Icon: CheckCircle2 },
  warning: { bg: 'var(--gnf-sun-light)', border: 'var(--gnf-sun)', color: '#92400e', Icon: AlertTriangle },
  error: { bg: 'var(--gnf-coral-light)', border: 'var(--gnf-coral)', color: '#991b1b', Icon: AlertCircle },
};

export function Alert({ variant = 'info', children, title }: AlertProps) {
  const { bg, border, color, Icon } = config[variant];

  return (
    <div
      role="alert"
      style={{
        display: 'flex',
        gap: 'var(--gnf-space-3)',
        padding: 'var(--gnf-space-4)',
        borderRadius: 'var(--gnf-radius)',
        backgroundColor: bg,
        borderLeft: `4px solid ${border}`,
        color,
      }}
    >
      <Icon size={20} style={{ flexShrink: 0, marginTop: 2 }} />
      <div>
        {title && <strong style={{ display: 'block', marginBottom: 4 }}>{title}</strong>}
        <div style={{ fontSize: '0.875rem' }}>{children}</div>
      </div>
    </div>
  );
}
