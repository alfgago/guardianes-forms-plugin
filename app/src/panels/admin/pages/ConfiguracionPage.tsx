import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { Spinner } from '@/components/ui/Spinner';
import { Card } from '@/components/ui/Card';
import { DreToggle } from '../components/DreToggle';

export function ConfiguracionPage() {
  const { data: dres, isLoading } = useQuery({
    queryKey: ['admin-dres'],
    queryFn: () => adminApi.getDres(),
  });

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Regiones</h2>

      <Card>
        <h3 style={{ marginBottom: 'var(--gnf-space-4)' }}>Direcciones Regionales de Educacion</h3>
        <p style={{ color: 'var(--gnf-muted)', fontSize: '0.875rem', marginBottom: 'var(--gnf-space-4)' }}>
          Activa o desactiva aqui las regiones que pueden participar en el programa.
        </p>
        {isLoading ? (
          <Spinner />
        ) : (
          <div>
            {(dres ?? []).map((dre) => (
              <DreToggle key={dre.id} dre={dre} />
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
