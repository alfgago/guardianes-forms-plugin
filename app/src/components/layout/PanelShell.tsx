import { useState, type ReactNode } from 'react';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';

interface PanelShellProps {
  title: string;
  subtitle?: string;
  nav: ReactNode;
  children: ReactNode;
  topBarActions?: ReactNode;
  sidebarFooterExtra?: ReactNode;
}

export function PanelShell({ title, subtitle, nav, children, topBarActions, sidebarFooterExtra }: PanelShellProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="gnf-shell">
      <Sidebar
        title={title}
        subtitle={subtitle}
        open={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        footerExtra={sidebarFooterExtra}
      >
        {nav}
      </Sidebar>
      <TopBar title={title} onMenuClick={() => setSidebarOpen(true)} actions={topBarActions} />
      <main className="gnf-shell__main">
        <div className="gnf-page-enter">{children}</div>
      </main>
    </div>
  );
}
