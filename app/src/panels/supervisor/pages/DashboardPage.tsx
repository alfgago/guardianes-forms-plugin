import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { supervisorApi } from '@/api/supervisor';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { CircuitoFilter } from '@/components/domain/CircuitoFilter';
import { CentroTable } from '../components/CentroTable';
import { School, Clock, CheckCircle2, AlertCircle } from 'lucide-react';

interface DashboardPageProps {
  onViewCentro: (centroId: number) => void;
}

export function DashboardPage({ onViewCentro }: DashboardPageProps) {
  const year = useYearStore((s) => s.selectedYear);
  const [circuito, setCircuito] = useState('');

  const { data: dashboard, isLoading: loadingStats } = useQuery({
    queryKey: ['supervisor-dashboard', year],
    queryFn: () => supervisorApi.getDashboard(year),
  });

  const { data: allCentros, isLoading: loadingAllCentros } = useQuery({
    queryKey: ['supervisor-centros', year, 'all'],
    queryFn: () => supervisorApi.getCentros(year),
  });

  const { data: centros, isLoading: loadingCentros } = useQuery({
    queryKey: ['supervisor-centros', year, circuito],
    queryFn: () => supervisorApi.getCentros(year, circuito || undefined),
  });

  if (loadingStats) return <Spinner />;

  const stats = dashboard?.stats;
  const circuitos = [...new Set((allCentros ?? []).map((c) => c.circuito).filter(Boolean) as string[])].sort();

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-6)' }}>
        <div>
          <h2>Escritorio</h2>
          <p style={{ color: 'var(--gnf-muted)' }}>Año {year} | {dashboard?.regionName}</p>
        </div>
      </div>

      {stats && (
        <StatsGrid>
          <StatCard label="Centros" value={stats.centros} icon={<School size={24} />} color="#0369a1" bg="#e0f2fe" />
          <StatCard label="Pendientes" value={stats.enviados} icon={<Clock size={24} />} color="#d97706" bg="var(--gnf-sun-light)" />
          <StatCard label="Aprobados" value={stats.aprobados} icon={<CheckCircle2 size={24} />} color="#16a34a" bg="#dcfce7" />
          <StatCard label="Corrección" value={stats.correccion} icon={<AlertCircle size={24} />} color="#dc2626" bg="var(--gnf-coral-light)" />
        </StatsGrid>
      )}

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 'var(--gnf-space-4)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-4)' }}>
        <div>
          <h3 style={{ marginBottom: 'var(--gnf-space-1)' }}>Centros Educativos</h3>
          <p style={{ margin: 0, color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
            {circuito ? `Filtrando por circuito ${circuito}.` : 'Vista completa de la DRE.'}
          </p>
        </div>
        {loadingAllCentros ? null : circuitos.length > 0 ? (
          <div style={{ display: 'grid', gap: 6 }}>
            <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--gnf-muted)', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
              Filtrar por circuito
            </span>
            <CircuitoFilter circuitos={circuitos} value={circuito} onChange={setCircuito} />
          </div>
        ) : (
          <span style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>No hay circuitos cargados para filtrar.</span>
        )}
      </div>

      {loadingCentros ? (
        <Spinner />
      ) : (
        <CentroTable centros={centros ?? []} onViewDetail={onViewCentro} />
      )}
    </div>
  );
}
