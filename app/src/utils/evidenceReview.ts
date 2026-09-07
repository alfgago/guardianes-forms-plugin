import type { Evidencia } from '@/types';

export type EvidenceReviewAction = 'aprobar' | 'rechazar';

export const REJECTION_REASON_OPTIONS = [
  { value: 'no_corresponde', label: 'Evidencia no corresponde.' },
  { value: 'otra_accion_reto', label: 'Evidencia de otra acción o reto.' },
  { value: 'ya_valorada', label: 'Evidencia ya valorada.' },
  { value: 'accion_no_amigable', label: 'Acción debe ser amigable.' },
] as const;

export type RejectionReason = (typeof REJECTION_REASON_OPTIONS)[number]['value'];

export function getRejectionReasonLabel(reason?: string | null) {
  return REJECTION_REASON_OPTIONS.find((option) => option.value === reason)?.label ?? '';
}

export function formatEvidenceOriginalDate(value?: string | null) {
  if (!value) {
    return 'No disponible en los metadatos';
  }

  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value;
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString('es-CR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export function getEvidenceOriginalDate(evidence: Pick<Evidencia, 'original_date' | 'photo_date'>) {
  return evidence.original_date ?? evidence.photo_date ?? null;
}

export function hasVerifiableDateIssue(evidence: Pick<Evidencia, 'requires_year_validation' | 'tipo' | 'type' | 'nombre' | 'filename' | 'original_date' | 'photo_date' | 'date_source' | 'exifYear' | 'supervisor_comment' | 'warning'>) {
  if (!evidence.requires_year_validation) {
    return false;
  }

  const fileName = evidence.nombre ?? evidence.filename ?? '';
  const tipo = evidence.tipo ?? evidence.type ?? '';
  const isImage = tipo === 'imagen' || /\.(jpe?g|png|gif|webp|heic|heif|tiff?)$/i.test(fileName);

  if (!isImage) {
    return false;
  }

  if (evidence.date_source === 'browser_file_metadata') {
    return false;
  }

  if (evidence.original_date || evidence.photo_date || evidence.exifYear) {
    return true;
  }

  const comment = evidence.supervisor_comment ?? evidence.warning ?? '';
  return /\b(19|20)\d{2}\b/.test(comment);
}

export function getEvidenceReviewStatus(evidence: Evidencia) {
  return evidence.estado ?? (hasVerifiableDateIssue(evidence) ? 'rechazada' : 'pendiente');
}
