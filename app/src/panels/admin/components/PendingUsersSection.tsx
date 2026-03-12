import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Eye, Pencil, UserCheck, UserX, Users } from 'lucide-react';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { useToast } from '@/components/ui/Toast';
import { adminApi } from '@/api/admin';
import { ROLE_LABELS } from '@/utils/constants';
import { formatDate } from '@/utils/formatters';
import type { PendingUser } from '@/types';

interface PendingUsersSectionProps {
  users: PendingUser[];
  onEdit?: (user: PendingUser) => void;
}

export function PendingUsersSection({ users, onEdit }: PendingUsersSectionProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const approve = useMutation({
    mutationFn: (userId: number) => adminApi.approveUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-pending-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats'] });
      toast('success', 'Usuario aprobado.');
    },
  });

  const reject = useMutation({
    mutationFn: (userId: number) => adminApi.rejectUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-pending-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats'] });
      toast('info', 'Usuario rechazado.');
    },
  });

  if (users.length === 0) {
    return <EmptyState icon={<Users size={48} />} title="No hay usuarios para mostrar" />;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
      {users.map((user) => (
        <Card key={user.id} style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)' }}>
          <div style={{ flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
              <strong>{user.name}</strong>
              <Badge>{ROLE_LABELS[user.role]}</Badge>
              <Badge
                color={user.status === 'pendiente' ? '#b45309' : '#166534'}
                bg={user.status === 'pendiente' ? 'rgba(245, 158, 11, 0.12)' : 'rgba(34, 197, 94, 0.12)'}
              >
                {user.status === 'pendiente' ? 'Pendiente' : 'Activo'}
              </Badge>
            </div>
            <div style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', marginTop: 2 }}>
              {user.email} | Registrado: {formatDate(user.registeredAt)}
              {user.centroName && <> | Centro: {user.centroName}</>}
              {user.regionName && <> | Region: {user.regionName}</>}
            </div>
          </div>

          <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
            {onEdit && (
              <Button variant="outline" size="sm" icon={<Pencil size={14} />} onClick={() => onEdit(user)}>
                Editar
              </Button>
            )}

            {user.status === 'pendiente' && (
              <>
                <Button
                  size="sm"
                  icon={<UserCheck size={14} />}
                  loading={approve.isPending}
                  onClick={() => approve.mutate(user.id)}
                >
                  Aprobar
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  icon={<UserX size={14} />}
                  loading={reject.isPending}
                  onClick={() => reject.mutate(user.id)}
                >
                  Rechazar
                </Button>
              </>
            )}

            {user.canImpersonate && user.impersonateUrl && (
              <Button
                variant="outline"
                size="sm"
                icon={<Eye size={14} />}
                onClick={() => {
                  window.location.href = user.impersonateUrl!;
                }}
              >
                Ver como
              </Button>
            )}
          </div>
        </Card>
      ))}
    </div>
  );
}
