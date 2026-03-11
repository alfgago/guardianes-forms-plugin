import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { StatsGrid } from '@/components/data/StatsGrid';
import { StatCard } from '@/components/data/StatCard';
import { Card } from '@/components/ui/Card';
import { School, Users, Clock, CheckCircle2, AlertCircle, BookOpen } from 'lucide-react';

export function InicioPage() {
  const year = useYearStore((s) => s.selectedYear);

  const { data: stats, isLoading } = useQuery({
    queryKey: ['admin-stats', year],
    queryFn: () => adminApi.getStats(year),
  });

  if (isLoading) return <Spinner />;

  return (
    <div>
      <Card style={{ background: 'linear-gradient(135deg, var(--gnf-forest) 0%, var(--gnf-ocean) 100%)', color: 'var(--gnf-white)', marginBottom: 'var(--gnf-space-6)' }}>
        <h2 style={{ color: 'var(--gnf-white)', margin: '0 0 var(--gnf-space-2)' }}>Bienvenido al Panel Administrativo</h2>
        <p style={{ opacity: 0.85, margin: 0 }}>Gestión del programa Bandera Azul Ecológica | Año {year}</p>
      </Card>

      {stats && (
        <StatsGrid columns={3}>
          <StatCard label="Centros activos" value={stats.centros} icon={<School size={24} />} color="#0369a1" bg="#e0f2fe" />
          <StatCard label="Total usuarios" value={stats.totalUsers} icon={<Users size={24} />} color="var(--gnf-forest)" bg="#dcfce7" />
          <StatCard label="Usuarios pendientes" value={stats.pendingUsers} icon={<Clock size={24} />} color="#d97706" bg="var(--gnf-sun-light)" />
          <StatCard label="Enviados" value={stats.enviados} icon={<BookOpen size={24} />} color="var(--gnf-ocean)" bg="#e0f2fe" />
          <StatCard label="Aprobados" value={stats.aprobados} icon={<CheckCircle2 size={24} />} color="#16a34a" bg="#dcfce7" />
          <StatCard label="En corrección" value={stats.correccion} icon={<AlertCircle size={24} />} color="#dc2626" bg="var(--gnf-coral-light)" />
        </StatsGrid>
      )}
    </div>
  );
}
