import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { comiteApi } from '@/api/comite';
import { get } from '@/api/client';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { RegionFilter } from '@/components/domain/RegionFilter';
import { CentroTable } from '../../supervisor/components/CentroTable';
import { School, CheckCircle2, Clock, AlertCircle } from 'lucide-react';
import type { Region } from '@/types';

interface CentrosPageProps {
  onViewCentro: (centroId: number) => void;
}

export function CentrosPage({ onViewCentro }: CentrosPageProps) {
  const year = useYearStore((s) => s.selectedYear);
  const [region, setRegion] = useState('');

  const { data: dashboard } = useQuery({
    queryKey: ['comite-dashboard', year],
    queryFn: () => comiteApi.getDashboard(year),
  });

  const { data: regions } = useQuery({
    queryKey: ['regions'],
    queryFn: () => get<Region[]>('/regions'),
  });

  const { data: centros, isLoading } = useQuery({
    queryKey: ['comite-centros', year, region],
    queryFn: () => comiteApi.getCentros(year, region || undefined),
  });

  const stats = dashboard?.stats;

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Centros Educativos</h2>

      {stats && (
        <StatsGrid>
          <StatCard label="Centros" value={stats.centros} icon={<School size={24} />} color="#0369a1" bg="#e0f2fe" />
          <StatCard label="Pendientes" value={stats.enviados} icon={<Clock size={24} />} color="#d97706" bg="var(--gnf-sun-light)" />
          <StatCard label="Aprobados" value={stats.aprobados} icon={<CheckCircle2 size={24} />} color="#16a34a" bg="#dcfce7" />
          <StatCard label="Corrección" value={stats.correccion} icon={<AlertCircle size={24} />} color="#dc2626" bg="var(--gnf-coral-light)" />
        </StatsGrid>
      )}

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-4)' }}>
        <h3>Todos los centros</h3>
        <RegionFilter regions={regions ?? []} value={region} onChange={setRegion} />
      </div>

      {isLoading ? <Spinner /> : <CentroTable centros={centros ?? []} onViewDetail={onViewCentro} />}
    </div>
  );
}
