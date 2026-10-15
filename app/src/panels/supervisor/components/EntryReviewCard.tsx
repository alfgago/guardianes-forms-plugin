import { useCallback, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, FileText, PauseCircle, PencilLine, Upload, XCircle } from 'lucide-react';
import { supervisorApi } from '@/api/supervisor';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { useToast } from '@/components/ui/Toast';
import { Modal } from '@/components/ui/Modal';
import { evidenceAnchor, evidenceMatches, type ReviewFilter } from './reviewNavigation';
import './challenge-review.css';
import type { Evidencia, RetoEntry, RetoEntryResponse } from '@/types';
import {
  formatEvidenceOriginalDate,
  getEvidenceOriginalDate,
  getEvidenceReviewStatus,
  getReviewReasonLabel,
  PAUSE_REASON_OPTIONS,
  REJECTION_REASON_OPTIONS,
} from '@/utils/evidenceReview';

interface EntryReviewCardProps {
  entry: RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string };
  filter?: ReviewFilter;
  onEditingChange?: (editing: boolean) => void;
}

export function EntryReviewCard({ entry, filter = 'all', onEditingChange }: EntryReviewCardProps) {
  return <FilteredEntryReview key={filter} entry={entry} filter={filter} onEditingChange={onEditingChange} />;
}

function FilteredEntryReview({ entry, filter = 'all', onEditingChange }: EntryReviewCardProps) {
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  // Keep the just-reviewed item in place until the reviewer changes filter or reto.
  const [retained, setRetained] = useState<number[]>([]);
  const responses = entry.responses ?? [];
  const allEvidencias = entry.evidencias ?? [];
  const visible = (ev: Evidencia, index: number) => !ev.replaced && (evidenceMatches(ev, filter) || retained.includes(index));
  const onEdit = (index: number, editing: boolean) => {
    setEditingIndex(editing ? index : null);
    onEditingChange?.(editing);
  };
  const onReviewed = (index: number) => setRetained(current => current.includes(index) ? current : [...current, index]);
  const visibleResponses = filter === 'all' ? responses : responses.filter(response => allEvidencias.some((ev, index) => ev.field_id === response.fieldId && visible(ev, index)));

  const orphanEvidence = allEvidencias.filter(
    (ev, index) => visible(ev, index) && !responses.some((response) => response.fieldId === (ev.field_id ?? 0)),
  );

  return (
    <div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
        {visibleResponses.length > 0 ? (
          visibleResponses.map((response) => (
            <EntryResponseBlock
              key={response.fieldId}
              response={response}
              entryId={entry.id}
              allEvidencias={allEvidencias}
              visible={visible}
              editingIndex={editingIndex}
              onEdit={onEdit}
              onReviewed={onReviewed}
            />
          ))
        ) : (
          <div
            style={{
              padding: 'var(--gnf-space-4)',
              border: '1px solid var(--gnf-border)',
              borderRadius: 'var(--gnf-radius)',
              background: 'var(--gnf-white)',
              color: 'var(--gnf-muted)',
              fontSize: '0.875rem',
            }}
          >
            {filter === 'all' ? 'Este reto no tiene preguntas configuradas.' : orphanEvidence.length ? 'Evidencias de preguntas anteriores.' : 'No hay evidencias con este estado en el reto.'}
          </div>
        )}

        {orphanEvidence.length > 0 && (
          <div
            style={{
              padding: 'var(--gnf-space-4)',
              border: '1px solid var(--gnf-border)',
              borderRadius: 'var(--gnf-radius)',
              background: 'var(--gnf-white)',
            }}
          >
            <h5 style={{ margin: '0 0 var(--gnf-space-3)', fontSize: '0.875rem' }}>Evidencias de preguntas anteriores y archivos adicionales</h5>
            {orphanEvidence.map((evidence) => (
              <EvidenceReviewItem key={`${evidence.field_id}-${evidence.nombre}-${allEvidencias.indexOf(evidence)}`}
                evidence={evidence} entryId={entry.id} evidenceIndex={allEvidencias.indexOf(evidence)} editingIndex={editingIndex} onEdit={onEdit} onReviewed={onReviewed} />
            ))}
          </div>
        )}
      </div>

      {entry.supervisorNotes && (
        <div
          style={{
            padding: 'var(--gnf-space-3)',
            background: 'var(--gnf-white)',
            borderRadius: 'var(--gnf-radius-sm)',
            fontSize: '0.8125rem',
            marginTop: 'var(--gnf-space-4)',
            border: '1px solid var(--gnf-border)',
            borderLeft: '4px solid var(--gnf-ocean)',
          }}
        >
          <strong>Feedback previo:</strong> {entry.supervisorNotes}
        </div>
      )}
    </div>
  );
}

function EntryResponseBlock({
  response,
  entryId,
  allEvidencias,
  visible,
  editingIndex,
  onEdit,
  onReviewed,
}: {
  response: RetoEntryResponse;
  entryId: number;
  allEvidencias: Evidencia[];
  visible: (ev: Evidencia, index: number) => boolean;
  editingIndex: number | null;
  onEdit: (index: number, editing: boolean) => void;
  onReviewed: (index: number) => void;
}) {
  const activeEvidence = allEvidencias.map((ev, index) => ({ ev, index })).filter(({ ev, index }) => ev.field_id === response.fieldId && visible(ev, index));
  const hasEvidence = activeEvidence.length > 0;
  const hasTextValue = response.displayValue.trim().length > 0;
  const isEvidenceField = response.type === 'file-upload' || response.type === 'file';
  const answerText = hasTextValue ? response.displayValue : 'Sin respuesta';
  const shouldShowAnswer = hasTextValue || !hasEvidence || !isEvidenceField;

  return (
    <div
      style={{
        padding: 'var(--gnf-space-4)',
        border: '1px solid var(--gnf-border)',
        borderRadius: 'var(--gnf-radius)',
        background: 'var(--gnf-white)',
      }}
    >
      <div
        style={{
          display: 'flex',
          alignItems: 'flex-start',
          justifyContent: 'space-between',
          gap: 'var(--gnf-space-3)',
          marginBottom: 'var(--gnf-space-3)',
        }}
      >
        <div style={{ minWidth: 0, flex: 1 }} className={response.isRequiredEvidence ? 'gnf-review-requirement' : undefined}>
          {response.isRequiredEvidence && <strong>REQUISITO PARA EL GALARDÓN</strong>}
          <h5 style={{ margin: 0, fontSize: '0.9375rem', color: 'var(--gnf-ocean-dark)' }}>
            {response.label}
          </h5>
          {response.puntos > 0 && (
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--gnf-space-2)',
                marginTop: 'var(--gnf-space-2)',
                flexWrap: 'wrap',
              }}
            >
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  padding: '4px 10px',
                  borderRadius: '999px',
                  fontSize: '0.75rem',
                  fontWeight: 700,
                  background: 'rgba(22, 163, 74, 0.12)',
                  color: '#15803d',
                }}
              >
                {response.puntos} eco puntos
              </span>
            </div>
          )}
        </div>

        {hasEvidence && (
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              fontSize: '0.75rem',
              fontWeight: 600,
              color: 'var(--gnf-ocean-dark)',
            }}
          >
            <Upload size={14} />
            {activeEvidence.length} archivo{activeEvidence.length === 1 ? '' : 's'}
          </span>
        )}
      </div>

      {shouldShowAnswer && (
        <div
          style={{
            padding: 'var(--gnf-space-3)',
            background: hasTextValue ? 'rgba(30, 95, 138, 0.05)' : 'rgba(148, 163, 184, 0.12)',
            borderRadius: 'var(--gnf-radius-sm)',
            color: hasTextValue ? 'var(--gnf-gray-900)' : 'var(--gnf-muted)',
            fontSize: '0.875rem',
            marginBottom: hasEvidence ? 'var(--gnf-space-3)' : 0,
            whiteSpace: 'pre-wrap',
          }}
        >
          {answerText}
        </div>
      )}

      {activeEvidence.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--gnf-space-3)' }}>
          {activeEvidence.map(({ ev, index }) => {
            return (
              <EvidenceReviewItem
                key={`${ev.field_id}-${ev.nombre}-${index}`}
                evidence={ev}
                entryId={entryId}
                evidenceIndex={index}
                editingIndex={editingIndex}
                onEdit={onEdit}
                onReviewed={onReviewed}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}

function EvidenceReviewItem({
  evidence,
  entryId,
  evidenceIndex,
  editingIndex,
  onEdit,
  onReviewed,
}: {
  evidence: Evidencia;
  entryId: number;
  evidenceIndex: number;
  editingIndex: number | null;
  onEdit: (index: number, editing: boolean) => void;
  onReviewed: (index: number) => void;
}) {
  const [reviewComment, setReviewComment] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [reviewMode, setReviewMode] = useState<'aprobar' | 'rechazar' | 'pausar' | 'editar' | null>(null);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const canReview = evidence.reviewable ?? ((evidence.field_id ?? 0) > 0 || evidence.puntos != null);
  const estado = getEvidenceReviewStatus(evidence);
  const isRejected = estado === 'rechazada';
  const isApproved = estado === 'aprobada';
  const isPaused = estado === 'en_pausa';
  const isReviewed = isApproved || isRejected || isPaused;
  const imgUrl = evidence.ruta || evidence.url || '';
  const isImage = (evidence.tipo ?? evidence.type) === 'imagen';
  const nombre = evidence.nombre ?? evidence.filename ?? 'Archivo';

  const originalDate = getEvidenceOriginalDate(evidence);
  const dateDisplay = formatEvidenceOriginalDate(originalDate);
  const isAutoRejected = isRejected && evidence.reviewed_by === 0;
  const reviewReasonLabel = getReviewReasonLabel(evidence.review_reason);
  const reviewerName = evidence.reviewed_by_name?.trim();
  const reviewedDate = evidence.reviewed_at
    ? new Date(evidence.reviewed_at).toLocaleDateString('es-CR')
    : '';
  const reviewMeta = !isAutoRejected && (reviewerName || reviewedDate)
    ? `${reviewerName ? `Revisado por ${reviewerName}` : 'Revisado'}${reviewedDate ? ` el ${reviewedDate}` : ''}`
    : '';

  const mutation = useMutation({
    mutationFn: (action: 'aprobar' | 'rechazar' | 'pausar') =>
      supervisorApi.reviewEvidence(entryId, evidenceIndex, {
        action,
        comment: reviewComment.trim(),
        reviewReason: action !== 'aprobar' ? rejectReason : undefined,
      }),
    onSuccess: (_, action) => {
      onReviewed(evidenceIndex);
      onEdit(evidenceIndex, false);
      toast('success', action === 'aprobar' ? 'Evidencia aprobada.' : action === 'pausar' ? 'Evidencia en pausa.' : 'Evidencia rechazada.');
      queryClient.invalidateQueries({ queryKey: ['supervisor-centro'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-centros'] });
      queryClient.invalidateQueries({ queryKey: ['admin-centro-supervisor'] });
      queryClient.invalidateQueries({ queryKey: ['admin-centros'] });
      queryClient.invalidateQueries({ queryKey: ['supervisor-dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      setReviewComment('');
      setRejectReason('');
      setReviewMode(null);
    },
    onError: (err: any) => toast('error', err?.message || 'Error al procesar.'),
  });

  const closeReviewForm = () => {
    if (mutation.isPending) return;
    onEdit(evidenceIndex, false);
    setReviewComment('');
    setRejectReason('');
    setReviewMode(null);
  };

  const openApproveForm = () => {
    onEdit(evidenceIndex, true);
    setReviewComment(evidence.supervisor_comment ?? '');
    setRejectReason('');
    setReviewMode('aprobar');
  };

  const openRejectForm = () => {
    onEdit(evidenceIndex, true);
    setReviewComment(evidence.supervisor_comment ?? '');
    setRejectReason(evidence.review_reason ?? '');
    setReviewMode('rechazar');
  };

  const openEditCommentForm = () => {
    onEdit(evidenceIndex, true);
    setReviewComment(evidence.supervisor_comment ?? '');
    setRejectReason(evidence.review_reason ?? '');
    setReviewMode('editar');
  };

  const submitEditedComment = () => {
    mutation.mutate(isPaused ? 'pausar' : isRejected ? 'rechazar' : 'aprobar');
  };

  if (entryId === 0 || !canReview) {
    return (
      <div
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          gap: 'var(--gnf-space-3)',
          alignItems: 'center',
          padding: 'var(--gnf-space-2) 0',
        }}
      >
        {isImage && imgUrl ? (
          <EvidencePhoto url={imgUrl} name={nombre} />
        ) : (
          <div
            style={{
              width: 56,
              height: 56,
              borderRadius: 6,
              background: 'var(--gnf-gray-100)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <FileText size={20} style={{ color: 'var(--gnf-muted)' }} />
          </div>
        )}
        <div style={{ display: 'grid', gap: 4, minWidth: 0, flex: '1 1 180px', overflowWrap: 'anywhere' }}>
          <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>Archivo informativo</span>
          {imgUrl ? (
            <a
              href={imgUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{ fontSize: '0.8125rem', color: 'var(--gnf-ocean-dark)', fontWeight: 600, textDecoration: 'none' }}
            >
              {nombre}
            </a>
          ) : (
            <span style={{ fontSize: '0.8125rem', color: 'var(--gnf-muted)' }}>{nombre}</span>
          )}
          {!isImage && imgUrl && (
            <a
              href={imgUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{ fontSize: '0.75rem', color: 'var(--gnf-ocean)', fontWeight: 600, textDecoration: 'none' }}
            >
              Abrir archivo
            </a>
          )}
          <span style={{ fontSize: '0.75rem', color: 'var(--gnf-muted)' }}>
            Fecha original del archivo: {dateDisplay}
          </span>
          {evidence.requires_year_validation && (
            <span style={{ fontSize: '0.75rem', color: '#b45309' }}>
              La fecha original del archivo no coincide con el año activo.
            </span>
          )}
        </div>
      </div>
    );
  }

  const borderColor = isApproved ? 'var(--gnf-forest)' : isRejected ? 'var(--gnf-coral)' : isPaused ? '#397ca9' : 'var(--gnf-border)';
  const bgColor = isApproved
    ? 'rgba(45, 138, 95, 0.04)'
    : isRejected
      ? 'rgba(239, 107, 74, 0.04)'
      : 'var(--gnf-white)';
  const showApprovalCommentForm = reviewMode === 'aprobar' || (reviewMode === 'editar' && isApproved);
  const showPauseCommentForm = reviewMode === 'pausar' || (reviewMode === 'editar' && isPaused);
  const showRejectionCommentForm = reviewMode === 'rechazar' || (reviewMode === 'editar' && isRejected);
  const canApprove = !isApproved;
  const canReject = !isRejected;

  return (
    <div
      id={evidenceAnchor(entryId, evidenceIndex)}
      className="gnf-review-evidence"
      tabIndex={-1}
      style={{
        display: 'flex',
        gap: 'var(--gnf-space-4)',
        flexWrap: 'wrap',
        padding: 'var(--gnf-space-4)',
        border: `1px solid ${borderColor}`,
        borderRadius: 'var(--gnf-radius)',
        background: bgColor,
        opacity: isRejected ? 0.85 : 1,
      }}
    >
      <div style={{ flexShrink: 0 }}>
        {isImage && imgUrl ? (
          <EvidencePhoto url={imgUrl} name={nombre} />
        ) : (
          <a
            href={imgUrl || '#'}
            target="_blank"
            rel="noopener noreferrer"
            style={{
              display: 'flex',
              width: 80,
              height: 80,
              borderRadius: 8,
              background: 'var(--gnf-gray-100)',
              alignItems: 'center',
              justifyContent: 'center',
              textDecoration: 'none',
            }}
          >
            <FileText size={28} style={{ color: 'var(--gnf-muted)' }} />
          </a>
        )}
      </div>

      <div style={{ flex: '1 1 220px', minWidth: 0 }}>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 'var(--gnf-space-2)',
            flexWrap: 'wrap',
            marginBottom: 4,
          }}
        >
          {imgUrl ? (
            <a
              href={imgUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{
                fontSize: '0.75rem',
                fontWeight: 400,
                wordBreak: 'break-all',
                color: 'var(--gnf-gray-800)',
                textDecoration: 'none',
              }}
            >
              {nombre}
            </a>
          ) : (
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 600,
                wordBreak: 'break-all',
                color: 'var(--gnf-gray-800)',
              }}
            >
              {nombre}
            </span>
          )}
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              padding: '2px 8px',
              borderRadius: '999px',
              fontSize: '0.6875rem',
              fontWeight: 700,
              background: isApproved
                ? 'rgba(45, 138, 95, 0.12)'
                : isRejected
                  ? 'rgba(239, 107, 74, 0.12)'
                  : 'rgba(251, 191, 36, 0.15)',
              color: isApproved ? 'var(--gnf-forest)' : isRejected ? 'var(--gnf-coral)' : '#b45309',
            }}
          >
            {isApproved ? 'Aprobada' : isRejected ? 'Rechazada' : isPaused ? 'En pausa' : 'Por revisar'}
          </span>
        </div>

        {!isImage && imgUrl && (
          <div style={{ marginBottom: 6 }}>
            <a
              href={imgUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{ fontSize: '0.75rem', color: 'var(--gnf-ocean)', fontWeight: 600, textDecoration: 'none' }}
            >
              Abrir archivo
            </a>
          </div>
        )}

        <div
          style={{
            fontSize: '0.75rem',
            color: evidence.requires_year_validation ? '#b45309' : 'var(--gnf-muted)',
            marginBottom: 4,
          }}
        >
          Fecha original del archivo: {dateDisplay}
        </div>

        {evidence.requires_year_validation && (
          <div style={{ fontSize: '0.75rem', color: '#b45309', marginBottom: 6 }}>
            La fecha original del archivo no coincide con el año activo.
          </div>
        )}

        {!isAutoRejected && (reviewReasonLabel || evidence.supervisor_comment) && (
          <div
            style={{
              fontSize: '0.8125rem',
              padding: '6px 10px',
              marginBottom: 6,
              background: isApproved ? '#eff8f2' : isPaused ? '#edf5fb' : 'rgba(239, 107, 74, 0.06)',
              borderLeft: `3px solid ${borderColor}`,
              borderRadius: '0 var(--gnf-radius-sm) var(--gnf-radius-sm) 0',
              color: 'var(--gnf-gray-700)',
            }}
          >
            {reviewReasonLabel && (
              <div>
                <strong>Motivo:</strong> {reviewReasonLabel}
              </div>
            )}
            {evidence.supervisor_comment && (
              <div>{evidence.supervisor_comment}</div>
            )}
          </div>
        )}

        {reviewMeta && (
          <div style={{ fontSize: '0.6875rem', color: 'var(--gnf-muted)', marginBottom: 6 }}>
            {reviewMeta}
          </div>
        )}

        <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', alignItems: 'center' }}>
          {reviewMode === null && (
            <>
              {canApprove && (
                <Button
                  size="sm"
                  icon={<CheckCircle2 size={14} />}
                  disabled={mutation.isPending || (editingIndex !== null && editingIndex !== evidenceIndex)}
                  onClick={openApproveForm}
                  style={{ fontSize: '0.75rem', padding: '4px 10px' }}
                >
                  Aprobar
                </Button>
              )}

              {canReject && (
                <Button
                  variant="danger"
                  size="sm"
                  icon={<XCircle size={14} />}
                  disabled={mutation.isPending || (editingIndex !== null && editingIndex !== evidenceIndex)}
                  onClick={openRejectForm}
                  style={{ fontSize: '0.75rem', padding: '4px 10px' }}
                >
                  Rechazar
                </Button>
              )}

              {!isPaused && (
                <Button
                  variant="outline"
                  size="sm"
                  icon={<PauseCircle size={14} />}
                  disabled={mutation.isPending || (editingIndex !== null && editingIndex !== evidenceIndex)}
                  onClick={() => {
                    onEdit(evidenceIndex, true);
                    setReviewComment(evidence.supervisor_comment ?? '');
                    setRejectReason('');
                    setReviewMode('pausar');
                  }}
                >
                  En pausa
                </Button>
              )}
              {isReviewed && (
                <Button
                  variant="outline"
                  size="sm"
                  icon={<PencilLine size={14} />}
                  onClick={openEditCommentForm}
                  disabled={editingIndex !== null && editingIndex !== evidenceIndex}
                  style={{ fontSize: '0.75rem', padding: '4px 10px' }}
                >
                  Editar comentario
                </Button>
              )}
            </>
          )}

          {showApprovalCommentForm && (
            <div style={{ width: '100%', marginTop: 'var(--gnf-space-2)' }}>
              <Textarea
                label=""
                value={reviewComment}
                onChange={(event) => setReviewComment(event.target.value)}
                placeholder="Comentario opcional para el centro educativo..."
                rows={2}
                style={{ fontSize: '0.8125rem' }}
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', marginTop: 4 }}>
                <Button
                  size="sm"
                  loading={mutation.isPending && mutation.variables === 'aprobar'}
                  onClick={reviewMode === 'editar' ? submitEditedComment : () => mutation.mutate('aprobar')}
                  disabled={mutation.isPending}
                  style={{ fontSize: '0.75rem' }}
                >
                  {reviewMode === 'editar' ? 'Guardar comentario' : 'Guardar aprobación'}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={closeReviewForm}
                  style={{ fontSize: '0.75rem' }}
                >
                  Cancelar
                </Button>
              </div>
            </div>
          )}

          {(showRejectionCommentForm || showPauseCommentForm) && (
            <div style={{ width: '100%', marginTop: 'var(--gnf-space-2)' }}>
              <Select
                label={showPauseCommentForm ? 'Motivo de pausa' : 'Motivo de rechazo'}
                value={rejectReason}
                onChange={(event) => setRejectReason(event.target.value)}
                options={showPauseCommentForm ? [...PAUSE_REASON_OPTIONS] : [...REJECTION_REASON_OPTIONS]}
                placeholder="Selecciona una causa"
                style={{ marginBottom: 'var(--gnf-space-2)' }}
              />
              <Textarea
                label=""
                value={reviewComment}
                onChange={(event) => setReviewComment(event.target.value)}
                placeholder="Anotación adicional..."
                rows={2}
                style={{ fontSize: '0.8125rem' }}
              />
              <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', marginTop: 4 }}>
                <Button
                  variant={showPauseCommentForm ? 'outline' : 'danger'}
                  size="sm"
                  loading={mutation.isPending}
                  onClick={reviewMode === 'editar' ? submitEditedComment : () => mutation.mutate(showPauseCommentForm ? 'pausar' : 'rechazar')}
                  disabled={!rejectReason || mutation.isPending}
                  style={{ fontSize: '0.75rem' }}
                >
                  {reviewMode === 'editar' ? 'Guardar comentario' : showPauseCommentForm ? 'Guardar pausa' : 'Guardar rechazo'}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={closeReviewForm}
                  style={{ fontSize: '0.75rem' }}
                >
                  Cancelar
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function EvidencePhoto({ url, name }: { url: string; name: string }) {
  const [open, setOpen] = useState(false);
  const close = useCallback(() => setOpen(false), []);
  return <>
    <button type="button" className="gnf-review-zoom" title="Ampliar imagen" aria-label={`Ampliar imagen: ${name}`} onClick={() => setOpen(true)}>
      <img src={url} alt={name} className="gnf-review-photo" loading="lazy" />
    </button>
    <Modal open={open} onClose={close} title="Vista de evidencia" width="1000px">
      <img src={open ? url : undefined} alt={name} style={{ display: 'block', maxWidth: '100%', maxHeight: '65vh', objectFit: 'contain', margin: '0 auto' }} />
      <a href={url} target="_blank" rel="noopener noreferrer">Abrir archivo original</a>
    </Modal>
  </>;
}
