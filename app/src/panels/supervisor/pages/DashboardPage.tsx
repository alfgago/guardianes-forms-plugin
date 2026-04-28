import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { School, Clock, CheckCircle2, AlertCircle } from 'lucide-react';
import { supervisorApi } from '@/api/supervisor';
import { get } from '@/api/client';
import { useYearStore } from '@/stores/useYearStore';
import { useAuthStore } from '@/stores/useAuthStore';
import { Spinner } from '@/components/ui/Spinner';
import { Input } from '@/components/ui/Input';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { CircuitoFilter } from '@/components/domain/CircuitoFilter';
import { RegionFilter } from '@/components/domain/RegionFilter';
import { CentroTable } from '../components/CentroTable';
import type { Region } from '@/types';

interface DashboardPageProps {
  onViewCentro: (centroId: number) => void;
}

function FilterField({ label, children, minWidth = 180 }: { label: string; children: ReactNode; minWidth?: number }) {
  return (
    <div style={{ display: 'grid', gap: 6, minWidth }}>
      <span
        style={{
          fontSize: '0.75rem',
          fontWeight: 700,
          color: 'var(--gnf-muted)',
          textTransform: 'uppercase',
          letterSpacing: '0.04em',
        }}
      >
        {label}
      </span>
      {children}
    </div>
  );
}

export function DashboardPage({ onViewCentro }: DashboardPageProps) {
  const year = useYearStore((s) => s.selectedYear);
  const user = useAuthStore((s) => s.user);
  const [circuito, setCircuito] = useState('');
  const [region, setRegion] = useState('');
  const [search, setSearch] = useState('');

  const isComite = !!user?.roles.includes('comite_bae');
  const assignedRegionIds = useMemo(() => {
    if (user?.regionIds?.length) {
      return user.regionIds;
    }
    if (user?.regionId) {
      return [user.regionId];
    }
    return [];
  }, [user?.regionId, user?.regionIds]);
  const canFilterByRegion = isComite && assignedRegionIds.length > 1;
  const selectedRegion = canFilterByRegion && region ? Number(region) : undefined;

  const { data: regionOptions } = useQuery({
    queryKey: ['supervisor-region-options'],
    queryFn: () => get<Region[]>('/regions'),
    enabled: canFilterByRegion,
  });

  const availableRegions = useMemo(
    () => (regionOptions ?? []).filter((item) => assignedRegionIds.includes(item.id)),
    [assignedRegionIds, regionOptions],
  );

  useEffect(() => {
    if (!canFilterByRegion && region) {
      setRegion('');
    }
  }, [canFilterByRegion, region]);

  const { data: dashboard, isLoading: loadingStats } = useQuery({
    queryKey: ['supervisor-dashboard', year, selectedRegion ?? 'all'],
    queryFn: () => supervisorApi.getDashboard(year, selectedRegion),
  });

  const { data: scopedCentros, isLoading: loadingScopedCentros } = useQuery({
    queryKey: ['supervisor-centros', year, 'scope', selectedRegion ?? 'all'],
    queryFn: () => supervisorApi.getCentros(year, undefined, selectedRegion),
  });

  const circuitos = useMemo(
    () => [...new Set((scopedCentros ?? []).map((centro) => centro.circuito).filter(Boolean) as string[])].sort(),
    [scopedCentros],
  );

  useEffect(() => {
    if (circuito && !circuitos.includes(circuito)) {
      setCircuito('');
    }
  }, [circuito, circuitos]);

  const { data: circuitoCentros, isLoading: loadingCircuitoCentros } = useQuery({
    queryKey: ['supervisor-centros', year, selectedRegion ?? 'all', circuito || 'all'],
    queryFn: () => supervisorApi.getCentros(year, circuito || undefined, selectedRegion),
    enabled: !!circuito,
  });

  const centrosBase = circuito ? (circuitoCentros ?? []) : (scopedCentros ?? []);
  const loadingCentros = circuito ? loadingCircuitoCentros : loadingScopedCentros;

  const centros = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) {
      return centrosBase;
    }

    return centrosBase.filter((centro) => (
      centro.nombre.toLowerCase().includes(query)
      || (centro.codigoMep ?? '').toLowerCase().includes(query)
      || (centro.regionName ?? '').toLowerCase().includes(query)
      || (centro.circuito ?? '').toLowerCase().includes(query)
    ));
  }, [centrosBase, search]);

  const regionSummary = useMemo(() => {
    if (canFilterByRegion) {
      return region
        ? availableRegions.find((item) => String(item.id) === region)?.name ?? dashboard?.regionName ?? ''
        : 'Ver todo';
    }

    return dashboard?.regionName ?? user?.regionNames?.join(', ') ?? '';
  }, [availableRegions, canFilterByRegion, dashboard?.regionName, region, user?.regionNames]);

  if (loadingStats) return <Spinner />;

  const stats = dashboard?.stats;
  const hasRegionFilter = canFilterByRegion && availableRegions.length > 0;
  const emptyMessage = search.trim()
    ? 'No hay centros que coincidan con la búsqueda.'
    : circuito || region
      ? 'No hay centros para los filtros actuales.'
      : 'No hay centros con matrícula activa.';

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-6)' }}>
        <div>
          <h2>Escritorio</h2>
          <p style={{ color: 'var(--gnf-muted)' }}>Año {year}{regionSummary ? ` | ${regionSummary}` : ''}</p>
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

      <div style={{ display: 'grid', gap: 'var(--gnf-space-3)', marginBottom: 'var(--gnf-space-4)' }}>
        <div>
          <h3 style={{ marginBottom: 'var(--gnf-space-1)' }}>Centros Educativos</h3>
          <p style={{ margin: 0, color: 'var(--gnf-muted)', fontSize: '0.875rem' }}>
            {[
              regionSummary ? `Región: ${regionSummary}` : '',
              circuito ? `Circuito ${circuito}` : '',
              search.trim() ? `Búsqueda: "${search.trim()}"` : '',
            ].filter(Boolean).join(' • ') || 'Vista general de los centros asignados.'}
          </p>
        </div>

        <div
          style={{
            display: 'flex',
            gap: 'var(--gnf-space-3)',
            flexWrap: 'wrap',
            alignItems: 'flex-end',
            padding: 'var(--gnf-space-4)',
            border: '1px solid var(--gnf-border)',
            borderRadius: 'var(--gnf-radius)',
            background: 'rgba(255, 255, 255, 0.86)',
          }}
        >
          <div style={{ flex: '1 1 320px', minWidth: 280 }}>
            <Input
              label="Buscar"
              placeholder="Centro, código MEP, región o circuito..."
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              style={{ marginBottom: 0 }}
            />
          </div>

          {circuitos.length > 0 && (
            <FilterField label="Circuito" minWidth={200}>
              <CircuitoFilter circuitos={circuitos} value={circuito} onChange={setCircuito} />
            </FilterField>
          )}

          {hasRegionFilter && (
            <FilterField label="Región" minWidth={220}>
              <RegionFilter
                regions={availableRegions}
                value={region}
                onChange={setRegion}
                allLabel="Ver todo"
              />
            </FilterField>
          )}
        </div>
      </div>

      {loadingCentros ? (
        <Spinner />
      ) : (
        <CentroTable centros={centros} onViewDetail={onViewCentro} emptyMessage={emptyMessage} />
      )}
    </div>
  );
}
