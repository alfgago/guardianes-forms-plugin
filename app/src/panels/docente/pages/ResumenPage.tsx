import { useQuery } from '@tanstack/react-query';
import { retosApi } from '@/api/retos';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { CentroCard } from '@/components/domain/CentroCard';
import { ProgressHero } from '../components/ProgressHero';
import { RetoGrid } from '../components/RetoGrid';
import type { RetoWithEntry } from '@/types';

interface ResumenPageProps {
  onFillForm: (retoId: number) => void;
  onReopen: (retoId: number) => void;
  onViewFeedback: (notes: string) => void;
}

export function ResumenPage({ onFillForm, onReopen, onViewFeedback }: ResumenPageProps) {
  const year = useYearStore((s) => s.selectedYear);

  const { data: dashboard, isLoading: loadingDashboard } = useQuery({
    queryKey: ['docente-dashboard', year],
    queryFn: () => retosApi.getDashboard(year),
  });

  const { data: retos, isLoading: loadingRetos } = useQuery({
    queryKey: ['docente-retos', year],
    queryFn: () => retosApi.getRetos(year),
  });

  if (loadingDashboard || loadingRetos) return <Spinner />;

  if (!dashboard) return <Alert variant="error">Error al cargar los datos del panel.</Alert>;

  if (dashboard.docenteEstado === 'pendiente') {
    return (
      <Alert variant="warning" title="Acceso pendiente">
        Tu acceso para gestionar este centro educativo esta pendiente de aprobacion administrativa. En cuanto se apruebe
        podras continuar con la matricula y los eco retos.
      </Alert>
    );
  }

  return (
    <div>
      <CentroCard
        centro={{
          id: dashboard.centro.id,
          nombre: dashboard.centro.nombre,
          codigoMep: dashboard.centro.codigoMep,
          regionName: dashboard.centro.regionName,
          regionId: 0,
        }}
      />

      <div style={{ marginTop: 'var(--gnf-space-6)' }}>
        <ProgressHero
          metaEstrellas={dashboard.metaEstrellas}
          estrellaFinal={dashboard.estrellaFinal}
          anio={year}
          retosCount={dashboard.retosCount}
          aprobados={dashboard.aprobados}
          enviados={dashboard.enviados}
          correccion={dashboard.correccion}
          enProgreso={dashboard.enProgreso}
          puntajeTotal={dashboard.puntajeTotal}
        />
      </div>

      <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Retos de mi centro educativo</h3>
      <RetoGrid
        retos={(retos ?? []) as RetoWithEntry[]}
        onFillForm={onFillForm}
        onReopen={onReopen}
        onViewFeedback={onViewFeedback}
      />
    </div>
  );
}
