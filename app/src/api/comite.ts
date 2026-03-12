import { get, post } from './client';
import type { CentroWithStats, DashboardStats, RetoEntry } from '@/types';

interface ComiteDashboard {
  stats: DashboardStats;
}

interface ComiteCentroDetail {
  centro: CentroWithStats;
  entries: (RetoEntry & { retoTitulo: string; retoColor: string; retoIconUrl?: string })[];
  review: {
    anio: number;
    status: string;
    notes: string;
    userId: number;
    userName: string;
    updatedAt: string;
  };
  observations: {
    anio: number;
    userId: number;
    userName: string;
    observation: string;
    createdAt: string;
  }[];
}

interface HistorialItem {
  id: string;
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

  validateCentro(centroId: number, year: number, data: { action: 'validar' | 'rechazar'; notes?: string }) {
    return post<{ success: boolean }>(`/comite/centros/${centroId}/validate`, { year, ...data });
  },

  addObservation(centroId: number, year: number, data: { observation: string }) {
    return post<{ success: boolean }>(`/comite/centros/${centroId}/observation`, { year, ...data });
  },

  getHistorial(year: number) {
    return get<HistorialItem[]>('/comite/historial', { year });
  },
};
