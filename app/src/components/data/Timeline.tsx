import type { ReactNode } from 'react';
import { formatDateTime } from '@/utils/formatters';

export interface TimelineItem {
  id: string | number;
  icon?: ReactNode;
  color?: string;
  title: string;
  description?: string;
  timestamp: string;
}

interface TimelineProps {
  items: TimelineItem[];
}

export function Timeline({ items }: TimelineProps) {
  return (
    <div style={{ position: 'relative', paddingLeft: 'var(--gnf-space-8)' }}>
      <div
        style={{
          position: 'absolute',
          left: 11,
          top: 0,
          bottom: 0,
          width: 2,
          background: 'var(--gnf-gray-200)',
        }}
      />
      {items.map((item) => (
        <div key={item.id} style={{ position: 'relative', marginBottom: 'var(--gnf-space-6)' }}>
          <div
            style={{
              position: 'absolute',
              left: -21,
              top: 4,
              width: 24,
              height: 24,
              borderRadius: '50%',
              background: item.color ?? 'var(--gnf-forest)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: 'var(--gnf-white)',
              fontSize: '0.75rem',
              zIndex: 1,
            }}
          >
            {item.icon}
          </div>
          <div>
            <strong style={{ fontSize: '0.9375rem' }}>{item.title}</strong>
            {item.description && (
              <p style={{ color: 'var(--gnf-muted)', fontSize: '0.8125rem', margin: 'var(--gnf-space-1) 0 0' }}>
                {item.description}
              </p>
            )}
            <time style={{ fontSize: '0.75rem', color: 'var(--gnf-gray-400)' }}>
              {formatDateTime(item.timestamp)}
            </time>
          </div>
        </div>
      ))}
    </div>
  );
}
