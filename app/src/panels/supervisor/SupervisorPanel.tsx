import { AlertTriangle, Bell, Clock3, LayoutDashboard, School } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { Alert } from '@/components/ui/Alert';
import { Card } from '@/components/ui/Card';
import { NotificationBell } from '@/components/domain/NotificationBell';
import { YearSelector } from '@/components/domain/YearSelector';
import { usePanel } from '@/hooks/usePanel';
import { useTrackPageView } from '@/hooks/useTrackPageView';
import { useBootstrapNotifications } from '@/hooks/useBootstrapNotifications';
import { useAuthStore } from '@/stores/useAuthStore';
import { useYearStore } from '@/stores/useYearStore';
import { useNotificationStore } from '@/stores/useNotificationStore';
import type { User } from '@/types';
import { DashboardPage } from './pages/DashboardPage';
import { CentroDetailPage } from './pages/CentroDetailPage';
import { NotificacionesPage } from './pages/NotificacionesPage';

const NAV_ITEMS = [
  { page: 'dashboard', label: 'Escritorio', icon: <LayoutDashboard size={18} /> },
  { page: 'notificaciones', label: 'Notificaciones', icon: <Bell size={18} />, badgeKey: 'notifications' as const },
];

function SupervisorPendingPanel({ user }: { user: User | null }) {
  const year = useYearStore((s) => s.selectedYear);
  const isRejected = user?.estado === 'rechazado';
  const isComite = user?.roles.includes('comite_bae') ?? false;
  const roleLabel = isComite ? 'comite BAE' : 'supervisor';
  const regionLabel = user?.regionNames?.length
    ? user.regionNames.join(', ')
    : user?.regionId
      ? `Region ${user.regionId}`
      : 'Sin region asignada';

  return (
    <PanelShell
      title={isComite ? 'Panel Comite BAE' : 'Panel DRE'}
      subtitle={isRejected ? 'Solicitud rechazada' : `Ano ${year} · Pendiente de autorizacion`}
      nav={null}
    >
      <div style={{ display: 'grid', gap: 'var(--gnf-space-4)', maxWidth: 780 }}>
        <Alert variant={isRejected ? 'error' : 'warning'} title={isRejected ? 'Cuenta rechazada' : 'Cuenta pendiente de autorizacion'}>
          {isRejected
            ? `Tu solicitud de ${roleLabel} fue rechazada. Contacta a un administrador si necesitas que revisen el caso.`
            : `Tu solicitud de ${roleLabel} fue recibida, pero un administrador debe autorizarla manualmente antes de habilitar el acceso a centros, retos y notificaciones.`}
        </Alert>

        <Card>
          <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', alignItems: 'flex-start' }}>
            <div
              style={{
                width: 56,
                height: 56,
                borderRadius: '50%',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: isRejected ? 'rgba(239, 68, 68, 0.12)' : 'rgba(245, 158, 11, 0.12)',
                color: isRejected ? 'var(--gnf-coral)' : '#b45309',
                flexShrink: 0,
              }}
            >
              {isRejected ? <AlertTriangle size={24} /> : <Clock3 size={24} />}
            </div>

            <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
              <div>
                <h2 style={{ margin: 0, color: 'var(--gnf-ocean-dark)' }}>
                  {isRejected ? 'Acceso de revision no habilitado' : 'Estamos esperando la aprobacion del administrador'}
                </h2>
                <p style={{ margin: 'var(--gnf-space-2) 0 0', color: 'var(--gnf-muted)' }}>
                  {isRejected
                    ? 'Mientras la cuenta permanezca rechazada, este panel no mostrara informacion de centros ni herramientas de revision.'
                    : 'Mientras la cuenta este pendiente, este panel quedara bloqueado y no mostrara informacion sensible.'}
                </p>
              </div>

              <div
                style={{
                  display: 'grid',
                  gap: 'var(--gnf-space-2)',
                  padding: 'var(--gnf-space-4)',
                  background: 'rgba(54, 148, 132, 0.06)',
                  borderRadius: 'var(--gnf-radius)',
                  border: '1px solid var(--gnf-border)',
                }}
              >
                <div><strong>Cuenta:</strong> {user?.email ?? 'Sin correo disponible'}</div>
                <div><strong>Rol solicitado:</strong> {isComite ? 'Comite BAE' : 'Supervisor'}</div>
                <div><strong>Region:</strong> {regionLabel}</div>
                <div><strong>Estado:</strong> {isRejected ? 'Rechazada' : 'Pendiente de autorizacion'}</div>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </PanelShell>
  );
}

function ActiveSupervisorPanel() {
  const { page, params, navigate } = usePanel('dashboard');
  const year = useYearStore((s) => s.selectedYear);
  const unreadCount = useNotificationStore((s) => s.unreadCount);

  useTrackPageView({ panel: 'supervisor', page, year });
  useBootstrapNotifications();

  function renderPage() {
    if (page === 'centro' && params.centro_id) {
      return <CentroDetailPage centroId={Number(params.centro_id)} onBack={() => navigate('dashboard')} />;
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
      title="Panel DRE"
      subtitle={`Ano ${year}`}
      sidebarFooterExtra={<YearSelector />}
      topBarActions={<NotificationBell onClick={() => navigate('notificaciones')} />}
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
            page="centros"
            label="Centros"
            icon={<School size={18} />}
            active={page === 'dashboard' || page === 'centro'}
            onClick={() => navigate('dashboard')}
          />
        </>
      }
    >
      {renderPage()}
    </PanelShell>
  );
}

export function SupervisorPanel() {
  const user = useAuthStore((s) => s.user);

  if (user?.estado === 'pendiente' || user?.estado === 'rechazado') {
    return <SupervisorPendingPanel user={user} />;
  }

  return <ActiveSupervisorPanel />;
}
