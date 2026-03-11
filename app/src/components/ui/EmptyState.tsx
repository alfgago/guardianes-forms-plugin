import type { ReactNode } from 'react';
import { Inbox } from 'lucide-react';

interface EmptyStateProps {
  icon?: ReactNode;
  title: string;
  description?: string;
  action?: ReactNode;
}

export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 'var(--gnf-space-12) var(--gnf-space-6)',
        textAlign: 'center',
      }}
    >
      <div style={{ color: 'var(--gnf-gray-300)', marginBottom: 'var(--gnf-space-4)' }}>
        {icon ?? <Inbox size={56} />}
      </div>
      <h3 style={{ marginBottom: 'var(--gnf-space-2)', color: 'var(--gnf-gray-700)' }}>{title}</h3>
      {description && (
        <p style={{ color: 'var(--gnf-muted)', maxWidth: 400, marginBottom: 'var(--gnf-space-5)' }}>
          {description}
        </p>
      )}
      {action}
    </div>
  );
}
