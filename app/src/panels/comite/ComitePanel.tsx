import { School, History } from 'lucide-react';
import { PanelShell } from '@/components/layout/PanelShell';
import { SidebarLink } from '@/components/layout/SidebarLink';
import { YearSelector } from '@/components/domain/YearSelector';
import { usePanel } from '@/hooks/usePanel';
import { useTrackPageView } from '@/hooks/useTrackPageView';
import { useYearStore } from '@/stores/useYearStore';
import { CentrosPage } from './pages/CentrosPage';
import { CentroDetailPage } from './pages/CentroDetailPage';
import { HistorialPage } from './pages/HistorialPage';

const NAV_ITEMS = [
  { page: 'centros', label: 'Centros', icon: <School size={18} /> },
  { page: 'historial', label: 'Historial', icon: <History size={18} /> },
];

export function ComitePanel() {
  const { page, params, navigate } = usePanel('centros');
  const year = useYearStore((s) => s.selectedYear);
  useTrackPageView({ panel: 'comite', page, year });

  function renderPage() {
    if (page === 'centro' && params.centro_id) {
      return <CentroDetailPage centroId={Number(params.centro_id)} onBack={() => navigate('centros')} />;
    }

    switch (page) {
      case 'centros':
        return <CentrosPage onViewCentro={(id) => navigate('centro', { centro_id: String(id) })} />;
      case 'historial':
        return <HistorialPage />;
      default:
        return <CentrosPage onViewCentro={(id) => navigate('centro', { centro_id: String(id) })} />;
    }
  }

  return (
    <PanelShell
      title="Comite BAE"
      subtitle={`Ano ${year}`}
      sidebarFooterExtra={<YearSelector />}
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
    </PanelShell>
  );
}
