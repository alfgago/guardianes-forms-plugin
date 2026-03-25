import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Bell, Check, CheckCheck, CheckCircle2, ExternalLink, MapPin, RotateCcw, School, TriangleAlert } from 'lucide-react';
import { notificationsApi } from '@/api/notifications';
import { supervisorApi } from '@/api/supervisor';
import { EmptyState } from '@/components/ui/EmptyState';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Textarea } from '@/components/ui/Textarea';
import { useToast } from '@/components/ui/Toast';
import { useNotificationStore } from '@/stores/useNotificationStore';
import { useYearStore } from '@/stores/useYearStore';
import { formatDateTime } from '@/utils/formatters';
import { navigateTo } from '@/utils/url';
import type { Notification, NotificationType } from '@/types';

const TYPE_META: Record<NotificationType, { label: string; color: string; bg: string }> = {
  correccion: { label: 'Corrección solicitada', color: '#b45309', bg: 'rgba(245, 158, 11, 0.14)' },
  aprobado: { label: 'Aprobado', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  invalid_photo_date: { label: 'Validación de año', color: '#b45309', bg: 'rgba(245, 158, 11, 0.14)' },
  matricula: { label: 'Matrícula', color: '#0f766e', bg: 'rgba(20, 184, 166, 0.14)' },
  general: { label: 'General', color: '#475569', bg: 'rgba(148, 163, 184, 0.18)' },
  participacion_enviada: { label: 'Participación enviada', color: '#1d4ed8', bg: 'rgba(59, 130, 246, 0.14)' },
  reto_enviado: { label: 'Reto enviado', color: '#1d4ed8', bg: 'rgba(59, 130, 246, 0.14)' },
  feedback_actualizado: { label: 'Feedback actualizado', color: '#7c3aed', bg: 'rgba(139, 92, 246, 0.14)' },
  cuenta_aprobada: { label: 'Cuenta aprobada', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  docente_aprobado: { label: 'Centro aprobado', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  docente_solicita_acceso: { label: 'Solicitud de acceso', color: '#1d4ed8', bg: 'rgba(59, 130, 246, 0.14)' },
  nueva_matricula: { label: 'Nueva matrícula', color: '#0f766e', bg: 'rgba(20, 184, 166, 0.14)' },
  validado: { label: 'Validado', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  rechazado: { label: 'Rechazado', color: '#b91c1c', bg: 'rgba(239, 68, 68, 0.14)' },
};

export function NotificacionesPage() {
  const setNotifications = useNotificationStore((state) => state.setNotifications);
  const markRead = useNotificationStore((state) => state.markRead);
  const markAllRead = useNotificationStore((state) => state.markAllRead);
  const year = useYearStore((state) => state.selectedYear);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: notifications, isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => notificationsApi.getAll(),
  });

  useEffect(() => {
    if (notifications) {
      setNotifications(notifications);
    }
  }, [notifications, setNotifications]);

  const markMutation = useMutation({
    mutationFn: (id: number) => notificationsApi.markAsRead(id),
    onSuccess: (_data, id) => {
      markRead(id);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const markAllMutation = useMutation({
    mutationFn: () => notificationsApi.markAllAsRead(),
    onSuccess: () => {
      markAllRead();
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const reviewMutation = useMutation({
    mutationFn: async ({
      notification,
      action,
      notes,
    }: {
      notification: Notification;
      action: 'aprobar' | 'correccion';
      notes?: string;
    }) => {
      if (!notification.entryId) {
        throw new Error('La notificación no tiene una entrada asociada para revisar.');
      }

      await supervisorApi.updateEntry(notification.entryId, {
        action,
        notes: action === 'correccion' ? notes : undefined,
      });

      if (!notification.leido) {
        await notificationsApi.markAsRead(notification.id);
      }
    },
    onSuccess: (_data, { notification, action }) => {
      markRead(notification.id);
      toast('success', action === 'aprobar' ? 'Reto aprobado.' : 'Corrección solicitada.');
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-centro'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-centros', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-stats', year] });
      queryClient.invalidateQueries({ queryKey: ['admin-reports', year] });
    },
    onError: (error) => {
      toast('error', error instanceof Error ? error.message : 'No se pudo actualizar la revisión.');
    },
  });

  const unreadNotifications = useMemo(
    () => (notifications ?? []).filter((notification) => !notification.leido),
    [notifications],
  );

  const readNotifications = useMemo(
    () => (notifications ?? []).filter((notification) => notification.leido),
    [notifications],
  );

  async function handleOpen(notification: Notification) {
    if (!notification.actionTarget) {
      if (!notification.leido) {
        markMutation.mutate(notification.id);
      }
      return;
    }

    if (!notification.leido) {
      try {
        await notificationsApi.markAsRead(notification.id);
        markRead(notification.id);
      } catch {
        // Ignore read-state failures and still send the user to the actionable destination.
      }
    }

    navigateTo(notification.actionTarget.page, notification.actionTarget.params);
  }

  if (isLoading) return <Spinner />;

  if (!notifications?.length) {
    return (
      <EmptyState
        icon={<Bell size={48} />}
        title="Sin historial de notificaciones"
        description="Cuando haya envíos, validaciones o revisiones aparecerán aquí."
      />
    );
  }

  return (
    <div>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          gap: 'var(--gnf-space-4)',
          flexWrap: 'wrap',
          marginBottom: 'var(--gnf-space-6)',
        }}
      >
        <div>
          <h2 style={{ marginBottom: 'var(--gnf-space-2)' }}>Notificaciones</h2>
          <p style={{ margin: 0, color: 'var(--gnf-muted)' }}>
            {unreadNotifications.length} pendiente{unreadNotifications.length === 1 ? '' : 's'} y {readNotifications.length} en historial reciente.
          </p>
        </div>

        {unreadNotifications.length > 0 && (
          <Button
            variant="outline"
            size="sm"
            icon={<CheckCheck size={14} />}
            onClick={() => markAllMutation.mutate()}
            loading={markAllMutation.isPending}
          >
            Marcar todas como leídas
          </Button>
        )}
      </div>

      <div style={{ display: 'grid', gap: 'var(--gnf-space-6)' }}>
        <section>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginBottom: 'var(--gnf-space-3)' }}>
            <h3 style={{ margin: 0 }}>Pendientes</h3>
            <Badge color="#1d4ed8" bg="rgba(59, 130, 246, 0.14)">{unreadNotifications.length}</Badge>
          </div>

          {unreadNotifications.length === 0 ? (
            <Card>
              <EmptyState
                icon={<CheckCircle2 size={44} />}
                title="No hay notificaciones pendientes"
                description="Todo está al día. Puedes revisar abajo el historial reciente."
              />
            </Card>
          ) : (
            <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
              {unreadNotifications.map((notification) => (
                <NotificationCard
                  key={notification.id}
                  notification={notification}
                  onMarkRead={(id) => markMutation.mutate(id)}
                  onOpen={handleOpen}
                  onReview={(action, notes) => reviewMutation.mutate({ notification, action, notes })}
                  markPending={markMutation.isPending && markMutation.variables === notification.id}
                  reviewPending={reviewMutation.isPending && reviewMutation.variables?.notification.id === notification.id}
                />
              ))}
            </div>
          )}
        </section>

        {readNotifications.length > 0 && (
          <section>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginBottom: 'var(--gnf-space-3)' }}>
              <h3 style={{ margin: 0 }}>Historial reciente</h3>
              <Badge>{readNotifications.length}</Badge>
            </div>

            <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
              {readNotifications.map((notification) => (
                <NotificationCard
                  key={notification.id}
                  notification={notification}
                  onMarkRead={(id) => markMutation.mutate(id)}
                  onOpen={handleOpen}
                  onReview={(action, notes) => reviewMutation.mutate({ notification, action, notes })}
                  markPending={false}
                  reviewPending={reviewMutation.isPending && reviewMutation.variables?.notification.id === notification.id}
                />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  );
}

interface NotificationCardProps {
  notification: Notification;
  onMarkRead: (id: number) => void;
  onOpen: (notification: Notification) => void;
  onReview: (action: 'aprobar' | 'correccion', notes?: string) => void;
  markPending: boolean;
  reviewPending: boolean;
}

function NotificationCard({
  notification,
  onMarkRead,
  onOpen,
  onReview,
  markPending,
  reviewPending,
}: NotificationCardProps) {
  const [showCorrectionForm, setShowCorrectionForm] = useState(false);
  const [notes, setNotes] = useState('');
  const meta = TYPE_META[notification.tipo] ?? TYPE_META.general;
  const isReviewable = Boolean(notification.canReview && notification.entryId && notification.entryStatus === 'enviado');
  const locationLabel = [notification.regionName, notification.circuito ? `Circuito ${notification.circuito}` : '']
    .filter(Boolean)
    .join(' | ');

  return (
    <Card
      style={{
        display: 'grid',
        gap: 'var(--gnf-space-4)',
        opacity: notification.leido ? 0.74 : 1,
        borderLeft: `4px solid ${notification.requiresYearValidation ? '#f59e0b' : 'var(--gnf-border)'}`,
      }}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', marginBottom: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
            <Badge color={meta.color} bg={meta.bg}>{meta.label}</Badge>
            {!notification.leido && (
              <Badge color="#1d4ed8" bg="rgba(59, 130, 246, 0.14)">Pendiente</Badge>
            )}
            <time style={{ fontSize: '0.75rem', color: 'var(--gnf-gray-400)' }}>
              {formatDateTime(notification.createdAt)}
            </time>
          </div>

          <p style={{ margin: 0, fontSize: '0.95rem', color: 'var(--gnf-gray-900)' }}>{notification.mensaje}</p>

          <div style={{ display: 'flex', gap: 'var(--gnf-space-3)', flexWrap: 'wrap', marginTop: 'var(--gnf-space-3)', fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
            {notification.centroNombre && (
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                <School size={14} />
                {notification.centroNombre}
              </span>
            )}
            {notification.retoTitulo && <span>Reto: {notification.retoTitulo}</span>}
            {locationLabel && (
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                <MapPin size={14} />
                {locationLabel}
              </span>
            )}
          </div>
        </div>

        <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
          {notification.actionTarget && (
            <Button variant="outline" size="sm" icon={<ExternalLink size={14} />} onClick={() => onOpen(notification)}>
              {notification.actionLabel || 'Abrir detalle'}
            </Button>
          )}

          {!notification.leido && (
            <Button variant="ghost" size="sm" icon={<Check size={14} />} onClick={() => onMarkRead(notification.id)} loading={markPending}>
              Marcar leída
            </Button>
          )}
        </div>
      </div>

      {notification.requiresYearValidation && (
        <Alert variant="warning" title="Evidencia con fecha fuera del año activo">
          Este reto no se puede aprobar desde aquí hasta que el centro reemplace la imagen o se valide la evidencia correcta.
        </Alert>
      )}

      {isReviewable && (
        <div style={{ paddingTop: 'var(--gnf-space-2)', borderTop: '1px solid var(--gnf-border)' }}>
          {showCorrectionForm ? (
            <div>
              <Textarea
                label="Nota de corrección"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                placeholder="Indica qué debe corregir el centro educativo."
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
                <Button
                  variant="danger"
                  size="sm"
                  icon={<RotateCcw size={14} />}
                  loading={reviewPending}
                  disabled={!notes.trim()}
                  onClick={() => onReview('correccion', notes.trim())}
                >
                  Enviar corrección
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setShowCorrectionForm(false)}>
                  Cancelar
                </Button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
              <Button
                size="sm"
                icon={<CheckCircle2 size={14} />}
                loading={reviewPending}
                disabled={notification.requiresYearValidation}
                onClick={() => onReview('aprobar')}
              >
                Aprobar
              </Button>
              <Button variant="outline" size="sm" icon={<RotateCcw size={14} />} onClick={() => setShowCorrectionForm(true)}>
                Solicitar corrección
              </Button>
            </div>
          )}
        </div>
      )}

      {notification.tipo === 'invalid_photo_date' && !notification.requiresYearValidation && (
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: '0.8125rem', color: '#b45309' }}>
          <TriangleAlert size={14} />
          Revisa la evidencia antes de aprobar el reto.
        </div>
      )}
    </Card>
  );
}
