import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from '@/api/notifications';
import { useNotificationStore } from '@/stores/useNotificationStore';
import { Spinner } from '@/components/ui/Spinner';
import { EmptyState } from '@/components/ui/EmptyState';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { formatDateTime } from '@/utils/formatters';
import { Bell, Check } from 'lucide-react';
import { useEffect } from 'react';

export function NotificacionesPage() {
  const setNotifications = useNotificationStore((s) => s.setNotifications);
  const markRead = useNotificationStore((s) => s.markRead);
  const queryClient = useQueryClient();

  const { data: notifications, isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.getAll(),
  });

  useEffect(() => {
    if (notifications) setNotifications(notifications);
  }, [notifications, setNotifications]);

  const markMutation = useMutation({
    mutationFn: (id: number) => notificationsApi.markAsRead(id),
    onSuccess: (_, id) => {
      markRead(id);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  if (isLoading) return <Spinner />;

  if (!notifications?.length) {
    return <EmptyState icon={<Bell size={48} />} title="Sin notificaciones" description="No hay notificaciones pendientes." />;
  }

  return (
    <div>
      <h2 style={{ marginBottom: 'var(--gnf-space-6)' }}>Notificaciones</h2>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
        {notifications.map((n) => (
          <Card
            key={n.id}
            style={{
              display: 'flex',
              alignItems: 'flex-start',
              gap: 'var(--gnf-space-4)',
              opacity: n.leido ? 0.6 : 1,
            }}
          >
            <div style={{ flex: 1 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginBottom: 4 }}>
                <Badge>{n.tipo}</Badge>
                <time style={{ fontSize: '0.75rem', color: 'var(--gnf-gray-400)' }}>
                  {formatDateTime(n.createdAt)}
                </time>
              </div>
              <p style={{ margin: 0, fontSize: '0.9375rem' }}>{n.mensaje}</p>
            </div>
            {!n.leido && (
              <Button
                variant="ghost"
                size="sm"
                icon={<Check size={14} />}
                onClick={() => markMutation.mutate(n.id)}
                loading={markMutation.isPending}
              >
                Marcar leída
              </Button>
            )}
          </Card>
        ))}
      </div>
    </div>
  );
}
