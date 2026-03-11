import type { ReactNode } from 'react';

interface Tab {
  id: string;
  label: string;
  icon?: ReactNode;
}

interface TabsProps {
  tabs: Tab[];
  active: string;
  onChange: (id: string) => void;
}

export function Tabs({ tabs, active, onChange }: TabsProps) {
  return (
    <div
      role="tablist"
      style={{
        display: 'flex',
        gap: 'var(--gnf-space-1)',
        borderBottom: '2px solid var(--gnf-border)',
        marginBottom: 'var(--gnf-space-6)',
        overflowX: 'auto',
      }}
    >
      {tabs.map((tab) => (
        <button
          key={tab.id}
          role="tab"
          aria-selected={active === tab.id}
          onClick={() => onChange(tab.id)}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 'var(--gnf-space-2)',
            padding: 'var(--gnf-space-3) var(--gnf-space-5)',
            border: 'none',
            background: 'none',
            cursor: 'pointer',
            fontWeight: 600,
            fontSize: '0.9375rem',
            fontFamily: 'var(--gnf-font-body)',
            color: active === tab.id ? 'var(--gnf-forest)' : 'var(--gnf-gray-500)',
            borderBottom: active === tab.id ? '2px solid var(--gnf-forest)' : '2px solid transparent',
            marginBottom: '-2px',
            transition: 'all var(--gnf-transition-fast)',
            whiteSpace: 'nowrap',
          }}
        >
          {tab.icon}
          {tab.label}
        </button>
      ))}
    </div>
  );
}
