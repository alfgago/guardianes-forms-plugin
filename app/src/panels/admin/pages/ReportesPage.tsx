import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Card } from '@/components/ui/Card';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { Button } from '@/components/ui/Button';
import { Download, School, Star, Award } from 'lucide-react';

export function ReportesPage() {
  const year = useYearStore((s) => s.selectedYear);

  const { data, isLoading } = useQuery({
    queryKey: ['admin-reports', year],
    queryFn: () => adminApi.getReports(year),
  });

  if (isLoading) return <Spinner />;

  const summary = data?.summary;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--gnf-space-6)' }}>
        <h2>Reportes</h2>
        <Button variant="outline" icon={<Download size={16} />}>
          Exportar CSV
        </Button>
      </div>

      {summary && (
        <StatsGrid>
          <StatCard label="Total centros" value={summary.totalCentros} icon={<School size={24} />} color="var(--gnf-ocean)" bg="#e0f2fe" />
          <StatCard label="Retos aprobados" value={summary.totalAprobados} icon={<Award size={24} />} color="#16a34a" bg="#dcfce7" />
          <StatCard label="Promedio estrellas" value={summary.promedioEstrellas.toFixed(1)} icon={<Star size={24} />} color="#d97706" bg="var(--gnf-sun-light)" />
          <StatCard label="Promedio puntaje" value={Math.round(summary.promedioPuntaje)} color="var(--gnf-forest)" bg="#dcfce7" />
        </StatsGrid>
      )}

      <Card>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Detalle por centro</h3>
        <p style={{ color: 'var(--gnf-muted)' }}>
          {data?.centros.length ?? 0} centros con matrícula activa en {year}.
        </p>
      </Card>
    </div>
  );
}
