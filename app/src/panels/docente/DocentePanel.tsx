import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { LayoutDashboard, FileText, BookOpen, Bell } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { YearSelector } from '@/components/domain/YearSelector';
import { NotificationBell } from '@/components/domain/NotificationBell';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { usePanel } from '@/hooks/usePanel';
import { useTrackPageView } from '@/hooks/useTrackPageView';
import { useBootstrapNotifications } from '@/hooks/useBootstrapNotifications';
import { useYearStore } from '@/stores/useYearStore';
import { useNotificationStore } from '@/stores/useNotificationStore';
import { retosApi } from '@/api/retos';
import { ResumenPage } from './pages/ResumenPage';
import { FormulariosPage } from './pages/FormulariosPage';
import { MatriculaPage } from './pages/MatriculaPage';
import { NotificacionesPage } from '@/panels/supervisor/pages/NotificacionesPage';

const NAV_ITEMS = [
  { page: 'resumen', label: 'Resumen', icon: <LayoutDashboard size={18} /> },
  { page: 'formularios', label: 'Eco retos', icon: <FileText size={18} /> },
  { page: 'matricula', label: 'Matricula', icon: <BookOpen size={18} /> },
  { page: 'notificaciones', label: 'Notificaciones', icon: <Bell size={18} />, badgeKey: 'notifications' as const },
];

export function DocentePanel() {
  const { page, params, navigate } = usePanel('resumen');
  const year = useYearStore((s) => s.selectedYear);
  const unreadCount = useNotificationStore((s) => s.unreadCount);
  const queryClient = useQueryClient();
  const [feedbackModal, setFeedbackModal] = useState<string | null>(null);
  useTrackPageView({ panel: 'docente', page, year });
  useBootstrapNotifications();

  const dashboardQuery = useQuery({
    queryKey: ['docente-dashboard', year],
    queryFn: () => retosApi.getDashboard(year),
  });

  useEffect(() => {
    if (!dashboardQuery.data) return;
    if (dashboardQuery.data.tieneMatricula) return;
    if (page === 'matricula') return;

    navigate('matricula');
  }, [dashboardQuery.data, navigate, page]);

  const mustCompleteMatriculaFirst = dashboardQuery.data ? !dashboardQuery.data.tieneMatricula && page !== 'matricula' : false;

  const reopenMutation = useMutation({
    mutationFn: (retoId: number) => retosApi.reopenReto(retoId, year),
    onSuccess: (_data, retoId) => {
      queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });
      queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
      queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
      navigate('formularios', { reto_id: String(retoId) });
    },
  });

  function handleFillForm(retoId: number) {
    navigate('formularios', { reto_id: String(retoId) });
  }

  function handleReopen(retoId: number) {
    reopenMutation.mutate(retoId);
  }

  function renderPage() {
    switch (page) {
      case 'resumen':
        return (
          <ResumenPage
            onFillForm={handleFillForm}
            onReopen={handleReopen}
            onViewFeedback={(notes) => setFeedbackModal(notes)}
          />
        );
      case 'formularios':
        return <FormulariosPage retoId={params.reto_id ? Number(params.reto_id) : undefined} />;
      case 'matricula':
        return <MatriculaPage />;
      case 'notificaciones':
        return <NotificacionesPage />;
      default:
        return (
          <ResumenPage
            onFillForm={handleFillForm}
            onReopen={handleReopen}
            onViewFeedback={(notes) => setFeedbackModal(notes)}
          />
        );
    }
  }

  return (
    <PanelShell
      title="Panel Centro Educativo"
      subtitle={`Año ${year}`}
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
        </>
      }
    >
      {dashboardQuery.isLoading && page !== 'matricula' && <Spinner />}
      {mustCompleteMatriculaFirst && <Spinner />}
      {dashboardQuery.isError && page !== 'matricula' && (
        <Alert variant="warning">No se pudo validar la matricula del centro. Puedes completarla desde esta seccion.</Alert>
      )}
      {reopenMutation.isError && <Alert variant="error">No se pudo reabrir el reto. Intenta de nuevo.</Alert>}

      {!mustCompleteMatriculaFirst && (!dashboardQuery.isLoading || page === 'matricula') && renderPage()}

      <Modal open={feedbackModal !== null} onClose={() => setFeedbackModal(null)} title="Observacion de revision">
        <p>{feedbackModal}</p>
      </Modal>
    </PanelShell>
  );
}
