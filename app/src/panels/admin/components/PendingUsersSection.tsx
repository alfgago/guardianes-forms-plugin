import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { useToast } from '@/components/ui/Toast';
import { adminApi } from '@/api/admin';
import { ROLE_LABELS } from '@/utils/constants';
import { formatDate } from '@/utils/formatters';
import { UserCheck, UserX, Users } from 'lucide-react';
import type { PendingUser } from '@/types';

interface PendingUsersSectionProps {
  users: PendingUser[];
}

export function PendingUsersSection({ users }: PendingUsersSectionProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const approve = useMutation({
    mutationFn: (userId: number) => adminApi.approveUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-pending-users'] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats'] });
      toast('success', 'Usuario aprobado.');
    },
  });

  const reject = useMutation({
    mutationFn: (userId: number) => adminApi.rejectUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-pending-users'] });
      toast('info', 'Usuario rechazado.');
    },
  });

  if (users.length === 0) {
    return <EmptyState icon={<Users size={48} />} title="Sin usuarios pendientes" />;
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
      {users.map((u) => (
        <Card key={u.id} style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-4)' }}>
          <div style={{ flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)' }}>
              <strong>{u.name}</strong>
              <Badge>{ROLE_LABELS[u.role]}</Badge>
            </div>
            <div style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)', marginTop: 2 }}>
              {u.email} | Registrado: {formatDate(u.registeredAt)}
              {u.centroName && <> | Centro: {u.centroName}</>}
              {u.regionName && <> | Región: {u.regionName}</>}
            </div>
          </div>
          <div style={{ display: 'flex', gap: 'var(--gnf-space-2)' }}>
            <Button
              size="sm"
              icon={<UserCheck size={14} />}
              loading={approve.isPending}
              onClick={() => approve.mutate(u.id)}
            >
              Aprobar
            </Button>
            <Button
              variant="danger"
              size="sm"
              icon={<UserX size={14} />}
              loading={reject.isPending}
              onClick={() => reject.mutate(u.id)}
            >
              Rechazar
            </Button>
          </div>
        </Card>
      ))}
    </div>
  );
}
