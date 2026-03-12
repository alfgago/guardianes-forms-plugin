import { get, post, del } from './client';
import type { MatriculaFormValues, MatriculaPrefill } from '@/types';

export const matriculaApi = {
  getPrefill(year: number) {
    return get<MatriculaPrefill>('/docente/matricula', { year });
  },

  save(fields: MatriculaFormValues & { retosSeleccionados: number[] }, year: number) {
    return post<{ success: boolean; message: string }>('/docente/matricula', { year, fields });
  },

  addReto(retoId: number, year: number) {
    return post<{ success: boolean }>('/docente/matricula/retos', { retoId, year });
  },

  removeReto(retoId: number, year: number) {
    return del<{ success: boolean }>(`/docente/matricula/retos/${retoId}?year=${year}`);
  },

  saveWizardProgress(data: Record<string, unknown>) {
    return post<{ success: boolean }>('/docente/wizard/progress', data);
  },
};
