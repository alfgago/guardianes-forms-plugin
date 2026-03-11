import type { ReactNode } from 'react';
import { LogOut } from 'lucide-react';
import { useAuthStore } from '@/stores/useAuthStore';

interface SidebarProps {
  title: string;
  subtitle?: string;
  children: ReactNode;
  open: boolean;
  onClose: () => void;
}

export function Sidebar({ title, subtitle, children, open, onClose }: SidebarProps) {
  const user = useAuthStore((s) => s.user);

  return (
    <>
      {open && <div className="gnf-shell__overlay" onClick={onClose} />}
      <aside className={`gnf-shell__sidebar ${open ? 'gnf-shell__sidebar--open' : ''}`}>
        <div className="gnf-sidebar__brand">
          <h2>{title}</h2>
          {subtitle && <p>{subtitle}</p>}
        </div>
        <nav className="gnf-sidebar__nav">{children}</nav>
        {user && (
          <div className="gnf-sidebar__footer">
            <div style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', marginBottom: 'var(--gnf-space-2)' }}>
              {user.name}
            </div>
            <a
              href={'/wp-login.php?action=logout'}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--gnf-space-2)',
                fontSize: '0.8125rem',
                color: 'var(--gnf-gray-500)',
              }}
            >
              <LogOut size={14} />
              Cerrar sesión
            </a>
          </div>
        )}
      </aside>
    </>
  );
}
