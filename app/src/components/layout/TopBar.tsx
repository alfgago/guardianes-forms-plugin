import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import { useAppStore } from '@/stores/useAppStore';

const HOME_URL = 'https://movimientoguardianes.org/bae2026/';

interface TopBarProps {
  title: string;
  onMenuClick: () => void;
  actions?: ReactNode;
}

export function TopBar({ title, onMenuClick, actions }: TopBarProps) {
  const logoUrl = useAppStore((state) => state.logoUrl);

  return (
    <header className="gnf-topbar">
      <div className="gnf-topbar__left">
        <button className="gnf-topbar__hamburger" onClick={onMenuClick} aria-label="Abrir menu">
          <Menu size={22} />
        </button>
      </div>

      <div className="gnf-topbar__center">
        <span className="gnf-topbar__title">{title}</span>
      </div>

      <div className="gnf-topbar__right">
        {actions}
        <a href={HOME_URL} className="gnf-topbar__logo-link" aria-label="Ir al inicio">
          {logoUrl && <img src={logoUrl} alt="Bandera Azul y Guardianes" className="gnf-topbar__logo" />}
        </a>
      </div>
    </header>
  );
}
