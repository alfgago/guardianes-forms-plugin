import { get, post } from './client';
import type { RetoWithEntry } from '@/types';

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
  formId: number;
  estado: string;
  puntaje: number;
  puntajeMaximo: number;
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
    return get<{ html: string }>(`/docente/retos/${retoId}/form-html`, { year });
  },

  finalizeReto(retoId: number) {
    return post<{ success: boolean }>(`/docente/retos/${retoId}/finalize`);
  },

  reopenReto(retoId: number) {
    return post<{ success: boolean }>(`/docente/retos/${retoId}/reopen`);
  },

  submitAll(year: number) {
    return post<{ success: boolean }>('/docente/submit', { year });
  },
};
