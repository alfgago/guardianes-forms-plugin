import { LayoutDashboard, Users, School, BookOpen, BarChart3, Settings, ShieldCheck } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { YearSelector } from '@/components/domain/YearSelector';
import { usePanel } from '@/hooks/usePanel';
import { useTrackPageView } from '@/hooks/useTrackPageView';
import { useYearStore } from '@/stores/useYearStore';
import { InicioPage } from './pages/InicioPage';
import { UsuariosPage } from './pages/UsuariosPage';
import { CentrosPage } from './pages/CentrosPage';
import { RetosPage } from './pages/RetosPage';
import { ReportesPage } from './pages/ReportesPage';
import { ConfiguracionPage } from './pages/ConfiguracionPage';
import { CentroDetailPage } from './pages/CentroDetailPage';
import { AuditPage } from './pages/AuditPage';

const NAV_ITEMS = [
  { page: 'inicio', label: 'Inicio', icon: <LayoutDashboard size={18} /> },
  { page: 'usuarios', label: 'Usuarios', icon: <Users size={18} /> },
  { page: 'centros', label: 'Centros', icon: <School size={18} /> },
  { page: 'retos', label: 'Retos', icon: <BookOpen size={18} /> },
  { page: 'reportes', label: 'Reportes', icon: <BarChart3 size={18} /> },
  { page: 'auditoria', label: 'Auditoria', icon: <ShieldCheck size={18} /> },
  { page: 'configuracion', label: 'Regiones', icon: <Settings size={18} /> },
];

export function AdminPanel() {
  const { page, params, navigate } = usePanel('inicio');
  const year = useYearStore((state) => state.selectedYear);
  useTrackPageView({ panel: 'admin', page, year });

  function renderPage() {
    if (page === 'centro' && params.centro_id) {
      return <CentroDetailPage centroId={Number(params.centro_id)} onBack={() => navigate('centros')} />;
    }

    switch (page) {
      case 'inicio':
        return <InicioPage />;
      case 'usuarios':
        return <UsuariosPage />;
      case 'centros':
        return <CentrosPage onViewCentro={(id) => navigate('centro', { centro_id: String(id) })} />;
      case 'retos':
        return <RetosPage />;
      case 'reportes':
        return <ReportesPage />;
      case 'auditoria':
        return <AuditPage />;
      case 'configuracion':
        return <ConfiguracionPage />;
      default:
        return <InicioPage />;
    }
  }

  return (
    <PanelShell
      title="Panel Admin"
      subtitle={`Año ${year}`}
      sidebarFooterExtra={<YearSelector />}
      nav={
        <>
          {NAV_ITEMS.map((item) => (
            <SidebarLink
              key={item.page}
              page={item.page}
              label={item.label}
              icon={item.icon}
              active={page === item.page || (item.page === 'centros' && page === 'centro')}
              onClick={navigate}
            />
          ))}
        </>
      }
    >
      {renderPage()}
    </PanelShell>
  );
}
