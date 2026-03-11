import { get, post } from './client';
import type { CentroWithStats, DashboardStats, RetoEntry } from '@/types';

interface SupervisorDashboard {
  stats: DashboardStats;
  regionName: string;
}

interface SupervisorCentroDetail {
  centro: CentroWithStats;
  entries: (RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string })[];
}

interface UpdateEntryPayload {
  action: 'aprobar' | 'correccion';
  notes?: string;
}

export const supervisorApi = {
  getDashboard(year: number) {
    return get<SupervisorDashboard>('/supervisor/dashboard', { year });
  },

  getCentros(year: number, circuito?: string) {
    return get<CentroWithStats[]>('/supervisor/centros', { year, circuito });
  },

  getCentroDetail(centroId: number, year: number) {
    return get<SupervisorCentroDetail>(`/supervisor/centros/${centroId}`, { year });
  },

  updateEntry(entryId: number, data: UpdateEntryPayload) {
    return post<{ success: boolean }>(`/supervisor/entries/${entryId}`, data);
  },

  exportCsv(year: number) {
    return get<Blob>('/supervisor/export', { year });
  },
};
