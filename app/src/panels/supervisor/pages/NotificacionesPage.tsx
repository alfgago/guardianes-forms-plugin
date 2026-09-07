import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Bell,
  Check,
  CheckCheck,
  CheckCircle2,
  ExternalLink,
  FileText,
  PencilLine,
  MapPin,
  School,
  TriangleAlert,
  Upload,
  XCircle,
} from 'lucide-react';
import { notificationsApi } from '@/api/notifications';
import { supervisorApi } from '@/api/supervisor';
import { EmptyState } from '@/components/ui/EmptyState';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import { useNotificationStore } from '@/stores/useNotificationStore';
import { useToast } from '@/components/ui/Toast';
import { formatDateTime } from '@/utils/formatters';
import { navigateTo } from '@/utils/url';
import { formatEvidenceOriginalDate, getRejectionReasonLabel, REJECTION_REASON_OPTIONS } from '@/utils/evidenceReview';
import type { Notification, NotificationEvidenceItem, NotificationType } from '@/types';

const TYPE_META: Record<NotificationType, { label: string; color: string; bg: string }> = {
  correccion: { label: 'Corrección solicitada', color: '#b45309', bg: 'rgba(245, 158, 11, 0.14)' },
  aprobado: { label: 'Aprobado', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  invalid_photo_date: { label: 'Validación de año', color: '#b45309', bg: 'rgba(245, 158, 11, 0.14)' },
  evidencia_subida: { label: 'Nueva evidencia', color: '#1d4ed8', bg: 'rgba(59, 130, 246, 0.14)' },
  evidencia_resubida: { label: 'Evidencia corregida', color: '#0369a1', bg: 'rgba(14, 116, 144, 0.14)' },
  evidencia_aprobada: { label: 'Evidencia aprobada', color: '#166534', bg: 'rgba(34, 197, 94, 0.14)' },
  evidencia_rechazada: { label: 'Evidencia rechazada', color: '#b91c1c', bg: 'rgba(239, 68, 68, 0.14)' },
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
  const queryClient = useQueryClient();

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

  const unreadNotifications = useMemo(
    () => (notifications ?? []).filter((notification) => !notification.leido),
    [notifications],
  );

  const readNotifications = useMemo(
    () => (notifications ?? []).filter((notification) => notification.leido),
    [notifications],
  );

  async function handleMarkRead(notificationId: number) {
    if (!notifications?.some((item) => item.id === notificationId && !item.leido)) {
      return;
    }

    try {
      await notificationsApi.markAsRead(notificationId);
    } finally {
      markRead(notificationId);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    }
  }

  async function handleOpen(notification: Notification) {
    if (!notification.actionTarget) {
      if (!notification.leido) {
        await handleMarkRead(notification.id);
      }
      return;
    }

    if (!notification.leido) {
      await handleMarkRead(notification.id);
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
                  onReviewed={handleMarkRead}
                  markPending={markMutation.isPending && markMutation.variables === notification.id}
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
                  onReviewed={handleMarkRead}
                  markPending={false}
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
  onOpen: (notification: Notification) => void | Promise<void>;
  onReviewed: (id: number) => void | Promise<void>;
  markPending: boolean;
}

function NotificationCard({
  notification,
  onMarkRead,
  onOpen,
  onReviewed,
  markPending,
}: NotificationCardProps) {
  const meta = TYPE_META[notification.tipo] ?? TYPE_META.general;
  const locationLabel = [notification.regionName, notification.circuito ? `Circuito ${notification.circuito}` : '']
    .filter(Boolean)
    .join(' | ');
  const evidenceItems = notification.evidenceItems ?? [];

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

      {evidenceItems.length > 0 && (
        <div style={{ display: 'grid', gap: 'var(--gnf-space-3)' }}>
          {evidenceItems.map((evidence) => (
            <NotificationEvidenceCard
              key={`${notification.id}-${evidence.evidenceIndex}-${evidence.fieldId ?? 'field'}`}
              notification={notification}
              evidence={evidence}
              onReviewed={onReviewed}
            />
          ))}
        </div>
      )}

      {notification.tipo === 'invalid_photo_date' && evidenceItems.length === 0 && (
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: '0.8125rem', color: '#b45309' }}>
          <TriangleAlert size={14} />
          Revisa la evidencia en el detalle del centro.
        </div>
      )}
    </Card>
  );
}

function NotificationEvidenceCard({
  notification,
  evidence,
  onReviewed,
}: {
  notification: Notification;
  evidence: NotificationEvidenceItem;
  onReviewed: (id: number) => void | Promise<void>;
}) {
  const [reviewComment, setReviewComment] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [reviewMode, setReviewMode] = useState<'aprobar' | 'rechazar' | 'editar' | null>(null);
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const status = evidence.estado ?? 'pendiente';
  const isApproved = status === 'aprobada';
  const isRejected = status === 'rechazada';
  const isReviewed = isApproved || isRejected;
  const canReview = !!notification.entryId && !!notification.canReview && (!!evidence.canReview || isReviewed);
  const reviewReasonLabel = getRejectionReasonLabel(evidence.reviewReason);

  const mutation = useMutation({
    mutationFn: (action: 'aprobar' | 'rechazar') => supervisorApi.reviewEvidence(notification.entryId!, evidence.evidenceIndex, {
      action,
      comment: reviewComment.trim(),
      reviewReason: action === 'rechazar' ? rejectReason : undefined,
    }),
    onSuccess: async (_data, action) => {
      toast('success', action === 'aprobar' ? 'Evidencia aprobada.' : 'Evidencia rechazada.');
      queryClient.invalidateQueries({ queryKey: ['supervisor-centro'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-centros'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      if (!notification.leido) {
        await onReviewed(notification.id);
      }
      setReviewComment('');
      setRejectReason('');
      setReviewMode(null);
    },
    onError: (error: Error) => {
      toast('error', error.message || 'No se pudo actualizar la evidencia.');
    },
  });

  const closeReviewForm = () => {
    setReviewComment('');
    setRejectReason('');
    setReviewMode(null);
  };

  const openApproveForm = () => {
    setReviewComment(evidence.supervisorComment ?? '');
    setRejectReason('');
    setReviewMode('aprobar');
  };

  const openRejectForm = () => {
    setReviewComment(evidence.supervisorComment ?? '');
    setRejectReason(evidence.reviewReason ?? '');
    setReviewMode('rechazar');
  };

  const openEditCommentForm = () => {
    setReviewComment(evidence.supervisorComment ?? '');
    setRejectReason(evidence.reviewReason ?? '');
    setReviewMode('editar');
  };

  const submitEditedComment = () => {
    mutation.mutate(isRejected ? 'rechazar' : 'aprobar');
  };

  const statusColor = isApproved ? '#166534' : isRejected ? '#b91c1c' : '#b45309';
  const statusBg = isApproved ? 'rgba(34, 197, 94, 0.12)' : isRejected ? 'rgba(239, 68, 68, 0.12)' : 'rgba(245, 158, 11, 0.14)';
  const borderColor = evidence.requiresYearValidation ? '#f59e0b' : isRejected ? '#ef4444' : 'var(--gnf-border)';
  const reviewedByName = evidence.reviewedByName?.trim();
  const reviewMeta = evidence.reviewedBy !== 0 && (reviewedByName || evidence.reviewedAt)
    ? `${reviewedByName ? `Revisado por ${reviewedByName}` : 'Revisado'}${evidence.reviewedAt ? ` el ${formatDateTime(evidence.reviewedAt)}` : ''}`
    : '';
  const showApprovalCommentForm = reviewMode === 'aprobar' || (reviewMode === 'editar' && !isRejected);
  const showRejectionCommentForm = reviewMode === 'rechazar' || (reviewMode === 'editar' && isRejected);
  const canApprove = !isApproved;
  const canReject = !isRejected;

  return (
    <div
      style={{
        display: 'grid',
        gap: 'var(--gnf-space-3)',
        padding: 'var(--gnf-space-4)',
        border: `1px solid ${borderColor}`,
        borderRadius: 'var(--gnf-radius)',
        background: evidence.requiresYearValidation ? 'rgba(255, 247, 237, 0.8)' : 'rgba(248, 250, 252, 0.7)',
      }}
    >
      <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
        <div style={{ flexShrink: 0 }}>
          {evidence.previewUrl ? (
            <a href={evidence.previewUrl} target="_blank" rel="noopener noreferrer" style={{ display: 'block', textDecoration: 'none' }}>
              {evidence.isImage ? (
                <img
                  src={evidence.previewUrl}
                  alt={evidence.fileName || evidence.questionLabel}
                  style={{ width: 104, height: 104, objectFit: 'cover', borderRadius: 'var(--gnf-radius-sm)', border: '1px solid rgba(148, 163, 184, 0.35)' }}
                />
              ) : (
                <div
                  style={{
                    width: 104,
                    height: 104,
                    borderRadius: 'var(--gnf-radius-sm)',
                    border: '1px dashed rgba(148, 163, 184, 0.45)',
                    background: 'var(--gnf-white)',
                    display: 'grid',
                    placeItems: 'center',
                    color: 'var(--gnf-muted)',
                  }}
                >
                  <FileText size={30} />
                </div>
              )}
            </a>
          ) : (
            <div
              style={{
                width: 104,
                height: 104,
                borderRadius: 'var(--gnf-radius-sm)',
                border: '1px dashed rgba(148, 163, 184, 0.45)',
                background: 'var(--gnf-white)',
                display: 'grid',
                placeItems: 'center',
                color: 'var(--gnf-muted)',
              }}
            >
              <Upload size={28} />
            </div>
          )}
        </div>

        <div style={{ flex: 1, minWidth: 260, display: 'grid', gap: 'var(--gnf-space-2)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
            <strong style={{ color: 'var(--gnf-ocean-dark)' }}>{evidence.questionLabel}</strong>
            <Badge color={statusColor} bg={statusBg}>
              {isApproved ? 'Aprobada' : isRejected ? 'Rechazada' : 'Pendiente'}
            </Badge>
            {evidence.puntos != null && evidence.puntos > 0 && (
              <Badge color="#166534" bg="rgba(34, 197, 94, 0.12)">{evidence.puntos} pts</Badge>
            )}
          </div>

          <div style={{ display: 'grid', gap: 4, fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>
            {evidence.fileName && (
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                <Upload size={14} />
                {evidence.isImage || !evidence.previewUrl ? (
                  <span>{evidence.isImage ? 'Imagen adjunta' : evidence.fileName}</span>
                ) : (
                  <>
                    <a
                      href={evidence.previewUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ color: 'var(--gnf-ocean-dark)', fontWeight: 600, textDecoration: 'none' }}
                    >
                      {evidence.fileName}
                    </a>
                    <a
                      href={evidence.previewUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ color: 'var(--gnf-ocean)', fontWeight: 600, textDecoration: 'none' }}
                    >
                      Abrir archivo
                    </a>
                  </>
                )}
              </span>
            )}
            <span>
              Fecha original del archivo: {formatEvidenceOriginalDate(evidence.originalDate ?? evidence.photoDate)}
            </span>
            {reviewMeta && <span>{reviewMeta}</span>}
          </div>

          {(reviewReasonLabel || evidence.supervisorComment) && (
            <div
              style={{
                padding: '10px 12px',
                borderRadius: 'var(--gnf-radius-sm)',
                background: isRejected ? 'rgba(239, 68, 68, 0.08)' : 'rgba(30, 95, 138, 0.06)',
                fontSize: '0.8125rem',
                color: 'var(--gnf-gray-700)',
              }}
            >
              {reviewReasonLabel && (
                <div>
                  <strong>Motivo:</strong> {reviewReasonLabel}
                </div>
              )}
              {evidence.supervisorComment && <div>{evidence.supervisorComment}</div>}
            </div>
          )}

          {evidence.requiresYearValidation && (
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: '0.8125rem', color: '#b45309' }}>
              <TriangleAlert size={14} />
              La fecha original del archivo no coincide con el año activo.
            </div>
          )}
        </div>
      </div>

      {canReview && (
        <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
          {reviewMode === null && (
            <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
              {canApprove && (
                <Button
                  size="sm"
                  icon={<CheckCircle2 size={14} />}
                  disabled={mutation.isPending}
                  onClick={openApproveForm}
                >
                  Aprobar
                </Button>
              )}
              {canReject && (
                <Button
                  variant="danger"
                  size="sm"
                  icon={<XCircle size={14} />}
                  disabled={mutation.isPending}
                  onClick={openRejectForm}
                >
                  Rechazar
                </Button>
              )}
              {isReviewed && (
                <Button
                  variant="outline"
                  size="sm"
                  icon={<PencilLine size={14} />}
                  onClick={openEditCommentForm}
                >
                  Editar comentario
                </Button>
              )}
            </div>
          )}

          {showApprovalCommentForm && (
            <div style={{ paddingTop: 'var(--gnf-space-1)' }}>
              <Textarea
                label=""
                value={reviewComment}
                onChange={(event) => setReviewComment(event.target.value)}
                placeholder="Comentario opcional para el centro educativo..."
                rows={2}
                style={{ marginBottom: 'var(--gnf-space-2)' }}
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
                <Button
                  size="sm"
                  loading={mutation.isPending && mutation.variables === 'aprobar'}
                  disabled={mutation.isPending}
                  onClick={reviewMode === 'editar' ? submitEditedComment : () => mutation.mutate('aprobar')}
                >
                  {reviewMode === 'editar' ? 'Guardar comentario' : 'Guardar aprobación'}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={closeReviewForm}
                >
                  Cancelar
                </Button>
              </div>
            </div>
          )}

          {showRejectionCommentForm && (
            <div style={{ paddingTop: 'var(--gnf-space-1)' }}>
              <Select
                label=""
                value={rejectReason}
                onChange={(event) => setRejectReason(event.target.value)}
                options={[...REJECTION_REASON_OPTIONS]}
                placeholder="Selecciona el tipo de situación"
                style={{ marginBottom: 'var(--gnf-space-2)' }}
              />
              <Textarea
                label=""
                value={reviewComment}
                onChange={(event) => setReviewComment(event.target.value)}
                placeholder="Anotación adicional..."
                rows={2}
                style={{ marginBottom: 'var(--gnf-space-2)' }}
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap' }}>
                <Button
                  variant="danger"
                  size="sm"
                  loading={mutation.isPending && mutation.variables === 'rechazar'}
                  disabled={!rejectReason || mutation.isPending}
                  onClick={reviewMode === 'editar' ? submitEditedComment : () => mutation.mutate('rechazar')}
                >
                  {reviewMode === 'editar' ? 'Guardar comentario' : 'Guardar rechazo'}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={closeReviewForm}
                >
                  Cancelar
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
