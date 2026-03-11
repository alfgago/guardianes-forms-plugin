import { get, post, del } from './client';
import type { MatriculaPrefill } from '@/types';

export const matriculaApi = {
  getPrefill(year: number) {
    return get<MatriculaPrefill>('/docente/matricula', { year });
  },

  addReto(retoId: number) {
    return post<{ success: boolean }>('/docente/matricula/retos', { retoId });
  },

  removeReto(retoId: number) {
    return del<{ success: boolean }>(`/docente/matricula/retos/${retoId}`);
  },

  saveWizardProgress(data: Record<string, unknown>) {
    return post<{ success: boolean }>('/docente/wizard/progress', data);
  },
};
