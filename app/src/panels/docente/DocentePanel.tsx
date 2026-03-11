import { useState } from 'react';
import { LayoutDashboard, FileText, BookOpen } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { YearSelector } from '@/components/domain/YearSelector';
import { NotificationBell } from '@/components/domain/NotificationBell';
import { Modal } from '@/components/ui/Modal';
import { usePanel } from '@/hooks/usePanel';
import { useYearStore } from '@/stores/useYearStore';
import { ResumenPage } from './pages/ResumenPage';
import { FormulariosPage } from './pages/FormulariosPage';
import { MatriculaPage } from './pages/MatriculaPage';

const NAV_ITEMS = [
  { page: 'resumen', label: 'Resumen', icon: <LayoutDashboard size={18} /> },
  { page: 'formularios', label: 'Formularios', icon: <FileText size={18} /> },
  { page: 'matricula', label: 'Matrícula', icon: <BookOpen size={18} /> },
];

export function DocentePanel() {
  const { page, params, navigate } = usePanel('resumen');
  const year = useYearStore((s) => s.selectedYear);
  const [feedbackModal, setFeedbackModal] = useState<string | null>(null);

  function handleFillForm(retoId: number) {
    navigate('formularios', { reto_id: String(retoId) });
  }

  function renderPage() {
    switch (page) {
      case 'resumen':
        return (
          <ResumenPage
            onFillForm={handleFillForm}
            onReopen={() => {}}
            onViewFeedback={(notes) => setFeedbackModal(notes)}
          />
        );
      case 'formularios':
        return <FormulariosPage retoId={params.reto_id ? Number(params.reto_id) : undefined} />;
      case 'matricula':
        return <MatriculaPage />;
      default:
        return (
          <ResumenPage
            onFillForm={handleFillForm}
            onReopen={() => {}}
            onViewFeedback={(notes) => setFeedbackModal(notes)}
          />
        );
    }
  }

  return (
    <PanelShell
      title="Panel Docente"
      subtitle={`Año ${year}`}
      topBarActions={
        <>
          <YearSelector />
          <NotificationBell />
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
              onClick={navigate}
            />
          ))}
        </>
      }
    >
      {renderPage()}

      <Modal open={feedbackModal !== null} onClose={() => setFeedbackModal(null)} title="Feedback del Supervisor">
        <p>{feedbackModal}</p>
      </Modal>
    </PanelShell>
  );
}
