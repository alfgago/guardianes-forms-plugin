import type { Evidencia, RetoEntry } from '@/types';
import { getEvidenceReviewStatus } from '@/utils/evidenceReview';

export type ReviewEntry = RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string };
export type ReviewFilter = 'all' | 'pendiente' | 'aprobada' | 'rechazada' | 'en_pausa';
export const reviewFilters: { value: ReviewFilter; label: string }[] = [
  { value: 'all', label: 'Todas' },
  { value: 'pendiente', label: 'Por revisar' },
  { value: 'aprobada', label: 'Aprobadas' },
  { value: 'rechazada', label: 'Rechazadas' },
  { value: 'en_pausa', label: 'En pausa' },
];

export function isReviewable(evidence: Evidencia) {
  return !evidence.replaced && (evidence.reviewable ?? ((evidence.field_id ?? 0) > 0 || evidence.puntos != null));
}

export function reviewCounts(entry: RetoEntry) {
  const counts = { all: 0, pendiente: 0, aprobada: 0, rechazada: 0, en_pausa: 0 };
  for (const evidence of entry.evidencias ?? []) {
    if (evidence.replaced) continue;
    counts.all++;
    if (entry.id > 0 && isReviewable(evidence)) counts[getEvidenceReviewStatus(evidence)]++;
  }
  return counts;
}

export function evidenceMatches(evidence: Evidencia, filter: ReviewFilter) {
  return !evidence.replaced && (filter === 'all' || (isReviewable(evidence) && getEvidenceReviewStatus(evidence) === filter));
}

export function evidenceAnchor(entryId: number, index: number) {
  return `review-evidence-${entryId}-${index}`;
}

export function nextPending(entries: ReviewEntry[], retoId: number, afterIndex: number) {
  const start = entries.findIndex(entry => entry.retoId === retoId);
  const ordered = [...entries.slice(Math.max(0, start)), ...entries.slice(0, Math.max(0, start))];
  const candidates = ordered.flatMap(entry => entry.id > 0 ? (entry.evidencias ?? []).flatMap((ev, index) =>
    isReviewable(ev) && getEvidenceReviewStatus(ev) === 'pendiente' ? [{ retoId: entry.retoId, entryId: entry.id, index }] : []) : []);
  return candidates.find(item => item.retoId === retoId && item.index > afterIndex)
    ?? candidates.find(item => item.retoId !== retoId)
    ?? candidates[0];
}
