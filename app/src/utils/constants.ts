import type { Estado, UserRole } from '@/types';

export const ESTADO_LABELS: Record<Estado, string> = {
  no_iniciado: 'No iniciado',
  en_progreso: 'En progreso',
  completo: 'Completo',
  enviado: 'Enviado',
  aprobado: 'Aprobado',
  correccion: 'Correccion',
};

export const ESTADO_COLORS: Record<Estado, string> = {
  no_iniciado: 'var(--gnf-gray-400)',
  en_progreso: 'var(--gnf-ocean)',
  completo: 'var(--gnf-forest)',
  enviado: 'var(--gnf-sun)',
  aprobado: 'var(--gnf-leaf)',
  correccion: 'var(--gnf-coral)',
};

export const ESTADO_BG_COLORS: Record<Estado, string> = {
  no_iniciado: 'var(--gnf-gray-100)',
  en_progreso: 'rgba(14, 165, 233, 0.12)',
  completo: 'rgba(34, 197, 94, 0.12)',
  enviado: 'rgba(245, 158, 11, 0.14)',
  aprobado: 'rgba(34, 197, 94, 0.12)',
  correccion: 'rgba(239, 68, 68, 0.12)',
};

export const ROLE_LABELS: Record<UserRole, string> = {
  docente: 'Centro Educativo',
  supervisor: 'Supervisor',
  comite_bae: 'Comite BAE',
  administrator: 'Administrador',
};

export const STAR_RANGES = [
  { min: 1, max: 20, stars: 1 },
  { min: 21, max: 40, stars: 2 },
  { min: 41, max: 60, stars: 3 },
  { min: 61, max: 80, stars: 4 },
  { min: 81, max: Infinity, stars: 5 },
] as const;

export const NIVELES_EDUCATIVOS = [
  'Preescolar',
  'Primaria',
  'Secundaria',
] as const;

export const DEPENDENCIAS = [
  'Publico',
  'Privado',
  'Subvencionado',
] as const;

export const JORNADAS = [
  'Diurno',
  'Nocturno',
] as const;
