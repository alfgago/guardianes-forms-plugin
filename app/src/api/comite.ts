import { get, post } from './client';
import type { CentroWithStats, DashboardStats, RetoEntry } from '@/types';

interface ComiteDashboard {
  stats: DashboardStats;
}

interface ComiteCentroDetail {
  centro: CentroWithStats;
  entries: (RetoEntry & { retoTitulo: string; retoColor: string })[];
}

interface HistorialItem {
  id: number;
  centroId: number;
  centroNombre: string;
  action: string;
  details: string;
  userId: number;
  userName: string;
  createdAt: string;
}

export const comiteApi = {
  getDashboard(year: number) {
    return get<ComiteDashboard>('/comite/dashboard', { year });
  },

  getCentros(year: number, region?: string) {
    return get<CentroWithStats[]>('/comite/centros', { year, region });
  },

  getCentroDetail(centroId: number, year: number) {
    return get<ComiteCentroDetail>(`/comite/centros/${centroId}`, { year });
  },

  validateCentro(centroId: number, data: { action: 'validar' | 'rechazar'; notes?: string }) {
    return post<{ success: boolean }>(`/comite/centros/${centroId}/validate`, data);
  },

  addObservation(centroId: number, data: { observation: string }) {
    return post<{ success: boolean }>(`/comite/centros/${centroId}/observation`, data);
  },

  getHistorial(year: number) {
    return get<HistorialItem[]>('/comite/historial', { year });
  },
};
