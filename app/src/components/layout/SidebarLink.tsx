import type { ReactNode } from 'react';
import { buildPageUrl } from '@/utils/url';

interface SidebarLinkProps {
  page: string;
  label: string;
  icon: ReactNode;
  active: boolean;
  badge?: number;
  onClick: (page: string) => void;
}

export function SidebarLink({ page, label, icon, active, badge, onClick }: SidebarLinkProps) {
  return (
    <a
      href={buildPageUrl(page)}
      onClick={(e) => {
        e.preventDefault();
        onClick(page);
      }}
      className={`gnf-sidebar__link ${active ? 'gnf-sidebar__link--active' : ''}`}
    >
      {icon}
      <span style={{ flex: 1 }}>{label}</span>
      {badge !== undefined && badge > 0 && (
        <span
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            minWidth: 20,
            height: 20,
            padding: '0 6px',
            borderRadius: 'var(--gnf-radius-full)',
            fontSize: '0.6875rem',
            fontWeight: 700,
            background: active ? 'rgba(255,255,255,0.25)' : 'var(--gnf-coral)',
            color: 'var(--gnf-white)',
          }}
        >
          {badge}
        </span>
      )}
    </a>
  );
}
