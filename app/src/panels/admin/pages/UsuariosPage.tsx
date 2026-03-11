import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/api/admin';
import { Spinner } from '@/components/ui/Spinner';
import { PendingUsersSection } from '../components/PendingUsersSection';

export function UsuariosPage() {
  const { data: users, isLoading } = useQuery({
    queryKey: ['admin-pending-users'],
    queryFn: () => adminApi.getPendingUsers(),
  });

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Usuarios Pendientes</h2>
      {isLoading ? <Spinner /> : <PendingUsersSection users={users ?? []} />}
    </div>
  );
}
