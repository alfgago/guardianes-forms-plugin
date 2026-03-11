import { LayoutDashboard, School, Bell } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { YearSelector } from '@/components/domain/YearSelector';
import { NotificationBell } from '@/components/domain/NotificationBell';
import { usePanel } from '@/hooks/usePanel';
import { useYearStore } from '@/stores/useYearStore';
import { useNotificationStore } from '@/stores/useNotificationStore';
import { DashboardPage } from './pages/DashboardPage';
import { CentroDetailPage } from './pages/CentroDetailPage';
import { NotificacionesPage } from './pages/NotificacionesPage';

const NAV_ITEMS = [
  { page: 'dashboard', label: 'Dashboard', icon: <LayoutDashboard size={18} /> },
  { page: 'notificaciones', label: 'Notificaciones', icon: <Bell size={18} />, badgeKey: 'notifications' as const },
];

export function SupervisorPanel() {
  const { page, params, navigate } = usePanel('dashboard');
  const year = useYearStore((s) => s.selectedYear);
  const unreadCount = useNotificationStore((s) => s.unreadCount);

  function renderPage() {
    if (page === 'centro' && params.centro_id) {
      return (
        <CentroDetailPage
          centroId={Number(params.centro_id)}
          onBack={() => navigate('dashboard')}
        />
      );
    }

    switch (page) {
      case 'dashboard':
        return <DashboardPage onViewCentro={(id) => navigate('centro', { centro_id: String(id) })} />;
      case 'notificaciones':
        return <NotificacionesPage />;
      default:
        return <DashboardPage onViewCentro={(id) => navigate('centro', { centro_id: String(id) })} />;
    }
  }

  return (
    <PanelShell
      title="Panel Supervisor"
      subtitle={`Año ${year}`}
      topBarActions={
        <>
          <YearSelector />
          <NotificationBell onClick={() => navigate('notificaciones')} />
        </>
      }
      nav={
        <>
          {NAV_ITEMS.map((item) => (
            <SidebarLink
              key={item.page}
              page={item.page}
              label={item.label}
              icon={item.icon}
              active={page === item.page}
              badge={item.badgeKey === 'notifications' ? unreadCount : undefined}
              onClick={navigate}
            />
          ))}
          <SidebarLink
            page="centros-detail"
            label="Centros"
            icon={<School size={18} />}
            active={page === 'centro'}
            onClick={() => navigate('dashboard')}
          />
        </>
      }
    >
      {renderPage()}
    </PanelShell>
  );
}
