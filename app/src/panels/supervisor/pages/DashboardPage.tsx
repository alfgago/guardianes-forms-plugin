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

  const { data: centros, isLoading: loadingCentros } = useQuery({
    queryKey: ['supervisor-centros', year, circuito],
    queryFn: () => supervisorApi.getCentros(year, circuito || undefined),
  });

  if (loadingStats) return <Spinner />;

  const stats = dashboard?.stats;
  const circuitos = [...new Set((centros ?? []).map((c) => c.circuito).filter(Boolean) as string[])].sort();

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-6)' }}>
        <div>
          <h2>Dashboard</h2>
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

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-4)' }}>
        <h3>Centros Educativos</h3>
        {circuitos.length > 1 && (
          <CircuitoFilter circuitos={circuitos} value={circuito} onChange={setCircuito} />
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
