import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';

interface TopBarProps {
  title: string;
  onMenuClick: () => void;
  actions?: ReactNode;
}

export function TopBar({ title, onMenuClick, actions }: TopBarProps) {
  return (
    <header className="gnf-topbar">
      <button className="gnf-topbar__hamburger" onClick={onMenuClick} aria-label="Menú">
        <Menu size={24} />
      </button>
      <span className="gnf-topbar__title">{title}</span>
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
        {actions}
      </div>
    </header>
  );
}
