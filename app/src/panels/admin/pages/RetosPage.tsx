import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { useYearStore } from '@/stores/useYearStore';
import { Spinner } from '@/components/ui/Spinner';
import { Card } from '@/components/ui/Card';
import { ProgressBar } from '@/components/ui/ProgressBar';

export function RetosPage() {
  const year = useYearStore((s) => s.selectedYear);

  const { data: retos, isLoading } = useQuery({
    queryKey: ['admin-retos', year],
    queryFn: () => adminApi.getRetos(year),
  });

  if (isLoading) return <Spinner />;

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Estadísticas de Retos</h2>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-4)' }}>
        {(retos ?? []).map((reto) => (
          <Card key={reto.id}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 'var(--gnf-space-3)' }}>
              <strong>{reto.titulo}</strong>
              <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{reto.total} participantes</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 'var(--gnf-space-3)', fontSize: '0.8125rem' }}>
              <div>
                <span style={{ color: '#16a34a' }}>Aprobados: {reto.aprobados}</span>
                <ProgressBar value={reto.aprobados} max={reto.total} color="#22c55e" height={4} />
              </div>
              <div>
                <span style={{ color: '#d97706' }}>Enviados: {reto.enviados}</span>
                <ProgressBar value={reto.enviados} max={reto.total} color="var(--gnf-sun)" height={4} />
              </div>
              <div>
                <span style={{ color: '#dc2626' }}>Corrección: {reto.correccion}</span>
                <ProgressBar value={reto.correccion} max={reto.total} color="var(--gnf-coral)" height={4} />
              </div>
              <div>
                <span style={{ color: 'var(--gnf-ocean)' }}>En progreso: {reto.enProgreso}</span>
                <ProgressBar value={reto.enProgreso} max={reto.total} color="var(--gnf-ocean)" height={4} />
              </div>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
