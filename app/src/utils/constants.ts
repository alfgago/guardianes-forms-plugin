import type { Estado, UserRole } from '@/types';

export const ESTADO_LABELS: Record<Estado, string> = {
  no_iniciado: 'No Iniciado',
  en_progreso: 'En Progreso',
  completo: 'Completo',
  enviado: 'Enviado',
  aprobado: 'Aprobado',
  correccion: 'Corrección',
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
  en_progreso: '#e0f2fe',
  completo: '#dcfce7',
  enviado: 'var(--gnf-sun-light)',
  aprobado: '#dcfce7',
  correccion: 'var(--gnf-coral-light)',
};

export const ROLE_LABELS: Record<UserRole, string> = {
  docente: 'Docente',
  supervisor: 'Supervisor',
  comite_bae: 'Comité BAE',
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
  'Público',
  'Privado',
  'Subvencionado',
] as const;

export const JORNADAS = [
  'Diurno',
  'Nocturno',
] as const;
