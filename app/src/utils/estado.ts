import type { Estado } from '@/types';

const TRANSITIONS: Record<Estado, Estado[]> = {
  no_iniciado: ['en_progreso'],
  en_progreso: ['completo'],
  completo: ['enviado', 'en_progreso'],
  enviado: ['aprobado', 'correccion'],
  aprobado: [],
  correccion: ['en_progreso'],
  sin_evidencias: [],
};

export function canTransitionTo(from: Estado, to: Estado): boolean {
  return TRANSITIONS[from]?.includes(to) ?? false;
}

export function isEditable(estado: Estado): boolean {
  return estado === 'no_iniciado' || estado === 'en_progreso';
}

export function isReviewable(estado: Estado): boolean {
  return estado === 'enviado';
}

export function canReopen(estado: Estado): boolean {
  return estado === 'correccion';
}

export function canSubmitForReview(estado: Estado): boolean {
  return estado === 'completo';
}

export function isTerminal(estado: Estado): boolean {
  return estado === 'aprobado';
}
