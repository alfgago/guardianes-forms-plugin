import { get, post } from './client';
import type { CentroWithStats, DashboardStats, Evidencia, RetoEntry } from '@/types';

interface SupervisorDashboard {
  stats: DashboardStats;
  regionName: string;
  selectedRegionId?: number;
  availableRegionIds?: number[];
  canFilterAll?: boolean;
}

interface SupervisorCentroDetail {
  centro: CentroWithStats;
  entries: (RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string })[];
}

export const supervisorApi = {
  getDashboard(year: number, region?: number) {
    return get<SupervisorDashboard>('/supervisor/dashboard', { year, region });
  },

  getCentros(year: number, circuito?: string, region?: number) {
    return get<CentroWithStats[]>('/supervisor/centros', { year, circuito, region });
  },

  getCentroDetail(centroId: number, year: number) {
    return get<SupervisorCentroDetail>(`/supervisor/centros/${centroId}`, { year });
  },

  reviewEvidence(entryId: number, evidenceIndex: number, data: { action: 'aprobar' | 'rechazar'; comment: string }) {
    return post<{
      success: boolean;
      evidence: Evidencia;
      entry_puntaje: number;
      entry_status: { status: string; badge: string; label: string; aprobadas: number; rechazadas: number; pendientes: number; total: number };
    }>(`/supervisor/evidence/${entryId}/${evidenceIndex}`, data);
  },

  exportCsv(year: number) {
    return get<Blob>('/supervisor/export', { year });
  },
};
