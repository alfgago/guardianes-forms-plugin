import type { ReactNode } from 'react';
import { Home, LogOut } from 'lucide-react';
import { useAuthStore } from '@/stores/useAuthStore';
import { useAppStore } from '@/stores/useAppStore';
import { authApi } from '@/api/auth';

const HOME_URL = 'https://movimientoguardianes.org/bae2026/';

interface SidebarProps {
  title: string;
  subtitle?: string;
  children: ReactNode;
  open: boolean;
  onClose: () => void;
  footerExtra?: ReactNode;
}

export function Sidebar({ title, subtitle, children, open, onClose, footerExtra }: SidebarProps) {
  const user = useAuthStore((state) => state.user);
  const logoUrl = useAppStore((state) => state.logoUrl);
  const clearAuth = useAuthStore((state) => state.logout);

  async function handleLogout() {
    try {
      await authApi.logout();
    } catch {
      // Ignore request errors and still force the redirect.
    }

    clearAuth();
    window.location.href = `${window.location.pathname}${window.location.search}`;
  }

  return (
    <>
      {open && <div className="gnf-shell__overlay" onClick={onClose} />}
      <aside className={`gnf-shell__sidebar ${open ? 'gnf-shell__sidebar--open' : ''}`}>
        <div className="gnf-sidebar__brand">
          <a href={HOME_URL} className="gnf-sidebar__brand-link">
            {logoUrl && <img src={logoUrl} alt="Bandera Azul y Guardianes" className="gnf-sidebar__brand-logo" />}
          </a>
          <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
          </div>
        </div>

        <nav className="gnf-sidebar__nav">{children}</nav>

        <div className="gnf-sidebar__footer">
          {footerExtra && <div className="gnf-sidebar__footer-block">{footerExtra}</div>}

          <a href={HOME_URL} className="gnf-sidebar__footer-link">
            <Home size={14} />
            Ir al inicio
          </a>

          {user && (
            <div className="gnf-sidebar__footer-user">
              <div className="gnf-sidebar__footer-name">{user.name}</div>
              <button type="button" onClick={handleLogout} className="gnf-sidebar__footer-link" style={{ border: 'none', background: 'none', padding: 0 }}>
                <LogOut size={14} />
                Cerrar sesion
              </button>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}
