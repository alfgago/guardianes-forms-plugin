import { get, post } from './client';
import type { Reto, RetoEntry, RetoWithEntry } from '@/types';

interface DocenteDashboard {
  centro: {
    id: number;
    nombre: string;
    codigoMep: string;
    regionName: string;
    estado: string;
  };
  docenteEstado: string;
  metaEstrellas: number;
  puntajeTotal: number;
  estrellaFinal: number;
  retosCount: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
  tieneMatricula: boolean;
  allRetosComplete: boolean;
}

interface WizardStep {
  retoId: number;
  retoTitulo: string;
  retoColor: string;
  retoIconUrl: string;
  pdfUrl?: string;
  formId: number;
  estado: string;
  puntaje: number;
  puntajeMaximo: number;
}

export interface RetoFieldPoint {
  fieldId: number;
  label: string;
  puntos: number;
  tipo: string;
}

export interface ConditionalRule {
  fieldId: number;
  operator: string;
  value: string;
}

export interface ConditionalFieldRule {
  fieldId: number;
  conditionalType: 'show' | 'hide' | string;
  groups: ConditionalRule[][];
}

export interface AutosaveFieldPayload {
  type: string;
  name: string;
  value: string | string[];
}

export interface RetoFormResponse {
  html: string;
  formId: number;
  fieldPoints: RetoFieldPoint[];
  conditionalRules: ConditionalFieldRule[];
  savedValues: Record<string, string | string[]>;
  savedAt?: string;
  entry: RetoEntry | null;
  reto: Pick<Reto, 'id' | 'titulo' | 'color' | 'iconUrl' | 'pdfUrl' | 'puntajeMaximo'>;
}

export const retosApi = {
  getDashboard(year: number) {
    return get<DocenteDashboard>('/docente/dashboard', { year });
  },

  getRetos(year: number) {
    return get<RetoWithEntry[]>('/docente/retos', { year });
  },

  getWizardSteps(year: number) {
    return get<WizardStep[]>('/docente/wizard', { year });
  },

  getFormHtml(retoId: number, year: number) {
    return get<RetoFormResponse>(`/docente/retos/${retoId}/form-html`, { year });
  },

  autosaveReto(retoId: number, year: number, formId: number, fields: Record<string, AutosaveFieldPayload>) {
    return post<{ success: boolean; savedAt?: string; entry: RetoEntry | null }>(
      `/docente/retos/${retoId}/autosave`,
      { year, formId, fields },
    );
  },

  removeEvidence(retoId: number, year: number, index: number) {
    return post<{ success: boolean; entry: RetoEntry | null }>(
      `/docente/retos/${retoId}/remove-evidence`,
      { year, index },
    );
  },

  finalizeReto(retoId: number, year: number) {
    return post<{ success: boolean }>(`/docente/retos/${retoId}/finalize`, { year });
  },

  reopenReto(retoId: number, year: number) {
    return post<{ success: boolean }>(`/docente/retos/${retoId}/reopen`, { year });
  },

  submitAll(year: number) {
    return post<{ success: boolean }>('/docente/submit', { year });
  },
};
