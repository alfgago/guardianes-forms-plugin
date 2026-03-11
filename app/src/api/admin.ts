import { get, post } from './client';
import type { CentroWithStats, DashboardStats, PendingUser } from '@/types';

interface AdminStats extends DashboardStats {
  totalUsers: number;
  pendingUsers: number;
}

interface AdminRetoStats {
  id: number;
  titulo: string;
  total: number;
  aprobados: number;
  enviados: number;
  correccion: number;
  enProgreso: number;
}

interface ReportData {
  centros: CentroWithStats[];
  summary: {
    totalCentros: number;
    totalAprobados: number;
    promedioEstrellas: number;
    promedioPuntaje: number;
  };
}

interface Dre {
  id: number;
  name: string;
  slug: string;
  enabled: boolean;
}

export const adminApi = {
  getStats(year: number) {
    return get<AdminStats>('/admin/stats', { year });
  },

  getPendingUsers() {
    return get<PendingUser[]>('/admin/users/pending');
  },

  approveUser(userId: number) {
    return post<{ success: boolean }>(`/admin/users/${userId}/approve`);
  },

  rejectUser(userId: number) {
    return post<{ success: boolean }>(`/admin/users/${userId}/reject`);
  },

  getCentros(year: number, region?: string, search?: string) {
    return get<CentroWithStats[]>('/admin/centros', { year, region, s: search });
  },

  approveCentro(centroId: number) {
    return post<{ success: boolean }>(`/admin/centros/${centroId}/approve`);
  },

  rejectCentro(centroId: number) {
    return post<{ success: boolean }>(`/admin/centros/${centroId}/reject`);
  },

  getRetos(year: number) {
    return get<AdminRetoStats[]>('/admin/retos', { year });
  },

  getReports(year: number) {
    return get<ReportData>('/admin/reports', { year });
  },

  getDres() {
    return get<Dre[]>('/admin/dre');
  },

  toggleDre(dreId: number) {
    return post<{ success: boolean; enabled: boolean }>(`/admin/dre/${dreId}/toggle`);
  },
};
